<?php

declare(strict_types=1);

namespace Pushery\WireKit\Theming;

use InvalidArgumentException;
use LogicException;

/**
 * Every color token of both modes, derived from four base colors, with the contrast of each held.
 *
 * `AccentLadder` derives the four accent tokens from one brand color. A shop that picks its own
 * colors needs the rest as well: the page, its card and panel surfaces, text in three strengths,
 * the borders of fields, the focus ring, and that for the light and for the dark mode. This class
 * takes four colors, the background, a surface, the accent and a dark color, and returns:
 *
 * - `light()` and `dark()`: the `--color-wk-*` tokens of each mode, the surfaces, text, borders,
 *   ring and accent. The dark mode needs no colors of its own: the dark color becomes the page, the
 *   background becomes the text, and the accent is lifted where it would sink into the page.
 * - `sections`: per mode, four schemes for a part of a page, `default`, `surface`, `dark` and
 *   `accent`, each with its ground, text, muted text, border and link, and whether it reads as a
 *   light or a dark part.
 * - `shifts`: each base color that had to move to keep a promise, from what to what, and why.
 * - `checks`: every pairing that is promised, with the ratio its written values reach.
 *
 * The promises, in both modes and on every ground of the mode (page, card, subtle, muted and the
 * fill of a field): text, muted and subtle text and the accent as text read at 4.5:1 or more; the
 * border of a field and the focus ring stand at 3:1 or more (WCAG 1.4.11). The error, success and
 * warning text of WireKit's own theme read at 4.5:1 on the page, a card and a field, where a
 * form's messages stand. The label of the accent reads at 4.5:1 on the fill and on its hover. In
 * each section the text, the muted text and the link read at 4.5:1 on its ground. A base color
 * that keeps a promise from being kept moves in lightness only, keeping its hue. Every ratio is measured with `WcagContrast` on the value as it
 * is written, an `oklch()` value or, with `hex`, a `#rrggbb` color, and `from()` throws rather than
 * return a scheme that misses one.
 *
 * The state colors stay WireKit's: they carry a meaning of their own, and a shop's palette could
 * otherwise turn its error red into its accent.
 */
final class ColorScheme
{
    /** The ratio of text: WCAG 1.4.3, AA for text of normal size. */
    private const TEXT_CONTRAST = 4.5;

    /** The ratio of the border of a field and of the focus ring: WCAG 1.4.11. */
    private const UI_CONTRAST = 3.0;

    /** The ratio muted text aims for where the text leaves room, between text and subtle text. */
    private const MUTED_CONTRAST = 6.0;

    /** The ratio subtle text and the placeholder aim for, just above the floor. */
    private const SUBTLE_CONTRAST = 4.6;

    /** The step a color moves by until it keeps its promise, in OKLCH lightness. */
    private const STEP = 0.01;

    /**
     * The grounds of a mode as offsets from the page, in OKLCH lightness, positive toward white.
     * They follow WireKit's own theme: white cards on a white page and panels a little darker in
     * the light mode, cards and panels lifted off the page in the dark one.
     */
    private const GROUNDS = [
        'light' => ['elevated' => 0.02, 'subtle' => -0.015, 'muted' => -0.03, 'skeleton' => -0.07],
        'dark' => ['elevated' => 0.06, 'subtle' => 0.06, 'muted' => 0.12, 'skeleton' => 0.185],
    ];

    /**
     * Where each border lies between the page and the text, as a share of the way, the way
     * WireKit's own theme places them. The two of a field are where its search starts; it moves on
     * toward the text until the border stands at 3:1.
     */
    private const BORDERS = [
        'light' => ['border' => 0.10, 'border-subtle' => 0.07, 'border-hover' => 0.235, 'border-strong' => 0.40, 'border-strong-hover' => 0.49],
        'dark' => ['border' => 0.45, 'border-subtle' => 0.35, 'border-hover' => 0.35, 'border-strong' => 0.435, 'border-strong-hover' => 0.52],
    ];

