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
 * `get_component_examples`, `get_tokens`. No write tools and no network —
 * everything it serves ships in the Packagist tarball, so a developer-hosted
 * local server is always version-matched to their installed WireKit.
 *
 * Nothing is read from `docs/` at runtime, which is not a detail: `docs/` is
 * export-ignored, so it is absent in a real install. The worked examples are
 * extracted from it at BUILD time into a file that ships, and a test fails when
 * the two drift apart.
 *
 * This list is not decoration — `McpServerTest` fails when it stops matching the
 * tools actually registered. It said "four read-only tools" for exactly as long
 * as there were four, and the fifth was added without it noticing.
 */
final class McpServer
{
    /** The latest MCP protocol revision this server is built against. */
    public const PROTOCOL_VERSION = '2025-06-18';

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
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    /** @param array<string, mixed> $params */
    private function initializeResult(array $params): array
    {
        $requested = is_string($params['protocolVersion'] ?? null)
            ? $params['protocolVersion']
            : self::PROTOCOL_VERSION;

        return [
            'protocolVersion' => $requested,
            'capabilities' => ['tools' => (object) []],
            'serverInfo' => ['name' => 'wirekit', 'version' => $this->version],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'search_components',
                'description' => 'Search WireKit components by name, category, or description. Returns matching components with their category and short description.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Substring to match against name / category / description.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results (default 20, max 100).'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'list_components',
                'description' => 'List every WireKit component with its category and description.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_component',
                'description' => 'Get the full prop signature of one WireKit component: tag, category, description, and every declared prop with its default and a hint of the values it accepts.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'The component name, e.g. "button" or "card".'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'get_component_examples',
                'description' => 'Get worked examples for one WireKit component — real, reviewed usage taken from its documentation page, not markup assembled from a prop list. Ask for this BEFORE writing custom markup; a sub-component resolves to its parent page.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'The component name, e.g. "button" or "card.body".'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'get_tokens',
                'description' => 'List every WireKit design token (the --*-wk-* CSS variables) as name → value pairs.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function handleToolCall(int|string|null $id, array $params): array
    {
        $name = is_string($params['name'] ?? null) ? $params['name'] : '';
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        return match ($name) {
            'search_components' => $this->toolResult($id, $this->catalog->searchComponents(
                is_string($args['query'] ?? null) ? $args['query'] : '',
                is_int($args['limit'] ?? null) ? $args['limit'] : 20,
            )),
            'list_components' => $this->toolResult($id, $this->catalog->components()),
            'get_component' => $this->getComponentResult($id, is_string($args['name'] ?? null) ? $args['name'] : ''),
            'get_component_examples' => $this->getComponentExamplesResult($id, is_string($args['name'] ?? null) ? $args['name'] : ''),
            'get_tokens' => $this->toolResult($id, $this->catalog->tokens()),
            default => $this->error($id, -32602, "Unknown tool: {$name}"),
        };
    }

    private function getComponentResult(int|string|null $id, string $name): array
    {
        $component = $this->catalog->getComponent($name);

        if ($component === null) {
            return $this->ok($id, [
                'content' => [['type' => 'text', 'text' => "Unknown component: {$name}"]],
                'isError' => true,
            ]);
        }

        return $this->toolResult($id, $component);
    }

    private function getComponentExamplesResult(int|string|null $id, string $name): array
    {
        $examples = $this->catalog->examples($name);

        // The same shape `get_component` uses for an unknown name, on purpose: an
        // agent that learned one error shape should not have to learn a second.
        if ($examples === null) {
            return $this->ok($id, [
                'content' => [['type' => 'text', 'text' => "Unknown component: {$name}"]],
                'isError' => true,
            ]);
        }

        return $this->toolResult($id, $examples);
    }

    /**
     * Wrap a catalog payload as an MCP tool result (a single text content block
     * carrying pretty-printed JSON — readable for a human curling the server,
     * structured for an editor that re-parses it).
     */
    private function toolResult(int|string|null $id, mixed $payload): array
    {
        $text = (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->ok($id, ['content' => [['type' => 'text', 'text' => $text]]]);
    }

    private function ok(int|string|null $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function error(int|string|null $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
