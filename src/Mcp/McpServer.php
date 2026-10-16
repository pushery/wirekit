<?php

declare(strict_types=1);

namespace Pushery\WireKit\Mcp;

/**
 * Minimal, read-only Model Context Protocol (MCP) server for WireKit.
 *
 * Speaks JSON-RPC 2.0 over a transport-agnostic message API: feed it one
 * decoded request with `handle()` and it returns the decoded response (or null
 * for a notification, which has no reply). `McpServeCommand` wraps this in the
 * stdio loop AI editors (Claude Code / Cursor / Cline) spawn locally.
 *
 * Exposes read-only tools sourced from the shipped catalog:
 * `search_components`, `list_components`, `get_component`,
 * `get_component_examples`, `get_component_accessibility`, `get_tokens`,
 * `get_conventions`, `list_recipes`, `get_recipe`, `list_presets`, `get_preset`. No
 * write tools and no network —
 * everything it serves ships in the Packagist tarball, so a developer-hosted
 * local server is always version-matched to their installed WireKit.
 *
 * It also exposes four RESOURCES — `wirekit://catalog`, `wirekit://themes`,
 * `wirekit://recipes` and `wirekit://changelog`. A resource is the same shipped
 * data addressed by URI instead of by call: an assistant attaches the catalog to a
 * conversation once instead of asking for it on every turn, and a client that lists
 * resources shows a developer what this server knows without a round trip per
 * question. Each one is a whole set rather than a single item, because a URI per
 * component would put a hundred and eighty entries in a resource picker, which is a
 * menu nobody reads; the per-item questions are what the tools are for.
 *
 * Nothing is read from `docs/` at runtime, which is not a detail: `docs/` is
 * export-ignored, so it is absent in a real install. The worked examples are
 * extracted from it at BUILD time into a file that ships, and a test fails when
 * the two drift apart.
 *
 * This list names the tools actually registered, one entry for each.
 */
final class McpServer
{
    /** The latest MCP protocol revision this server is built against. */
    public const PROTOCOL_VERSION = '2025-06-18';

    /**
     * Every revision this server speaks, newest first. See `initializeResult()` for why the
     * list is short and what adding to it commits us to.
     *
     * @var list<string>
     */
    private const SUPPORTED_PROTOCOL_VERSIONS = [self::PROTOCOL_VERSION];

    public function __construct(
        private readonly McpCatalog $catalog,
        private readonly string $version = 'dev',
    ) {}

    /**
     * Dispatch one decoded JSON-RPC request. Returns the decoded response, or
     * null when the message is a notification (no `id`) and needs no reply.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    public function handle(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = is_string($message['method'] ?? null) ? $message['method'] : '';
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        // Notifications carry no `id` and never get a response.
        $isNotification = ! array_key_exists('id', $message);

        if ($isNotification) {
            return null;
        }

        return match ($method) {
            'initialize' => $this->ok($id, $this->initializeResult($params)),
            'ping' => $this->ok($id, (object) []),
            'tools/list' => $this->ok($id, ['tools' => $this->toolDefinitions()]),
            'tools/call' => $this->handleToolCall($id, $params),
            'resources/list' => $this->ok($id, ['resources' => $this->resourceDefinitions()]),
            'resources/read' => $this->readResource($id, $params),
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initializeResult(array $params): array
    {
        /*
         * Not an echo of whatever arrived: an empty string is not a version.
         *
         * A handshake that mirrors the client's request agrees to everything: a revision this
         * server was never built against, a typo, `''`. Nothing fails at the handshake then —
         * it fails later, at the first message whose shape the two sides disagree about, which
         * is the hardest place in the protocol to read a fault.
         *
         * The specification puts the decision the other way round: the server answers with a
         * version IT supports, and the client decides whether it can live with that. So an
         * unrecognized request gets this server's own revision rather than its own words back.
         *
         * The list is what this server has actually been implemented against, which is why it
         * holds one entry. Adding a revision means reading that revision's diff first — a
         * second entry here is a claim about wire compatibility, not a courtesy.
         */
        $requested = $params['protocolVersion'] ?? null;

        return [
            'protocolVersion' => in_array($requested, self::SUPPORTED_PROTOCOL_VERSIONS, true)
                ? $requested
                : self::PROTOCOL_VERSION,
            // Both halves are announced, and a client that sees neither asks for neither: the
            // handshake is where a server says what it has, so an unannounced capability is an
            // unused one however completely it is implemented.
            'capabilities' => ['tools' => (object) [], 'resources' => (object) []],
            'serverInfo' => ['name' => 'wirekit', 'version' => $this->version],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function toolDefinitions(): array
    {
        return McpDefinitions::tools();
    }

    /**
     * The resources this server exposes, each a whole set addressed by one URI.
     *
     * @return list<array<string, string>>
     */
    private function resourceDefinitions(): array
    {
        return McpDefinitions::resources();
    }

    /**
     * Read one resource.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function readResource(int|string|null $id, array $params): array
    {
        $uri = is_string($params['uri'] ?? null) ? $params['uri'] : '';
        $read = McpDefinitions::read($this->catalog, $uri);

        if ($read === null) {
            // -32602 (invalid params) rather than -32601 (method not found): `resources/read`
            // exists, and it is the URI that does not.
            return $this->error($id, -32602, "Unknown resource: {$uri}");
        }

        if (isset($read['missing'])) {
            // -32603 rather than the unknown-resource error above: the resource is one this
            // server offers, and the install is missing the file it reads. Those are different
            // faults and a developer chasing them looks in different places.
            return $this->error($id, -32603, $read['missing']);
        }

        // The `contents` envelope the protocol expects is a list, because one URI may resolve to
        // several parts.
        return $this->ok($id, ['contents' => [['uri' => $uri, 'mimeType' => $read['mimeType'], 'text' => $read['text']]]]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function handleToolCall(int|string|null $id, array $params): array
    {
        $name = is_string($params['name'] ?? null) ? $params['name'] : '';
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $answer = McpDefinitions::call($this->catalog, $name, $args);

        if (isset($answer['unknownTool'])) {
            return $this->error($id, -32602, "Unknown tool: {$name}");
        }

        if (isset($answer['refusal'])) {
            return $this->ok($id, [
                'content' => [['type' => 'text', 'text' => $answer['refusal']]],
                'isError' => true,
            ]);
        }

        // A single text content block carrying pretty-printed JSON: readable for a human curling
        // the server, structured for an editor that re-parses it.
        return $this->ok($id, ['content' => [['type' => 'text', 'text' => McpDefinitions::encode($answer['payload'] ?? [])]]]);
    }

    /**
     * `mixed`, because JSON-RPC leaves the `result` member untyped: any JSON value.
     *
     * @return array<string, mixed>
     */
    private function ok(int|string|null $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /** @return array<string, mixed> */
    private function error(int|string|null $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
