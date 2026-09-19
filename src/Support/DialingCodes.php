<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Every country's international dialing code, as a table this package carries itself.
 *
 * The country picker's recipe argues, correctly, that country DATA needs no dependency: the `intl`
 * extension already carries the Unicode CLDR, so it names and sorts every country for free, and a
 * Composer package bundling the same data would be megabytes for nothing.
 *
 * That argument does not extend to phone numbers, and the difference is why this file exists.
 * Measured on 2026-09-18 against PHP 8.5.8 / ICU 77.1, with a control that passed: ICU's
 * `telephoneCodeData` bundle is not reachable through PHP, so `intl` knows no dialing code at all.
 *
 * What it would take to know more than this table does — a national format per country, and a real
 * validity check — is metadata measured in megabytes, and it is the reason a phone field usually
 * arrives with a dependency attached. This table is the part that is small: 245 rows, about 3 KB,
 * enough to show `+49` beside a field and to assemble an E.164 value from it. Anything past that
 * is an application's own choice, documented as a recipe rather than taken here.
 *
 * The rows are GENERATED from the canonical upstream dataset, never typed by hand. A mistyped
 * dialing code is the one defect in this file that no test can catch: there is no oracle for "+268
 * is really Eswatini" short of the dataset itself.
 *
 * Source: `giggsey/libphonenumber-for-php-lite` 9.0.39. That package is NOT a dependency here — it
 * is the generator's input, installed in a scratch directory, run once, and discarded. To
 * regenerate, in a directory OUTSIDE this package:
 *
 *     composer require giggsey/libphonenumber-for-php-lite
 *     php -r 'require "vendor/autoload.php";
 *         $u = \libphonenumber\PhoneNumberUtil::getInstance();
 *         $r = $u->getSupportedRegions(); sort($r);
 *         foreach ($r as $c) { printf("%s => %d,\n", var_export($c, true), $u->getCountryCodeForRegion($c)); }'
 *
 * Keep the rows sorted by region and check a handful of codes against something that is not the
 * generator — a generated expectation agrees with a broken generator.
 */
