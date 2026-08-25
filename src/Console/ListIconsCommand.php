<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Contracts\IconPreset;
use Pushery\WireKit\Icons\Presets\HeroiconsAppPreset;
use Pushery\WireKit\Icons\Presets\HeroiconsMarketingPreset;
use Pushery\WireKit\Icons\Presets\HeroiconsPreset;
use Pushery\WireKit\Icons\Presets\LucidePreset;
use Pushery\WireKit\Icons\Presets\PhosphorPreset;
use Pushery\WireKit\Icons\Presets\TablerPreset;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\PropsParser;
use Pushery\WireKit\Support\SuggestSimilar;
use Pushery\WireKit\WireKit;

class ListIconsCommand extends Command
{
    protected $signature = 'wirekit:icons
        {--preset= : Filter to a single preset. Values: heroicons, heroicons-app, heroicons-marketing, lucide, phosphor, tabler.}
        {--as= : Output format: count|json|aliases|presets. Default: human-readable per-preset table.}
        {--format= : Alias for --as. Symfony-Console-style spelling for developers accustomed to the `--format=json` idiom common in other Laravel commands.}
        {--audit : Read your views and report which icon names resolve through a declared alias and which name a glyph directly.}
        {--path=* : Directory to scan under --audit. Repeatable. Defaults to the application view paths.}';

    protected $description = 'List every icon alias shipped with WireKit, grouped by preset';

    /**
     * Output formats supported by --as=…. Each format dispatches to a
     * branch in handle(). Mirrors ListComponentsCommand / ListFontsCommand's
     * API so developers don't have to learn a third convention.
     */
    private const FORMATS = ['count', 'json', 'aliases', 'presets'];

    /**
     * Report which icon names in the caller's views resolve through a declared
     * alias, and which fall through to a glyph name.
     *
     * ## Why this is worth a command
     *
     * The two states look IDENTICAL in a browser. A glyph name renders
     * perfectly as long as the preset stays put; it breaks on a preset switch,
     * and then all of them break at once. There is no signal until the moment it
     * is expensive — which is exactly the shape an audit is for.
     *
     * ## Why it never fails on a fall-through
     *
     * Naming a glyph directly is a legitimate choice, not a violation. Some
     * glyphs have no alias and never will (`gavel`, `armchair`), and an audit
     * that reported those as errors would be switched off inside a week. The
     * exit code is 0 whenever the audit could measure at all.
     *
     * ## Why it does not suggest replacements
     *
     * "`sliders` is not an alias" is useful. "Use `settings` instead" would be
     * wrong unless the two point at the same glyph — checked once across ten
     * such pairs, and ten of ten pointed at a different character. A suggestion
     * without that check is a redesign wearing an adoption's clothes.
     */
    private function auditIconNames(): int
    {
        $paths = $this->resolveScanPaths();

        if ($paths === []) {
            $this->error('No directory to scan.');
            $this->line('  Pass --path=<dir>, or run this from an application where view.paths resolves.');

            // A configuration problem, not a clean result. Reporting success
            // here would mean answering "everything is an alias" about nothing.
            return self::FAILURE;
        }

        $findings = [];
        $filesScanned = 0;

        foreach ($paths as $path) {
            /** @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $files */
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $filesScanned++;

                foreach ($this->iconNamesIn((string) file_get_contents($file->getPathname())) as $hit) {
                    $findings[] = $hit + ['file' => $file->getPathname()];
                }
            }
        }

        if ($findings === []) {
            $this->error(sprintf(
                'Found no icon name in %d file(s) across %d path(s) — neither an <x-wirekit::icon> '
                .'tag nor an `icon="…"` prop on any of the %d components that take one.',
                $filesScanned,
                count($paths),
                count(self::componentsTakingAnIconName())
            ));
            $this->line('  Scanned: '.implode(', ', $paths));
            $this->line('');
            $this->line('  That is NOT the same answer as "every name is an alias" — it is the answer');
            $this->line('  "nothing was measured". Check the path before reading anything into it.');

            return self::FAILURE;
        }

        $literals = array_values(array_filter($findings, fn (array $f): bool => $f['name'] !== null));
        $dynamic = count($findings) - count($literals);

