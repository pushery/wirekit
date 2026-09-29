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
final class RootDarkSymmetryCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkRootDarkSymmetry();
    }

    /**
     * Detect `:root` ↔ `.dark` color-token override asymmetry.
     *
     * If a developer overrides `--color-wk-accent` in `:root` but DOES NOT
     * provide a matching declaration in `.dark`, that override silently
     * carries into dark mode — `:root` outranks the `:where(.dark)` WireKit
     * declares its own dark value with, so the token keeps the light value
     * rather than turning over. The existing checkTokenAlignment()
     * compares Tailwind↔WireKit pairs, not the developer's own root vs
     * dark blocks — so this complementary check fills that gap.
     *
     * Restricts to `--color-wk-*` family. Font / radius / shadow / motion
     * tokens are typically theme-agnostic (same value in both modes), so
     * asymmetry there is not a bug. Reads `resources/css/app.css` only —
     * the source of truth for developer overrides; the built bundle aggregates
     * Tailwind output with the developer's source so reading the source is
     * cleaner.
     */
    private function checkRootDarkSymmetry(): void
    {
        // Every path out of this check SAYS something. The doctor's contract is that
        // every check emits at least one observable line — the note on the
        // Alpine-hygiene check states it, and a silent return makes a test flaky,
        // which is the cheap version of the same problem.
        //
        // The expensive version is what a developer sees: a check that vanishes and
        // one that passes look identical in the output, so an app whose stylesheet
        // this check cannot read is told nothing at all — not that it is fine, not
        // that it was skipped, not why. That shape is common rather than exotic: an
        // application using the `@theme` block from the theming guide has no `:root`
        // rule of its own and lands here every time.
        $appCss = resource_path('css/app.css');
        if (! file_exists($appCss)) {
            $this->reportInfo('Token symmetry: skipped — no resources/css/app.css to read');

            return;
        }
        $content = file_get_contents($appCss);
        if ($content === false) {
            $this->reportInfo('Token symmetry: skipped — resources/css/app.css could not be read');

            return;
        }
        // Strip CSS comments first so a `:root {` / `.dark {` written
        // inside a comment can't mis-anchor extractCssBlock().
        $content = AppCss::withoutComments($content);

        $rootBlock = $this->extractCssBlock($content, ':root');
        $darkBlock = $this->extractCssBlock($content, '.dark');

        if ($rootBlock === '' || $darkBlock === '') {
            // No :root or no .dark — no asymmetry to report. The developer
            // either has neither (clean default) or has only :root with
            // no dark intention (also fine — they're light-only).
            //
            // Named rather than merged into one line, because the two say different
            // things: no `:root` means this check has nothing to compare, and no
            // `.dark` means there is no dark theme for it to be asymmetric with.
            $this->reportInfo($rootBlock === ''
                ? 'Token symmetry: skipped — no `:root { … }` rule in resources/css/app.css'
                : 'Token symmetry: skipped — no `.dark { … }` rule in resources/css/app.css (light-only theme)');

            return;
        }

        $rootTokens = $this->parseColorTokens($rootBlock);
        $darkTokens = $this->parseColorTokens($darkBlock);

        if ($rootTokens === []) {
            $this->reportInfo('Token symmetry: skipped — the `:root` rule overrides no `--color-wk-*` token');

            return;
        }

        $asymmetric = array_diff_key($rootTokens, $darkTokens);

        if ($asymmetric === []) {
            $this->reportPass('Token symmetry: every overridden `--color-wk-*` token has a matching `.dark` declaration');

            return;
        }

        $this->reportWarn('Token symmetry: '.count($asymmetric).' color token(s) overridden in `:root` but not in `.dark`');

        // The advice is the same for every token here; the REASON is not, so there are two
        // messages.
        //
        // "Dark mode falls back to WireKit defaults for these tokens" would be wrong:
        // every default is declared under `:where(:root)` at specificity 0 precisely so
        // that a developer's `:root` override wins "regardless of source order", and the
        // dark defaults are `:where(.dark)`, also specificity 0. So an application that
        // declares a token in `:root` keeps ITS value in dark mode — nothing falls back
        // to anything.
        //
        // A value that goes through `var()` is a second case, and it can turn over on the
        // root. A custom property is substituted on the element that declares it,
        // so `--a: var(--b)` written in `:root` resolves against `:root`'s `--b`: on
        // `<html class="dark">` those are the same element and the pair turns over, while
        // a `.dark` further down the tree inherits the value already resolved in light.
        // The advice stands there too, for that reason rather than the false one.
        $derived = array_filter($asymmetric, fn (string $value): bool => preg_match('/\bvar\(\s*--/', $value) === 1);
        $literal = array_diff_key($asymmetric, $derived);

        if ($literal !== []) {
            foreach (array_keys($literal) as $token) {
                $this->line("    <fg=gray>•</> {$token}");
            }
            $this->line('    <fg=gray>Your `:root` value keeps applying in dark mode — it outranks the `:where(.dark)`</>');
            $this->line('    <fg=gray>WireKit declares its own dark value with, so the token never turns over.</>');
            $this->line('    <fg=gray>Add matching declarations to your `.dark { … }` block.</>');
        }

        if ($derived !== []) {
            foreach (array_keys($derived) as $token) {
                $this->line("    <fg=gray>•</> {$token}");
            }
            $this->line('    <fg=gray>These resolve at `:root`, where they are declared. On `<html class="dark">` that is</>');
            $this->line('    <fg=gray>the same element, so they follow what they point at — but a `.dark` further down</>');
            $this->line('    <fg=gray>the tree inherits the value already resolved in light.</>');
            $this->line('    <fg=gray>Repeat the same `var()` references under `.dark { … }` so a subtree resolves them too.</>');
        }
    }

    /**
     * Parse `--color-wk-*: value;` declarations from a CSS block body.
     * Returns ['--color-wk-name' => 'value', ...]. Comments stripped first.
     * Restricted to the color-wk family — font/radius/shadow/motion tokens
     * are theme-agnostic and don't need .dark counterparts.
     *
     * @return array<string, string>
     */
    private function parseColorTokens(string $block): array
    {
        $block = preg_replace('~/\*.*?\*/~s', '', $block) ?? '';
        $tokens = [];
        foreach (explode(';', $block) as $decl) {
            $decl = trim($decl);
            if ($decl === '' || ! str_starts_with($decl, '--color-wk-')) {
                continue;
            }
            [$name, $value] = array_pad(array_map('trim', explode(':', $decl, 2)), 2, '');
            if ($name !== '') {
                $tokens[$name] = $value;
            }
        }

        return $tokens;
    }
}