final class DialingCodes
{
    /**
     * ISO 3166-1 alpha-2 region code to its international dialing code, without the leading plus.
     *
     * Several regions share one code — `US`, `CA` and the Caribbean regions are all 1 — so this
     * maps in one direction only. Going back from a code to a country is a guess, and the
     * component never makes it: the country is state the field holds, not something it infers.
     *
     * @var array<string, int>
     */
    private const CODES = [
        'AC' => 247, 'AD' => 376, 'AE' => 971, 'AF' => 93, 'AG' => 1, 'AI' => 1, 'AL' => 355, 'AM' => 374,
        'AO' => 244, 'AR' => 54, 'AS' => 1, 'AT' => 43, 'AU' => 61, 'AW' => 297, 'AX' => 358, 'AZ' => 994,
        'BA' => 387, 'BB' => 1, 'BD' => 880, 'BE' => 32, 'BF' => 226, 'BG' => 359, 'BH' => 973,
        'BI' => 257, 'BJ' => 229, 'BL' => 590, 'BM' => 1, 'BN' => 673, 'BO' => 591, 'BQ' => 599,
        'BR' => 55, 'BS' => 1, 'BT' => 975, 'BW' => 267, 'BY' => 375, 'BZ' => 501, 'CA' => 1, 'CC' => 61,
        'CD' => 243, 'CF' => 236, 'CG' => 242, 'CH' => 41, 'CI' => 225, 'CK' => 682, 'CL' => 56,
        'CM' => 237, 'CN' => 86, 'CO' => 57, 'CR' => 506, 'CU' => 53, 'CV' => 238, 'CW' => 599, 'CX' => 61,
        'CY' => 357, 'CZ' => 420, 'DE' => 49, 'DJ' => 253, 'DK' => 45, 'DM' => 1, 'DO' => 1, 'DZ' => 213,
        'EC' => 593, 'EE' => 372, 'EG' => 20, 'EH' => 212, 'ER' => 291, 'ES' => 34, 'ET' => 251,
        'FI' => 358, 'FJ' => 679, 'FK' => 500, 'FM' => 691, 'FO' => 298, 'FR' => 33, 'GA' => 241,
        'GB' => 44, 'GD' => 1, 'GE' => 995, 'GF' => 594, 'GG' => 44, 'GH' => 233, 'GI' => 350, 'GL' => 299,
        'GM' => 220, 'GN' => 224, 'GP' => 590, 'GQ' => 240, 'GR' => 30, 'GT' => 502, 'GU' => 1,
        'GW' => 245, 'GY' => 592, 'HK' => 852, 'HN' => 504, 'HR' => 385, 'HT' => 509, 'HU' => 36,
        'ID' => 62, 'IE' => 353, 'IL' => 972, 'IM' => 44, 'IN' => 91, 'IO' => 246, 'IQ' => 964, 'IR' => 98,
        'IS' => 354, 'IT' => 39, 'JE' => 44, 'JM' => 1, 'JO' => 962, 'JP' => 81, 'KE' => 254, 'KG' => 996,
        'KH' => 855, 'KI' => 686, 'KM' => 269, 'KN' => 1, 'KP' => 850, 'KR' => 82, 'KW' => 965, 'KY' => 1,
        'KZ' => 7, 'LA' => 856, 'LB' => 961, 'LC' => 1, 'LI' => 423, 'LK' => 94, 'LR' => 231, 'LS' => 266,
        'LT' => 370, 'LU' => 352, 'LV' => 371, 'LY' => 218, 'MA' => 212, 'MC' => 377, 'MD' => 373,
        'ME' => 382, 'MF' => 590, 'MG' => 261, 'MH' => 692, 'MK' => 389, 'ML' => 223, 'MM' => 95,
        'MN' => 976, 'MO' => 853, 'MP' => 1, 'MQ' => 596, 'MR' => 222, 'MS' => 1, 'MT' => 356, 'MU' => 230,
        'MV' => 960, 'MW' => 265, 'MX' => 52, 'MY' => 60, 'MZ' => 258, 'NA' => 264, 'NC' => 687,
        'NE' => 227, 'NF' => 672, 'NG' => 234, 'NI' => 505, 'NL' => 31, 'NO' => 47, 'NP' => 977,
        'NR' => 674, 'NU' => 683, 'NZ' => 64, 'OM' => 968, 'PA' => 507, 'PE' => 51, 'PF' => 689,
        'PG' => 675, 'PH' => 63, 'PK' => 92, 'PL' => 48, 'PM' => 508, 'PR' => 1, 'PS' => 970, 'PT' => 351,
        'PW' => 680, 'PY' => 595, 'QA' => 974, 'RE' => 262, 'RO' => 40, 'RS' => 381, 'RU' => 7,
        'RW' => 250, 'SA' => 966, 'SB' => 677, 'SC' => 248, 'SD' => 249, 'SE' => 46, 'SG' => 65,
        'SH' => 290, 'SI' => 386, 'SJ' => 47, 'SK' => 421, 'SL' => 232, 'SM' => 378, 'SN' => 221,
        'SO' => 252, 'SR' => 597, 'SS' => 211, 'ST' => 239, 'SV' => 503, 'SX' => 1, 'SY' => 963,
        'SZ' => 268, 'TA' => 290, 'TC' => 1, 'TD' => 235, 'TG' => 228, 'TH' => 66, 'TJ' => 992,
        'TK' => 690, 'TL' => 670, 'TM' => 993, 'TN' => 216, 'TO' => 676, 'TR' => 90, 'TT' => 1,
        'TV' => 688, 'TW' => 886, 'TZ' => 255, 'UA' => 380, 'UG' => 256, 'US' => 1, 'UY' => 598,
        'UZ' => 998, 'VA' => 39, 'VC' => 1, 'VE' => 58, 'VG' => 1, 'VI' => 1, 'VN' => 84, 'VU' => 678,
        'WF' => 681, 'WS' => 685, 'XK' => 383, 'YE' => 967, 'YT' => 262, 'ZA' => 27, 'ZM' => 260,
        'ZW' => 263,
    ];