        /*
         * Tags were found, but not one of them names anything the source can
         * judge. Reporting that as three zeros reads like a clean sweep — the
         * exact shape this command refuses everywhere else, and it slipped
         * through here because the earlier guard asks whether ANY tag was seen,
         * not whether any could be DECIDED.
         *
         * Found by running the command's own documented default form against a
         * real application rather than the fixture it was built against.
         */
        if ($literals === []) {
            $this->error(sprintf(
                'Found %d icon usage(s) in %d file(s), and not one names an icon literally.',
                count($findings),
                $filesScanned
            ));

            if ($dynamic > 0) {
                // Both bound forms, because both surfaces are read: a tag binds its name as
                // `:name`, a component's prop as `:icon`. Naming only the first sends the
                // developer whose icons are props looking for a syntax they never wrote.
                $this->line(sprintf(
                    '  All %d are bound at runtime (:name="…" on a tag, :icon="…" on a prop), '
                    .'which the source cannot resolve.',
                    $dynamic
                ));
            }

            $this->line('');
            $this->line('  Nothing was judged, which is NOT the same answer as "every name is an alias".');

            return self::FAILURE;
        }

        $aliases = [];
        $fallThrough = [];

        foreach ($literals as $finding) {
            /** @var string $name */
            $name = $finding['name'];

            if (WireKit::isIconAlias($name)) {
                $aliases[] = $finding;
            } else {
                $fallThrough[] = $finding;
            }
        }

        $this->line('');
        $this->line(sprintf('  %d icon name(s) in %d file(s)', count($literals), $filesScanned));
        $this->line(sprintf('  %d resolve through a declared alias', count($aliases)));
        $this->line(sprintf('  %d resolve through the fall-through — they name a glyph directly', count($fallThrough)));

        if ($dynamic > 0) {
            // Counted rather than dropped. A scanner that silently ignores
            // `:name="$icon"` under-reports, and the reader has no way to tell
            // an audit that found nothing there from one that looked away.
            $this->line(sprintf('  %d bound at runtime (:name="…") — not decidable from the source', $dynamic));
        }

        if ($fallThrough !== []) {
            $this->line('');
            $this->line('  Not aliases (they break on a preset switch):');

            $seen = [];

            foreach ($fallThrough as $finding) {
                $key = $finding['name'].'@'.$finding['file'].':'.$finding['line'];

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                $this->line(sprintf(
                    '    %-24s %s:%d',
                    $finding['name'],
                    $finding['file'],
                    $finding['line']
                ));
            }
        }

        $this->line('');

