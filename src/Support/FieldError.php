<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

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
     */
    public static function first(?object $errors, ?string $name): ?string
    {
        $key = self::key($name);

        if ($key === null || ! ($errors instanceof ViewErrorBag || $errors instanceof MessageBag)) {
            return null;
        }

        $message = (string) $errors->first($key);

        if ($message === '' && str_ends_with((string) $name, '[]')) {
            $message = (string) $errors->first($key.'.*');
        }

        return $message === '' ? null : $message;
    }

    /** Whether the bag holds a message for the field. */
    public static function has(?object $errors, ?string $name): bool
    {
        return self::first($errors, $name) !== null;
    }
}
