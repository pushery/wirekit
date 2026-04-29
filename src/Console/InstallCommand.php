<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Pushery\WireKit\Fonts\FontRegistry;

class InstallCommand extends Command
{
    protected $signature = 'wirekit:install
        {--preset=default : Theme preset (default, minimal, soft, material, brutalist, retro-terminal, cupertino)}
        {--font= : Inject sans font-family override (must be a sans-category key from FontRegistry)}
        {--font-serif= : Inject serif font-family override (must be a serif-category key from FontRegistry)}
        {--font-mono= : Inject mono font-family override (must be a mono-category key from FontRegistry)}';

    protected $description = 'Install WireKit into your Laravel application';

    public function handle(): int
    {
        $this->info('Installing WireKit...');
        $this->line('');

        $this->maybeRunInteractivePrompts();

        $this->publishConfig();
        $this->publishAssets();
        $this->addTailwindSource();
        $this->addBladeDirectives();
        $this->addGitignoreEntry();
        $this->processFontFlags();

        $preset = $this->option('preset');
        if ($preset !== 'default') {
            $this->call('wirekit:theme', ['preset' => $preset]);
        }

        $this->line('');
        $this->call('wirekit:verify');

        $this->line('');
        $this->info('WireKit installed successfully!');

        return self::SUCCESS;
    }

    /**
     * Interactive prompt mode for `wirekit:install`.
     *
     * Fires when:
     *   1. No flags are passed (preset is default, all --font* options empty), AND
     *   2. The command is running in an interactive TTY ($this->input->isInteractive()).
     *
     * In CI / --no-interaction / scripted contexts, this method is a no-op and
     * the command runs with v1.5.0-identical defaults. Beginners running
     * `php artisan wirekit:install` interactively get a guided choose-your-
     * preset / choose-your-fonts flow.
     *
     * Selected values are written back into the option array so the rest of
     * `handle()` picks them up via `$this->option(...)` as if they had been
     * passed as flags.
     */
    private function maybeRunInteractivePrompts(): void
    {
        // Skip when any flag was passed — explicit invocation wins over prompts.
        $hasFlags = $this->option('preset') !== 'default'
            || ! empty($this->option('font'))
            || ! empty($this->option('font-serif'))
            || ! empty($this->option('font-mono'));

        if ($hasFlags) {
            return;
        }

        // Skip in non-interactive contexts (CI, --no-interaction, piped scripts)
        if (! $this->input->isInteractive()) {
            return;
        }

        // Skip in unit tests — Laravel's artisan() test runner considers itself
        // interactive but doesn't forward real stdin. Without this guard, every
        // existing test that doesn't explicitly mock the prompts would fail.
        if (app()->runningUnitTests()) {
            return;
        }

        $this->line('  <fg=blue>i</> Interactive setup — press Enter at any prompt to skip.');
        $this->line('');

        $preset = $this->choice(
            'Theme preset',
            ['default', 'minimal', 'soft', 'material', 'brutalist', 'retro-terminal', 'cupertino'],
            'default'
        );
        if ($preset !== 'default') {
            $this->input->setOption('preset', $preset);
        }

        // Sans font — top-5 popular keys + skip
        $sansChoice = $this->choice(
            'Sans-serif font (skip = use bundled defaults)',
            ['skip', 'inter', 'roboto', 'open-sans', 'lato', 'montserrat'],
            'skip'
        );
        if ($sansChoice !== 'skip') {
            $this->input->setOption('font', $sansChoice);
        }

        $serifChoice = $this->choice(
            'Serif font (optional)',
            ['skip', 'lora', 'playfair-display', 'merriweather'],
            'skip'
        );
        if ($serifChoice !== 'skip') {
            $this->input->setOption('font-serif', $serifChoice);
        }

        $monoChoice = $this->choice(
            'Monospace font (optional)',
            ['skip', 'jetbrains-mono', 'fira-code', 'source-code-pro'],
            'skip'
        );
        if ($monoChoice !== 'skip') {
            $this->input->setOption('font-mono', $monoChoice);
        }

        $this->line('');
    }

