<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Pushery\WireKit\ComponentRegistry;
use Throwable;

/**
 * The default a WireKit component declares for a prop, read where a value off the prop's list needs
 * a fallback.
 *
 * A value a prop does not take renders as the prop left out would: with its default, wherever the
 * list of allowed values holds that default. The default is read from the component's `@props`: a
 * quoted text, a whole number, or `config('key', …)` around one of them, which is read with the
 * application's configuration, so a default an application sets in `config/wirekit.php` is the
 * fallback as well. Anything else, a default of `null` or one the view builds, gives no answer,
 * and the strictness gate then takes the first allowed value.
 *
 * Read on the error path only, once per component and process. A component the package does not
 * ship has no view to read and gives no answer.
 *
 * @internal Called from WireKit::validateProp(); not a developer-facing API.
 */
final class PropDefault
{
    /**
     * The defaults read so far, by component and prop: the configuration key, if any, and the
     * literal text, or null where the default is not one this reads.
     *
     * @var array<string, array<string, array{0: ?string, 1: string}|null>>
     */
    private static array $declared = [];

    /**
     * The prop's default as the text it renders with, where the allowed list holds it; null
     * otherwise.
     *
     * @param  list<string|int>  $allowed
     */
    public static function within(string $component, string $prop, array $allowed): ?string
    {
        $allowed = array_map(strval(...), $allowed);

        foreach (self::candidates($component, $prop) as $candidate) {
            if (in_array($candidate, $allowed, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * What the prop's default can be, in the order it is asked: the value the application
     * configures where the declaration names a configuration key, then the literal of the
     * declaration. A configured value that is blank or no text counts as unset.
     *
     * @return list<string>
     */
    private static function candidates(string $component, string $prop): array
    {
        self::$declared[$component] ??= self::read($component);
        $entry = self::$declared[$component][$prop] ?? null;

        if ($entry === null) {
            return [];
        }

        [$key, $literal] = $entry;
        $configured = $key === null ? null : config($key);

        return (is_string($configured) && trim($configured) !== '') || is_int($configured)
            ? [(string) $configured, $literal]
            : [$literal];
    }

    /**
     * Every prop of the component with the default this reads, or an empty list where the
     * component has no view to read.
     *
     * @return array<string, array{0: ?string, 1: string}|null>
     */
    private static function read(string $component): array
    {
        try {
            $props = ComponentRegistry::extractProps($component);
        } catch (Throwable) {
            return [];
        }

        $defaults = [];

        foreach ($props as $prop) {
            $defaults[$prop['name']] = self::parse(trim((string) $prop['default_normalized']));
        }

        return $defaults;
    }

    /**
     * A quoted text or a whole number, bare or as the fallback of a `config()` call.
     *
     * @return array{0: ?string, 1: string}|null
     */
    private static function parse(string $expression): ?array
    {
        $key = null;

        if (preg_match("/^config\\(\\s*'([^']+)'\\s*,\\s*(.+)\\)$/s", $expression, $match) === 1) {
            $key = $match[1];
            $expression = trim($match[2]);
        }

        if (preg_match("/^'([^'\\\\]*)'$/", $expression, $match) === 1 || preg_match('/^(-?\d+)$/', $expression, $match) === 1) {
            return [$key, $match[1]];
        }

        return null;
    }
}
