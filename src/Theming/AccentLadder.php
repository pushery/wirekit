<?php

declare(strict_types=1);

namespace Pushery\WireKit\Theming;

use InvalidArgumentException;
use LogicException;

/**
 * The four accent tokens of one mode, derived from one brand color, with their contrast held.
 *
 * A theme preset writes `--color-wk-accent`, `-hover`, `-content` and `-fg` by hand for its own
 * hue. A color somebody picks at runtime, the brand color of a shop for one, has no preset, and
 * a fill, a hover, a text color and a label color that each read on what they stand on are four
 * measurements, not one value. This class takes the color and returns the four, so that:
 *
 * - `--color-wk-accent-fg` reads at 4.5:1 or more on `--color-wk-accent` and on
 *   `--color-wk-accent-hover`, the label of a primary button at rest and under the pointer;
 * - `--color-wk-accent-content` reads at 4.5:1 or more on every surface it is checked against:
 *   by default the page, card and subtle surfaces of WireKit's own theme in that mode, and the
 *   tinted surfaces `callout` and `alert` draw on a card, the accent's own tint among them.
 *
 * The fill is the color as given, in its own lightness; on a dark page a fill darker than 68%
 * lightness is lifted to it, as the presets lift theirs. The hover lies 6% of lightness away from
 * the page, or toward it where the label would not read on it otherwise. The text color starts
 * at the fill and moves away from the page in steps of 1% until it reads on every surface. Every
 * check runs on the value as it is written out, through `WcagContrast`, so the promise holds for
 * the string a developer puts into a stylesheet: an `oklch()` value, or a `#rrggbb` color where
 * the tokens are asked for as hex.
 */
final class AccentLadder
{
    /** The ratio every guaranteed pairing clears: WCAG 1.4.3, AA for text of normal size. */
    private const TEXT_CONTRAST = 4.5;

    /** The lightness a fill is lifted to on a dark page, where the presets put theirs. */
    private const DARK_FILL_LIGHTNESS = 0.68;

    /** How far the hover lies from the fill, in OKLCH lightness. */
    private const HOVER_STEP = 0.06;

    /** The step the text color moves by until it reads, in OKLCH lightness. */
    private const CONTENT_STEP = 0.01;

    /**
     * The labels WireKit's own theme writes on its accent, then the two that always reach a ratio.
     *
     * The first two are `--color-wk-accent-fg` of the shipped light and dark theme. One of white
     * and black reaches 4.58:1 on any opaque color, so the list cannot run out.
     */
    private const LABELS = [
        ['oklch(98.5% 0 0)', 'oklch(20.5% 0 0)'],
        ['oklch(100% 0 0)', 'oklch(0% 0 0)'],
    ];

    /**
     * The surfaces of WireKit's own theme, per mode: `--color-wk-bg`, `-bg-elevated` and
     * `-bg-subtle`, and the three state colors whose tints `callout` and `alert` draw.
     *
     * Held to `dist/wirekit.css` by a test, which fails when the stylesheet moves a value.
     *
     * @var array<string, array<string, string>>
     */
    private const THEME = [
        'light' => [
            '--color-wk-bg' => '#fff',
            '--color-wk-bg-elevated' => '#fff',
            '--color-wk-bg-subtle' => 'oklch(98.5% 0 0)',
            '--color-wk-danger' => 'oklch(57.7% 0.245 27.325)',
            '--color-wk-success' => 'oklch(62.7% 0.194 149.214)',
            '--color-wk-warning' => 'oklch(76.9% 0.188 70.08)',
        ],
        'dark' => [
            '--color-wk-bg' => 'oklch(14.5% 0 0)',
            '--color-wk-bg-elevated' => 'oklch(20.5% 0 0)',
            '--color-wk-bg-subtle' => 'oklch(20.5% 0 0)',
            '--color-wk-danger' => 'oklch(57.7% 0.245 27.325)',
            '--color-wk-success' => 'oklch(72.3% 0.219 149.579)',
            '--color-wk-warning' => 'oklch(82.8% 0.189 84.429)',
        ],
    ];

    /**
     * How much of a state color `callout` (15%) and `alert` (10%) mix into the card surface.
     * Held to the two views by a test.
     */
    private const TINTS = [10, 15];

