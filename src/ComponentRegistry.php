<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Illuminate\Support\Facades\File;
use Pushery\WireKit\Components\Chart;
use Pushery\WireKit\Components\Field;
use Pushery\WireKit\Components\FieldSet;
use Pushery\WireKit\Components\SidebarGroup;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\ClassPropsExtractor;
use Pushery\WireKit\Support\ComponentTokens;
use Pushery\WireKit\Support\PropsParser;

class ComponentRegistry
{
    /**
     * The catalog, read once per process from resources/catalog/components.php.
     *
     * @var array<string, array{category: string, description: string}>|null
     */
    private static ?array $catalog = null;

    /**
     * Component metadata registry. Every top-level component MUST have an entry.
     * Anti-drift tests enforce this — adding a blade file without a registry
     * entry will fail CI.
     *
     * The entries are data and live in resources/catalog/components.php; this method is the one
     * way to read them.
     *
     * @return array<string, array{category: string, description: string}>
     */
    public static function all(): array
    {
        if (self::$catalog === null) {
            /** @var array<string, array{category: string, description: string}> $entries */
            $entries = require \dirname(__DIR__).'/resources/catalog/components.php';
            self::$catalog = $entries;
        }

        return self::$catalog;
    }

    /**
     * Get component metadata by name.
     *
     * @return array{category: string, description: string}|null
     */
    public static function get(string $name): ?array
    {
        return static::all()[$name] ?? null;
    }

    /**
     * Registered Blade-tag overrides for class-based components whose
     * tag form differs from the anonymous `<x-wirekit::name>` convention.
     *
     * The anonymous form (default) reads `<x-wirekit::{name}>`.
     * Class-based components registered via `loadViewComponentsAs(...)`
     * use the alternate form `<x-wirekit-{name}>` — Laravel's prefixed-
     * class-component shape. The two cannot be detected from
     * ComponentRegistry::all() metadata alone (both ship a Blade file
     * AND a PHP class); this map encodes the exception per component.
     *
     * Used by CLI surfaces (`wirekit:show`, `wirekit:list`,
     * `wirekit:export-json`, `wirekit:install summary`,
     * `wirekit:export-api-map`) so every "what tag do I use?" report
     * prints the WORKING form. Without this map, `wirekit:show chart`
     * would print `<x-wirekit::chart>`, which renders a 500 because the
     * anonymous Blade file references an undefined `$alpineComponent`
     * variable (the class constructor populates it via the class-based
     * path).
     *
     * @var array<string, string>
     */
    private const TAG_OVERRIDES = [
        // Class-based, registered in WireKitServiceProvider::boot() —
        // the anonymous file at resources/views/components/chart.blade.php
        // exists but is meant to be reached via the class component, which
        // injects the `$alpineComponent` variable into the view context.
        'chart' => '<x-wirekit-chart>',
    ];

    /**
     * Return the canonical Blade tag form for a registered component.
     *
     * Defaults to the anonymous `<x-wirekit::name>` shape. Components
     * registered as class-based via `loadViewComponentsAs(...)` carry
     * an override in `TAG_OVERRIDES` and use the prefixed-class shape
     * (e.g. `<x-wirekit-chart>` for `chart`).
     *
     * The leading `<` and trailing `>` are part of the returned value —
     * callers concatenate-ready.
     */
    public static function tag(string $name): string
    {
        return self::TAG_OVERRIDES[$name] ?? "<x-wirekit::{$name}>";
    }

    /**
     * Exposes the deprecated tag-form
     * alias for class-based components so tool integrators can
     * verify both the canonical (single-hyphen) shape AND any
     * historical (double-colon) shape that documentation might have
     * shipped. Returns NULL for normal anonymous components (their
     * canonical = their alias).
     *
     * Used by ExportJsonCommand to emit a `tag_alias` field next to
     * `tag` so a downstream tool that hard-codes the double-colon form
     * can still grep against the schema and find the component.
     */
    public static function tagAlias(string $name): ?string
    {
        return isset(self::TAG_OVERRIDES[$name]) ? "<x-wirekit::{$name}>" : null;
    }

