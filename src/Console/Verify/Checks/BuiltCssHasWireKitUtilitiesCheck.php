<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class BuiltCssHasWireKitUtilitiesCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkBuiltCssHasWireKitUtilities();
    }

    /**
     * Final post-build sanity: if a Vite manifest exists, the BUILT app CSS
     * should reference at least one WireKit token. Catches the silent-failure
     * mode where a developer adds the @source line to app.css but forgets to
     * run `npm run build` — the source-side check would still pass while the
     * page renders without WireKit utilities.
     *
     * Skipped silently in environments without `public/build/manifest.json`
     * (dev / pre-build / package-test scenarios).
     */
    private function checkBuiltCssHasWireKitUtilities(): void
    {
        $manifestPath = public_path('build/manifest.json');
        if (! file_exists($manifestPath)) {
            // Dev mode / pre-build — silently skip. Other checks already
            // surface the source-side state.
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            $this->reportWarn('Vite manifest at public/build/manifest.json is not valid JSON — skipping built-CSS check');

            return;
        }

        // Vite manifest shape varies across major versions. Walk the entire
        // structure and collect every value whose `file` ends in `.css`.
        $cssEntries = [];
        $walk = function ($node) use (&$walk, &$cssEntries) {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['file']) && is_string($node['file']) && str_ends_with($node['file'], '.css')) {
                $cssEntries[] = $node['file'];
            }
            // Some manifests nest CSS files in a `css` array on a JS entry.
            if (isset($node['css']) && is_array($node['css'])) {
                foreach ($node['css'] as $css) {
                    if (is_string($css) && str_ends_with($css, '.css')) {
                        $cssEntries[] = $css;
                    }
                }
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($manifest);

        if (empty($cssEntries)) {
            // No CSS in the build output at all — likely a JS-only developer.
            // Not necessarily a problem; skip silently.
            return;
        }

        foreach (array_unique($cssEntries) as $cssEntry) {
            $cssPath = public_path('build/'.$cssEntry);
            if (! file_exists($cssPath)) {
                continue;
            }
            $css = (string) file_get_contents($cssPath);

            // A wk token inside a RULE, never merely somewhere in the bundle.
            //
            // This read `str_contains($css, '--color-wk-')`, and the most ordinary
            // customization there is defeats it: `wirekit:theme` writes a `:root { … }`
            // block of `--color-wk-*` declarations straight into the developer's own
            // app.css, and the theming guide asks them to hand-write the same overrides.
            // Tailwind copies those declarations into the bundle whether or not WireKit
            // was ever scanned — so an install that had themed WireKit and then lost the
            // @source line, or never rebuilt, produced a bundle full of `--color-wk-`
            // and not one WireKit rule, under a PASS line promising utility rules.
            //
            // A declaration block whose selector is not the document root is the
            // difference: a theme override lives on `:root` / `.dark`, while everything
            // this check is looking for — the shipped `.wk-*` rules and the utilities
            // Tailwind generates from the package's Blade templates — lives on a
            // selector of its own.
            if ($this->cssHasWireKitRuleOutsideThemeScope($css)) {
                $this->reportPass('Built app CSS contains WireKit utility rules');
                $this->reportABuildOlderThanTheInstalledTemplates($manifestPath);

                return;
            }
        }

        $this->reportFail('Built app CSS does not reference WireKit utilities');
        $this->line('    Hint: run `npm run build` after adding the @source line for WireKit templates to app.css.');
    }

    /**
     * A build older than the templates it was made from lacks every class they gained since.
     *
     * The bundle still carries WireKit rules then, so the check above passes, and a component
     * whose update brought a new class renders without it and without an error anywhere: after
     * an update that added a larger size to a one-time-code input, its cells fell back to a
     * field's default width until the application was rebuilt. Tailwind only generates the
     * classes it scanned, and the manifest is the one record of when it scanned.
     *
     * When the package was installed is read by `installedAt()`. A running dev server
     * (`public/hot`) builds on demand and is left out, and a difference of a few seconds is
     * ignored, because an install and a build on the same machine can land in the same second.
     */
    private function reportABuildOlderThanTheInstalledTemplates(string $manifestPath): void
    {
        if (file_exists(public_path('hot'))) {
            return;
        }

        clearstatcache(true, $manifestPath);
        $builtAt = @filemtime($manifestPath);
        $installedAt = self::installedAt(dirname(__DIR__, 4));

        if ($builtAt === false || $installedAt === null || $builtAt >= $installedAt - 2) {
            return;
        }

        $this->reportWarn(sprintf(
            'Built app CSS is older than the installed WireKit templates (built %s UTC, installed %s UTC)',
            gmdate('Y-m-d H:i', $builtAt),
            gmdate('Y-m-d H:i', $installedAt)
        ));
        $this->line('    Hint: run `npm run build`. Tailwind only generates the classes it scanned, so a class a newer template uses is missing from this bundle.');
    }

    /**
     * When the package at `$root` was installed, or last changed, as a Unix timestamp.
     *
     * The newest of the modification times of its directory, its Blade templates and its token
     * lists, and the time its directory last changed status. Composer unpacks a release archive and
     * moves the unpacked folder into place. The unpacking keeps the archive's times on every file
     * and directory, so their modification times date the release rather than the install; the
     * move is what changes the directory's status, so its status time dates the install. A source
     * install dates every file a checkout changes.
     *
     * A deploy that copies a vendor directory onto a server gives the copy as the install there,
     * also when the build it copied with it is older. Run the check where the build runs.
     *
     * Public so it can be read on a directory other than the one this package is installed in.
     */
    public static function installedAt(string $root): ?int
    {
        clearstatcache(true, $root);
        $times = [@filemtime($root), @filectime($root)];

        foreach (glob($root.'/resources/tailwind/*.txt') ?: [] as $file) {
            $times[] = @filemtime($file);
        }

        if (is_dir($root.'/resources/views')) {
            $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/resources/views', \FilesystemIterator::SKIP_DOTS));

            foreach ($views as $file) {
                if ($file instanceof \SplFileInfo && str_ends_with($file->getFilename(), '.blade.php')) {
                    $times[] = $file->getMTime();
                }
            }
        }

        $times = array_filter($times, is_int(...));

        return $times === [] ? null : max($times);
    }

    /**
     * Does this bundle carry a WireKit token inside a rule that is NOT a theme override?
     *
     * The blocks are read innermost-first: `[^{}]*` cannot cross a brace, so a nested
     * at-rule contributes its inner selector rather than the `@media` wrapper, which is
     * the level the question is asked at.
     *
     * A selector counts as theme scope only when EVERY comma-separated part of it does.
     * `:root, .dark` is an override; `.dark .wk-field` is a rule. The list is short on
     * purpose — a name not on it is a rule, so the check errs toward asking for a
     * rebuild rather than toward a PASS nobody earned.
     */
    private function cssHasWireKitRuleOutsideThemeScope(string $css): bool
    {
        if (! str_contains($css, '--color-wk-')) {
            return false;
        }

        // Comments go first, because everything between a block's `}` and the next `{`
        // is read as that block's selector — so a banner or a `/* wirekit:theme start */`
        // marker sitting above a `:root` override makes the override look like a rule,
        // which is precisely the shape this check exists to reject.
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);

        preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);

        foreach ($blocks as $block) {
            if (! str_contains($block[2], '--color-wk-')) {
                continue;
            }

            if (! $this->isThemeScopeSelector($block[1])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is every part of this selector one of the document-root scopes a theme block uses?
     */
    private function isThemeScopeSelector(string $selector): bool
    {
        // The whole-selector prefix a nested at-rule leaves behind (`@media (…) {` never
        // reaches here, but `@layer base` and friends can head a block of their own).
        $selector = trim($selector);

        if ($selector === '' || str_starts_with($selector, '@')) {
            return true;
        }

        foreach (explode(',', $selector) as $part) {
            $part = strtolower(trim($part));

            // `:root`, `html`, `.dark`, `:host`, `*`, and the compounds a dark-mode
            // block is written with (`:root.dark`, `html.dark`, `[data-theme='dark']`).
            if (preg_match('/^(?::root|html|body|\*|:host|\.dark|\[[^\]]+\])+$/', $part) !== 1) {
                return false;
            }
        }

        return true;
    }
}
