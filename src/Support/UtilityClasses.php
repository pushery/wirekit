<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\Arr;
use Illuminate\View\ComponentAttributeBag;

/**
 * Read and filter the utilities of a class string by what they set.
 *
 * Two utilities that set the same property under the same variants are not decided by their order
 * in the `class` attribute. The browser applies whichever rule comes later in the compiled
 * stylesheet, and Tailwind sorts those rules by value: `p-0` sorts before
 * `p-[var(--padding-wk-x-md)]`, so appending `p-0` to a padded block leaves the padding in place.
 * A component that wants one of two values therefore has to emit only that one. When the other
 * comes from a block a developer can personalize, the component cannot know what the block holds,
 * and these methods take the competing utilities out of it.
 *
 * Only utilities without a variant are read. `hover:p-2` or `sm:h-6` applies in a state or at a
 * width of its own, so it does not compete with the base utility, and it stays. An inline style is
 * the exception: it wins over a utility under a variant as well, so `yieldStyleToCaller()` reads
 * those too.
 */
final class UtilityClasses
{
    /**
     * Every padding utility: `p-*`, and the axis and side forms (`px-*`, `pt-*`, `ps-*`, and the
     * block start and end, `pbs-*` and `pbe-*`).
     */
    public const PADDING = '/^p(?:[xytrblse]|bs|be)?-/';

    /**
     * The opacity a color utility may carry after a slash: `/50`, `/12.5`, `/[0.3]`, `/(--opacity)`.
     */
    private const OPACITY = '(?:\/(?:\d+(?:\.\d+)?|\[[^\]]+\]|\([^)]+\)))?';

    /**
     * The color values Tailwind reads after `bg-` and `text-` whatever the utility: a palette color
     * or a keyword, a typed `color:` value, a hex value or a color function, and an untyped `var()`
     * or custom property, which Tailwind reads as a color too. A gradient angle (`bg-linear-45`) is
     * an image, not a palette color.
     */
    private const COLOR = '(?!(?:linear|radial|conic)-)(?:transparent|current|inherit|white|black|[a-z]+-\d{2,3})'
        .'|\[color:[^\]]+\]'
        .'|\[(?:#|(?:rgba?|hsla?|hwb|color|(?:ok)?(?:lab|lch)|light-dark|color-mix|--alpha|var)\()[^\]]*\]'
        .'|\((?:color:)?--[\w-]+\)';

    /** The length units Tailwind reads in an arbitrary value. */
    private const LENGTH_UNITS = 'cm|mm|Q|in|pc|pt|px|em|ex|ch|rem|lh|rlh|vw|vh|vmin|vmax|vb|vi|svw|svh|lvw|lvh|dvw|dvh|cqw|cqh|cqi|cqb|cqmin|cqmax';

    /**
     * A background color: a palette color or a keyword, an arbitrary color (`bg-[#fff]`,
     * `bg-[oklch(…)]`, `bg-[var(--x)]`, a named color), the custom-property form (`bg-(--x)`), each
     * with or without an opacity (`bg-white/80`). An image, a size or a position does not match:
     * `bg-cover`, `bg-center`, `bg-linear-to-r`, `bg-[url(…)]`, `bg-[length:…]`, `bg-[50%]`.
     *
     * A color of the application's own theme without a shade (`bg-brand`) is not read: its name does
     * not say what it sets.
     */
    public const BACKGROUND_COLOR = '/^bg-(?:'.self::COLOR.'|\[(?!(?:left|right|top|bottom|center|cover|contain|auto)\])[a-z]+\])'.self::OPACITY.'$/';

    /**
     * A text color, in the same forms as a background color: `text-red-500/50`, `text-[#333]`,
     * `text-[var(--x)]`, `text-(--x)`. A size (`text-sm`, `text-[15px]`, `text-[length:…]`, a size
     * keyword such as `text-[larger]`) or an alignment does not match.
     */
    public const TEXT_COLOR = '/^text-(?:'.self::COLOR.'|\[(?!(?:small|medium|large|smaller|larger)\])[a-z]+\])'.self::OPACITY.'$/';