    /**
     * The error, success and warning text of WireKit's own theme, per mode. Every ground has to
     * carry them, since a form shows its messages in them whatever the page. Held to
     * `dist/wirekit.css` by a test.
     *
     * @var array<string, array<string, string>>
     */
    private const STATES = [
        'light' => [
            '--color-wk-danger-text' => 'oklch(50.5% 0.213 27.518)',
            '--color-wk-success-text' => 'oklch(45.6% 0.17 149.7)',
            '--color-wk-warning-text' => 'oklch(47.6% 0.158 65)',
        ],
        'dark' => [
            '--color-wk-danger-text' => 'oklch(70.4% 0.191 22.216)',
            '--color-wk-success-text' => 'oklch(79.2% 0.209 151.711)',
            '--color-wk-warning-text' => 'oklch(85.4% 0.183 81.0)',
        ],
    ];

    /** The grounds a form's messages stand on, which carry the state colors. */
    private const FORM_GROUNDS = ['bg', 'bg-elevated', 'bg-input'];

    /** The fills whose tints `callout` and `alert` draw on a card, per mode, as WireKit's theme writes them. */
    private const STATE_FILLS = [
        'light' => ['oklch(57.7% 0.245 27.325)', 'oklch(62.7% 0.194 149.214)', 'oklch(76.9% 0.188 70.08)'],
        'dark' => ['oklch(57.7% 0.245 27.325)', 'oklch(72.3% 0.219 149.579)', 'oklch(82.8% 0.189 84.429)'],
    ];

    /**
     * @param  array{light: array<string, string>, dark: array<string, string>}  $tokens
     * @param  array{light: array<string, array<string, string>>, dark: array<string, array<string, string>>}  $sections
     * @param  list<array{base: string, mode: string, from: string, to: string, because: string}>  $shifts
     * @param  list<array{mode: string, pair: string, ratio: float, minimum: float}>  $checks
     */
    private function __construct(
        public readonly array $tokens,
        public readonly array $sections,
        public readonly array $shifts,
        public readonly array $checks,
    ) {}

    /**
     * The scheme of four base colors, as CSS colors.
     *
     * `$background` is the page of the light mode and the text of the dark one, `$dark` the
     * reverse; `$surface` is the ground of the surface section; `$accent` the brand color, as
     * `AccentLadder` takes it. `$hex` writes every value as a `#rrggbb` color, for a store or a
     * validator that takes hex only, and every ratio then holds for the hex values as written.
     *
     * @throws InvalidArgumentException when a color cannot be read, or is translucent
     */
    public static function from(string $background, string $surface, string $accent, string $dark, bool $hex = false): self
    {
        $bases = ['background' => $background, 'surface' => $surface, 'accent' => $accent, 'dark' => $dark];
        $read = [];

        foreach ($bases as $role => $color) {
            $read[$role] = Oklch::fromCss($color)
                ?? throw new InvalidArgumentException("ColorScheme cannot read the {$role} color [{$color}] as an opaque color.");
        }

        $shifts = [];
        $checks = [];

        // The light mode reads the background as its page and the dark color as its text; the dark
        // mode the other way round.
        [$lightTokens, $lightSections] = self::mode($read['background'], $read['dark'], $read['surface'], $accent, $bases, 'light', $hex, $shifts, $checks);
        [$darkTokens, $darkSections] = self::mode($read['dark'], $read['background'], $read['surface'], $accent, $bases, 'dark', $hex, $shifts, $checks);

        return new self(
            ['light' => $lightTokens, 'dark' => $darkTokens],
            ['light' => $lightSections, 'dark' => $darkSections],
            $shifts,
            $checks,
        );
    }

    /**
     * The tokens of the light mode, keyed by their custom property.
     *
     * @return array<string, string>
     */
    public function light(): array
    {
        return $this->tokens['light'];
    }

    /**
     * The tokens of the dark mode, keyed by their custom property.
     *
     * @return array<string, string>
     */
    public function dark(): array
    {
        return $this->tokens['dark'];
    }

