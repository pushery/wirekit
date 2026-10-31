<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\Str;
use Illuminate\Translation\Translator;

/**
 * `trans_choice()` for the kit's own sentences, with the form chosen by the language of the line.
 *
 * Laravel picks a plural form by looking the locale up in a table of rules whose entries are all
 * written with an underscore. `de_AT` and `pt_BR` find their rule; `de-AT` and `pt-BR` find
 * nothing and take the first form for every number ("7 Stern"). The kit reads both spellings as
 * one locale (see BaseLocaleJsonLoader), so the form is chosen by the language the locale names:
 * in that table every regional entry shares the rule of its language, which makes the language
 * answer exactly as the regional entry would wherever one exists.
 *
 * A locale the package ships no catalog for reads the package's English catalog underneath, and an
 * English line has the forms of English. Chosen by Polish or Arabic it would take a form the line
 * does not have ("5 item", "1 items"), so in such a language the package's own English line is
 * chosen by English. A line of the application's, in its own language, keeps its language's rule.
 *
 * The line itself is read the way `trans_choice()` reads it, in the application's locale and
 * otherwise in the fallback locale, so an application's `lang/de-AT.json` keeps answering for the
 * keys it defines.
 */
final class Plural
{
    /**
     * The package's English catalog, read once.
     *
     * @var array<string, string>|null
     */
    private static ?array $english = null;

    /**
     * Whether the package ships a catalog for a language, by language.
     *
     * @var array<string, bool>
     */
    private static array $ships = [];

    /**
     * The sentence for a number, in the form the number asks for.
     *
     * The number is taken as `trans_choice()` takes it, whatever a prop holds: a countable is
     * counted, and a numeric string is the number it holds. A value that names no number, a text
     * or nothing, counts as none, as a numeric prop that holds no number reads as its default.
     *
     * @param  array<string, string|int|float|null>  $replace
     */
    public static function choice(string $key, mixed $number, array $replace = []): string
    {
        $number = match (true) {
            is_countable($number) => count($number),
            is_int($number), is_float($number) => $number,
            is_numeric($number) => $number + 0,
            default => 0,
        };

        // A number that is not finite counts as none as well: no form holds it, and PHP 8.5
        // warns when one is written into the sentence.
        if (is_float($number) && ! is_finite($number)) {
            $number = 0;
        }

        $translator = app('translator');

        if (! $translator instanceof Translator) {
            return (string) trans_choice($key, $number, $replace);
        }

        $resolved = self::resolve($translator, $key);

        if ($resolved === null) {
            return (string) trans_choice($key, $number, $replace);
        }

        [$line, $language] = $resolved;

        $replace['count'] ??= $number;

        // The form is chosen before the values go in, as `trans_choice()` does: a value may
        // hold a `|` of its own, a title for instance, which must not split the line.
        return self::replace((string) $translator->getSelector()->choose($line, $number, $language), $replace);
    }

    /**
     * The language whose rule chooses among the forms of a key's line under the current locale:
     * English for the package's English line read in a language it ships no catalog for,
     * otherwise the language the line is read in.
     */
    public static function rule(string $key): string
    {
        $translator = app('translator');
        $resolved = $translator instanceof Translator ? self::resolve($translator, $key) : null;

        return $resolved[1] ?? self::language(app()->getLocale());
    }

    /**
     * The language a locale names, in either spelling: `de` for `de-AT`, `de_AT` and `de`.
     */
    public static function language(string $locale): string
    {
        return strtolower(explode('_', str_replace('-', '_', $locale))[0]);
    }

    /**
     * The line of a key as `trans_choice()` reads it, and the language that chooses its form.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function resolve(Translator $translator, string $key): ?array
    {
        $locale = $translator->getLocale();
        $locale = $translator->hasForLocale($key, $locale) ? $locale : $translator->getFallback();
        $line = $translator->get($key, [], $locale);

        if (! is_string($line)) {
            return null;
        }

        $language = self::language($locale);

        // Only a language the package ships no catalog for reads the English line underneath;
        // in one it ships, a line that reads like English is a translation that happens to.
        if (! self::ships($language) && $line === self::englishLine($key)) {
            return [$line, 'en'];
        }

        return [$line, $language];
    }

    /**
     * Whether the package ships a catalog for a language.
     *
     * A locale can come from a request, so only a subtag of letters reaches the filesystem.
     */
    private static function ships(string $language): bool
    {
        return self::$ships[$language] ??= preg_match('/^[a-z]{2,8}$/', $language) === 1
            && is_file(dirname(__DIR__, 2).'/lang/'.$language.'.json');
    }

    /** The package's own English line for a key, or null where the package ships none. */
    private static function englishLine(string $key): ?string
    {
        if (self::$english === null) {
            $path = dirname(__DIR__, 2).'/lang/en.json';
            $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            self::$english = is_array($decoded) ? array_filter($decoded, is_string(...)) : [];
        }

        return self::$english[$key] ?? null;
    }

    /**
     * The placeholders of a chosen form, filled as the translator fills them: `:count` as given,
     * `:Count` with a capital first letter and `:COUNT` in capitals.
     *
     * @param  array<string, string|int|float|null>  $replace
     */
    private static function replace(string $line, array $replace): string
    {
        $pairs = [];

        foreach ($replace as $name => $value) {
            $value = NumericProp::text($value);
            $pairs[':'.Str::ucfirst($name)] = Str::ucfirst($value);
            $pairs[':'.Str::upper($name)] = Str::upper($value);
            $pairs[':'.$name] = $value;
        }

        return strtr($line, $pairs);
    }
}
