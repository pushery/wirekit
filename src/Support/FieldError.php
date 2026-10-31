<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;
use Stringable;

/**
 * The validation error of one form field, read from the bag Laravel shares as `$errors`.
 *
 * Laravel keys an error by the field's dotted path, and a field's HTML name writes the same path
 * with brackets: `items[0][qty]` fails as `items.0.qty`, and the values of `tags[]` fail as `tags`
 * or, one by one, as `tags.0`. Asked with the HTML name, the bag holds nothing for such a field,
 * so a component reads its error through this class.
 */
final class FieldError
{
    /**
     * The bag key for an HTML field name: brackets become dots, and a trailing `[]` goes.
     *
     * A name that already is a dotted path, such as a `wire:model` key, stays as it is.
     */
    public static function key(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $key = rtrim((string) preg_replace('/\[([^\]]*)\]/', '.$1', $name), '.');

        return $key === '' ? null : $key;
    }

    /**
     * The first message the bag holds for the field, or null when it holds none.
     *
     * A name that ends in `[]` sends several values; when the list as a whole has no message, the
     * first message about one of its values answers.
     *
     * A field sent in named parts, the two ends of a range as `trip[start]` and `trip[end]`, is
     * validated part by part, so its messages are filed as `trip.start` and `trip.end`. When the
     * whole has no message, the first part that has one answers, in the order of `$parts`.
     *
     * @param  list<string>  $parts
     */
    public static function first(?object $errors, ?string $name, array $parts = []): ?string
    {
        $key = self::key($name);

        if ($key === null || ! ($errors instanceof ViewErrorBag || $errors instanceof MessageBag)) {
            return null;
        }

        $message = (string) $errors->first($key);

        if ($message === '' && str_ends_with((string) $name, '[]')) {
            $message = (string) $errors->first($key.'.*');
        }

        foreach ($parts as $part) {
            if ($message !== '') {
                break;
            }

            $message = (string) $errors->first($key.'.'.$part);
        }

        return $message === '' ? null : $message;
    }

    /** Whether the bag holds a message for the field. */
    public static function has(?object $errors, ?string $name): bool
    {
        return self::first($errors, $name) !== null;
    }

    /**
     * The name a field's error is filed under: its own `name`, else the property its
     * `wire:model` binds, or null when it has neither.
     *
     * Livewire files a failed validation under the property path, so a field bound with
     * `wire:model="form.email"` and no `name` finds its message under `form.email`. Every
     * modifier spelling binds the same property. `x-model` is left out: it binds in the browser
     * and nothing validates it on the server.
     */
    public static function nameOf(?string $name, ComponentAttributeBag $attributes): ?string
    {
        if (filled($name)) {
            return $name;
        }

        $bound = $attributes->whereStartsWith('wire:model')->first();

        return is_string($bound) && trim($bound) !== '' ? trim($bound) : null;
    }

    /**
     * The caller's `error` as one message. A list, as `$errors->get()` hands one over, is its first
     * message that is not empty, which is what `$errors->first()` would have given; a list of the
     * lists a wildcard key returns is read the same way. Any other value comes back as it is.
     *
     * @param  iterable<array-key, mixed>|string|Stringable|false|null  $error
     */
    public static function one(iterable|string|Stringable|false|null $error): string|Stringable|false|null
    {
        return is_iterable($error) ? self::firstOf($error) : $error;
    }

    /**
     * Whether the caller turned the field's error off.
     *
     * `false` does, and so does `error="false"` written without the colon, which arrives as text.
     * A field whose error is off shows none and does not read the bag, for a page that reports the
     * failure somewhere else: a notice above the form for a rejected sign-in link, say, where the
     * field below would repeat it and mark an entry invalid that nothing is wrong with.
     *
     * @param  iterable<array-key, mixed>|string|Stringable|false|null  $error
     */
    public static function isOff(iterable|string|Stringable|false|null $error): bool
    {
        $error = self::one($error);

        return $error === false || (is_string($error) && strcasecmp(trim($error), 'false') === 0);
    }

    /**
     * The message a field shows, or null when it shows none.
     *
     * The caller's own message wins. Without one, the first message the bag holds for the field
     * answers, so a failed validation reaches the field without `:error` written by hand. An empty
     * message counts as none, so a field is never marked invalid without a message to show, and a
     * field whose error is off shows nothing, whatever the bag holds. A list of messages shows its
     * first (one()). A field sent in named parts names them, and a message filed under one of them
     * is the field's (first()).
     *
     * @param  iterable<array-key, mixed>|string|Stringable|false|null  $error
     * @param  list<string>  $parts
     */
    public static function message(?object $errors, ?string $name, iterable|string|Stringable|false|null $error = null, array $parts = []): string|Stringable|null
    {
        $error = self::one($error);

        if (self::isOff($error)) {
            return null;
        }

        if ($error !== null && $error !== false && (string) $error !== '') {
            return $error;
        }

        return self::first($errors, $name, $parts);
    }

    /**
     * The first message in the list that is not empty, depth first, or null.
     *
     * @param  iterable<array-key, mixed>  $messages
     */
    private static function firstOf(iterable $messages): string|Stringable|null
    {
        foreach ($messages as $message) {
            if (is_iterable($message)) {
                $message = self::firstOf($message);
            }

            if ((is_string($message) || $message instanceof Stringable) && trim((string) $message) !== '') {
                return $message;
            }
        }

        return null;
    }
}