    /**
     * The tokens and the sections of one mode.
     *
     * @param  array{0: float, 1: float, 2: float}  $page
     * @param  array{0: float, 1: float, 2: float}  $ink
     * @param  array{0: float, 1: float, 2: float}  $surface
     * @param  array<string, string>  $bases
     * @param  'light'|'dark'  $mode
     * @param  list<array{base: string, mode: string, from: string, to: string, because: string}>  $shifts
     * @param  list<array{mode: string, pair: string, ratio: float, minimum: float}>  $checks
     * @return array{0: array<string, string>, 1: array<string, array<string, string>>}
     */
    private static function mode(array $page, array $ink, array $surface, string $accent, array $bases, string $mode, bool $hex, array &$shifts, array &$checks): array
    {
        $isDark = $mode === 'dark';
        $pageRole = $isDark ? 'dark' : 'background';
        $inkRole = $isDark ? 'background' : 'dark';

        // Toward white in the dark mode's text, toward black in the light mode's: the side the
        // text lies on, seen from the page.
        $towardInk = $isDark ? 1.0 : -1.0;

        // 1. The page, moved away from the text until every ground carries the state colors.
        $page = Oklch::toGamut($page);
        $start = $page;

        while (! self::statesReadOn(self::grounds($page, $mode, $hex), $mode)) {
            if ($isDark ? $page[0] <= 0.0 : $page[0] >= 1.0) {
                throw new LogicException("ColorScheme found no page for the {$mode} mode that carries WireKit's state colors.");
            }

            $page = Oklch::toGamut([max(0.0, min(1.0, $page[0] - $towardInk * self::STEP)), $page[1], $page[2]]);
        }

        $grounds = self::grounds($page, $mode, $hex);

        if ($page !== $start) {
            $shifts[] = self::shift($pageRole, $mode, $bases[$pageRole], $grounds['bg'], 'the error, success and warning text of WireKit read on the page, a card and a field at 4.5:1');
        }

        // 2. The text, moved toward its end until it reads on every ground.
        $text = self::reading($ink, $grounds, self::TEXT_CONTRAST, $towardInk, $hex)
            ?? throw new LogicException("ColorScheme found no text for the {$mode} mode that reads on every ground.");

        if ($text !== self::write($ink, $hex)) {
            $shifts[] = self::shift($inkRole, $mode, $bases[$inkRole], $text, 'it reads as text on every ground at 4.5:1');
        }

        $textLch = self::lch($text);

        // 3. Muted and subtle text, between the text and the page, as close to the page as they may.
        //    Their floor is the text's own on the grounds that carry text: the skeleton carries
        //    none, and the text may stand under 4.5:1 there, which would let them follow it.
        $textFloor = self::lowest($text, self::groundsOnly($grounds));
        $muted = self::toward($textLch, $page[0], $grounds, max(self::SUBTLE_CONTRAST, min(self::MUTED_CONTRAST, $textFloor)), $hex);
        $subtle = self::toward($textLch, $page[0], $grounds, min(self::SUBTLE_CONTRAST, $textFloor), $hex);

        // 4. The borders, between the page and the text in the page's hue; a field's moves on until
        //    it stands at 3:1.
        $borders = [];

        foreach (self::BORDERS[$mode] as $name => $share) {
            $borders[$name] = self::write([$page[0] + $share * ($textLch[0] - $page[0]), $page[1], $page[2]], $hex);
        }

        $borders['border-strong'] = self::field(self::lch($borders['border-strong']), $grounds, $towardInk, $hex);
        $strongHover = self::lch($borders['border-strong-hover']);
        $borders['border-strong-hover'] = self::field(
            [$towardInk > 0 ? max($strongHover[0], self::lch($borders['border-strong'])[0] + self::STEP) : min($strongHover[0], self::lch($borders['border-strong'])[0] - self::STEP), $strongHover[1], $strongHover[2]],
            $grounds,
            $towardInk,
            $hex,
        );

        // 5. The accent on the grounds of the mode, the tints `callout` and `alert` draw on a card,
        //    and the ground of the surface section where the text of the mode reads on it.
        $surfaceGround = $isDark
            ? self::write([min(1.0, $page[0] + 0.08), $surface[1] * 0.6, $surface[2]], $hex)
            : self::write($surface, $hex);
        $accentSurfaces = array_values(array_unique([...array_values(self::groundsOnly($grounds)), ...self::stateTints($grounds['bg-elevated'], $mode)]));

        if (WcagContrast::ratio($text, $surfaceGround) >= self::TEXT_CONTRAST) {
            $accentSurfaces[] = $surfaceGround;
        }

        $accentTokens = AccentLadder::for($accent, dark: $isDark, surfaces: $accentSurfaces, hex: $hex);

        // The light mode never lifts the fill, so a dark fill that differs from it was lifted.
        if ($isDark && $accentTokens['--color-wk-accent'] !== AccentLadder::for($accent, hex: $hex)['--color-wk-accent']) {
            $shifts[] = self::shift('accent', $mode, $accent, $accentTokens['--color-wk-accent'], 'a fill darker than 68% lightness is lifted on a dark page, as the presets lift theirs');
        }

        $tokens = [
            '--color-wk-bg' => $grounds['bg'],
            '--color-wk-bg-elevated' => $grounds['bg-elevated'],
            '--color-wk-bg-subtle' => $grounds['bg-subtle'],
            '--color-wk-bg-muted' => $grounds['bg-muted'],
            '--color-wk-bg-input' => $grounds['bg-input'],
            '--color-wk-bg-inverse' => $text,
            '--color-wk-bg-skeleton' => $grounds['bg-skeleton'],
            '--color-wk-text' => $text,
            '--color-wk-text-muted' => $muted,
            '--color-wk-text-subtle' => $subtle,
            '--color-wk-text-placeholder' => $subtle,
            '--color-wk-text-inverse' => $grounds['bg'],
            '--color-wk-border' => $borders['border'],
            '--color-wk-border-subtle' => $borders['border-subtle'],
            '--color-wk-border-hover' => $borders['border-hover'],
            '--color-wk-border-strong' => $borders['border-strong'],
            '--color-wk-border-strong-hover' => $borders['border-strong-hover'],
            '--color-wk-ring' => $text,
            '--color-wk-ring-offset' => $grounds['bg'],
            ...$accentTokens,
        ];

        // 6. The sections: the page, the surface, the dark color and the accent as grounds.
        $darkGround = $isDark ? $grounds['bg'] : $text;
        $sections = [
            'default' => self::section($grounds['bg'], $text, $accentTokens['--color-wk-accent-content'], $accent, $mode, $hex),
            'surface' => self::section($surfaceGround, $text, $accentTokens['--color-wk-accent-content'], $accent, $mode, $hex, $grounds['bg']),
            'dark' => self::section($darkGround, $isDark ? $text : $grounds['bg'], $accentTokens['--color-wk-accent-content'], $accent, $mode, $hex),
            'accent' => self::section($accentTokens['--color-wk-accent'], $accentTokens['--color-wk-accent-fg'], $accentTokens['--color-wk-accent-fg'], $accent, $mode, $hex),
        ];

        self::check($tokens, $sections, $mode, $checks);

        return [$tokens, $sections];
    }