    /**
     * The four accent tokens for one mode, keyed by their custom property.
     *
     * `$surfaces` replaces the surfaces of WireKit's theme with your own, as CSS colors: the
     * page, card and panel colors of a preset or of your design, and the tinted surfaces your
     * text sits on. The tints the accent itself makes on each of them, at the strengths `callout`
     * and `alert` use, are added in any case, because they depend on the accent that is being
     * derived.
     *
     * `$hex` writes every token as a `#rrggbb` color instead of an `oklch()` value, for a store or a
     * validator that takes hex only. Every ratio then holds for the hex values as written, and a
     * fill given as a hex color comes back as that hex color unless it is lifted.
     *
     * `$lift` set to false keeps a dark fill on a dark page as it was given: for a color chosen for
     * the dark mode on purpose, where the lift would change it. No ratio depends on the lift, which
     * only keeps a fill derived for both modes from sinking into a dark page.
     *
     * @param  list<string>  $surfaces
     * @return array{'--color-wk-accent': string, '--color-wk-accent-hover': string, '--color-wk-accent-content': string, '--color-wk-accent-fg': string}
     *
     * @throws InvalidArgumentException when the color or a surface cannot be read, or is translucent
     */
    public static function for(string $color, bool $dark = false, array $surfaces = [], bool $hex = false, bool $lift = true): array
    {
        if ($surfaces === []) {
            return self::onTheme($color, $dark, [], $hex, $lift);
        }

        return self::derive($color, $dark, static fn (string $accent): array => self::ownSurfaces($surfaces, $accent), $hex, $lift);
    }

    /**
     * The four accent tokens on a theme that names its own surfaces and state colors.
     *
     * `$tokens` holds what the theme declares for `--color-wk-bg`, `-bg-elevated`, `-bg-subtle`,
     * `-danger`, `-success` and `-warning`, as CSS colors; a token it leaves out is WireKit's own.
     * The text color then reads on that theme's page, card and subtle surfaces and on the tints
     * `callout` and `alert` draw on its card, as it does on WireKit's own with `for()`.
     *
     * @internal `wirekit:theme --accent` passes the tokens of the preset it writes. A theme of your
     *           own passes its surfaces to `for()`.
     *
     * @param  array<string, string>  $tokens
     * @return array{'--color-wk-accent': string, '--color-wk-accent-hover': string, '--color-wk-accent-content': string, '--color-wk-accent-fg': string}
     *
     * @throws InvalidArgumentException when the color or a token cannot be read, or is translucent
     */
    public static function onTheme(string $color, bool $dark, array $tokens, bool $hex = false, bool $lift = true): array
    {
        $theme = array_intersect_key($tokens, self::THEME['light']) + self::THEME[$dark ? 'dark' : 'light'];

        foreach ($theme as $token => $value) {
            if (WcagContrast::ratio('#000', $value) === null) {
                throw new InvalidArgumentException("AccentLadder cannot read {$token} [{$value}] as an opaque color.");
            }
        }

        return self::derive($color, $dark, static fn (string $accent): array => self::themeSurfaces($theme, $accent), $hex, $lift);
    }

    /**
     * The fill, its hover, the text color and the label, the text color read on the surfaces
     * `$backgrounds` gives for the derived fill.
     *
     * @param  callable(string): list<string>  $backgrounds
     * @return array{'--color-wk-accent': string, '--color-wk-accent-hover': string, '--color-wk-accent-content': string, '--color-wk-accent-fg': string}
     */
    private static function derive(string $color, bool $dark, callable $backgrounds, bool $hex, bool $lift): array
    {
        $brand = Oklch::fromCss($color);

        if ($brand === null) {
            throw new InvalidArgumentException("AccentLadder cannot read [{$color}] as an opaque color.");
        }

        // The fill: the brand as given, lifted on a dark page where it would sink into it.
        $lifted = $lift && $dark && $brand[0] < self::DARK_FILL_LIGHTNESS;
        $fill = $lifted
            ? Oklch::toGamut([self::DARK_FILL_LIGHTNESS, $brand[1], $brand[2]])
            : Oklch::toGamut($brand);
        $accent = ($hex && ! $lifted ? self::hexAsGiven($color) : null) ?? self::write($fill, $hex);

        $label = self::label($accent, $hex);

        return [
            '--color-wk-accent' => $accent,
            '--color-wk-accent-hover' => self::hover($fill, $label, $dark, $hex),
            '--color-wk-accent-content' => self::content($fill, $backgrounds($accent), $dark, $hex),
            '--color-wk-accent-fg' => $label,
        ];
    }

    /**
     * The color as the tokens are written: an `oklch()` value, or a `#rrggbb` color.
     *
     * Both are written from the unrounded color. Read back from the `oklch()` string, whose
     * numbers are rounded, a hex color can land one step off in a channel.
     *
     * @param  array{0: float, 1: float, 2: float}  $lch
     */
    private static function write(array $lch, bool $hex): string
    {
        return $hex ? Oklch::toHex($lch) : Oklch::toCss($lch);
    }

    /** A color given in hex as `#rrggbb` in lower case, or null for any other form. */
    private static function hexAsGiven(string $color): ?string
    {
        if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', trim($color), $match) !== 1) {
            return null;
        }

        // A translucent color was refused before this point, so a fourth or eighth digit is opaque.
        $digits = strtolower($match[1]);

