<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;

/**
 * A text that shrinks from its own size down to a floor so it fits its line.
 *
 * The stylesheet measures the text through copies of itself, generated from `data-wk-fit`, and
 * reads the result through size containers (the `data-wk-fit-*` rules in `dist/wirekit.css`).
 * This class writes the markup those rules expect, for `<x-wirekit::fit-text>` and every `fit`
 * prop, so it has one source.
 *
 * The markup carries no white space between its tags. A text set in `white-space: pre-line` or
 * `pre-wrap` keeps a line break between two elements as an empty line, and the box would grow by
 * one for each.
 */
final class FitText
{
    /**
     * The steps that measure the text. On one line: the text's own size and seven halvings; on
     * several: the lines the floor needs, the text's own size and six halvings.
     */
    private const STEPS = 8;

    /**
     * The floor as written, without surrounding white space, or '' when it is not a length in `px`,
     * `rem` or `em`.
     *
     * The floor is written into a `style` attribute, where escaping does not stop a `;`, so only a
     * length is taken, and nothing that ends a declaration.
     */
    public static function floor(string $value): string
    {
        $floor = BrowserTrim::trim($value);

        if ($floor === null || preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em)$/', $floor) !== 1) {
            return '';
        }

        return $floor;
    }

    /**
     * What a `fit` prop asks for: false for no fitting, '' for the token's floor, or a floor.
     *
     * `true`, an empty value and the attribute written bare ask for the token's floor; `false`,
     * `null` and the string `false` ask for none. A length is a floor of its own. Anything else is
     * reported through the strictness gate under the component and prop it was given to, and the
     * token's floor applies.
     *
     * The value is `mixed` because a prop arrives as the call site wrote it: a bare attribute is
     * `true`, a written one a string, and a bound one whatever the expression returns.
     */
    public static function prop(mixed $value, string $component, string $prop = 'fit'): string|false
    {
        if ($value === null || $value === false) {
            return false;
        }

        if ($value === true) {
            return '';
        }

        $written = is_scalar($value) ? trim((string) $value) : '';

        if ($written === '' || in_array(strtolower($written), ['true', '1'], true)) {
            return '';
        }

        if (in_array(strtolower($written), ['false', '0'], true)) {
            return false;
        }

        $floor = self::floor($written);

        if ($floor === '') {
            StrictnessGate::reject(
                $component,
                $prop,
                is_scalar($value) ? (string) $value : get_debug_type($value),
                'true, or a CSS length such as 0.75em, 0.5rem or 8px',
                'true',
            );
        }

        return $floor;
    }

    /**
     * The text the copies measure: the markup's text, entities decoded, white space collapsed.
     *
     * The copies are generated content read from an attribute, which renders text and never markup,
     * so the tags go, and with them the comments Livewire's morph leaves in a slot. White space
     * collapses the way the browser collapses it; a no-break space stays, because it holds a figure
     * such as `1 234 €` together.
     */
    public static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[ \t\n\f\r]+/', ' ', $text), " \t\n\f\r");
    }

    /**
     * The words of a text the copies measure, one to a line. A no-break space joins two words into
     * one, as it does in the text.
     */
    public static function words(string $text): string
    {
        return implode("\n", array_values(array_filter(explode(' ', $text), static fn (string $word): bool => $word !== '')));
    }

    /**
     * The fitted box around a text.
     *
     * @param  Htmlable|string  $content  the text as markup, a slot for instance; a string is escaped
     * @param  ComponentAttributeBag  $attributes  the box's own attributes, a floor in its `style`
     *                                             as `--wk-fit-floor`
     * @param  bool  $wrap  whether the text may break over several lines; a text that may not stays
     *                      on one line and runs past the box below the floor
     * @param  string|null  $text  what the copies measure, when it is not the content's own text: a
     *                             text whose markup holds words a reader does not see
     * @param  ComponentAttributeBag|null  $textAttributes  attributes of the visible text, such as a
     *                                                      binding that writes it while the copies keep
     *                                                      the text the server rendered
     */
    public static function box(Htmlable|string $content, ComponentAttributeBag $attributes, bool $wrap = true, ?string $text = null, ?ComponentAttributeBag $textAttributes = null): HtmlString
    {
        $html = $content instanceof Htmlable ? $content->toHtml() : e($content);
        $copy = $text ?? self::plain($html);
        $escaped = e($copy);
        $mode = $wrap ? 'lines' : 'line';

        $box = $attributes->merge([
            'data-wk-fit-box' => $mode,
            'data-wk-fit' => $copy,
        ]);

        // Over several lines each step counts the words as well, one to a line, so a size that
        // breaks a word the floor keeps whole is refused. The line feeds are written as character
        // references, so the markup stays on one line.
        $words = $wrap ? ' data-wk-fit-words="'.str_replace("\n", '&#10;', e(self::words($copy))).'"' : '';
        $open = '';

        for ($step = 0; $step < self::STEPS; $step++) {
            $open .= '<span data-wk-fit-step="'.$step.'" data-wk-fit="'.$escaped.'"'.$words.'><span>';
        }

        return new HtmlString(
            '<span '.trim((string) $box).'>'
            .'<span data-wk-fit-frame>'
            .$open
            .'<span data-wk-fit-text="'.$mode.'"'.($textAttributes !== null && $textAttributes->getAttributes() !== [] ? ' '.trim((string) $textAttributes) : '').'>'.$html.'</span>'
            .str_repeat('</span></span>', self::STEPS)
            .'</span>'
            .'</span>'
        );
    }
}