    /**
     * A font size: a size keyword, with or without a line height (`text-sm`, `text-lg/7`), a typed
     * arbitrary value (`text-[length:…]`, `text-(length:…)`, a percentage or a size keyword), or an
     * arbitrary length, percentage, math function or size keyword, again with or without a line
     * height (`text-[15px]`, `text-[1.25rem]/7`, `text-[clamp(…)]`, `text-[larger]`). A color, an
     * alignment or an untyped `text-[var(…)]`, which Tailwind reads as a color, does not match.
     */
    public const FONT_SIZE = '/^text-(?:(?:xs|sm|base|lg|xl|[2-9]xl)(?:\/\S+)?$'
        .'|[\[(](?:length|percentage|absolute-size|relative-size):'
        .'|\[(?:\d*\.?\d+(?:'.self::LENGTH_UNITS.'|%)|(?:calc|min|max|clamp)\([^\]]*|xx-small|x-small|small|medium|large|x-large|xx-large|xxx-large|smaller|larger)\](?:\/\S+)?$)/';

    /**
     * A font weight: a weight keyword, a typed arbitrary value (`font-[number:…]`, `font-(number:…)`),
     * a number (`font-[550]`), or an untyped `var()` or custom property (`font-[var(--x)]`,
     * `font-(--x)`), which Tailwind reads as a weight. A family (`font-sans`, `font-[Inter]`,
     * `font-[family-name:…]`) does not match.
     */
    public const FONT_WEIGHT = '/^font-(?:(?:thin|extralight|light|normal|medium|semibold|bold|extrabold|black)$|[\[(]number:|\[\d+\]$|\[var\([^\]]*\]$|\(--[\w-]+\)$)/';

    /**
     * A `display` a developer can write on a component: `hidden` and every display keyword. Tailwind
     * orders them by name in the stylesheet, so `hidden` beside a component's own `inline-flex`
     * comes first and loses, and the element stays on screen.
     */
    public const SETS_DISPLAY = '/^(?:hidden|block|inline-block|inline|flex|inline-flex|grid|inline-grid|contents|flow-root|list-item|table|inline-table|table-(?:caption|cell|column|column-group|footer-group|header-group|row-group|row))$/';

    /**
     * A `display` a component writes for its own element: every keyword of SETS_DISPLAY but `hidden`.
     * An element the component hides until its script shows it stays hidden whatever a developer
     * writes beside it.
     */
    public const DISPLAY = '/^(?:block|inline-block|inline|flex|inline-flex|grid|inline-grid|contents|flow-root|list-item|table|inline-table|table-(?:caption|cell|column|column-group|footer-group|header-group|row-group|row))$/';

    /**
     * A `position`: every position keyword. Tailwind orders them by name as well, so `absolute` or
     * `fixed` beside a component's own `relative` comes first and loses, and the element stays in the
     * flow. A child the component positions against its root keeps its anchor, since `absolute`,
     * `fixed` and `sticky` position the root too.
     */
    public const POSITION = '/^(?:static|fixed|absolute|relative|sticky)$/';

    /**
     * A spacing value: a number of spacing steps, `px`, `auto`, an arbitrary value or a custom
     * property. A word after the prefix is not one, so a class of the application's own such as
     * `my-card` is no margin.
     */
    private const SPACING = '(?:\d+(?:\.\d+)?|px|auto|\[[^\]]+\]|\([^)]+\))$';

    /**
     * A size value: a spacing value, a fraction, a keyword of the viewport, the content or the line,
     * `none`, `prose`, or a container size from `3xs` to `7xl`.
     */
    private const SIZE = '(?:\d+(?:\.\d+)?|px|auto|full|screen|[sld]?v[wh]|min|max|fit|lh|none|prose|\d+\/\d+|[23]?xs|sm|md|lg|[2-7]?xl|\[[^\]]+\]|\([^)]+\))$';