    /**
     * Routes each `--font*` flag through the font-override injector.
     *
     * Centralised here so future multi-font flags (`--font-serif`,
     * `--font-mono`) can be added by extending the categories array.
     */
    private function processFontFlags(): void
    {
        $categories = [
            'sans' => 'font',
            'serif' => 'font-serif',
            'mono' => 'font-mono',
        ];

        foreach ($categories as $category => $optionName) {
            $key = $this->option($optionName);
            if ($key === null || $key === '') {
                continue;
            }

            try {
                $this->injectFontOverrides($category, (string) $key);
                $this->publishFontAssets();
            } catch (InvalidArgumentException $e) {
                $this->line('  <fg=red>✗</> '.$e->getMessage());
            }
        }
    }

    /**
     * Resolves the font key, validates the category, and idempotently writes
     * a marker-pair-bracketed @theme + :root override block into app.css.
     *
     * Marker shape: `/* wirekit:font-{category}:start *\/` … `:end *\/`
     * Re-running with the same key produces byte-identical output; re-running
     * with a different key swaps the bracketed content.
     *
     * Throws on unknown key or wrong category.
     */
    private function injectFontOverrides(string $category, string $fontKey): void
    {
        $preset = FontRegistry::get($fontKey);

        if ($preset === null) {
            $available = array_keys(FontRegistry::category($category));

            throw new InvalidArgumentException(
                "Unknown font key '{$fontKey}'. Available {$category} fonts: ".implode(', ', $available)
            );
        }

        if ($preset->category !== $category) {
            $available = array_keys(FontRegistry::category($category));

            throw new InvalidArgumentException(
                "Font '{$fontKey}' is category '{$preset->category}', not '{$category}'. Available {$category} fonts: ".implode(', ', $available)
            );
        }

        $shape = $this->detectTailwindConfigShape();

        match ($shape) {
            'css-first', 'both' => $this->injectFontOverridesCssFirst($category, $preset, $shape === 'both'),
            'js-config' => $this->injectFontOverridesJsConfig($category, $preset),
            'none' => $this->line("  <fg=yellow>!</> Neither resources/css/app.css nor tailwind.config.js found — skipping --font-{$category}={$fontKey} injection"),
        };
    }

    /**
     * Detects which Tailwind config shape the consumer uses.
     *
     * Priority on tie: CSS-first (Tailwind v4 default) wins over JS-config.
     * Tailwind v4 deprecates the JS config; we lean into the recommended path.
     *
     * @return 'css-first'|'js-config'|'both'|'none'
     */
    private function detectTailwindConfigShape(): string
    {
        $appCss = resource_path('css/app.css');
        $jsConfig = base_path('tailwind.config.js');

        $cssFirst = file_exists($appCss) && str_contains((string) file_get_contents($appCss), '@theme');
        $jsConfigPresent = file_exists($jsConfig);

        return match (true) {
            $cssFirst && $jsConfigPresent => 'both',
            $cssFirst => 'css-first',
            $jsConfigPresent => 'js-config',
            file_exists($appCss) => 'css-first', // app.css exists but no @theme yet — still write CSS-first
            default => 'none',
        };
    }

    /**
     * CSS-first injection — writes @theme + :root override block into app.css.
     */
    private function injectFontOverridesCssFirst(string $category, $preset, bool $isBoth = false): void
    {
        $appCss = resource_path('css/app.css');
        $content = (string) file_get_contents($appCss);
        $block = $this->buildFontOverrideBlock($category, $preset->fontFamily());

        $startMarker = "/* wirekit:font-{$category}:start */";
        $endMarker = "/* wirekit:font-{$category}:end */";

        $pattern = '/'.preg_quote($startMarker, '/').'.*?'.preg_quote($endMarker, '/').'/s';

        if (preg_match($pattern, $content) === 1) {
            $newContent = preg_replace($pattern, $block, $content, 1);
        } else {
            $newContent = rtrim($content)."\n\n".$block."\n";
        }

        if ($newContent === $content) {
            $this->line("  <fg=yellow>!</> Font {$category}={$preset->key} already injected — no change");

            return;
        }

        File::put($appCss, $newContent);

        if ($isBoth) {
            $this->line('  <fg=blue>i</> Both app.css @theme and tailwind.config.js detected — writing CSS-first per Tailwind v4 recommendation');
        }

        $this->line("  <fg=green>✓</> Injected --font-{$category} + --font-wk-{$category} = {$preset->family} into app.css");
    }

