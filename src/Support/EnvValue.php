<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\Env;

/**
 * An environment variable for `config/wirekit.php`, where a blank value means "not set".
 *
 * `env()` returns an empty string for a variable that is present with nothing after the `=`,
 * and every reader of the configuration then takes that as a value: an integer cast turns it
 * into 0, and a loose boolean reads it as off. A line such as `WIREKIT_DEDUPE_IDS=` in a `.env`
 * file, or one that holds only spaces, says there is no value, so the configuration's default
 * applies, as it does when the line is absent. Any other value is returned exactly as `env()`
 * returns it: `false`, `0`, `null` and `(empty)` keep the meaning Laravel gives them.
 */
final class EnvValue
{
    public static function get(string $key, bool|int|float|string|null $default = null): bool|int|float|string|null
    {
        // The raw string, before `env()` turns `null`, `false` or a quoted value into
        // something else, so that only an empty or all-whitespace value counts as blank.
        $raw = Env::getRepository()->get($key);

        if ($raw === null || trim($raw) === '') {
            return $default;
        }

        return Env::get($key, $default);
    }
}