    /**
     * The value groups in which a caller's class replaces a component's own: each maps the utilities
     * of one property family to the sides of it they set.
     *
     * A component writes its values from tokens, `p-[var(--padding-wk-x-md)]`, and Tailwind writes
     * such a value after a named one of the same property, so a caller's `p-0` lost beside it. A
     * caller's utility therefore takes out each utility of the component in the same group that sets
     * nothing it does not set itself: `p-0` takes the component's `p-…`, `px-…` and `pt-…`, and
     * `px-0` its `px-…` and `pl-…`, but neither its `py-…` nor its `p-…`. The component's `p-…`
     * also sets the block axis, which the caller left alone, and it loses where the two overlap
     * anyway: Tailwind writes a utility of more properties before one of fewer, `p-…` before
     * `px-0`, `size-…` before `h-0`.
     *
     * Overflow, the layer and the border width are not in the list. Each says what the component
     * needs to work, not how it looks.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const GROUPS = [
        'padding' => [
            '/^p-'.self::SPACING.'/' => ['t', 'r', 'b', 'l', 's', 'e', 'bs', 'be'],
            '/^px-'.self::SPACING.'/' => ['r', 'l', 's', 'e'],
            '/^py-'.self::SPACING.'/' => ['t', 'b', 'bs', 'be'],
            '/^pt-'.self::SPACING.'/' => ['t'],
            '/^pr-'.self::SPACING.'/' => ['r'],
            '/^pb-'.self::SPACING.'/' => ['b'],
            '/^pl-'.self::SPACING.'/' => ['l'],
            '/^ps-'.self::SPACING.'/' => ['s'],
            '/^pe-'.self::SPACING.'/' => ['e'],
            '/^pbs-'.self::SPACING.'/' => ['bs'],
            '/^pbe-'.self::SPACING.'/' => ['be'],
        ],
        'margin' => [
            '/^m-'.self::SPACING.'/' => ['t', 'r', 'b', 'l', 's', 'e', 'bs', 'be'],
            '/^mx-'.self::SPACING.'/' => ['r', 'l', 's', 'e'],
            '/^my-'.self::SPACING.'/' => ['t', 'b', 'bs', 'be'],
            '/^mt-'.self::SPACING.'/' => ['t'],
            '/^mr-'.self::SPACING.'/' => ['r'],
            '/^mb-'.self::SPACING.'/' => ['b'],
            '/^ml-'.self::SPACING.'/' => ['l'],
            '/^ms-'.self::SPACING.'/' => ['s'],
            '/^me-'.self::SPACING.'/' => ['e'],
            '/^mbs-'.self::SPACING.'/' => ['bs'],
            '/^mbe-'.self::SPACING.'/' => ['be'],
        ],
        'gap' => [
            '/^gap-'.self::SPACING.'/' => ['x', 'y'],
            '/^gap-x-'.self::SPACING.'/' => ['x'],
            '/^gap-y-'.self::SPACING.'/' => ['y'],
        ],
        'size' => [
            '/^w-'.self::SIZE.'/' => ['w'],
            '/^h-'.self::SIZE.'/' => ['h'],
            '/^size-'.self::SIZE.'/' => ['w', 'h'],
        ],
        'min-width' => ['/^min-w-'.self::SIZE.'/' => ['all']],
        'max-width' => ['/^max-w-'.self::SIZE.'/' => ['all']],
        'min-height' => ['/^min-h-'.self::SIZE.'/' => ['all']],
        'max-height' => ['/^max-h-'.self::SIZE.'/' => ['all']],
        'font-size' => [self::FONT_SIZE => ['all']],
        'font-weight' => [self::FONT_WEIGHT => ['all']],
        'text-align' => ['/^text-(?:left|center|right|justify|start|end)$/' => ['all']],
        'justify-content' => ['/^justify-(?:start|end|end-safe|center|center-safe|between|around|evenly|stretch|baseline|normal)$/' => ['all']],
        'justify-items' => ['/^justify-items-(?:start|end|end-safe|center|center-safe|stretch|normal)$/' => ['all']],
        'justify-self' => ['/^justify-self-(?:auto|start|end|end-safe|center|center-safe|stretch)$/' => ['all']],
        'align-items' => ['/^items-(?:start|end|end-safe|center|center-safe|baseline|baseline-last|stretch)$/' => ['all']],
        'align-self' => ['/^self-(?:auto|start|end|end-safe|center|center-safe|stretch|baseline|baseline-last)$/' => ['all']],
        'flex-direction' => ['/^flex-(?:row|col)(?:-reverse)?$/' => ['all']],
        'white-space' => ['/^whitespace-(?:normal|nowrap|pre|pre-line|pre-wrap|break-spaces)$/' => ['all']],
    ];

    /** How many class strings `replacedBy()` keeps an answer for before it starts again. */
    private const REMEMBERED = 512;

    /**
     * The answer of `replacedBy()` for each class string read: the same `class` on a thousand table
     * cells is read once. An answer depends on the string alone, so it holds across requests.
     *
     * @var array<string, list<string>>
     */
    private static array $replaced = [];