    /**
     * The national trunk prefix a region puts in front of a local number, where it has one.
     *
     * This is the digit a reader types and E.164 does not carry. A German number written
     * `0151 23456789` is `+4915123456789` once the country code is in front: the `0` is a
     * national-format artifact and disappears. Dropping it is correct for 144 of the 245 regions
     * here — and WRONG for the other 101, which have no trunk prefix at all, so a leading digit
     * there is part of the number.
     *
     * Italy is the example worth remembering, because it looks like the German case and is not:
     * it has NO trunk prefix, so `06…` stays `+3906…` and stripping the zero would delete a real
     * digit from a Rome landline.
     *
     * Only four distinct values occur across every region — `0` (113 times), `1` (26), `8` (4)
     * and `06` (1) — so this is a small table rather than the formatting metadata it looks like
     * the start of. It answers exactly one question, and deliberately not the next one: how a
     * number should be GROUPED for display is per-region data measured in megabytes, and this
     * package does not have it.
     *
     * Generated alongside CODES, from `getNationalPrefix()` per region.
     *
     * @var array<string, string>
     */
    private const TRUNK_PREFIXES = [
        'AE' => '0', 'AF' => '0', 'AG' => '1', 'AI' => '1', 'AL' => '0', 'AM' => '0', 'AR' => '0',
        'AS' => '1', 'AT' => '0', 'AU' => '0', 'AX' => '0', 'AZ' => '0', 'BA' => '0', 'BB' => '1',
        'BD' => '0', 'BE' => '0', 'BG' => '0', 'BL' => '0', 'BM' => '1', 'BO' => '0', 'BR' => '0',
        'BS' => '1', 'BY' => '8', 'CA' => '1', 'CC' => '0', 'CD' => '0', 'CH' => '0', 'CN' => '0',
        'CO' => '0', 'CU' => '0', 'CX' => '0', 'DE' => '0', 'DM' => '1', 'DO' => '1', 'DZ' => '0',
        'EC' => '0', 'EG' => '0', 'EH' => '0', 'ER' => '0', 'ET' => '0', 'FI' => '0', 'FR' => '0',
        'GB' => '0', 'GD' => '1', 'GE' => '0', 'GF' => '0', 'GG' => '0', 'GH' => '0', 'GP' => '0',
        'GU' => '1', 'HR' => '0', 'HU' => '06', 'ID' => '0', 'IE' => '0', 'IL' => '0', 'IM' => '0',
        'IN' => '0', 'IQ' => '0', 'IR' => '0', 'JE' => '0', 'JM' => '1', 'JO' => '0', 'JP' => '0',
        'KE' => '0', 'KG' => '0', 'KH' => '0', 'KI' => '0', 'KN' => '1', 'KP' => '0', 'KR' => '0',
        'KY' => '1', 'KZ' => '8', 'LA' => '0', 'LB' => '0', 'LC' => '1', 'LI' => '0', 'LK' => '0',
        'LR' => '0', 'LT' => '0', 'LY' => '0', 'MA' => '0', 'MC' => '0', 'MD' => '0', 'ME' => '0',
        'MF' => '0', 'MG' => '0', 'MH' => '1', 'MK' => '0', 'MM' => '0', 'MN' => '0', 'MP' => '1',
        'MQ' => '0', 'MS' => '1', 'MW' => '0', 'MY' => '0', 'NA' => '0', 'NG' => '0', 'NL' => '0',
        'NP' => '0', 'NZ' => '0', 'PE' => '0', 'PH' => '0', 'PK' => '0', 'PM' => '0', 'PR' => '1',
        'PS' => '0', 'PY' => '0', 'RE' => '0', 'RO' => '0', 'RS' => '0', 'RU' => '8', 'RW' => '0',
        'SA' => '0', 'SD' => '0', 'SE' => '0', 'SI' => '0', 'SK' => '0', 'SL' => '0', 'SO' => '0',
        'SS' => '0', 'SX' => '1', 'SY' => '0', 'TC' => '1', 'TH' => '0', 'TM' => '8', 'TR' => '0',
        'TT' => '1', 'TW' => '0', 'TZ' => '0', 'UA' => '0', 'UG' => '0', 'US' => '1', 'UY' => '0',
        'VC' => '1', 'VE' => '0', 'VG' => '1', 'VI' => '1', 'VN' => '0', 'XK' => '0', 'YE' => '0',
        'YT' => '0', 'ZA' => '0', 'ZM' => '0', 'ZW' => '0',
    ];

