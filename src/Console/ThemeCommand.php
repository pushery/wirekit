<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pushery\WireKit\Support\SuggestSimilar;
use Pushery\WireKit\Theming\ThemePresetRegistry;

class ThemeCommand extends Command
{
    protected $signature = 'wirekit:theme {preset : Theme preset name — see ThemePresetRegistry::keys() for the canonical list (default, minimal, soft, material, brutalist, retro-terminal, cupertino at v2.1.0; downstream packages may register additional presets via ThemePresetRegistry::register()).}';

    protected $description = 'Apply a WireKit theme preset to your app.css';

    public function handle(): int
    {
        $preset = (string) $this->argument('preset');

        if (! ThemePresetRegistry::isValid($preset)) {
            $this->error("Unknown preset: {$preset}");
            $available = ThemePresetRegistry::keys();
            $this->line('  Available: '.implode(', ', $available));

            $suggestion = SuggestSimilar::format(
                SuggestSimilar::byLevenshtein($preset, $available)
            );
            if ($suggestion !== null) {
                $this->line('  '.$suggestion);
            }

            return self::FAILURE;
        }

        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            $this->error('resources/css/app.css not found');

            return self::FAILURE;
        }

        $content = (string) file_get_contents($appCss);

        // Remove existing WireKit theme block if present. This branch is
        // shared by `default` (the "return to bundled values" preset) and
        // by every other preset (so re-applying a new preset doesn't
        // accumulate stacked theme blocks). Idempotent — running the same
        // preset twice produces byte-identical output.
        $newContent = (string) preg_replace(
            '/\/\* wirekit:theme start \*\/.*?\/\* wirekit:theme end \*\/\n?/s',
            '',
            $content
        );

        $themeMeta = ThemePresetRegistry::get($preset);
        if ($themeMeta === null) {
            // Defensive: isValid() above already filtered. This branch
            // can only trigger if the registry shape mutates mid-call,
            // which shouldn't happen but keeps the type checker happy.
            return self::FAILURE;
        }

        if (ThemePresetRegistry::isDefault($preset)) {
            // `default` is a no-op apart from the block-removal above.
            // Always succeeds — whether or not a preset block existed.
            if ($newContent !== $content) {
                File::put($appCss, $newContent);
                $this->info('Reverted to default theme — previous preset block removed.');
            } else {
                $this->info('Already on default theme — no preset block to remove.');
            }

            return self::SUCCESS;
        }

        // Append the new preset block. The empty dark_vars case skips the
        // .dark block emission entirely so developers running the registry
        // through a strict CSS linter don't see an empty selector.
        $vars = $themeMeta['vars'];
        $darkVars = $themeMeta['dark_vars'];
        $themeBlock = "\n/* wirekit:theme start */\n@theme {\n{$vars}\n}\n";
        if ($darkVars !== null && $darkVars !== '') {
            $themeBlock .= "\n.dark {\n{$darkVars}\n}\n";
        }
        $themeBlock .= "/* wirekit:theme end */\n";

        File::put($appCss, $newContent.$themeBlock);

        $this->info("Applied theme: {$themeMeta['label']}");
        $this->line('  Theme variables injected into resources/css/app.css');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     *
     * @deprecated v2.1.0 — use ThemePresetRegistry::keys() directly.
     *             Retained as a thin shim because the public-API export
     *             surface advertises this method.
     */
    public static function availablePresets(): array
    {
        return ThemePresetRegistry::keys();
    }
}
