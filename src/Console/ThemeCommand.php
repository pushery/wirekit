<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pushery\WireKit\Support\SuggestSimilar;
use Pushery\WireKit\Theming\AccentLadder;
use Pushery\WireKit\Theming\Oklch;
use Pushery\WireKit\Theming\ThemePresetRegistry;
use Pushery\WireKit\Theming\WcagContrast;

class ThemeCommand extends Command
{
    protected $signature = 'wirekit:theme {preset : Theme preset name — see ThemePresetRegistry::keys() for the canonical list (default, minimal, soft, material, brutalist, retro-terminal, cupertino, aurora; downstream packages may register additional presets via ThemePresetRegistry::register()).}
        {--accent= : A brand color the preset\'s accent is derived from for light and dark, with the contrast of its label and its text held (AccentLadder)}
        {--success= : A brand color the success state is derived from, the way the accent is}
        {--danger= : A brand color the danger state is derived from, the way the accent is}
        {--warning= : A brand color the warning state is derived from, the way the accent is}';

    protected $description = 'Apply a WireKit theme preset to your app.css';

    /**
     * The colors the command derives tokens from, in the order their blocks are written.
     */
    private const COLORS = ['accent', 'success', 'danger', 'warning'];

    /**
     * The border token each state's text color is written to as well.
     *
     * A field in an error or success state draws its border with the same color its message is
     * written in, so the two are one value. Warning has no border token of its own.
     */
    private const STATE_BORDERS = [
        'success' => '--color-wk-border-success',
        'danger' => '--color-wk-border-error',
    ];

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

        $colors = [];

        foreach (self::COLORS as $name) {
            $color = $this->option($name);

            if (is_string($color) && trim($color) !== '') {
                $colors[$name] = trim($color);
            }
        }

        // Read before the file is touched: a color the ladder cannot use leaves app.css as it was.
        foreach ($colors as $name => $color) {
            if (Oklch::fromCss($color) === null) {
                $this->error("Cannot read the {$name} [{$color}] as an opaque color.");
                $this->line("  Pass a hex color, rgb(), hsl() or oklch() without transparency, e.g. --{$name}=\"#e11d48\".");

                return self::FAILURE;
            }
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
        // preset twice produces byte-identical output. The block is written
        // with a line break in front of its start marker, so the pattern
        // takes that break with it; left behind, every run added one more.
        $newContent = preg_replace(
            '/\n?\/\* wirekit:theme start \*\/.*?\/\* wirekit:theme end \*\/\n?/s',
            '',
            $content
        );

        // A PCRE failure returns null, and cast to a string that is '': written,
        // it would empty the developer's stylesheet on exactly the input that
        // can break the match (an app.css carrying a start marker with no end
        // marker exhausts the backtrack limit past roughly a megabyte), leaving
        // nothing but the theme block. Their file is not ours to lose, so a
        // failed match refuses instead of guessing.
        if ($newContent === null) {
            $this->error('Could not scan resources/css/app.css for an existing theme block.');
            $this->line('  '.preg_last_error_msg().' — app.css was left untouched.');
            $this->line('  Check that the file has a matching /* wirekit:theme end */ marker.');

            return self::FAILURE;
        }

        $themeMeta = ThemePresetRegistry::get($preset);
        if ($themeMeta === null) {
            // Defensive: isValid() above already filtered. This branch
            // can only trigger if the registry shape mutates mid-call,
            // which shouldn't happen but keeps the type checker happy.
            return self::FAILURE;
        }

        if (ThemePresetRegistry::isDefault($preset) && $colors === []) {
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

        // The derived tokens go after the preset's own variables in each block, so they win over the
        // values the preset sets. They are derived on the surfaces the preset leaves in force: in
        // light mode the root block over the stylesheet's defaults, in dark mode the dark block,
        // then the root block, which applies there too, then the defaults.
        if ($colors !== []) {
            $root = self::declarations($vars);
            $dark = self::declarations((string) $darkVars) + $root;

            $vars = ltrim(rtrim($vars)."\n".self::derivedBlocks($colors, false, self::resolved($root)), "\n");
            $darkVars = ltrim(rtrim((string) $darkVars)."\n".self::derivedBlocks($colors, true, self::resolved($dark)), "\n");
        }

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

        foreach ($colors as $name => $color) {
            $this->line('  '.ucfirst($name)." derived from {$color} for light and dark");
        }

        return self::SUCCESS;
    }

    /**
     * The custom properties a block declares, comments left out.
     *
     * @return array<string, string>
     */
    private static function declarations(string $css): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('#(--[\w-]+)\s*:\s*([^;{}]+);#', $css, $matches, PREG_SET_ORDER);

        $declarations = [];

        foreach ($matches as [, $name, $value]) {
            $declarations[$name] = trim($value);
        }

        return $declarations;
    }

    /**
     * The declarations with their `var()` references filled in from one another, as Aurora's
     * colors read its hue.
     *
     * @param  array<string, string>  $declarations
     * @return array<string, string>
     */
    private static function resolved(array $declarations): array
    {
        return array_map(static fn (string $value): string => WcagContrast::resolveCssVars($value, $declarations), $declarations);
    }

    /**
     * The tokens derived from each color, a block of declarations per color.
     *
     * A state's fill is a surface as well: `callout` and `alert` tint the card with it. So the
     * fills come first, and every text color is then read on the tints of the states as this
     * block writes them.
     *
     * @param  array<string, string>  $colors  the color given for each name, in the order of COLORS
     * @param  array<string, string>  $theme  the tokens in force in this mode, their references filled in
     */
    private static function derivedBlocks(array $colors, bool $dark, array $theme): string
    {
        foreach ($colors as $name => $color) {
            if ($name !== 'accent') {
                $theme["--color-wk-{$name}"] = AccentLadder::onTheme($color, $dark, $theme)['--color-wk-accent'];
            }
        }

        $blocks = [];

        foreach ($colors as $name => $color) {
            $blocks[] = self::block($name, $color, self::tokens($name, AccentLadder::onTheme($color, $dark, $theme)));
        }

        return implode("\n", $blocks);
    }

    /**
     * The ladder's four tokens under the names one color stands for. The accent keeps them as they
     * are; a state takes them as its fill, hover, text and label, and its text color goes to its
     * border token as well.
     *
     * @param  array{'--color-wk-accent': string, '--color-wk-accent-hover': string, '--color-wk-accent-content': string, '--color-wk-accent-fg': string}  $ladder
     * @return array<string, string>
     */
    private static function tokens(string $name, array $ladder): array
    {
        if ($name === 'accent') {
            return $ladder;
        }

        $tokens = [
            "--color-wk-{$name}" => $ladder['--color-wk-accent'],
            "--color-wk-{$name}-hover" => $ladder['--color-wk-accent-hover'],
            "--color-wk-{$name}-text" => $ladder['--color-wk-accent-content'],
            "--color-wk-{$name}-fg" => $ladder['--color-wk-accent-fg'],
        ];

        if (isset(self::STATE_BORDERS[$name])) {
            $tokens[self::STATE_BORDERS[$name]] = $ladder['--color-wk-accent-content'];
        }

        return $tokens;
    }

    /**
     * Tokens as declarations, under a comment that says where they came from.
     *
     * @param  array<string, string>  $tokens
     */
    private static function block(string $name, string $color, array $tokens): string
    {
        $lines = ['    /* '.ucfirst($name)." derived from {$color} by AccentLadder */"];

        foreach ($tokens as $token => $value) {
            $lines[] = "    {$token}: {$value};";
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     *
     * @deprecated v2.1.0 — use ThemePresetRegistry::keys() directly.
     *
     * Nothing advertises this method: the public-API baseline covers no console class, the
     * MCP catalog and the project-root schema do not mention it, and the other methods of the
     * name belong to `IconResolver`, for icon presets.
     *
     * It stays for the reason PersistedToggle states for itself: this is an
     * MIT package on Packagist, so removing a public method is a backward-compatibility
     * decision rather than a cleanup, and it is not this file's to make. It goes in the
     * next MAJOR.
     *
     * Nothing in the package calls it.
     */
    public static function availablePresets(): array
    {
        return ThemePresetRegistry::keys();
    }
}