    /**
     * Every sub-component the package ships, keyed `parent.child`.
     *
     * Derived from the filesystem rather than hand-listed. A hand-maintained
     * list this long drifts the first time someone adds a file and forgets
     * the array; deriving it means the catalog cannot disagree with what ships.
     *
     * Sub-components are deliberately NOT in `all()`. They are part of the public
     * API — AGENTS.md tells an agent to reach for `card.body`, and `table.th`
     * carries its own documented props — but they are not components in the sense
     * every count in this project means by the word. Folding them in would inflate
     * the component count by close to half overnight without a single new component
     * shipping. They are a separate surface, discoverable on their own terms.
     *
     * `index.blade.php` is excluded: that file IS the parent (the directory-
     * component form), not a child of it.
     *
     * @return array<string, array{parent: string, child: string}>
     */
    public static function subComponents(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $base = __DIR__.'/../resources/views/components/';
        $subs = [];

        foreach ((array) glob($base.'*', GLOB_ONLYDIR) as $dir) {
            $parent = basename((string) $dir);

            // A directory with no registry entry is not a component's sub-tree.
            if (! isset(self::all()[$parent])) {
                continue;
            }

            foreach ((array) glob($dir.'/*.blade.php') as $file) {
                $child = basename((string) $file, '.blade.php');

                if ($child === 'index') {
                    continue;
                }

                $subs[$parent.'.'.$child] = ['parent' => $parent, 'child' => $child];
            }
        }

        ksort($subs);

        return $cache = $subs;
    }

    /**
     * The sub-component names belonging to one parent, sorted.
     *
     * @return list<string>
     */
    public static function subComponentsOf(string $parent): array
    {
        return array_keys(array_filter(
            self::subComponents(),
            static fn (array $meta): bool => $meta['parent'] === $parent
        ));
    }

    /** Is this a `parent.child` name the package actually ships? */
    public static function isSubComponent(string $name): bool
    {
        return isset(self::subComponents()[$name]);
    }

    /**
     * Metadata for a name of EITHER kind — `card` or `card.body`.
     *
     * The three discovery surfaces (the show command, the JSON export, the MCP
     * catalog) answer "what is card.body?" through this one resolver. A surface that
     * answered null would read to an agent as "there is no such component", and it
     * would then write content straight into <x-wirekit::card>, the exact mistake
     * AGENTS.md exists to prevent. One resolver, one answer.
     *
     * A sub-component inherits its parent's category (it belongs to the same part
     * of the library) and describes itself in terms of that parent, because that
     * is the only honest description available without hand-writing one apiece.
     *
     * @return array{category: string, description: string, parent?: string}|null
     */
    public static function resolve(string $name): ?array
    {
        $top = self::get($name);

        if ($top !== null) {
            return $top;
        }

        $sub = self::subComponents()[$name] ?? null;

        if ($sub === null) {
            return null;
        }

        $parentMeta = self::get($sub['parent']);

        return [
            'category' => $parentMeta['category'] ?? 'Display',
            'description' => "The {$sub['child']} part of the {$sub['parent']} component",
            'parent' => $sub['parent'],
        ];
    }

    /**
     * Get all components in a given category.
     *
     * @return array<string, array{category: string, description: string}>
     */
    public static function category(string $category): array
    {
        return array_filter(static::all(), fn ($meta) => $meta['category'] === $category);
    }

    /**
     * Get all unique category names.
     *
     * @return string[]
     */
    public static function categories(): array
    {
        return array_values(array_unique(array_column(static::all(), 'category')));
    }

    /**
     * Extract props from a component. Two flavors coexist:
     *
     * 1. **Anonymous Blade component** (the 99% case): parse the
     *    `@props([...])` directive from the Blade file via PropsParser.
     * 2. **Class-based Blade component** (chart): walk the
     *    constructor signature via Reflection (ClassPropsExtractor).
     *
     * The class-based path is selected when `CLASS_COMPONENTS` carries
     * an entry mapping the component name → its class. Both paths
     * return the same shape so downstream callers don't branch.
     *
     * One entry per prop, with its metadata. The fields make inline-comment
     * metadata available without source-grepping, and parsing the tokens
     * keeps a `config(...)` default whole and an inline comment out of it.
     *
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>, values: ?list<string>, value_type: ?string}>
     */
    public static function extractProps(string $name): array
    {
        if (isset(self::CLASS_COMPONENTS[$name])) {
            return ClassPropsExtractor::extract(self::CLASS_COMPONENTS[$name]);
        }

        return PropsParser::parseBlade(self::bladeFilePath($name));
    }

