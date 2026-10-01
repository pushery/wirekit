<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify;

use Illuminate\Support\Facades\File;
use Pushery\WireKit\Support\AppCss;

/**
 * One check `wirekit:verify` runs.
 *
 * A check reports through the run's report and reads what it shares with the other checks from
 * the run's context. The helpers below are the ones two checks use; everything else a check needs
 * lives in its own class.
 *
 * @internal
 */
abstract class VerifyCheck
{
    public function __construct(protected readonly VerifyContext $context) {}

    abstract public function run(): void;

    protected function reportPass(string $message): void
    {
        $this->context->report->pass($message);
    }

    protected function reportFail(string $message): void
    {
        $this->context->report->fail($message);
    }

    protected function reportWarn(string $message): void
    {
        $this->context->report->warn($message);
    }

    protected function reportInfo(string $message): void
    {
        $this->context->report->info($message);
    }

    protected function reportInfoIndented(string $message): void
    {
        $this->context->report->infoIndented($message);
    }

    protected function line(string $message = '', ?string $style = null): void
    {
        $this->context->report->line($message, $style);
    }

    /**
     * @return string|array<array-key, string|bool|null>|bool|null
     */
    protected function option(string $key): string|array|bool|null
    {
        return $this->context->command->option($key);
    }

    /**
     * @param  array<string, mixed>  $arguments  what the called command receives, as Artisan takes it
     */
    protected function call(string $command, array $arguments = []): int
    {
        return $this->context->command->call($command, $arguments);
    }

    /**
     * @return string[]
     */
    protected function findAllBladeFiles(): array
    {
        return $this->context->allBladeFiles();
    }

    /**
     * Detect whether resources/css/app.css imports wirekit.css via a CSS
     * `@import` rule. This is the alternative-but-equivalent setup path
     * to the `@wirekitStyles` Blade directive — see the integration docs
     * "Tip: Both setup paths work in v1.3.0+".
     */
    protected function hasWirekitCssImportInAppCss(): bool
    {
        $appCss = resource_path('css/app.css');
        if (! file_exists($appCss)) {
            return false;
        }
        // Strip CSS comments first (see AppCss::withoutComments()) so a commented
        // reference to an @import of wirekit.css isn't read as a real one.
        $content = AppCss::withoutComments((string) file_get_contents($appCss));

        return (bool) preg_match('/@import\b[^;]*wirekit\.css/', $content);
    }

    /**
     * Extract the bodies of every CSS rule whose selector list names
     * `$selector` — `:root { … }`, `.dark { … }`, or a shared head like
     * `:root, .dark { … }`, which counts for BOTH sides. Returns the inner
     * text without the wrapping braces, or an empty string when no rule
     * names the selector.
     *
     * Anchored at the rule head rather than found with strpos(), because a
     * substring search matches those characters wherever they occur. `.dark`
     * occurs inside `html.dark` and inside `.dark-mode` — and, the case that
     * made this check worse than merely noisy, inside the
     * `@custom-variant dark (…)` line the integration guide tells every
     * developer to write. That at-rule carries
     * no braces of its own, so the old search anchored inside it and then
     * walked forward to the NEXT rule's opening brace: on the documented
     * setup — the `@custom-variant` line, then `:root`, then `.dark` — it
     * handed back the `:root` body as the dark block, the two token sets
     * came out identical by construction, and the asymmetry check could
     * never fire for anyone who had followed the guide. The same search
     * invented asymmetry for anyone whose theme class sits on `<html>`.
     * Both directions, one cause: the selector was never anchored.
     *
     * Every matching block is concatenated rather than only the first, so a
     * `:root` split across two blocks is measured whole. The joining `;`
     * stops the last declaration of one body from fusing with the first of
     * the next, since parseColorTokens() splits on `;`.
     *
     * Non-matching blocks are descended into rather than stepped over,
     * which is what keeps `@layer theme { .dark { … } }` — the shape every
     * theme preset in the theming guide prints — reachable. A MATCHED block
     * is consumed whole and not re-entered: a rule nested inside `:root` is
     * a descendant rule (`:root .dark` styles elements inside the root, not
     * the root), so reading its declarations as root-level overrides would
     * answer a different question than the one the check asks.
     */
    protected function extractCssBlock(string $css, string $selector): string
    {
        $out = '';
        $len = strlen($css);
        $headStart = 0;
        $i = 0;

        while ($i < $len) {
            $char = $css[$i];

            // A `;` or `}` terminates whatever preceded it, so the next rule
            // head begins on the far side. Without this the head would
            // accumulate every declaration and brace-less at-rule since the
            // last block boundary, and a `@custom-variant` line would end up
            // attached to `:root`.
            if ($char === ';' || $char === '}') {
                $headStart = $i + 1;
                $i++;

                continue;
            }

            if ($char !== '{') {
                $i++;

                continue;
            }

            $head = trim(substr($css, $headStart, $i - $headStart));

            // Walk to the brace that closes this block, counting nesting.
            $depth = 1;
            $bodyStart = $i + 1;
            $j = $bodyStart;
            while ($j < $len && $depth > 0) {
                if ($css[$j] === '{') {
                    $depth++;
                } elseif ($css[$j] === '}') {
                    $depth--;
                }
                $j++;
            }
            // A truncated stylesheet has no closing brace to land on; take
            // the remainder rather than dropping its last character.
            $bodyEnd = $depth === 0 ? $j - 1 : $len;

            if ($this->selectorListMatches($head, $selector)) {
                $out .= substr($css, $bodyStart, $bodyEnd - $bodyStart).';';
                $i = $j;
                $headStart = $i;

                continue;
            }

            // Not ours — step INSIDE the block so a rule nested in a
            // container at-rule stays reachable.
            $i = $bodyStart;
            $headStart = $i;
        }

        return $out;
    }

