<?php

declare(strict_types=1);

namespace Pushery\WireKit\Mcp;

/**
 * What WireKit's MCP server offers and how each part answers, in one place for both servers.
 *
 * `wirekit:mcp-serve` serves through `laravel/mcp` where it is installed (Sdk\WireKitMcpServer) and
 * through this package's own JSON-RPC loop where it is not (McpServer). Both list the tools and
 * resources below and answer every call through `call()` and `read()`, so the two can never tell
 * an assistant different things about the same installation. Everything here is read-only and
 * comes from what ships in the package.
 */
final class McpDefinitions
{
    /** The flags every JSON answer is written with: readable for a person, exact for a client. */
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * The tools, each with the JSON Schema of its arguments.
     *
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public static function tools(): array
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
     * The resources, each a whole set addressed by one URI.
     *
     * @return list<array{uri: string, name: string, description: string, mimeType: string}>
     */
    public static function resources(): array
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
     * Answer one tool call: the payload, or the refusal a tool gives for a name it does not know,
     * or `unknownTool` for a tool this server does not have.
     *
     * The refusal has one shape for every per-item tool on purpose: an agent that learned one
     * error shape should not have to learn another.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{payload?: array<array-key, mixed>, refusal?: string, unknownTool?: true}
     */
    public static function call(McpCatalog $catalog, string $tool, array $arguments): array
    {
        $text = static fn (string $key): string => is_string($arguments[$key] ?? null) ? $arguments[$key] : '';

        return match ($tool) {
            'search_components' => ['payload' => $catalog->searchComponents(
                $text('query'),
                is_int($arguments['limit'] ?? null) ? $arguments['limit'] : 20,
            )],
            'list_components' => ['payload' => $catalog->components()],
            'get_component' => self::item($catalog->getComponent($text('name')), "Unknown component: {$text('name')}"),
            'get_component_examples' => self::item($catalog->examples($text('name')), "Unknown component: {$text('name')}"),
            'get_component_accessibility' => self::item($catalog->accessibility($text('name')), "Unknown component: {$text('name')}"),
            'get_tokens' => ['payload' => $catalog->tokens()],
            'list_presets' => ['payload' => $catalog->presets()],
            'get_preset' => self::item($catalog->preset($text('key')), "Unknown preset: {$text('key')}"),
            'list_recipes' => ['payload' => $catalog->recipes()],
            'get_recipe' => self::item($catalog->recipe($text('name')), "Unknown recipe: {$text('name')}"),
            // An absent file is reported rather than answered around. The two documents ship in
            // the tarball, so this arm means the installation is incomplete, and a tool that
            // returned an empty string here would teach an agent that WireKit has no conventions,
            // which is the most expensive wrong answer it could give.
            'get_conventions' => self::item(
                $catalog->conventions(($arguments['detailed'] ?? false) === true),
                'The conventions document is missing from this installation.',
            ),
            default => ['unknownTool' => true],
        };
    }

    /**
     * Read one resource: its contents, `missing` for one this server offers whose file the
     * installation lacks, or null for a URI it does not offer.
     *
     * @return array{mimeType: string, text: string}|array{missing: string}|null
     */
    public static function read(McpCatalog $catalog, string $uri): ?array
    {
        if ($uri === 'wirekit://changelog') {
            $text = self::changelogSection();

            return $text === null
                ? ['missing' => 'CHANGELOG.md is not present in this install.']
                : ['mimeType' => 'text/markdown', 'text' => $text];
        }

        $payload = match ($uri) {
            'wirekit://catalog' => $catalog->components(),
            'wirekit://themes' => $catalog->presets(),
            'wirekit://recipes' => $catalog->recipes(),
            default => null,
        };

        return $payload === null ? null : ['mimeType' => 'application/json', 'text' => self::encode($payload)];
    }

    /**
     * A payload as the text of an answer: pretty-printed JSON, readable for a human curling the
     * server and structured for an editor that parses it again.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public static function encode(array $payload): string
    {
        return (string) json_encode($payload, self::JSON_FLAGS);
    }

    /**
     * A per-item answer, or the refusal for a name the catalog does not know.
     *
     * @param  array<array-key, mixed>|null  $payload
     * @return array{payload: array<array-key, mixed>}|array{refusal: string}
     */
    private static function item(?array $payload, string $refusal): array
    {
        return $payload === null ? ['refusal' => $refusal] : ['payload' => $payload];
    }

    /**
     * The newest version's section of the shipped CHANGELOG, or null when the file is absent.
     *
     * Cut at the next `## [` heading rather than served whole: the file is several hundred
     * kilobytes and grows with every release, and a resource is attached in full. What a
     * developer wants from an assistant here is what changed in the version they have.
     */
    private static function changelogSection(): ?string
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
}
