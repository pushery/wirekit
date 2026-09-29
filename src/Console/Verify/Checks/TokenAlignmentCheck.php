<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Support\AppCss;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class TokenAlignmentCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkTokenAlignment();
    }

    /**
     * Token-alignment diagnostic — compares Tailwind tokens against WireKit
     * tokens in `resources/css/app.css`. Surfaces the footgun where WireKit
     * chrome renders in a different font than the body copy at install-time,
     * rather than letting the mismatch ship to production.
     *
     * Checks:
     *   --font-sans   ↔ --font-wk-sans
     *   --font-serif  ↔ --font-wk-serif
     *   --font-mono   ↔ --font-wk-mono
     *   --color-accent ↔ --color-wk-accent
     *   --color-accent-foreground ↔ --color-wk-accent-fg
     *   --radius      ↔ --radius-wk
     *   --shadow      ↔ --shadow-wk
     *
     * Skips any pair where either side is a `var(...)` reference (the developer
     * is intentionally aliasing) or unset. Emits `✓` when families match, `⚠` when
     * they differ with actionable hint.
     */
    private function checkTokenAlignment(): void
    {
        $this->line('');
        $this->line('  Token alignment:');

        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            $this->reportWarn('  resources/css/app.css not found — skipping token-alignment checks');

            return;
        }

        // Strip CSS comments first — the per-pair scanner greps the FIRST
        // `--token: value` it finds, so an example pasted in a comment
        // above the real declaration would otherwise mask it.
        $content = AppCss::withoutComments((string) file_get_contents($appCss));

        $checks = [
            ['Sans font', '--font-sans', '--font-wk-sans', 'php artisan wirekit:install --font=<key>'],
            ['Serif font', '--font-serif', '--font-wk-serif', 'php artisan wirekit:install --font-serif=<key>'],
            ['Mono font', '--font-mono', '--font-wk-mono', 'php artisan wirekit:install --font-mono=<key>'],
            ['Accent color', '--color-accent', '--color-wk-accent', 'set --color-accent in @theme to match WireKit accent'],
            ['Accent foreground', '--color-accent-foreground', '--color-wk-accent-fg', 'set --color-accent-foreground in @theme'],
            ['Border radius', '--radius', '--radius-wk', 'set --radius in @theme to match --radius-wk'],
            ['Shadow', '--shadow', '--shadow-wk', 'set --shadow in @theme to match --shadow-wk'],
        ];

        foreach ($checks as [$label, $tw, $wk, $hint]) {
            $this->compareTokenPair($content, $label, $tw, $wk, $hint);
        }
    }

    /**
     * Compares one Tailwind token vs. WireKit token pair and reports outcome.
     */
    private function compareTokenPair(string $cssContent, string $label, string $twToken, string $wkToken, string $hint): void
    {
        $twValue = $this->extractTokenValue($cssContent, $twToken);
        $wkValue = $this->extractTokenValue($cssContent, $wkToken);

        // Skip if either token is unset — and say WHICH one. The condition has
        // always tested both sides, but the message named the Tailwind side
        // unconditionally, so a missing WireKit-side token sent the reader to
        // the one file where nothing was wrong. Printing the token name also
        // removes the need to know which side is called "Tailwind" and which
        // "WireKit" in order to read the line at all.
        if ($twValue === null || $wkValue === null) {
            $reason = match (true) {
                $twValue === null && $wkValue === null => "neither {$twToken} nor {$wkToken} set",
                $twValue === null => "{$twToken} unset",
                default => "{$wkToken} unset",
            };

            $this->reportInfoIndented("{$label}: skipped ({$reason})");

            return;
        }

        // Skip if either side is a var(...) reference (intentional aliasing)
        if (str_contains($twValue, 'var(') || str_contains($wkValue, 'var(')) {
            $this->reportInfoIndented("{$label}: skipped (var(...) reference — intentional alias)");

            return;
        }

        $twNormalized = $this->normalizeTokenValue($twValue);
        $wkNormalized = $this->normalizeTokenValue($wkValue);

        if ($twNormalized === $wkNormalized) {
            $this->line("    <fg=green>✓</> {$label}: aligned ({$twNormalized})");
            $this->context->report->passed++;
        } else {
            $this->reportWarn("  {$label}: mismatch — Tailwind `{$twValue}` vs WireKit `{$wkValue}`. Fix: {$hint}");
        }
    }

    /**
     * Extracts the value of a CSS custom property from the content.
     *
     * Returns null if the token is not found (developer hasn't set it).
     */
    private function extractTokenValue(string $cssContent, string $token): ?string
    {
        $pattern = '/'.preg_quote($token, '/').'\s*:\s*([^;\n]+)/';

        if (preg_match($pattern, $cssContent, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Normalizes a token value for cross-comparison.
     *
     * For font families: extracts the first comma-separated token, lowercases,
     * trims quotes. So `'Inter', ui-sans-serif` and `"Inter", ui-sans` both
     * normalize to `inter`.
     *
     * For other values: lowercases + trims whitespace.
     */
    private function normalizeTokenValue(string $value): string
    {
        $first = trim(explode(',', $value)[0]);
        $first = trim($first, "'\"");

        return mb_strtolower($first);
    }
}
