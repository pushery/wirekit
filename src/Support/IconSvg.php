<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use BladeUI\Icons\Exceptions\SvgNotFound;
use Illuminate\Support\HtmlString;
use Pushery\WireKit\Icons\IconResolver;
use Pushery\WireKit\Icons\IconSetPackages;

/**
 * The SVG of an icon alias for a view that draws it inside a box of its own, rendered the way
 * `<x-wirekit::icon>` renders one.
 *
 * Two things can leave an icon without a glyph: an alias no preset knows, which the resolver
 * answers with an empty name outside a console or a test, and a name that resolves onto a set
 * nobody installed, which `svg()` answers with SvgNotFound. A console or a test fails loudly,
 * where the build can be fixed. A request draws nothing in place of the glyph and keeps the page,
 * as `config('wirekit.validation.throw_on_invalid')` promises.
 *
 * Without blade-icons nothing is drawn, but the alias is still looked up first, as
 * `<x-wirekit::icon>` looks up its own: a misspelled alias fails the same way in every
 * installation, not only in one that has an icon package.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class IconSvg
{
    /**
     * The icon's SVG, or null where nothing can be drawn.
     *
     * @param  array<string, string>  $attributes  the attributes the `<svg>` takes, as `svg()` takes them
     */
    public static function html(string $alias, array $attributes = []): ?HtmlString
    {
        $resolved = app(IconResolver::class)->resolve($alias);

        if ($resolved === '' || ! function_exists('svg')) {
            return null;
        }

        try {
            return new HtmlString(svg($resolved, $attributes)->toHtml());
        } catch (SvgNotFound $e) {
            if (StrictnessGate::shouldThrowOnInvalid()) {
                throw $e;
            }

            IconSetPackages::reportMissingSetOnce($alias, $resolved);

            return null;
        }
    }
}
