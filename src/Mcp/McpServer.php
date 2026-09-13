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
 * This list is not decoration — `McpServerTest` fails when it stops matching the
 * tools actually registered. It said "four read-only tools" for exactly as long
 * as there were four, and the fifth was added without it noticing.
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
         * ⚠️ THIS ECHOED WHATEVER ARRIVED, AND AN EMPTY STRING IS NOT A VERSION.
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
                'name' => 'get_component_accessibility',
                'description' => 'Get what one WireKit component has ALREADY wired for accessibility — the roles and ARIA attributes its markup emits, the keys its behavior handles, and the names it waits for the caller to supply. Ask for this before adding any role, aria-* attribute or key handler of your own: a role added on top of one the component already carries is two competing contracts on one element, and a name it waits for and never receives is an announced landmark that says nothing. Derived from the shipped sources, never from a list.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'The component name, e.g. "tabs" or "sidebar.item".'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'get_tokens',
                'description' => 'List every WireKit design token (the --*-wk-* CSS variables) as name → value pairs.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'list_recipes',
                'description' => 'List the WireKit recipe library — the composed page shapes (documentation reader, marketing landing page, KPI strip, on-page TOC and the rest) that `wirekit:make` scaffolds into a project as real Blade. Ask for this before assembling a whole page out of individual components: if one of these already is the page you were about to build, scaffolding it is a command rather than an afternoon.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_recipe',
                'description' => 'Get one recipe in full, including the Blade source the scaffold writes — so you can read the composition before running the command, or adapt it inline instead of scaffolding.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'The recipe name, e.g. "on-page-toc" — see list_recipes.'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'list_presets',
                'description' => 'List the bundled WireKit theme presets with the command that applies each one. A preset is applied by running a command, never by hand-writing tokens — reach for this before proposing a palette of your own.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_preset',
                'description' => 'Get one theme preset with the exact CSS custom properties `wirekit:theme` appends to app.css, light and dark. Read from the same registry the command writes from, so it describes the version installed rather than a copy of it.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string', 'description' => 'The preset key, e.g. "aurora" or "brutalist" — see list_presets.'],
                    ],
                    'required' => ['key'],
                ],
            ],
            [
                'name' => 'get_conventions',
                'description' => 'Get the house rules for authoring WireKit markup — the conventions that decide whether generated code passes this project\'s guards or fails them. Ask for this ONCE before writing any Blade, and before reaching for a Tailwind palette class, a `dark:` prefix, a hand-written color, an outer margin or an icon sized with `h-N w-N`: every one of those is rejected here, and the prop list you get from `get_component` does not say so. Pass `detailed` for the full authoring ruleset instead of the entry-point summary.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'detailed' => ['type' => 'boolean', 'description' => 'Return the full authoring ruleset rather than the short entry point (default false).'],
                    ],
                ],
            ],
        ];
    }

    /**
     * The resources this server exposes, each a whole set addressed by one URI.
     *
     * @return list<array<string, string>>
     */
    private function resourceDefinitions(): array
    {
        return [
            [
                'uri' => 'wirekit://catalog',
                'name' => 'Component catalog',
                'description' => 'Every WireKit component with its category, description and prop signature — the same data `list_components` and `get_component` answer from.',
                'mimeType' => 'application/json',
            ],
            [
                'uri' => 'wirekit://themes',
                'name' => 'Theme presets',
                'description' => 'Every shipped theme preset with the design-token values it sets, light and dark.',
                'mimeType' => 'application/json',
            ],
            [
                'uri' => 'wirekit://recipes',
                'name' => 'Recipes',
                'description' => 'The recipe library `wirekit:make` can scaffold, with the purpose of each.',
                'mimeType' => 'application/json',
            ],
            [
                'uri' => 'wirekit://changelog',
                'name' => 'Changelog — newest version',
                'description' => "The newest version's section of CHANGELOG.md, which is what changed in the version installed here. The whole file is several hundred kilobytes and a resource is attached whole, so this is deliberately one section rather than all of them.",
                'mimeType' => 'text/markdown',
            ],
        ];
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

        if ($uri === 'wirekit://changelog') {
            $text = $this->changelogSection();

            // -32603 rather than the unknown-resource error below: the resource is one this
            // server offers, and the install is missing the file it reads. Those are different
            // faults and a developer chasing them looks in different places.
            return $text === null
                ? $this->error($id, -32603, 'CHANGELOG.md is not present in this install.')
                : $this->ok($id, $this->resourceContents($uri, 'text/markdown', $text));
        }

        $payload = match ($uri) {
            'wirekit://catalog' => $this->catalog->components(),
            'wirekit://themes' => $this->catalog->presets(),
            'wirekit://recipes' => $this->catalog->recipes(),
            default => null,
        };

        if ($payload === null) {
            // -32602 (invalid params) rather than -32601 (method not found): `resources/read`
            // exists, and it is the URI that does not.
            return $this->error($id, -32602, "Unknown resource: {$uri}");
        }

        return $this->ok($id, $this->resourceContents(
            $uri,
            'application/json',
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ));
    }

    /**
     * The `contents` envelope the protocol expects — a list, because one URI may resolve to
     * several parts.
     *
     * @return array<string, mixed>
     */
    private function resourceContents(string $uri, string $mimeType, string $text): array
    {
        return ['contents' => [['uri' => $uri, 'mimeType' => $mimeType, 'text' => $text]]];
    }

    /**
     * The newest version's section of the shipped CHANGELOG, or null when the file is absent.
     *
     * Cut at the next `## [` heading rather than served whole: the file is several hundred
     * kilobytes and grows with every release, and a resource is attached in full. What a
     * developer wants from an assistant here is what changed in the version they have.
     */
    private function changelogSection(): ?string
    {
        $path = dirname(__DIR__, 2).'/CHANGELOG.md';

        if (! is_file($path)) {
            return null;
        }

        $contents = (string) file_get_contents($path);

        if (preg_match('/^## \[.*?(?=^## \[)/ms', $contents, $match) === 1) {
            return rtrim($match[0]);
        }

        // One section in the whole file, or a shape this cut does not recognize. Handing back
        // everything is wrong at this size, so the honest answer is the part that is certainly
        // a section: from the first heading to the end.
        $from = strpos($contents, '## [');

        return $from === false ? null : rtrim(substr($contents, $from));
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
            'get_component_accessibility' => $this->getComponentAccessibilityResult($id, is_string($args['name'] ?? null) ? $args['name'] : ''),
            'get_tokens' => $this->toolResult($id, $this->catalog->tokens()),
            'list_presets' => $this->toolResult($id, $this->catalog->presets()),
            'get_preset' => $this->getPresetResult($id, is_string($args['key'] ?? null) ? $args['key'] : ''),
            'list_recipes' => $this->toolResult($id, $this->catalog->recipes()),
            'get_recipe' => $this->getRecipeResult($id, is_string($args['name'] ?? null) ? $args['name'] : ''),
            'get_conventions' => $this->getConventionsResult($id, ($args['detailed'] ?? false) === true),
            default => $this->error($id, -32602, "Unknown tool: {$name}"),
        };
    }

    /** @return array<string, mixed> */
    private function getPresetResult(int|string|null $id, string $key): array
    {
        $preset = $this->catalog->preset($key);

        // The same unknown-name shape the rest of the per-item tools use.
        if ($preset === null) {
            return $this->ok($id, [
                'content' => [['type' => 'text', 'text' => "Unknown preset: {$key}"]],
                'isError' => true,
            ]);
        }

        return $this->toolResult($id, $preset);
    }

    /** @return array<string, mixed> */
    private function getRecipeResult(int|string|null $id, string $name): array
    {
        $recipe = $this->catalog->recipe($name);

        // The same unknown-name shape every other per-item tool uses. An agent that learned
        // one error shape should not have to learn another for recipes.
        if ($recipe === null) {
            return $this->ok($id, [
                'content' => [['type' => 'text', 'text' => "Unknown recipe: {$name}"]],
                'isError' => true,
            ]);
        }

        return $this->toolResult($id, $recipe);
    }

    /** @return array<string, mixed> */
    private function getConventionsResult(int|string|null $id, bool $detailed): array
    {
        $conventions = $this->catalog->conventions($detailed);

        // An absent file is reported rather than answered around. The two documents ship
        // in the tarball, so this arm means the installation is incomplete — and a tool
        // that returned an empty string here would teach an agent that WireKit has no
        // conventions, which is the most expensive wrong answer it could give.
        if ($conventions === null) {
            return $this->ok($id, [
                'content' => [['type' => 'text', 'text' => 'The conventions document is missing from this installation.']],
                'isError' => true,
            ]);
        }

        return $this->toolResult($id, $conventions);
    }

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
    private function getComponentAccessibilityResult(int|string|null $id, string $name): array
    {
        $contract = $this->catalog->accessibility($name);

        // The same error shape the other two per-component tools use, on purpose: an agent
        // that learned one should not have to learn a third.
        if ($contract === null) {
            return $this->ok($id, [
                'content' => [['type' => 'text', 'text' => "Unknown component: {$name}"]],
                'isError' => true,
            ]);
        }

        return $this->toolResult($id, $contract);
    }

    /**
     * Wrap a catalog payload as an MCP tool result (a single text content block
     * carrying pretty-printed JSON — readable for a human curling the server,
     * structured for an editor that re-parses it).
     *
     * @return array<string, mixed>
     */
    private function toolResult(int|string|null $id, mixed $payload): array
    {
        $text = (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->ok($id, ['content' => [['type' => 'text', 'text' => $text]]]);
    }

    /** @return array<string, mixed> */
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