    /**
     * The dialing code for a region, or null when the table does not carry it.
     *
     * Case does not matter, so a form that stores `de` and one that stores `DE` both resolve. A
     * region the table does not name returns null rather than a guess — the component then draws
     * no code at all, which is honest, instead of a plus sign in front of nothing.
     */
    public static function for(string $country): ?int
    {
        return self::CODES[strtoupper(trim($country))] ?? null;
    }

    /**
     * Whether the table carries a region.
     *
     * Separate from `for()` on purpose: a caller asking "is this a country I can offer?" wants a
     * boolean, and writing `!== null` at every call site is where a `0`-versus-null slip starts.
     * No dialing code is 0, but the next reader has to prove that to themselves, and this method
     * means they do not.
     */
    public static function has(string $country): bool
    {
        return isset(self::CODES[strtoupper(trim($country))]);
    }

    /**
     * Every region the table carries, sorted by its code.
     *
     * The order is the table's, which is alphabetical by region code — NOT the order a picker
     * should show. A picker sorts by the country's NAME in the reader's language, with a
     * collator, because a byte-wise sort puts every accented name after Z. The country picker
     * recipe does exactly that, and this list is its input rather than its output.
     *
     * @return list<string>
     */
    public static function countries(): array
    {
        return array_keys(self::CODES);
    }

    /**
     * The whole table, region code to dialing code.
     *
     * @return array<string, int>
     */
    public static function all(): array
    {
        return self::CODES;
    }

    /**
     * The region's national trunk prefix, or null where it has none.
     *
     * Null and "no prefix" are the same answer here and mean something specific: a leading digit
     * in a number from this region is PART of the number. That is not the same as "we do not
     * know", which this table never says — every supported region is either in it or deliberately
     * absent.
     */
    public static function trunkPrefix(string $country): ?string
    {
        return self::TRUNK_PREFIXES[strtoupper(trim($country))] ?? null;
    }

    /**
     * Assemble an E.164 value from a region and the digits somebody typed.
     *
     * This is the canonical version of the rule. The Alpine factory mirrors it so the field can
     * answer while the reader types, and a browser check holds the two against each other — two
     * copies of one rule drift, and the only defense is a test that runs both.
     *
     * What it does, and the list is deliberately short:
     *
     *   1. Everything that is not a digit is dropped. Readers type spaces, slashes, dashes and
     *      brackets, and none of them survive into E.164.
     *   2. A leading trunk prefix is removed where the region HAS one — see TRUNK_PREFIXES for
     *      why that is a lookup rather than "strip the zero".
     *   3. The dialing code goes in front, behind a plus.
     *
     * What it does NOT do is decide whether the result is a real, reachable number. It cannot:
     * that needs per-region length and range data this package does not carry. A field that
     * silently calls a valid number invalid is worse than one that never claims to know, so this
     * returns a well-formed string and leaves the judgment to the application.
     *
     * Returns null when the region is unknown or nothing is left after the digits are taken —
     * an empty field binds nothing rather than a bare country code, which would look like an
     * answer and be a fragment.
     */
    public static function toE164(string $country, string $national): ?string
    {
        $code = self::for($country);

        if ($code === null) {
            return null;
        }

        $digits = preg_replace('/\\D+/', '', $national) ?? '';

        if ($digits === '') {
            return null;
        }

        $trunk = self::trunkPrefix($country);

        if ($trunk !== null && str_starts_with($digits, $trunk)) {
            $digits = substr($digits, strlen($trunk));
        }

        return $digits === '' ? null : '+'.$code.$digits;
    }
}