    /**
     * The names a component accepts through `@aware` rather than `@props`.
     *
     * Kept separate from `extractProps()` on purpose. Every other reader of
     * that method — the JSON manifest, the API map, the docs pipeline — means
     * "the props this component declares", and an `@aware` key is not one: it
     * is a value the PARENT owns, which the child reads if it is there. Folding
     * the two together would quietly widen every published surface.
     *
     * The one question where they belong together is "is this attribute a name
     * the component knows?", because Blade accepts either spelling on the tag.
     * That union has a name of its own — `acceptedPropNames()` — and every reader
     * of that question goes through it. It was spelled out at a single caller
     * until a second one asked the same thing from `extractProps()` alone and
     * reported a correct call as a typo.
     *
     * A class-based component has no `@aware`; the empty list is the honest
     * answer rather than a special case.
     *
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>}>
     */
    public static function extractAwareProps(string $name): array
    {
        if (isset(self::CLASS_COMPONENTS[$name])) {
            return [];
        }

        return PropsParser::parseAwareBlade(self::bladeFilePath($name));
    }

    /**
     * Every name this component ACCEPTS on its tag — `@props` and `@aware` together.
     *
     * A different question from `extractProps()`, and the difference is the whole reason this
     * method exists. That one answers "which props does the component declare?", which is what a
     * manifest, a docs page and an API map mean. This one answers "would Blade do something with
     * this attribute?" — and there the two blocks are equal, because `@aware` reads
     * `currentComponentData`, which carries the component's OWN data before the parent chain. A
     * key written directly on the tag therefore arrives and takes effect:
     * `<x-wirekit::accordion.item variant="separated">` renders the separated chrome.
     *
     * Both readers of this question, the unknown-prop warning and `wirekit:doctor:props`, call
     * this method rather than writing the union out themselves, so neither can report an
     * `@aware` key such as `variant` on `accordion.item` as a typo.
     *
     * @return list<string>
     */
    public static function acceptedPropNames(string $name): array
    {
        return array_values(array_filter(array_map(
            static fn (array $prop): string => (string) $prop['name'],
            [
                ...self::extractProps($name),
                ...self::extractAwareProps($name),
            ],
        ), static fn (string $prop): bool => $prop !== ''));
    }

    /**
     * Return the backing class FQCN for class-based components, or null for
     * anonymous Blade components. A class registered under the tag of an
     * anonymous view is not one of them: see VIEW_CLASSES.
     *
     * @return class-string|null
     */
    public static function componentClass(string $name): ?string
    {
        return self::CLASS_COMPONENTS[$name] ?? null;
    }

    /**
     * Class-based-component registry. Keyed by component name; value is
     * the FQCN of the class registered via `loadViewComponentsAs(...)`
     * in WireKitServiceProvider. Mirror of the
     * `loadViewComponentsAs('wirekit', [...])` array — kept in sync
     * manually because the service provider doesn't surface the list
     * as data.
     *
     * @var array<string, class-string>
     */
    private const CLASS_COMPONENTS = [
        'chart' => Chart::class,
    ];

    /**
     * Classes registered under the tag of an anonymous view, keyed by that tag.
     *
     * WireKitServiceProvider registers them from this list. The markup and the declared props stay
     * in the view, so every reader of `@props` finds them there, which is why none of them is in
     * CLASS_COMPONENTS. Each class exists for what only a constructor can do: it runs before the
     * slot renders.
     *
     * @var array<string, class-string>
     */
    public const VIEW_CLASSES = [
        // A set hands the controls in its slot what they point at.
        'field.set' => FieldSet::class,
        // A field does the same for the one control it labels: the control learns the label it is
        // named by, and the field learns the element its label points at.
        'field' => Field::class,
        // The group does the opposite: it keeps its own `collapsible` from the rows in its slot,
        // which read the sidebar's through `@aware`.
        'sidebar.group' => SidebarGroup::class,
    ];

    /**
     * The names the class behind a component's view hands that view as data: its public
     * properties.
     *
     * They reach the template as variables, where a slot scan cannot tell them from slots, and
     * none of them is anything a developer passes. Both kinds of class hand them over, a class
     * component and a class behind an anonymous view.
     *
     * @return list<string>
     */
    private static function viewDataNamesOf(string $name): array
    {
        $class = self::CLASS_COMPONENTS[$name] ?? self::VIEW_CLASSES[$name] ?? null;

        return $class !== null ? ClassPropsExtractor::publicPropertyNames($class) : [];
    }

