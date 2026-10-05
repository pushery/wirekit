<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;

/**
 * A prop a caller wrote on a component's tag, read as the text it stands for.
 *
 * Blade escapes a value echoed into a component tag before the component receives it:
 * `label="{{ $name }}"` arrives as `Tom&#039;s &amp; Jerry`. So does every value a wrapper passes
 * on through its attribute bag, which holds each value escaped, even one its own caller bound. A
 * bound value, `:label="$name"`, arrives as written. A view that writes the first with `{{ }}` or
 * hands it to a script's configuration escapes it a second time: the reader sees the references,
 * a screen reader reads them out, and a form submits them as the value. Read here, every way of
 * writing the prop arrives as the same text, which the view escapes once.
 *
 * Only what Blade's escape writes is undone, as in UrlProp: `&amp;`, `&lt;`, `&gt;`, `&quot;` and
 * `&#039;`. A reference Blade never writes, `&#106;` or `&mdash;`, stays text, so a URL prop keeps
 * the scheme it was read with. The price is a bound value that holds one of the five as literal
 * text: it reads as the character it names.
 *
 * @internal Called from the view composer the service provider registers and from the class
 *           components; not a developer-facing API.
 */
final class PropText
{
    /**
     * Props whose value is HTML the view writes as it is. Undoing Blade's escape on a bound value
     * would turn an escaped `&lt;script&gt;` in its text into a tag, so these are read only where
     * Blade escaped them on the way in, which is when the value equals the bag's copy of it.
     *
     * @var array<string, list<string>>
     */
    private const HTML = [
        'editor' => ['value'],
    ];

    /**
     * Read every prop the caller wrote on the tag of the component this view renders, in place,
     * before the view runs. A partial a component includes is left alone: what reaches it was
     * read by the component that includes it.
     */
    public static function normalize(View $view): void
    {
        $component = UnboundModel::componentOf($view->name());

        if ($component === null || str_starts_with($component, 'partials.')) {
            return;
        }

        $data = $view->getData();
        $escaped = [];

        // Only a value with an ampersand can hold one of the references, so most renders stop here.
        foreach ($data as $key => $value) {
            if (is_string($key) && is_string($value) && str_contains($value, '&')) {
                $escaped[$key] = $value;
            }
        }

        $attributes = $data['attributes'] ?? null;

        if ($escaped === [] || ! $attributes instanceof ComponentAttributeBag) {
            return;
        }

        // Blade hands an anonymous component every attribute twice: in the bag under the name it
        // was written with, and as data under that name in camel case, which `@props` then keeps
        // for a prop. A value the caller did not write on this tag has no copy in the bag.
        $written = [];

        foreach (array_keys($attributes->getAttributes()) as $name) {
            $written[Str::camel((string) $name)] = (string) $name;
        }

        $html = self::HTML[$component] ?? [];

        foreach ($escaped as $key => $value) {
            if (! isset($written[$key])) {
                continue;
            }

            if (in_array($key, $html, true) && $attributes->get($written[$key]) !== $value) {
                continue;
            }

            $view->with($key, self::text($value));
        }
    }

    /**
     * The value with Blade's escape undone.
     *
     * A value that is not a string comes back as it is: a slot or an `HtmlString` is HTML the
     * caller means as written, and a number, an array or an object never passed through Blade's
     * escape. A prop is whatever the caller wrote or bound, which is why the parameter and the
     * return are `mixed`.
     */
    public static function text(mixed $value): mixed
    {
        // Every one of the five references begins with an ampersand.
        return is_string($value) && str_contains($value, '&')
            ? htmlspecialchars_decode($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401)
            : $value;
    }
}