    /**
     * The grounds of a mode, from its page.
     *
     * @param  array{0: float, 1: float, 2: float}  $page
     * @param  'light'|'dark'  $mode
     * @return array{bg: string, bg-elevated: string, bg-subtle: string, bg-muted: string, bg-input: string, bg-skeleton: string}
     */
    private static function grounds(array $page, string $mode, bool $hex): array
    {
        $at = static fn (float $offset): string => self::write([max(0.0, min(1.0, $page[0] + $offset)), $page[1], $page[2]], $hex);
        $offsets = self::GROUNDS[$mode];
        $elevated = $at($offsets['elevated']);

        return [
            'bg' => self::write($page, $hex),
            'bg-elevated' => $elevated,
            'bg-subtle' => $at($offsets['subtle']),
            'bg-muted' => $at($offsets['muted']),
            // A field is filled like a card in the light mode and like the page in the dark one.
            'bg-input' => $mode === 'dark' ? self::write($page, $hex) : $elevated,
            'bg-skeleton' => $at($offsets['skeleton']),
        ];
    }

    /**
     * The grounds text stands on, without the skeleton, which carries none.
     *
     * @param  array<string, string>  $grounds
     * @return array<string, string>
     */
    private static function groundsOnly(array $grounds): array
    {
        unset($grounds['bg-skeleton']);

        return $grounds;
    }