    /**
     * Get the blade file path for a component.
     *
     * Resolves the top-level `components/<name>.blade.php` first, and falls back
     * to the directory-component form `components/<name>/index.blade.php` when the
     * top-level file does not exist (e.g. `list` lives at `list/index.blade.php`).
     * Without this fallback the registry would read that component's props from
     * a path that does not exist and report it with zero props.
     */
    private static function bladeFilePath(string $name): string
    {
        $base = __DIR__.'/../resources/views/components/';

        // `parent.child` → components/parent/child.blade.php. Without this the
        // props of every sub-component would be unreachable: `table.th` carries a
        // documented `headerScope` prop that no discovery surface could report,
        // because the path it would look at does not exist.
        if (str_contains($name, '.')) {
            [$parent, $child] = explode('.', $name, 2);
            $nested = $base.$parent.'/'.$child.'.blade.php';

            if (is_file($nested)) {
                return $nested;
            }
        }

        $topLevel = $base.$name.'.blade.php';
        if (is_file($topLevel)) {
            return $topLevel;
        }

        $indexed = $base.$name.'/index.blade.php';
        if (is_file($indexed)) {
            return $indexed;
        }

        // Neither exists — return the top-level path, which PropsParser reads as
        // no props (`[]`) for a genuinely missing component.
        return $topLevel;
    }

    /**
     * The component's Blade file, or null when it has none.
     *
     * The nullable companion to `bladeFilePath()`, which answers with a path
     * whether or not the file exists — deliberate, because prop extraction wants
     * a path to hand to a parser that returns nothing for a missing file. A
     * caller that must DECIDE (does this component have a template at all?)
     * needs the other answer, and building it by re-walking the directory is how
     * two resolvers come to disagree.
     *
     * A walk that knows the flat and dotted forms but not
     * `components/<name>/index.blade.php` resolves `list`, the one component in that
     * form, to nothing, and an empty slots list from it reads exactly like the list
     * of a component that has no slot.
     */
    public static function existingBladeFilePath(string $name): ?string
    {
        $path = self::bladeFilePath($name);

        return is_file($path) ? $path : null;
    }

    /**
     * The slots a component's template declares, default and named, each with
     * whether it is required.
     *
     * Lives here for the same reason prop extraction does: it is one question
     * with one answer, and every surface that asks it — the JSON manifest, the
     * MCP catalog, anything later — must get the same one. The class-side
     * exclusion is part of that answer, not a caller's responsibility: a
     * class-based component's public properties are referenced in its template
     * as `{{ $name }}`, which a slot scanner reads as a required named slot
     * unless it is told otherwise. A caller that forgets the exclusion does not
     * fail, it reports `chart` as requiring an `<x-slot:chartConfig>`. The class
     * behind an anonymous view hands its view data the same way: `field` hands
     * the `wkField` it builds for the control it labels.
     *
     * Takes a sub-component's dotted name as well (`field.set`).
     *
     * @return list<array{name: string, required: bool}>
     */
    public static function slotsOf(string $name): array
    {
        $path = self::existingBladeFilePath($name);

        if ($path === null) {
            return [];
        }

        return BladeParser::extractSlotsWithMetadataFromSource(
            (string) file_get_contents($path),
            $path,
            self::viewDataNamesOf($name),
        );
    }

    /**
     * The design tokens a component reads, sorted: the custom properties from the shipped
     * stylesheet that its template, the partials it includes, the package helpers it calls and
     * the stylesheet rules on the classes and `data-wk-*` attributes it renders read.
     *
     * A sub-component answers for itself, and a parent never for its parts. That boundary is what
     * lets a reader place a token on the element that reads it. Derived on every call, so the
     * answer cannot fall behind the markup; how, and what it cannot see, is on ComponentTokens.
     *
     * @return list<string>
     */
    public static function tokensOf(string $name): array
    {
        return ComponentTokens::of($name);
    }

    /**
     * A parent's sub-components, each with the props it declares and the tokens it reads.
     *
     * The bare-name form (`subComponentsOf()`) tells a tool that `table.th`
     * exists and nothing else, so its `headerScope` prop — shipped and
     * documented — was unreachable through every surface fed by it. A name
     * without its props is a pointer to documentation the tool cannot read.
     *
     * Each part answers for its own tokens and never for its parent's, so a token is listed once,
     * beside the element that reads it.
     *
     * @return list<array{name: string, tag: string, props: list<array<string, mixed>>, tokens: list<string>}>
     */
    public static function describeSubComponentsOf(string $parent): array
    {
        $out = [];

        foreach (self::subComponentsOf($parent) as $sub) {
            $out[] = [
                'name' => $sub,
                'tag' => self::tag($sub),
                'props' => self::extractProps($sub),
                'tokens' => self::tokensOf($sub),
            ];
        }

        return $out;
    }
}