    /**
     * JS-config injection — writes into tailwind.config.js theme.extend.fontFamily.{cat}.
     *
     * Anchored regex match against the well-defined Tailwind v3 config shape.
     * On shape mismatch (custom config layout, comments interleaved, etc.),
     * logs an actionable skip message rather than risking AST corruption.
     */
    private function injectFontOverridesJsConfig(string $category, $preset): void
    {
        $jsConfig = base_path('tailwind.config.js');
        $content = (string) file_get_contents($jsConfig);
        $family = $preset->fontFamily();
        // Tailwind expects an array literal — split fallbacks into JS array form
        $jsArray = $this->cssFontFamilyToJsArray($family);

        // Match theme.extend.fontFamily.{cat}: [...] inside the config object
        $catKey = preg_quote($category, '/');
        $existingPattern = '/(theme\s*:\s*\{[^}]*extend\s*:\s*\{[^}]*fontFamily\s*:\s*\{[^}]*)'.$catKey.'\s*:\s*\[[^\]]*\]/s';

        if (preg_match($existingPattern, $content) === 1) {
            $newContent = preg_replace(
                $existingPattern,
                '$1'.$category.': '.$jsArray,
                $content,
                1
            );
            File::put($jsConfig, $newContent);
            $this->line("  <fg=green>✓</> Updated tailwind.config.js theme.extend.fontFamily.{$category} = {$preset->family}");

            return;
        }

        // Insert into existing fontFamily block (no key for this category yet)
        $extendPattern = '/(fontFamily\s*:\s*\{)([^}]*)(\})/s';
        if (preg_match($extendPattern, $content) === 1) {
            $newContent = preg_replace(
                $extendPattern,
                '$1$2        '.$category.': '.$jsArray.",\n      \$3",
                $content,
                1
            );
            File::put($jsConfig, $newContent);
            $this->line("  <fg=green>✓</> Added {$category} to tailwind.config.js theme.extend.fontFamily");

            return;
        }

        $this->line("  <fg=yellow>!</> tailwind.config.js shape mismatch — couldn't locate theme.extend.fontFamily. Add manually: {$category}: {$jsArray}");
    }

    /**
     * Converts a CSS font-family value (`'Inter', ui-sans-serif, system-ui`) to a JS array literal.
     */
    private function cssFontFamilyToJsArray(string $cssFontFamily): string
    {
        $parts = array_map('trim', explode(',', $cssFontFamily));
        $quoted = array_map(fn ($p) => str_starts_with($p, "'") ? '"'.trim($p, "'").'"' : '"'.$p.'"', $parts);

        return '['.implode(', ', $quoted).']';
    }

    /**
     * Builds the marker-bracketed @theme + :root override block.
     *
     * Two CSS variables are written:
     *   --font-{category}     → drives Tailwind utilities (font-sans, etc.)
     *   --font-wk-{category}  → drives WireKit chrome
     *
     * Setting both ensures Tailwind utilities and WireKit components stay
     * visually aligned. Closes the brief's "WireKit chrome was Inter while
     * copy was Instrument Sans" footgun.
     */
    private function buildFontOverrideBlock(string $category, string $fontFamily): string
    {
        return <<<CSS
/* wirekit:font-{$category}:start */
@theme {
    --font-{$category}: {$fontFamily};
}

@layer base {
    :root {
        --font-wk-{$category}: {$fontFamily};
    }
}
/* wirekit:font-{$category}:end */
CSS;
    }

