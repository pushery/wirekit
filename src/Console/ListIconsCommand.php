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
use Pushery\WireKit\Support\SuggestSimilar;

class ListIconsCommand extends Command
{
    protected $signature = 'wirekit:icons
        {--preset= : Filter to a single preset. Values: heroicons, heroicons-app, heroicons-marketing, lucide, phosphor, tabler.}
        {--as= : Output format: count|json|aliases|presets. Default: human-readable per-preset table.}
        {--format= : Alias for --as. Symfony-Console-style spelling for developers accustomed to the `--format=json` idiom common in other Laravel commands.}';

    protected $description = 'List every icon alias shipped with WireKit, grouped by preset';

    /**
     * Output formats supported by --as=…. Each format dispatches to a
     * branch in handle(). Mirrors ListComponentsCommand / ListFontsCommand's
     * API so developers don't have to learn a third convention.
     */
    private const FORMATS = ['count', 'json', 'aliases', 'presets'];

    /**
     * Preset key → instance map. Built once, consumed by every output
     * path. Same set as `IconResolver::BUILT_IN_PRESETS` — duplicated
     * here as instances (not classes) so the listing avoids the
     * Reflection round-trip on every render.
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