        // Always success from here: the fall-through is a route, not a fault.
        return self::SUCCESS;
    }

    /**
     * Directories to walk, from --path or the application's view paths.
     *
     * @return array<int, string>
     */
    private function resolveScanPaths(): array
    {
        /** @var array<int, string> $given */
        $given = (array) $this->option('path');

        if ($given !== []) {
            return array_values(array_filter($given, 'is_dir'));
        }

        /** @var array<int, string> $viewPaths */
        $viewPaths = (array) config('view.paths', []);

        return array_values(array_filter($viewPaths, 'is_dir'));
    }

    /**
     * Every `<x-wirekit::icon>` in one file, with the name it was given.
     *
     * `name` is null for a bound value (`:name="$icon"`), which is a real state
     * and not an omission — the source cannot say what it resolves to, and the
     * caller reports it as its own number rather than folding it into either
     * side.
     *
     * The walk is `BladeParser`'s, not this file's. A hand-written one was tried
     * first and the drift guard rejected it, correctly: three scanners in this
     * package each hand-wrote a tag walk, each learned Blade to a different
     * depth, and the same defect was found and fixed three times. Routing
     * through the one parser inherits what it knows — a `{{-- don't --}}`
     * comment inside a tag, a tag another element interrupted, a file that ends
     * mid-tag — none of which this command would have handled.
     *
     * The attribute VALUE is read here rather than there on purpose:
     * `tagsFromSource()` returns boundaries and names, deliberately, so that it
     * does not become the second and weaker parser for every caller's different
     * question. Reading the value out of the boundaries it hands back is the
     * shape that guard describes as the correct one.
     *
     * @return list<array{name: string|null, line: int}>
     */
    private function iconNamesIn(string $contents): array
    {
        $hits = [];

        $iconPropTags = self::componentsTakingAnIconName();

        foreach (BladeParser::tagsFromSource($contents) as $tag) {
            // The <x-wirekit::icon> tag names its icon in `name`; every other component
            // that takes one names it in `icon`. Both go through WireKit::icon(), so both
            // are the same contract and the audit has to see both — see the block comment
            // on componentsTakingAnIconName() for what missing the second half cost.
            $attribute = match (true) {
                $tag['name'] === 'x-wirekit::icon' => 'name',
                in_array($tag['name'], $iconPropTags, true) => 'icon',
                default => null,
            };

            if ($attribute === null) {
                continue;
            }

            // A tag another element interrupted never closed, so whatever was
            // collected inside it is an artifact rather than a usage.
            if ($tag['terminator'] !== '>') {
                continue;
            }

            $line = substr_count(substr($contents, 0, $tag['start']), "\n") + 1;

            if (in_array($attribute, $tag['attributes'], true)) {
                $attributes = substr($contents, $tag['attrStart'], $tag['attrEnd'] - $tag['attrStart']);

                if (preg_match('/(?<![:\w-])'.$attribute.'\s*=\s*"([^"]*)"/', $attributes, $m) === 1) {
                    // `icon` is also the boolean that switches a component's automatic
                    // glyph off — alert and callout both use it that way. Those are not
                    // icon names and must not be audited as if a preset had to supply them.
                    if ($attribute === 'icon' && in_array(strtolower($m[1]), ['true', 'false', '1', '0', ''], true)) {
                        continue;
                    }

                    $hits[] = ['name' => $m[1], 'line' => $line];

                    continue;
                }
            }

            if (in_array(':'.$attribute, $tag['attributes'], true)) {
                $hits[] = ['name' => null, 'line' => $line];
            }
        }

        return $hits;
    }

    /**
     * Every WireKit component that accepts an ICON NAME as a prop, derived rather than
     * listed.
     *
     * WHY THIS EXISTS. The audit separates names under contract (a declared alias) from
     * names that merely happen to work today (a glyph name through the fall-through) —
     * and it read only <x-wirekit::icon> tags. But a navigation column names its icons in
     * props, because that is what the components ask for, and a prop-passed name goes
     * through exactly the same WireKit::icon() resolution. Measured in a consuming
     * project: 12 of 13 icon names were invisible to the audit, which then reported a
     * clean result over 8% of the surface in a summary that reads as a statement about
     * all of it. That is not a counting inaccuracy; it inverts the answer.
     *
     * DERIVED FROM THE VIEWS, never a hand-kept list. A list here would be a second
     * source of truth for a fact the templates already state, and the component that got
     * an icon prop after the list was written is exactly the one nobody would remember to
     * add — which is the same shape of blindness this method exists to remove.
     *
     * @return list<string> tag names, e.g. `x-wirekit::sidebar.item`
     */
    private static function componentsTakingAnIconName(): array
    {
        static $tags = null;

        if ($tags !== null) {
            return $tags;
        }

        $root = dirname(__DIR__, 2).'/resources/views/components';
        $tags = [];

        if (! is_dir($root)) {
            return $tags;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $names = array_column(PropsParser::parseBlade($file->getPathname()), 'name');

            if (! in_array('icon', $names, true)) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.blade.php'));
            $tags[] = 'x-wirekit::'.str_replace('/', '.', $relative);
        }

        sort($tags);

        return $tags;
    }

    /**
     * Preset key → instance map. Built once, consumed by every output
     * path. Same set as `IconResolver::BUILT_IN_PRESETS` — duplicated
     * here as instances (not classes) so the listing avoids the
     * Reflection round-trip on every render.
     *
     * @return array<string, mixed>
     */
    private function buildPresetMap(): array
    {
        return [
            'heroicons' => new HeroiconsPreset,
            'heroicons-app' => new HeroiconsAppPreset,
            'heroicons-marketing' => new HeroiconsMarketingPreset,
            'lucide' => new LucidePreset,
            'phosphor' => new PhosphorPreset,
            'tabler' => new TablerPreset,
        ];
    }

    public function handle(): int
    {
        // Dispatched before the listing options are read, because the audit
        // answers a different question about a different input: the listing
        // describes WireKit's vocabulary, the audit describes the caller's use
        // of it. Sharing a command keeps them one thing to discover; sharing a
        // code path would make each one's flags noise in the other's help.
        if ($this->option('audit') === true) {
            return $this->auditIconNames();
        }

        $presetFilter = $this->option('preset');
        $asValue = $this->option('as');
        $formatValue = $this->option('format');

        if ($asValue !== null && $asValue !== '' && $formatValue !== null && $formatValue !== '' && $asValue !== $formatValue) {
            $this->error('--as and --format are aliases and must not be passed with different values.');
            $this->line('  Pass one OR the other, not both.');

            return self::FAILURE;
        }
        $format = $asValue ?? $formatValue;

        $allPresets = $this->buildPresetMap();
        $availableKeys = array_keys($allPresets);

        if ($presetFilter !== null && $presetFilter !== '') {
            if (! in_array($presetFilter, $availableKeys, true)) {
                $this->error("Unknown preset: {$presetFilter}");
                $this->line('  Available: '.implode(', ', $availableKeys));

                $hint = SuggestSimilar::format(
                    SuggestSimilar::byLevenshtein($presetFilter, $availableKeys)
                );
                if ($hint !== null) {
                    $this->line('  '.$hint);
                }

                return self::FAILURE;
            }
            $presets = [$presetFilter => $allPresets[$presetFilter]];
        } else {
            $presets = $allPresets;
        }

        if ($format !== null && $format !== '') {
            if (! in_array($format, self::FORMATS, true)) {
                $this->error("Unknown --as format: {$format}");
                $this->line('  Available: '.implode(', ', self::FORMATS));

                $hint = SuggestSimilar::format(
                    SuggestSimilar::byLevenshtein($format, self::FORMATS)
                );
                if ($hint !== null) {
                    $this->line('  '.$hint);
                }

                return self::FAILURE;
            }

            return $this->dispatch($format, $presets);
        }

        $this->renderHumanReadable($presets);

        return self::SUCCESS;
    }

    /**
     * Human-readable per-preset listing. Each preset section shows
     * the alias-count summary line, the active-by-default indicator
     * (heroicons is the package default), and every alias → identifier
     * mapping.
     *
     * @param  array<string, IconPreset>  $presets
     */
    private function renderHumanReadable(array $presets): void
    {
        $activePresets = $this->activePresetKeys();
        $totalAliases = 0;

        foreach ($presets as $key => $preset) {
            $aliases = $preset->icons();
            $count = count($aliases);
            $totalAliases += $count;
            $isActive = in_array($key, $activePresets, true);
            $activeMark = $isActive ? '<fg=green>[active]</>' : '<fg=gray>[opt-in]</>';

            $this->info("{$key}  ({$count} aliases)  {$activeMark}");
            ksort($aliases);
            foreach ($aliases as $alias => $identifier) {
                $this->line("  <fg=green>{$alias}</>  <fg=gray>→</>  {$identifier}");
            }
            $this->line('');
        }

        $this->info("Total: {$totalAliases} alias".($totalAliases === 1 ? '' : 'es').' across '.count($presets).' preset'.(count($presets) === 1 ? '' : 's'));
        $this->line('  Active preset(s): '.implode(', ', $activePresets));
        $this->line('  Set `wirekit.icons.presets` in config/wirekit.php to opt into additional presets.');
    }

    /**
     * Resolve which preset keys are currently active given the app's
     * `wirekit.icons.preset` / `wirekit.icons.presets` config. Used to
     * mark presets as [active] / [opt-in] in the human-readable listing.
     *
     * @return list<string>
     */
    private function activePresetKeys(): array
    {
        $multi = config('wirekit.icons.presets');
        if (is_array($multi) && $multi !== []) {
            return array_values(array_filter($multi, 'is_string'));
        }
        $single = config('wirekit.icons.preset', 'heroicons');

        return is_string($single) ? [$single] : ['heroicons'];
    }

    /**
     * Route to the requested --as= handler.
     *
     * @param  array<string, IconPreset>  $presets
     */
    private function dispatch(string $format, array $presets): int
    {
        switch ($format) {
            case 'count':
                $total = 0;
                foreach ($presets as $preset) {
                    $total += count($preset->icons());
                }
                $this->line((string) $total);

                return self::SUCCESS;

            case 'presets':
                foreach (array_keys($presets) as $key) {
                    $this->line($key);
                }

                return self::SUCCESS;

            case 'aliases':
                // Unique sorted alias list across every selected preset.
                $aliases = [];
                foreach ($presets as $preset) {
                    foreach (array_keys($preset->icons()) as $alias) {
                        $aliases[$alias] = true;
                    }
                }
                ksort($aliases);
                foreach (array_keys($aliases) as $alias) {
                    $this->line($alias);
                }

                return self::SUCCESS;

            case 'json':
                $active = $this->activePresetKeys();
                $payload = [];
                foreach ($presets as $key => $preset) {
                    $payload[] = [
                        'key' => $key,
                        'count' => count($preset->icons()),
                        'active' => in_array($key, $active, true),
                        'requires' => $preset->requires(),
                        'aliases' => $preset->icons(),
                    ];
                }
                // JSON_HEX_TAG: same XSS-safety contract as every other
                // wirekit:* JSON-emitting command.
                $this->output->write(
                    (string) json_encode(
                        $payload,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
                    )
                );

                return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
