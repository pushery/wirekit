<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pushery\WireKit\Support\SuggestSimilar;
use Pushery\WireKit\Theming\ThemePresetRegistry;

class ThemeCommand extends Command
{
    protected $signature = 'wirekit:theme {preset : Theme preset name — see ThemePresetRegistry::keys() for the canonical list (default, minimal, soft, material, brutalist, retro-terminal, cupertino, aurora at v2.5.0; downstream packages may register additional presets via ThemePresetRegistry::register()).}';

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
        // Emit the LIGHT palette as a plain unlayered `:root {}` block — NOT a
        // Tailwind `@theme {}` block. `@theme` compiles into the `theme` cascade
        // LAYER, and unlayered CSS always beats layered CSS, so dist/wirekit.css's
        // own unlayered `:where(:root)` defaults would override a layered preset
        // and the light palette would silently no-op on the `@wirekitStyles`
        // <link> path. A plain `:root {}` (specificity 0,1,0, unlayered) wins over
        // those defaults and mirrors the `.dark {}` block emitted just below.
        // (WireKit tokens are consumed via `var(--color-wk-*)`, not as generated
        // `bg-wk-*` utilities, so dropping `@theme` loses no utilities.)
        $themeBlock = "\n/* wirekit:theme start */\n:root {\n{$vars}\n}\n";
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
     *
     * ⚠️ THE REASON GIVEN HERE USED TO BE "the public-API export surface advertises this
     * method", AND THAT IS NOT TRUE. Nothing advertises it: the public-API baseline covers
     * no console class at all, the MCP catalog and the project-root schema do not mention
     * it, and every other occurrence of the name in this repository is
     * `IconResolver::availablePresets()` — a different class, for icon presets. So the
     * removal trigger named a condition that is not true and cannot become false, which
     * leaves an agent either deleting the method on a premise it just disproved or
     * deferring it forever.
     *
     * The real reason it stays is the one PersistedToggle states for itself: this is an
     * MIT package on Packagist, so removing a public method is a backward-compatibility
     * decision rather than a cleanup, and it is not this file's to make. It goes in the
     * next MAJOR.
     *
     * Zero callers measured in src/, resources/, config/, docs/ and tests/, and a guard
     * keeps it that way.
     */
    public static function availablePresets(): array
    {
        return ThemePresetRegistry::keys();
    }
}