    /**
     * The class string without the variant-free utilities whose name matches `$pattern`.
     *
     * The pattern is matched against the utility alone: an important marker (`!p-0`, `p-0!`) and the
     * minus sign of a negative value (`-mt-2`) are removed before it is read.
     */
    public static function without(string $classes, string $pattern): string
    {
        return implode(' ', array_filter(
            self::tokens($classes),
            static fn (string $token): bool => ! self::matches($token, $pattern),
        ));
    }

    /**
     * A component's classes for the element that takes the caller's attributes, without those the
     * caller's `class` replaces, in the shape they came in: a string, or a list for
     * `$attributes->class()`, conditional entries included.
     *
     * A display (`SETS_DISPLAY`) takes out the component's own (`DISPLAY`): `class="hidden
     * md:inline-flex"` on a button is how a developer hides it on a phone, and the button's own
     * `inline-flex` would keep it on screen. A position (`POSITION`) takes out the component's own:
     * `class="absolute top-2 right-2"` on a tooltip would otherwise stay in the flow beside its own
     * `relative`. A spacing, a size, a type or an alignment takes out the component's utilities it
     * covers in its group (`GROUPS`): `class="p-0"` on a card, `gap-2` on a stack and `text-xs` on a
     * badge would otherwise lose to the component's token value. A utility under a variant
     * (`md:hidden`, `lg:p-0`) is not read, since it applies at a width or in a state of its own, and
     * a component's own utility under a variant stays for the same reason.
     *
     * @param  string|array<array-key, string|bool|null>  $classes
     * @return string|array<array-key, string|bool|null>
     */
    public static function yieldToCaller(ComponentAttributeBag $attributes, string|array $classes): string|array
    {
        $replaced = self::replacedBy(self::callerClasses($attributes));

        if ($replaced === []) {
            return $classes;
        }

        $yield = static fn (string $list): string => implode(' ', array_filter(
            self::tokens($list),
            static fn (string $token): bool => ! array_any($replaced, static fn (string $pattern): bool => self::matches($token, $pattern)),
        ));

        if (is_string($classes)) {
            return $yield($classes);
        }

        $yielded = [];

        foreach ($classes as $key => $value) {
            if (is_string($key)) {
                // Two conditional entries can come out as one string; the classes stay while
                // either condition holds.
                $kept = $yield($key);
                $yielded[$kept] = array_key_exists($kept, $yielded) ? ($yielded[$kept] || $value) : $value;
            } else {
                $yielded[$key] = is_string($value) ? $yield($value) : $value;
            }
        }

        return $yielded;
    }

    /**
     * An inline style for the element that takes the caller's attributes, without the declarations
     * the caller's `class` reaches for, and the utilities that stand in for them.
     *
     * A declaration in `style` wins over every class, so a caller's `hidden`, `md:hidden` or
     * `absolute` would never reach an element whose own display or position is written inline. Where
     * the caller's class sets a display (a position), with a variant or without, the inline one is
     * left out and comes back as its utility, `display: flex` as `flex`, for the component to hand
     * through `yieldToCaller()` with its other classes: beside a display of the caller's own it is
     * taken out there, and beside one under a variant only it is the base that variant overrides.
     * A value without a utility of its own stays inline, and so does `display: none`, an element the
     * component hides.
     *
     * @return array{0: string, 1: string} the style, and the utilities for what it left out
     */
    public static function yieldStyleToCaller(ComponentAttributeBag $attributes, string $style): array
    {
        $callerClasses = self::callerClasses($attributes);

        $reached = array_filter([
            'display' => self::reaches($callerClasses, self::SETS_DISPLAY) ? self::DISPLAY : null,
            'position' => self::reaches($callerClasses, self::POSITION) ? self::POSITION : null,
        ]);

        if ($reached === []) {
            return [$style, ''];
        }

        $kept = [];
        $utilities = [];

        foreach (self::declarations($style) as $declaration) {
            [$property, $value] = array_pad(array_map(trim(...), explode(':', $declaration, 2)), 2, '');
            $pattern = $reached[strtolower($property)] ?? null;

            if ($pattern !== null && preg_match($pattern, $value) === 1) {
                $utilities[] = $value;

                continue;
            }

            $kept[] = $declaration;
        }

        return [$kept === [] ? '' : implode('; ', $kept).';', implode(' ', $utilities)];
    }

