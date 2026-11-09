<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Normalizer;
use Stringable;

/**
 * Text as a search of the kit compares it: accents folded away and lower-cased, so a reader who
 * types `munchen` finds `München` and `istanbul` finds `İstanbul`.
 *
 * The rule of `foldForSearch()` in the browser, so a query folded there and a row folded here meet:
 * the text is decomposed (NFD), the combining marks after a base letter are dropped, and the rest
 * is lower-cased. A mark stays where it makes another letter rather than an accent, a vowel sign
 * in Devanagari or Thai or the voicing mark on a kana, and a letter that is no base with marks,
 * such as `ß`, stays itself.
 *
 * Not `iconv()` with `//TRANSLIT`: what it writes depends on the platform, and on some it turns
 * `München` into `M"unchen`, a character nobody types. Without the intl extension the text is only
 * lower-cased: an accented row is then found by typing its accent.
 */
final class SearchFold
{
    /** The scripts in which a combining mark makes another letter. */
    private const MARKS_MAKE_LETTERS = '/[\p{Devanagari}\p{Bengali}\p{Gurmukhi}\p{Gujarati}\p{Oriya}\p{Tamil}\p{Telugu}\p{Kannada}\p{Malayalam}\p{Sinhala}\p{Thai}\p{Lao}\p{Tibetan}\p{Myanmar}\p{Khmer}\p{Hiragana}\p{Katakana}]/u';

    public static function text(string|Stringable|null $value): string
    {
        $text = (string) $value;

        if (class_exists(Normalizer::class)) {
            $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);

            if (is_string($decomposed)) {
                $text = (string) preg_replace_callback(
                    '/(\P{M})(\p{M}+)/u',
                    static fn (array $mark): string => preg_match(self::MARKS_MAKE_LETTERS, $mark[1]) === 1 ? $mark[0] : $mark[1],
                    $decomposed,
                );
            }
        }

        return mb_strtolower($text);
    }
}
