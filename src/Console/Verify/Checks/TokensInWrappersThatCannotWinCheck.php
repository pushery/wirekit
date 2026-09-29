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
final class TokensInWrappersThatCannotWinCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkTokensInWrappersThatCannotWin();
    }

    /**
     * A `--*-wk-*` override written in a wrapper that cannot win.
     *
     * The symmetry check above asks whether an override has a dark counterpart. This asks the
     * question before it: whether the override reaches anything at all. Two wrappers look right,
     * do nothing, and — this is what makes the class the quietest one there is — raise no error
     * either way. `docs/theming.md` names both:
     *
     *  - **`@theme { … }`** is Tailwind's utility-generation block, not an override route. A theme
     *    variable no utility references is dropped, so the declaration compiles to nothing; one
     *    that survives is emitted inside `@layer theme` and then loses to the unlayered defaults.
     *  - **any `@layer`**, for that second reason alone: `dist/wirekit.css` ships unlayered, and
     *    unlayered CSS beats layered CSS whatever the specificity.
     *
     * The layer half is deliberately narrow, and a real adopting application is why. The cascade only
     * compares declarations that apply to the SAME element, so a custom property set on a
     * DESCENDANT shadows the inherited one whether it sits in a layer or not — an adopting
     * application scopes `--size-wk-fab` to one component that way, correctly. Only a rule that
     * targets the root competes with WireKit's own declaration, so only those are read here.
     *
     * And it reads `--<family>-wk-<name>`, not `--wk-<name>`. The second shape is a lever a page
     * sets for a component to read off its own element (`--wk-fab-lift`), which is a different
     * contract with different rules; flagging it here would be a guess dressed as a check.
     */
    private function checkTokensInWrappersThatCannotWin(): void
    {
        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss) || ($content = file_get_contents($appCss)) === false) {
            $this->reportInfo('Token wrappers: skipped — no readable resources/css/app.css');

            return;
        }

        $content = AppCss::withoutComments($content);
        $offenders = [];

        // Declared straight inside `@theme`. Nested blocks come out first so a rule written in
        // there cannot be read as a declaration of the block itself.
        $theme = (string) preg_replace('/\{[^{}]*\}/', '', $this->extractAtRuleBlock($content, '@theme'));

        foreach (array_keys($this->parseWireKitTokens($theme)) as $token) {
            $offenders[] = [$token, '@theme'];
        }

        // Inside a layer, and only where the rule targets the root — see the note above.
        foreach ([':root', '.dark'] as $selector) {
            foreach (array_keys($this->parseWireKitTokens($this->extractCssBlock($this->extractAtRuleBlock($content, '@layer'), $selector))) as $token) {
                $offenders[] = [$token, '@layer … '.$selector];
            }
        }

        if ($offenders === []) {
            $this->reportPass('Token wrappers: no `--*-wk-*` override sits in an `@theme` or `@layer` block');

            return;
        }

        $this->reportWarn('Token wrappers: '.count($offenders).' `--*-wk-*` override(s) in a wrapper that cannot win');

        foreach ($offenders as [$token, $where]) {
            $this->line("    <fg=gray>•</> {$token} <fg=gray>in {$where}</>");
        }

        $this->line('    <fg=gray>These compile to nothing, or to a layered declaration that loses to WireKit\'s</>');
        $this->line('    <fg=gray>unlayered default — in BOTH modes, with no error either way.</>');
        $this->line('    <fg=gray>Move them to a plain `:root { … }` block, with a plain `.dark { … }` beside it.</>');
    }

    /**
     * The bodies of every at-rule block whose head starts with `$atRule`, concatenated.
     *
     * {@see extractCssBlock()} descends INTO an at-rule rather than matching it, which is what
     * makes it able to find a `:root` nested in a `@media`. This is the other half of the same
     * walk: it takes the at-rule's own body, so a caller can ask what a `@theme` or a `@layer`
     * contains. Feeding the result back into `extractCssBlock()` is how the layer half reaches
     * the rules inside.
     */
    private function extractAtRuleBlock(string $css, string $atRule): string
    {
        $out = '';
        $len = strlen($css);
        $headStart = 0;
        $i = 0;

        while ($i < $len) {
            $char = $css[$i];

            if ($char === ';' || $char === '}') {
                $headStart = $i + 1;
                $i++;

                continue;
            }

            if ($char !== '{') {
                $i++;

                continue;
            }

            $head = trim((string) preg_replace('/\s+/', ' ', substr($css, $headStart, $i - $headStart)));
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

            $bodyEnd = $depth === 0 ? $j - 1 : $len;

            // `@theme` and `@theme inline` are the same block; `@layer base` and `@layer` are both
            // layers. Matching the word and requiring a boundary keeps `@themed` out.
            if ($head === $atRule || str_starts_with($head, $atRule.' ')) {
                $out .= substr($css, $bodyStart, $bodyEnd - $bodyStart).';';
                $i = $j;
                $headStart = $i;

                continue;
            }

            $i = $bodyStart;
            $headStart = $i;
        }

        return $out;
    }

    /**
     * Parse `--<family>-wk-<name>: value;` declarations from a CSS block body.
     *
     * Wider than {@see parseColorTokens()} on purpose: this question is about the wrapper, and a
     * radius or a shadow written there is as inert as a color.
     *
     * @return array<string, string>
     */
    private function parseWireKitTokens(string $block): array
    {
        $tokens = [];

        foreach (explode(';', $block) as $decl) {
            [$name, $value] = array_pad(array_map('trim', explode(':', trim($decl), 2)), 2, '');

            if (preg_match('/^--[a-z0-9]+(?:-[a-z0-9]+)*-wk-/', $name) === 1) {
                $tokens[$name] = $value;
            }
        }

        return $tokens;
    }
}