    /**
     * True when a rule head names `$selector` as a whole entry of its
     * selector list. At-rule heads (`@media`, `@layer`, `@supports`,
     * `@custom-variant`) never match — they are containers, and
     * extractCssBlock() reaches what is inside them by descending instead.
     *
     * A root-qualified compound counts as the same element: `html.dark`,
     * `body.dark` and `:root.dark` are all the dark root written the long
     * way, and a custom property declared on any of them inherits to the
     * whole document exactly as one declared on `.dark` does. The
     * DESCENDANT form (`.dark .prose`) deliberately does not count — those
     * declarations apply to `.prose`, not to the root, so reading them as
     * the dark theme's token set would answer a different question than the
     * one the check asks.
     *
     * A comma inside a functional selector (`:root:not(.a, .b)`) splits an
     * entry that should have stayed whole. That yields a non-match, which is
     * the safe direction here: the check goes quiet rather than measuring
     * the wrong block and naming tokens that are not missing.
     */
    protected function selectorListMatches(string $head, string $selector): bool
    {
        if ($head === '' || str_starts_with($head, '@')) {
            return false;
        }

        foreach (explode(',', $head) as $entry) {
            // Collapse the whitespace a multi-line selector list carries, so
            // an entry written on its own indented line still compares as
            // the bare selector.
            $entry = trim((string) preg_replace('/\s+/', ' ', $entry));

            if ($entry === $selector) {
                return true;
            }

            if ($selector === '.dark' && preg_match('/^(?:html|body|:root)\.dark$/', $entry) === 1) {
                return true;
            }

            if ($selector === ':root' && $entry === 'html:root') {
                return true;
            }
        }

        return false;
    }

    /**
     * When the PACKAGE itself last changed: its registry, every component view, and its install.
     *
     * A `composer update pushery/wirekit` moves this and touches nothing under
     * `resources/views/`, so any check that compares the app's own sources against a cache
     * is blind to it by construction.
     *
     * The install is the time the package directory last changed status. Composer unpacks a
     * release archive and keeps the archive's modification times on every file, so on an install
     * from a release those times date the release rather than the update; moving the unpacked
     * folder into place is what dates the update.
     */
    protected function packageChangedAt(): int
    {
        return self::changedAt(dirname(__DIR__, 3));
    }

    /**
     * `packageChangedAt()` for the package at `$root`, as a Unix timestamp, 0 when nothing there
     * can be read. Public so it can be read on a directory other than the installed package.
     */
    public static function changedAt(string $root): int
    {
        clearstatcache(true, $root);
        $newest = max(@filemtime($root.'/src/ComponentRegistry.php') ?: 0, @filectime($root) ?: 0);
        $componentsDir = $root.'/resources/views/components';

        if (is_dir($componentsDir)) {
            foreach (File::allFiles($componentsDir) as $file) {
                $newest = max($newest, $file->getMTime());
            }
        }

        return $newest;
    }
}