        return '#'.(strlen($digits) <= 4
            ? $digits[0].$digits[0].$digits[1].$digits[1].$digits[2].$digits[2]
            : substr($digits, 0, 6));
    }

    /** The first label, of the theme's two and then of white and black, that reads on the fill. */
    private static function label(string $accent, bool $hex): string
    {
        foreach (self::LABELS as $pair) {
            if ($hex) {
                $pair = array_map(static fn (string $label): string => Oklch::toHex(Oklch::fromCss($label) ?? [0.0, 0.0, 0.0]), $pair);
            }

            $best = (self::ratio($pair[0], $accent) >= self::ratio($pair[1], $accent)) ? $pair[0] : $pair[1];

            if (self::ratio($best, $accent) >= self::TEXT_CONTRAST) {
                return $best;
            }
        }

        throw new LogicException("Neither white nor black reaches 4.5:1 on [{$accent}].");
    }

    /**
     * The hover: a step away from the page, where the label reads on it, else a step toward it.
     *
     * The label reads on the fill, and moving the fill away from the label's lightness only
     * raises the ratio, so one of the two directions always keeps it. A side without room for the
     * whole step is skipped: the step would end at black or white, where no hue is left, as a pale
     * yellow fill on a dark page would turn white under the pointer. The shipped dark theme steps
     * its 98.5% fill toward the page for the same reason.
     *
     * @param  array{0: float, 1: float, 2: float}  $fill
     */
    private static function hover(array $fill, string $label, bool $dark, bool $hex): string
    {
        $away = $dark ? self::HOVER_STEP : -self::HOVER_STEP;

        foreach ([$away, -$away] as $step) {
            $lightness = $fill[0] + $step;

            if ($lightness <= 0.0 || $lightness >= 1.0) {
                continue;
            }

            $hover = self::write([$lightness, $fill[1], $fill[2]], $hex);

            if (self::ratio($label, $hover) >= self::TEXT_CONTRAST) {
                return $hover;
            }
        }

        throw new LogicException("No hover for [{$label}] on the fill reads at 4.5:1 or more.");
    }

    /**
     * The text color: the fill, moved away from the page until it reads on every surface.
     *
     * @param  array{0: float, 1: float, 2: float}  $fill
     * @param  list<string>  $backgrounds
     */
    private static function content(array $fill, array $backgrounds, bool $dark, bool $hex): string
    {
        $lightness = $fill[0];

        while (true) {
            $candidate = self::write([$lightness, $fill[1], $fill[2]], $hex);

            if (self::readsOnAll($candidate, $backgrounds)) {
                return $candidate;
            }

            // Only the end the text moves toward stops it: black on a light page, white on a dark
            // one. A fill may start at the other end, with the whole way still ahead of it.
            if ($dark ? $lightness >= 1.0 : $lightness <= 0.0) {
                throw new LogicException("No lightness of [{$candidate}] reads at 4.5:1 on every surface.");
            }

            $lightness = max(0.0, min(1.0, $lightness + ($dark ? self::CONTENT_STEP : -self::CONTENT_STEP)));
        }
    }

    /**
     * The surfaces of a theme in one mode, and the tints `callout` and `alert` draw on its card
     * surface: one for each state color, and one for the accent.
     *
     * @param  array<string, string>  $theme
     * @return list<string>
     */
    private static function themeSurfaces(array $theme, string $accent): array
    {
        $elevated = $theme['--color-wk-bg-elevated'];
        $surfaces = [$theme['--color-wk-bg'], $elevated, $theme['--color-wk-bg-subtle']];

        foreach ([$theme['--color-wk-danger'], $theme['--color-wk-success'], $theme['--color-wk-warning'], $accent] as $tone) {
            foreach (self::TINTS as $percent) {
                $surfaces[] = "color-mix(in srgb, {$tone} {$percent}%, {$elevated})";
            }
        }

        return $surfaces;
    }

    /**
     * Your surfaces, and the tints the accent makes on each.
     *
     * @param  list<string>  $surfaces
     * @return list<string>
     */
    private static function ownSurfaces(array $surfaces, string $accent): array
    {
        $backgrounds = [];

        foreach ($surfaces as $surface) {
            if (WcagContrast::ratio('#000', $surface) === null) {
                throw new InvalidArgumentException("AccentLadder cannot read the surface [{$surface}] as an opaque color.");
            }

            $backgrounds[] = $surface;

            foreach (self::TINTS as $percent) {
                $backgrounds[] = "color-mix(in srgb, {$accent} {$percent}%, {$surface})";
            }
        }

        return $backgrounds;
    }

    /** @param  list<string>  $backgrounds */
    private static function readsOnAll(string $color, array $backgrounds): bool
    {
        foreach ($backgrounds as $background) {
            if (self::ratio($color, $background) < self::TEXT_CONTRAST) {
                return false;
            }
        }

        return true;
    }

    /** The ratio of two colors this class wrote or checked as opaque, so never unmeasurable. */
    private static function ratio(string $foreground, string $background): float
    {
        return WcagContrast::ratio($foreground, $background)
            ?? throw new LogicException("[{$foreground}] on [{$background}] cannot be measured.");
    }
}