    /**
     * Whether WireKit's error, success and warning text read on the grounds a form's messages
     * stand on: the page, a card and a field. The panels of a mode carry none, and asking them
     * as well would darken the dark color of a palette that WireKit's own dark theme leaves as
     * it is.
     *
     * @param  array<string, string>  $grounds
     * @param  'light'|'dark'  $mode
     */
    private static function statesReadOn(array $grounds, string $mode): bool
    {
        foreach (array_intersect_key($grounds, array_flip(self::FORM_GROUNDS)) as $ground) {
            foreach (self::STATES[$mode] as $state) {
                if (self::ratio($state, $ground) < self::TEXT_CONTRAST) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The tints `callout` and `alert` draw on a card with the state fills of the mode.
     *
     * @param  'light'|'dark'  $mode
     * @return list<string>
     */
    private static function stateTints(string $card, string $mode): array
    {
        $tints = [];

        foreach (self::STATE_FILLS[$mode] as $fill) {
            foreach ([10, 15] as $percent) {
                $tints[] = "color-mix(in srgb, {$fill} {$percent}%, {$card})";
            }
        }

        return $tints;
    }

    /**
     * The color, moved in lightness toward `$direction` from where it stands, until it reads on
     * every ground at `$minimum`, or null where even the end of the way does not.
     *
     * @param  array{0: float, 1: float, 2: float}  $color
     * @param  array<string, string>  $grounds
     */
    private static function reading(array $color, array $grounds, float $minimum, float $direction, bool $hex): ?string
    {
        $lightness = Oklch::toGamut($color)[0];

        while (true) {
            $candidate = self::write([$lightness, $color[1], $color[2]], $hex);

            if (self::lowest($candidate, self::groundsOnly($grounds)) >= $minimum) {
                return $candidate;
            }

            if ($direction > 0 ? $lightness >= 1.0 : $lightness <= 0.0) {
                return null;
            }

            $lightness = max(0.0, min(1.0, $lightness + $direction * self::STEP));
        }
    }

    /**
     * The color between the text and the page that lies as close to the page as it may while it
     * reads on every ground at `$target`; the text itself where no step toward the page does.
     *
     * @param  array{0: float, 1: float, 2: float}  $text
     * @param  array<string, string>  $grounds
     */
    private static function toward(array $text, float $page, array $grounds, float $target, bool $hex): string
    {
        $direction = $page >= $text[0] ? 1.0 : -1.0;
        $kept = self::write($text, $hex);
        $lightness = $text[0];

        while (true) {
            $lightness += $direction * self::STEP;

            if ($direction > 0 ? $lightness >= $page : $lightness <= $page) {
                return $kept;
            }

            $candidate = self::write([$lightness, $text[1], $text[2]], $hex);

            if (self::lowest($candidate, self::groundsOnly($grounds)) < $target) {
                return $kept;
            }

            $kept = $candidate;
        }
    }

    /**
     * The border of a field: moved on toward the text until it stands at 3:1 on every ground.
     *
     * @param  array{0: float, 1: float, 2: float}  $border
     * @param  array<string, string>  $grounds
     */
    private static function field(array $border, array $grounds, float $towardInk, bool $hex): string
    {
        return self::reading($border, $grounds, self::UI_CONTRAST, $towardInk, $hex)
            ?? throw new LogicException('ColorScheme found no border for a field that stands at 3:1 on every ground.');
    }

    /**
     * One section: its ground, the text that reads on it, the muted text, a border and the link.
     *
     * The text of the mode where it reads on the ground, else the text of the other side (the page
     * for a dark ground), else the better of the two moved away from the ground until it reads.
     * The link is the accent's text color where it reads on the ground, else the accent derived
     * for that ground alone, else the section's text.
     *
     * @return array{mode: string, bg: string, text: string, muted: string, border: string, link: string}
     */
    private static function section(string $ground, string $text, string $link, string $accent, string $mode, bool $hex, ?string $other = null): array
    {
        $other ??= $mode === 'dark' ? self::write([0.145, 0.0, 0.0], $hex) : self::write([1.0, 0.0, 0.0], $hex);
        $groundLch = self::lch($ground);
        $grounds = ['bg' => $ground];

        $chosen = self::ratio($text, $ground) >= self::TEXT_CONTRAST ? $text : (self::ratio($other, $ground) >= self::TEXT_CONTRAST ? $other : null);

        // On a ground of middle lightness neither may read. Each is moved away from the ground, the
        // better first, and on a ground where only one end reaches 4.5:1, a candidate on the other
        // side never does: pure white or black closes the list, and one of the two reads at 4.58:1
        // or more on any opaque color.
        if ($chosen === null) {
            $candidates = [$text, $other];
            usort($candidates, static fn (string $a, string $b): int => self::ratio($b, $ground) <=> self::ratio($a, $ground));
            $white = self::write([1.0, 0.0, 0.0], $hex);
            $black = self::write([0.0, 0.0, 0.0], $hex);
            $candidates[] = self::ratio($white, $ground) >= self::ratio($black, $ground) ? $white : $black;

            foreach ($candidates as $candidate) {
                $candidateLch = self::lch($candidate);
                $chosen = self::reading($candidateLch, $grounds, self::TEXT_CONTRAST, $candidateLch[0] >= $groundLch[0] ? 1.0 : -1.0, $hex);

                if ($chosen !== null) {
                    break;
                }
            }

            if ($chosen === null) {
                throw new LogicException("ColorScheme found no text for the ground [{$ground}].");
            }
        }

        $chosenLch = self::lch($chosen);
        $lightText = $chosenLch[0] > $groundLch[0];
        $muted = self::toward($chosenLch, $groundLch[0], $grounds, max(self::SUBTLE_CONTRAST, min(self::MUTED_CONTRAST, self::ratio($chosen, $ground))), $hex);
        $border = self::write([$groundLch[0] + 0.25 * ($chosenLch[0] - $groundLch[0]), $groundLch[1], $groundLch[2]], $hex);

        if (self::ratio($link, $ground) < self::TEXT_CONTRAST) {
            // The ladder also reads its text on the accent's own tints of the ground. A bright accent
            // on a ground of middle lightness can leave no lightness that reads on all of them, and
            // the link then takes the section's text, which reads: a link in the color of its text,
            // told apart by its underline.
            try {
                $link = AccentLadder::for($accent, dark: $lightText, surfaces: [$ground], hex: $hex)['--color-wk-accent-content'];
            } catch (LogicException) {
                $link = $chosen;
            }
        }

        return [
            'mode' => $lightText ? 'dark' : 'light',
            'bg' => $ground,
            'text' => $chosen,
            'muted' => $muted,
            'border' => $border,
            'link' => $link,
        ];
    }

    /**
     * Every promised pairing of a mode, with the ratio its written values reach; a pairing below
     * its minimum ends the derivation, since a scheme that misses one is not to be returned.
     *
     * @param  array<string, string>  $tokens
     * @param  array<string, array<string, string>>  $sections
     * @param  'light'|'dark'  $mode
     * @param  list<array{mode: string, pair: string, ratio: float, minimum: float}>  $checks
     */
    private static function check(array $tokens, array $sections, string $mode, array &$checks): void
    {
        $pairs = [];
        $grounds = ['bg', 'bg-elevated', 'bg-subtle', 'bg-muted', 'bg-input'];

        foreach ($grounds as $ground) {
            foreach (['text', 'text-muted', 'text-subtle', 'accent-content'] as $foreground) {
                $pairs[] = [$foreground, $ground, self::TEXT_CONTRAST];
            }

            foreach (['border-strong', 'border-strong-hover', 'ring'] as $foreground) {
                $pairs[] = [$foreground, $ground, self::UI_CONTRAST];
            }
        }

        $pairs[] = ['accent-fg', 'accent', self::TEXT_CONTRAST];
        $pairs[] = ['accent-fg', 'accent-hover', self::TEXT_CONTRAST];
        $pairs[] = ['text-inverse', 'bg-inverse', self::TEXT_CONTRAST];
        $pairs[] = ['ring', 'ring-offset', self::UI_CONTRAST];

        $measured = [];

        foreach ($pairs as [$foreground, $background, $minimum]) {
            $measured[] = [
                "{$foreground} on {$background}",
                self::ratio($tokens["--color-wk-{$foreground}"], $tokens["--color-wk-{$background}"]),
                $minimum,
            ];
        }

        foreach (self::FORM_GROUNDS as $ground) {
            foreach (self::STATES[$mode] as $state => $value) {
                $measured[] = [substr($state, strlen('--color-wk-'))." on {$ground}", self::ratio($value, $tokens["--color-wk-{$ground}"]), self::TEXT_CONTRAST];
            }
        }

        foreach ($sections as $name => $section) {
            foreach (['text', 'muted', 'link'] as $foreground) {
                $measured[] = ["{$name} section: {$foreground} on bg", self::ratio($section[$foreground], $section['bg']), self::TEXT_CONTRAST];
            }
        }

        foreach ($measured as [$pair, $ratio, $minimum]) {
            if ($ratio < $minimum) {
                throw new LogicException(sprintf('ColorScheme measured %s at %.2f:1 in the %s mode, under %.1f:1.', $pair, $ratio, $mode, $minimum));
            }

            $checks[] = ['mode' => $mode, 'pair' => $pair, 'ratio' => round($ratio, 2), 'minimum' => $minimum];
        }
    }

    /**
     * The lowest ratio of a color on the grounds.
     *
     * @param  array<string, string>  $grounds
     */
    private static function lowest(string $color, array $grounds): float
    {
        $lowest = INF;

        foreach ($grounds as $ground) {
            $lowest = min($lowest, self::ratio($color, $ground));
        }

        return $lowest;
    }

    /**
     * A shift of a base color, for the report.
     *
     * @return array{base: string, mode: string, from: string, to: string, because: string}
     */
    private static function shift(string $base, string $mode, string $from, string $to, string $because): array
    {
        return ['base' => $base, 'mode' => $mode, 'from' => $from, 'to' => $to, 'because' => $because];
    }

    /**
     * A color this class wrote, read back.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private static function lch(string $color): array
    {
        return Oklch::fromCss($color) ?? throw new LogicException("ColorScheme cannot read back [{$color}].");
    }

    /** @param  array{0: float, 1: float, 2: float}  $lch */
    private static function write(array $lch, bool $hex): string
    {
        $lch = [max(0.0, min(1.0, $lch[0])), $lch[1], $lch[2]];

        return $hex ? Oklch::toHex($lch) : Oklch::toCss($lch);
    }

    /** The ratio of two colors this class wrote or checked as opaque, so never unmeasurable. */
    private static function ratio(string $foreground, string $background): float
    {
        return WcagContrast::ratio($foreground, $background)
            ?? throw new LogicException("[{$foreground}] on [{$background}] cannot be measured.");
    }
}