    /**
     * Triggers the existing `vendor:publish --tag=wirekit-fonts` flow so the
     * resolved preset's local CSS file lands in `public/vendor/wirekit/fonts/`.
     *
     * Non-overwriting (`--force=false`) — re-running won't clobber consumer
     * customisations. Idempotent: silently skips already-published files.
     */
    private function publishFontAssets(): void
    {
        $this->callSilently('vendor:publish', ['--tag' => 'wirekit-fonts']);
    }

    private function publishConfig(): void
    {
        if (file_exists(config_path('wirekit.php'))) {
            $this->line('  <fg=yellow>!</> config/wirekit.php already exists — skipping');

            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'wirekit-config']);
        $this->line('  <fg=green>✓</> Published config/wirekit.php');
    }

    private function publishAssets(): void
    {
        $this->callSilently('vendor:publish', ['--tag' => 'wirekit-assets', '--force' => true]);
        $this->line('  <fg=green>✓</> Published assets to public/vendor/wirekit/');
    }

    private function addTailwindSource(): void
    {
        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            $this->line('  <fg=yellow>!</> resources/css/app.css not found — add @source manually');

            return;
        }

        $content = file_get_contents($appCss);

        if (str_contains($content, 'wirekit') && str_contains($content, '@source')) {
            $this->line('  <fg=yellow>!</> Tailwind @source already configured — skipping');

            return;
        }

        $sourceLine = "@source '../../vendor/pushery/wirekit/resources/views/**/*.blade.php';";

        // Insert after @import 'tailwindcss' if present, otherwise append
        if (str_contains($content, "@import 'tailwindcss'") || str_contains($content, '@import "tailwindcss"')) {
            $content = preg_replace(
                '/(@import\s+[\'"]tailwindcss[\'"];?)/',
                "$1\n{$sourceLine}",
                $content,
                1
            );
        } else {
            $content .= "\n{$sourceLine}\n";
        }

        File::put($appCss, $content);
        $this->line('  <fg=green>✓</> Added @source for WireKit to app.css');
    }

    private function addBladeDirectives(): void
    {
        $layoutPaths = [
            resource_path('views/components/layouts/app.blade.php'),
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/components/layout.blade.php'),
        ];

        $layoutFile = null;
        foreach ($layoutPaths as $path) {
            if (file_exists($path)) {
                $layoutFile = $path;

                break;
            }
        }

        if (! $layoutFile) {
            $this->line('  <fg=yellow>!</> No layout file found — add @wirekitStyles/@wirekitScripts manually');

            return;
        }

        $content = file_get_contents($layoutFile);
        $modified = false;

        if (! str_contains($content, '@wirekitStyles')) {
            // Add before </head> or @vite
            if (str_contains($content, '</head>')) {
                $content = str_replace('</head>', "    @wirekitStyles\n</head>", $content);
                $modified = true;
            }
        }

        if (! str_contains($content, '@wirekitScripts')) {
            // Add before </body>
            if (str_contains($content, '</body>')) {
                $content = str_replace('</body>', "    @wirekitScripts\n</body>", $content);
                $modified = true;
            }
        }

        if ($modified) {
            File::put($layoutFile, $content);
            $this->line('  <fg=green>✓</> Added Blade directives to '.basename($layoutFile));
        } else {
            $this->line('  <fg=yellow>!</> Blade directives already present in layout');
        }
    }

    private function addGitignoreEntry(): void
    {
        $gitignorePath = base_path('.gitignore');

        if (! file_exists($gitignorePath)) {
            return;
        }

        $content = file_get_contents($gitignorePath);

        if (str_contains($content, 'vendor/wirekit')) {
            return;
        }

        File::append($gitignorePath, "\n/public/vendor/wirekit\n");
        $this->line('  <fg=green>✓</> Added public/vendor/wirekit to .gitignore');
    }
}
