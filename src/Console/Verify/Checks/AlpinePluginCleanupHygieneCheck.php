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
final class AlpinePluginCleanupHygieneCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkAlpinePluginCleanupHygiene();
    }

    /**
     * Does any `.disconnect()` in this source lack a guard of its own?
     *
     * Judged per call site. Guarded means one of:
     *   - `this._observer?.disconnect()` — the optional-chaining form, on the line
     *   - a guard on `this._…` in the lines just above: either `if (! this._x)`
     *     (early return) or `if (this._x) {` (positive, wrapping the call)
     *
     * The look-back is small on purpose. A guard six lines up with a branch in
     * between does not protect this call, and accepting it would reintroduce the
     * file-wide blindness this replaced.
     */
    private function hasUnguardedDisconnect(string $source): bool
    {
        $lines = preg_split('/\R/', $source) ?: [];

        foreach ($lines as $i => $line) {
            if (preg_match('/\.disconnect\s*\(\s*\)/', $line) !== 1) {
                continue;
            }

            // Optional chaining guards itself.
            if (preg_match('/\?\.\s*disconnect\s*\(/', $line) === 1) {
                continue;
            }

            $context = implode("\n", array_slice($lines, max(0, $i - 3), min(3, $i)));

            // `this.anything`, not only `this._anything`: an Alpine factory's own state is
            // ordinarily spelled without an underscore, and `if (this.observer)` is the same
            // guard.
            //
            // It does NOT require the condition to name the same field as the call, and never
            // did. Requiring that would be a stricter check than the one asked for, and it would
            // reject the shapes a real teardown takes — a local alias, a destructured handle.
            //
            // A comparison with `null` or `undefined` is the same guard written out, in either
            // order and with `!=` as well as `!==`: `if (this.resizes !== null) {`. It was read as
            // unguarded, so the warning asked for a guard that stood right above the call.
            // Accepted only when the comparison is the whole condition or its first `&&` term; a
            // comparison with anything else (`this.observer !== previous`) guards nothing.
            $guarded = preg_match('/if\s*\(\s*!\s*this\.\w+/', $context) === 1
                || preg_match('/if\s*\(\s*this\.\w+\s*\)/', $context) === 1
                || preg_match('/if\s*\(\s*this\.\w+\s*!==?\s*(?:null|undefined)\s*(?:\)|&&)/', $context) === 1
                || preg_match('/if\s*\(\s*(?:null|undefined)\s*!==?\s*this\.\w+\s*(?:\)|&&)/', $context) === 1;

            if (! $guarded) {
                return true;
            }
        }

        return false;
    }

    /**
     * JavaScript source with every comment blanked out, line for line.
     *
     * Every pattern in the cleanup-hygiene check is a plain text match, so prose that names one
     * would count as the thing itself, in both directions: a comment naming
     * `MutationObserver.disconnect()` would turn the check red, and a comment that happens to
     * contain `destroy() {` would make the file look like it has a teardown while a real leak
     * goes unreported.
     *
     * Contents are replaced with SPACES rather than removed, and newlines are kept, because the
     * disconnect scan reads "the three lines above this one". Deleting the lines would move every
     * call site away from its own context.
     *
     * Quote state is tracked so a `//` inside a string literal stays put — `'https://…'` would
     * otherwise swallow the rest of its line, and with it any call that shares it. A REGEX literal
     * carrying a quote (`/['"]/`) is the shape this does not model; it would flip the state and
     * blank too much or too little from there to the end of the file. Named rather than hidden:
     * the cure is a real tokenizer, and the failure mode here is one file scanned wrongly, in a
     * check whose own docblock calls itself a heuristic.
     */
    private function stripJsComments(string $source): string
    {
        $out = '';
        $length = strlen($source);
        $quote = null;
        $i = 0;

        while ($i < $length) {
            $char = $source[$i];
            $next = $i + 1 < $length ? $source[$i + 1] : '';

            if ($quote !== null) {
                $out .= $char;

                // A backslash escapes whatever follows, the closing quote included.
                if ($char === '\\' && $next !== '') {
                    $out .= $next;
                    $i += 2;

                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                $i++;

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $out .= $char;
                $i++;

                continue;
            }

            if ($char === '/' && $next === '/') {
                while ($i < $length && $source[$i] !== "\n") {
                    $out .= ' ';
                    $i++;
                }

                continue;
            }

            if ($char === '/' && $next === '*') {
                $out .= '  ';
                $i += 2;

                while ($i < $length && ! ($source[$i] === '*' && ($i + 1 < $length) && $source[$i + 1] === '/')) {
                    $out .= $source[$i] === "\n" ? "\n" : ' ';
                    $i++;
                }

                if ($i < $length) {
                    $out .= '  ';
                    $i += 2;
                }

                continue;
            }

            $out .= $char;
            $i++;
        }

        return $out;
    }

    /**
     * Static analysis for Alpine-plugin defensive-cleanup hygiene.
     *
     * Scans the developer's `resources/js/` tree (the canonical location for
     * custom Alpine plugins extending WireKit) and flags two anti-patterns
     * that historically pollute developer browser-test console-error
     * assertions:
     *
     *  - **Observer instantiation WITHOUT a `destroy()` cleanup hook.** A
     *    `new IntersectionObserver(...)` / `new MutationObserver(...)` /
     *    `new ResizeObserver(...)` stored on `this` survives the Alpine
     *    instance's GC eligibility because the observer holds a reference
     *    to the host element. Memory leak + future-callback timing
     *    surface. Without `destroy()` the observer is never disconnected.
     *
     *  - **`disconnect()` call inside an observer callback WITHOUT a
     *    null-guard on the observer reference.** Browser-queued callbacks
     *    can execute AFTER Alpine teardown set `this._observer = null`
     *    (Livewire morph removing the host element pre-intersection is
     *    the canonical trigger). Without the guard, the callback throws
     *    `TypeError: Cannot read properties of null` — the bug class
     *    that WireKit's own `wirekitStatAnimate` / `wirekitAnimate`
     *    plugins shipped in earlier versions and patched in v2.0.0.
     *
     * Heuristic — not a perfect AST analysis, but covers the canonical
     * shape WireKit's own plugins follow. Edge cases (callback bound via
     * `.bind(this)`, observer reference held under a different name like
     * `this._intersectionObserver`) emit a soft WARN with a
     * docs-cross-link instead of a hard FAIL so developers can opt out
     * with a `// wirekit-doctor: cleanup-ok` comment when their pattern
     * is intentionally different.
     */
    private function checkAlpinePluginCleanupHygiene(): void
    {
        $developerJsDir = resource_path('js');
        if (! is_dir($developerJsDir)) {
            // Developers without a `resources/js/` tree have nothing for
            // this check to walk — but the doctor's contract is "every
            // check emits at least one observable line", so emit an INFO
            // here instead of returning silently. Tests asserting the
            // check ran (e.g. DoctorAliasTest "still runs and produces
            // structurally identical output") then see the marker
            // regardless of worker-sandbox state.
            $this->reportInfo('Alpine plugin cleanup hygiene: skipped — no resources/js/ directory (no developer-side Alpine plugins to scan)');

            return;
        }

        // Defensive iterator wrapping — if the directory exists but the
        // recursive walk throws (race-deleted sub-tree, permission flip
        // during parallel test runs, exotic filesystem error), surface
        // the issue as an INFO and degrade. Without this, an unhandled
        // throw aborts the whole doctor handle() pipeline mid-flight.
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($developerJsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
        } catch (\Throwable $e) {
            $this->reportInfo('Alpine plugin cleanup hygiene: scan skipped — '.$e::class.': '.mb_substr($e->getMessage(), 0, 100));

            return;
        }

        $issues = [];

        // Defensive iteration — wrap the foreach so a mid-walk throw
        // (filesystem race, vanished sub-directory) degrades to an INFO
        // emission instead of aborting the doctor's check pipeline.
        try {
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'js') {
                    continue;
                }
                $path = $file->getPathname();
                $source = @file_get_contents($path);
                if ($source === false || $source === '') {
                    continue;
                }

                // Opt-out comment lets developers acknowledge intentional patterns. Read BEFORE
                // the comments are blanked out below, since the marker is itself one.
                if (str_contains($source, '// wirekit-doctor: cleanup-ok')) {
                    continue;
                }

                // Every pattern below is a plain text match, so prose that NAMES one would count
                // as the thing itself — loudly for `.disconnect()`, and silently for `destroy() {`,
                // where a comment would hide a real leak. See stripJsComments().
                $source = $this->stripJsComments($source);

                $relativePath = str_replace($developerJsDir.'/', '', $path);

                // Anti-pattern 1: observer instantiation without destroy()
                $hasObserver = preg_match(
                    '/new\s+(?:IntersectionObserver|MutationObserver|ResizeObserver)\s*\(/',
                    $source
                ) === 1;
                $hasDestroy = (
                    preg_match('/\bdestroy\s*\(\s*\)\s*\{/', $source) === 1
                    || preg_match('/\bdestroy\s*:\s*(?:function\s*)?\(/', $source) === 1
                );

                // An observer built at MODULE level is not the same finding as one
                // built per component instance, and only the second can accumulate.
                // A page-lifetime observer — one MutationObserver on <html> or
                // <body>, inside a top-level IIFE, driving something for as long as
                // the document lives — has nothing to tear down; giving it a
                // destroy() would be the defect. Reported as a leak, it sends people
                // looking for a bug that is not there.
                //
                // The discriminator is where the construction sits: inside an Alpine
                // factory (`Alpine.data(...)`, an `init()`, a returned object) it is
                // per-instance; at top level it is not.
                $perInstance = (
                    preg_match('/Alpine\s*\.\s*data\s*\(/', $source) === 1
                    || preg_match('/\binit\s*\(\s*\)\s*\{/', $source) === 1
                    || preg_match('/\binit\s*:\s*(?:function\s*)?\(/', $source) === 1
                );

                if ($hasObserver && ! $hasDestroy && $perInstance) {
                    $issues[$relativePath][] = 'observer-without-destroy';
                }

                // Anti-pattern 2: a `disconnect()` that nothing guards.
                //
                // Checked PER CALL SITE rather than per file, and that distinction
                // is the whole correctness of this detector. A file-wide question —
                // "is there a guard anywhere in here" — gets both answers wrong:
                //
                //   • It rejects the POSITIVE guard form, `if (this._observer) { … }`,
                //     when the pattern knows only the negative and optional-chaining
                //     spellings. That is the same check written the other way round,
                //     and it usually does MORE (it nulls the reference afterwards).
                //
                //   • Teaching it the positive form file-wide would have been worse:
                //     a component that guards correctly in destroy() and calls
                //     disconnect() unguarded inside its observer callback would then
                //     read as clean. That is the real anti-pattern, and it lives in
                //     the same files as the correct guard.
                //
                // So each call site is judged by its own immediate context: the
                // optional-chaining form on the line itself, or a guard on `this._…`
                // within the few lines above it. A heuristic, deliberately — but one
                // whose failure mode is a warning rather than silence.
                //
                // And only in a file that builds its observer PER INSTANCE, the same
                // discriminator as the destroy() finding above. The TypeError the guard
                // prevents comes from a callback queued before destroy() nulls the
                // reference; a module-level observer is built once, never nulled, and has
                // no destroy() to race. Flagged anyway, it left two ways out, both worse
                // than the check: decorating a never-null value with `?.`, or the per-file
                // opt-out, which would also hide a per-instance observer added to the same
                // file later.
                if ($hasObserver && $perInstance && $this->hasUnguardedDisconnect($source)) {
                    $issues[$relativePath][] = 'disconnect-without-null-guard';
                }
            }
        } catch (\Throwable $e) {
            $this->reportInfo('Alpine plugin cleanup hygiene: scan partial — '.$e::class.': '.mb_substr($e->getMessage(), 0, 100));

            return;
        }

        if ($issues === []) {
            $this->reportPass('Alpine plugin cleanup hygiene: no developer-side observer-leak or null-guard anti-patterns detected');

            return;
        }

        $this->reportWarn('Alpine plugin cleanup hygiene: '.count($issues).' file(s) with potential anti-patterns');
        foreach ($issues as $relativePath => $detected) {
            $this->line("    <fg=gray>•</> {$relativePath}: ".implode(', ', $detected));
        }
        $this->line('    <fg=gray>See: '.WireKit::DOCS_URL.'/extending/authoring-custom-alpine-plugins</>');
        $this->line('    <fg=gray>Opt out per-file with a `// wirekit-doctor: cleanup-ok` comment if intentional.</>');
    }
}