    /** Whether the class string carries a variant-free utility whose name matches `$pattern`. */
    public static function has(string $classes, string $pattern): bool
    {
        foreach (self::tokens($classes) as $token) {
            if (self::matches($token, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The patterns of the component's utilities that a caller's class string replaces: its own
     * display beside a display of the caller's, its own position beside a position, and in each
     * value group the utilities whose sides the caller's cover.
     *
     * @return list<string>
     */
    private static function replacedBy(string $callerClasses): array
    {
        if (trim($callerClasses) === '') {
            return [];
        }

        if (isset(self::$replaced[$callerClasses])) {
            return self::$replaced[$callerClasses];
        }

        $utilities = [];

        foreach (self::tokens($callerClasses) as $token) {
            if (! self::hasVariant($token)) {
                $utilities[] = ltrim(trim($token, '!'), '-');
            }
        }

        $sets = static fn (string $pattern): bool => array_any($utilities, static fn (string $utility): bool => preg_match($pattern, $utility) === 1);
        $replaced = [];

        if ($sets(self::SETS_DISPLAY)) {
            $replaced[] = self::DISPLAY;
        }

        if ($sets(self::POSITION)) {
            $replaced[] = self::POSITION;
        }

        foreach (self::GROUPS as $members) {
            $covered = [];

            foreach ($members as $pattern => $sides) {
                if ($sets($pattern)) {
                    array_push($covered, ...$sides);
                }
            }

            if ($covered === []) {
                continue;
            }

            foreach ($members as $pattern => $sides) {
                if (array_diff($sides, $covered) === []) {
                    $replaced[] = $pattern;
                }
            }
        }

        if (count(self::$replaced) >= self::REMEMBERED) {
            self::$replaced = [];
        }

        return self::$replaced[$callerClasses] = $replaced;
    }

    /** The caller's `class`, a list bound as an array included, as one class string. */
    private static function callerClasses(ComponentAttributeBag $attributes): string
    {
        $caller = $attributes->get('class');

        return is_array($caller) ? Arr::toCssClasses($caller) : (is_string($caller) ? $caller : '');
    }

    /** Whether the class string carries a utility whose name matches `$pattern`, under a variant or not. */
    private static function reaches(string $classes, string $pattern): bool
    {
        foreach (self::tokens($classes) as $token) {
            if (preg_match($pattern, ltrim(trim(self::withoutVariants($token), '!'), '-')) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The utility of a token without its variants: what follows its last colon outside brackets and
     * parentheses, so `md:hidden` reads as `hidden` and `data-[open]:flex` as `flex`.
     */
    private static function withoutVariants(string $token): string
    {
        $depth = 0;
        $start = 0;

        foreach (str_split($token) as $index => $char) {
            if ($char === '[' || $char === '(') {
                $depth++;
            } elseif ($char === ']' || $char === ')') {
                $depth--;
            } elseif ($char === ':' && $depth === 0) {
                $start = $index + 1;
            }
        }

        return substr($token, $start);
    }

    /**
     * The declarations of an inline style, split at the semicolons outside parentheses and quotes,
     * each trimmed, the empty ones left out.
     *
     * @return list<string>
     */
    private static function declarations(string $style): array
    {
        $declarations = [];
        $current = '';
        $depth = 0;
        $quote = null;

        foreach (str_split($style) as $char) {
            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ';' && $depth === 0) {
                $declarations[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $declarations[] = trim($current);

        return array_values(array_filter($declarations, static fn (string $declaration): bool => $declaration !== ''));
    }

    /** @return list<string> */
    private static function tokens(string $classes): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', trim($classes)) ?: [],
            static fn (string $token): bool => $token !== '',
        ));
    }

    private static function matches(string $token, string $pattern): bool
    {
        if (self::hasVariant($token)) {
            return false;
        }

        $utility = ltrim(trim($token, '!'), '-');

        return preg_match($pattern, $utility) === 1;
    }

    /**
     * Whether the token carries a variant: a colon outside brackets and parentheses. The colon in
     * `text-[color:var(--x)]` or `bg-[url(a:b)]` is part of the value, not a variant.
     */
    private static function hasVariant(string $token): bool
    {
        $depth = 0;

        foreach (str_split($token) as $char) {
            if ($char === '[' || $char === '(') {
                $depth++;
            } elseif ($char === ']' || $char === ')') {
                $depth--;
            } elseif ($char === ':' && $depth === 0) {
                return true;
            }
        }

        return false;
    }
}
