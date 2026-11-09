<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The digits of a currency's minor unit as ISO 4217 gives them: two for the euro, none for the yen,
 * three for the Bahraini and the Iraqi dinar, four for the Chilean unidad de fomento.
 *
 * The digits are the standard's, from a table in the package, and not the ones ICU displays. ICU
 * takes those from CLDR, which rounds some currencies to whole units that the standard counts in
 * hundredths or thousandths: the Iraqi dinar has three digits in the standard and none in CLDR. A
 * payment provider hands over an amount in the currency's minor unit as the standard counts it, so
 * the amount is divided by the standard's digits, with intl and without it.
 */
final class CurrencyMinorUnits
{
    /**
     * Each code with its digits, read once per process from resources/catalog/iso4217-minor-units.php.
     *
     * @var array<string, int|null>|null
     */
    private static ?array $table = null;

    /**
     * The digits of the currency's minor unit, or null for a code the standard gives none (a
     * precious metal, the special drawing right, `XXX`) and for a code it does not list.
     */
    public static function digits(string $currency): ?int
    {
        return self::table()[strtoupper(trim($currency))] ?? null;
    }

    /** Whether the standard lists the code at all, with a minor unit or without one. */
    public static function lists(string $currency): bool
    {
        return array_key_exists(strtoupper(trim($currency)), self::table());
    }

    /** @return array<string, int|null> */
    private static function table(): array
    {
        if (self::$table === null) {
            /** @var array<string, int|null> $entries */
            $entries = require \dirname(__DIR__, 2).'/resources/catalog/iso4217-minor-units.php';
            self::$table = $entries;
        }

        return self::$table;
    }
}
