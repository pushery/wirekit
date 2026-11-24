<?php

declare(strict_types=1);

namespace Pushery\WireKit\Schema;

use Pushery\WireKit\Support\SafeUrl;
use Pushery\WireKit\Support\StrictnessGate;

/**
 * Typed schema.org builders for `<x-wirekit::structured-data>`.
 *
 * Every method returns a plain array **without** `@context` — the component adds
 * it once, at the top level. That is what makes nesting natural: an Offer inside
 * a Product is just the Offer fragment, with no repeated context to strip.
 *
 * ```php
 * <x-wirekit::structured-data :data="Schema::product(
 *     name: $product->name,
 *     offers: Schema::offer(price: $product->price, priceCurrency: 'EUR'),
 * )" />
 * ```
 *
 * What these do not do: they cannot tell whether the page actually shows what
 * the markup claims. Structured data that describes content the user cannot see
 * is a Google policy violation and gets the rich result withdrawn — so emit a
 * FAQPage only where the questions are really rendered, an AggregateRating only
 * where the rating really is. See the docs page.
 */
final class Schema
{
    /**
     * A product. Pass `offers` / `aggregateRating` / `review` as the fragments
     * returned by the matching builders.
     *
     * @param  array<string, mixed>|null  $offers
     * @param  array<string, mixed>|null  $aggregateRating
     * @param  list<array<string, mixed>>|null  $review
     * @param  array<string, mixed>  $extra  Any additional schema.org properties.
     * @param  string|array<string, mixed>|null  $image
     * @return array<string, mixed>
     */
    public static function product(
        string $name,
        ?string $description = null,
        string|array|null $image = null,
        ?string $sku = null,
        ?string $brand = null,
        ?array $offers = null,
        ?array $aggregateRating = null,
        ?array $review = null,
        array $extra = [],
    ): array {
        return self::node('Product', [
            'name' => $name,
            'description' => $description,
            'image' => $image,
            'sku' => $sku,
            'brand' => $brand !== null ? self::node('Brand', ['name' => $brand]) : null,
            'offers' => $offers,
            'aggregateRating' => $aggregateRating,
            'review' => $review,
        ], $extra);
    }

    /**
     * An offer (price + currency). `availability` takes the bare token —
     * "InStock", "OutOfStock", "PreOrder" — and is expanded to the full
     * schema.org URL, because that is the shape Google expects and the shape
     * everyone gets wrong.
     *
     * `priceSpecification` takes the specifications `strikethroughPrice()` and
     * `unitPrice()` build. Google reads a unit price only from a specification that
     * holds the price itself, and ignores the specification while the offer names a
     * price of its own, so where one of them holds the price (it has no `priceType`)
     * the offer leaves out `price` and `priceCurrency`. Otherwise the price stays on
     * the offer, and a struck price stands beside it.
     *
     * @param  list<array<string, mixed>>  $priceSpecification
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function offer(
        float|int|string|null $price = null,
        ?string $priceCurrency = null,
        ?string $availability = null,
        ?string $url = null,
        ?string $priceValidUntil = null,
        array $extra = [],
        array $priceSpecification = [],
    ): array {
        $specificationHoldsThePrice = array_any(
            $priceSpecification,
            static fn (array $specification): bool => ! isset($specification['priceType']) && isset($specification['price']),
        );

        return self::node('Offer', [
            // schema.org takes a price as Number or as Text. It is written as text, so a price
            // passed as a string such as `9.90` keeps its digits exactly, and it carries no
            // currency symbol and no thousands separator, with a full stop for the decimal point.
            'price' => $price !== null && ! $specificationHoldsThePrice ? self::reportedNumber($price, 'offer', 'price') : null,
            'priceCurrency' => $specificationHoldsThePrice ? null : $priceCurrency,
            'priceSpecification' => array_values($priceSpecification),
            'availability' => $availability !== null ? self::availabilityUrl($availability) : null,
            'url' => $url,
            'priceValidUntil' => $priceValidUntil,
        ], $extra);
    }

    /**
     * A struck price: the earlier price a sale is measured against, as a
     * `UnitPriceSpecification` with the `StrikethroughPrice` type. Goes into
     * `offer(priceSpecification: [...])`.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException for a price that is no number of zero or more with a full
     *                                   stop for the decimal point, such as `12,90`
     */
    public static function strikethroughPrice(
        float|int|string $price,
        string $priceCurrency,
        array $extra = [],
    ): array {
        return self::node('UnitPriceSpecification', [
            'priceType' => 'https://schema.org/StrikethroughPrice',
            'price' => self::priceText($price, 'strikethroughPrice'),
            'priceCurrency' => $priceCurrency,
        ], $extra);
    }

    /**
     * The price with the unit price it works out to: the price of the variant, the
     * quantity it holds (`content` in `contentUnit`), and the quantity the unit price
     * refers to (`reference`, in `referenceUnit`, which defaults to `contentUnit`).
     * Goes into `offer(priceSpecification: [...])`, where it holds the offer's price.
     *
     * A unit is `mg`, `g`, `kg`, `ml`, `cl`, `l`, `cm` or `m`, or its UN/CEFACT code,
     * and is written as that code, the form Google reads a unit price in. A quantity
     * with more than two decimals moves to the next smaller unit until it has two or
     * fewer, as Merchant Center asks: `0.125` kg is written as `125` g.
     *
     * The price is a number of zero or more and each quantity a number above zero, each
     * written with a full stop for the decimal point, as schema.org reads them; a value
     * in another form, `1,5` among them, is refused rather than read as a number it is not.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException for a unit outside that list, and for a price or a quantity in another form
     */
    public static function unitPrice(
        float|int|string $price,
        string $priceCurrency,
        float|int|string $content,
        string $contentUnit,
        float|int|string $reference = 1,
        ?string $referenceUnit = null,
        array $extra = [],
    ): array {
        [$contentValue, $contentCode] = self::unitQuantity($content, $contentUnit);
        [$referenceValue, $referenceCode] = self::unitQuantity($reference, $referenceUnit ?? $contentUnit);

        return self::node('UnitPriceSpecification', [
            'price' => self::priceText($price, 'unitPrice'),
            'priceCurrency' => $priceCurrency,
            'referenceQuantity' => self::node('QuantitativeValue', [
                'value' => $contentValue,
                'unitCode' => $contentCode,
                'valueReference' => self::node('QuantitativeValue', [
                    'value' => $referenceValue,
                    'unitCode' => $referenceCode,
                ]),
            ]),
        ], $extra);
    }

    /**
     * A number of a builder that is older than the checks of `priceText()`, as text, as it came.
     * A value that is no number with a full stop for the decimal point, such as `9,90`, goes
     * through the strictness gate: it throws in a test or on the console, and in a request it is
     * logged and kept as it was written, so a page in production renders what it rendered.
     */
    private static function reportedNumber(float|int|string $value, string $builder, string $key): string
    {
        $written = (string) $value;

        if (preg_match('/^-?\d+(?:\.\d+)?$/', trim($written)) === 1) {
            return $written;
        }

        return StrictnessGate::reject("Schema::{$builder}()", $key, $written, 'a number with a full stop for the decimal point, such as 9.90', $written);
    }

    /**
     * A price as schema.org and Google read it, as text: a number of zero or more with a full
     * stop for the decimal point and nothing else.
     *
     * @throws \InvalidArgumentException for a price in another form
     */
    private static function priceText(float|int|string $price, string $builder): string
    {
        $written = is_string($price) ? trim($price) : (string) $price;

        if (preg_match('/^\d+(?:\.\d+)?$/', $written) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'Schema::%s() takes a price as a number with a full stop for the decimal point; "%s" is not one.',
                $builder,
                $written,
            ));
        }

        return $written;
    }

    /**
     * A quantity as the value and the UN/CEFACT code a unit price is written in, moved to
     * the next smaller unit while it has more than two decimals.
     *
     * @return array{0: string, 1: string}
     */
    private static function unitQuantity(float|int|string $value, string $unit): array
    {
        $codes = ['mg' => 'MGM', 'g' => 'GRM', 'kg' => 'KGM', 'ml' => 'MLT', 'cl' => 'CLT', 'l' => 'LTR', 'cm' => 'CMT', 'm' => 'MTR'];
        // Each code's next smaller unit, and how many of it one of the larger makes.
        $smaller = ['KGM' => ['GRM', 1000], 'GRM' => ['MGM', 1000], 'LTR' => ['CLT', 100], 'CLT' => ['MLT', 10], 'MTR' => ['CMT', 100]];

        $code = $codes[strtolower(trim($unit))] ?? strtoupper(trim($unit));

        if (! in_array($code, $codes, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Schema::unitPrice() takes a unit of %s or its UN/CEFACT code; "%s" is neither.',
                implode(', ', array_keys($codes)),
                $unit,
            ));
        }

        // A quantity as schema.org reads a number: digits and a full stop, above zero. A cast
        // would read `1,5` as 1 and a word as 0, and the unit price would be wrong without a word.
        $written = is_string($value) ? trim($value) : (string) $value;

        if (preg_match('/^\d+(?:\.\d+)?$/', $written) !== 1 || (float) $written <= 0.0) {
            throw new \InvalidArgumentException(sprintf(
                'Schema::unitPrice() takes a quantity as a number above zero with a full stop for the decimal point; "%s" is not one.',
                $written,
            ));
        }

        $number = round((float) $written, 6);

        while (isset($smaller[$code]) && abs(round($number, 2) - $number) > 1e-9) {
            [$code, $factor] = $smaller[$code];
            $number = round($number * $factor, 6);
        }

        // Written as text, as a price is: no exponent, no trailing zeros, a full stop for the point.
        $text = rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');

        return [$text === '-0' ? '0' : $text, $code];
    }

    /**
     * An aggregate rating. `reviewCount` is what Google requires; a rating with
     * no count is not eligible for a rich result.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function aggregateRating(
        float|int|string $ratingValue,
        int $reviewCount,
        float|int|string $bestRating = 5,
        float|int|string $worstRating = 1,
        array $extra = [],
    ): array {
        return self::node('AggregateRating', [
            'ratingValue' => self::reportedNumber($ratingValue, 'aggregateRating', 'ratingValue'),
            'reviewCount' => $reviewCount,
            'bestRating' => self::reportedNumber($bestRating, 'aggregateRating', 'bestRating'),
            'worstRating' => self::reportedNumber($worstRating, 'aggregateRating', 'worstRating'),
        ], $extra);
    }

    /**
     * A single review.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function review(
        string $author,
        float|int|string|null $ratingValue = null,
        ?string $body = null,
        ?string $datePublished = null,
        float|int|string $bestRating = 5,
        array $extra = [],
    ): array {
        return self::node('Review', [
            'author' => self::node('Person', ['name' => $author]),
            'reviewRating' => $ratingValue !== null
                ? self::node('Rating', [
                    'ratingValue' => self::reportedNumber($ratingValue, 'review', 'ratingValue'),
                    'bestRating' => self::reportedNumber($bestRating, 'review', 'bestRating'),
                ])
                : null,
            'reviewBody' => $body,
            'datePublished' => $datePublished,
        ], $extra);
    }

    /**
     * An FAQ page. Accepts either a question => answer map or a list of
     * ['question' => …, 'answer' => …] rows.
     *
     * Only emit this where the questions and answers are actually visible on the
     * page — a FAQPage describing content the user cannot see is a policy
     * violation.
     *
     * @param  array<string, string>|list<array{question: string, answer: string}>  $questions
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function faqPage(array $questions, array $extra = []): array
    {
        $entities = [];

        foreach ($questions as $key => $value) {
            [$question, $answer] = is_array($value)
                ? [$value['question'] ?? '', $value['answer'] ?? '']
                : [(string) $key, (string) $value];

            if ($question === '' || $answer === '') {
                continue;
            }

            $entities[] = self::node('Question', [
                'name' => $question,
                'acceptedAnswer' => self::node('Answer', ['text' => $answer]),
            ]);
        }

        return self::node('FAQPage', ['mainEntity' => $entities], $extra);
    }

    /**
     * An article / blog post.
     *
     * @param  array<string, mixed>  $extra
     * @param  string|array<string, mixed>|null  $image
     * @return array<string, mixed>
     */
    public static function article(
        string $headline,
        ?string $author = null,
        ?string $datePublished = null,
        ?string $dateModified = null,
        string|array|null $image = null,
        ?string $description = null,
        array $extra = [],
    ): array {
        return self::node('Article', [
            'headline' => $headline,
            'author' => $author !== null ? self::node('Person', ['name' => $author]) : null,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'image' => $image,
            'description' => $description,
        ], $extra);
    }

    /**
     * A breadcrumb trail, in order. Accepts either a name => url map or a list of
     * ['name' => …, 'url' => …] steps; a step with no page of its own takes `null`.
     *
     * Two steps with the same name need the list: a map keeps each name once, at the place of
     * the first and with the address of the last, so a category and a product that share a name
     * would leave the trail one step short.
     *
     * @param  array<string, string|null>|list<array{name: string, url?: string|null}>  $items
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function breadcrumbList(array $items, array $extra = []): array
    {
        $steps = [];

        foreach ($items as $key => $value) {
            $steps[] = is_array($value)
                ? ['name' => $value['name'] ?? '', 'url' => $value['url'] ?? null]
                : ['name' => (string) $key, 'url' => $value];
        }

        return self::node('BreadcrumbList', ['itemListElement' => self::breadcrumbItems($steps)], $extra);
    }

    /**
     * The `ListItem`s of a breadcrumb trail, built the way a search engine accepts them.
     *
     * Every entry but the last needs an `item` URL, and one entry without it makes Google
     * discard the whole list — the result page then shows a bare URL instead of the trail. So
     * a step with no page of its own (a navigation group) stays in the visible trail and is
     * left out here, and the positions are counted over what remains. Consecutive steps that
     * point at the same URL are one step to a crawler; the first name wins.
     *
     * The last step is the page the reader is on and may go without a URL.
     *
     * One rule for every producer: the breadcrumb component and `breadcrumbList()` above both
     * build their trail here, so the two cannot answer the question differently. That includes
     * which address is a page at all. A URL is read through the rule the component links by:
     * `http`, `https`, `mailto`, `tel` or a relative address, and a relative one goes into the
     * JSON-LD made absolute. Any other scheme, `javascript:` among them, is a step without a page.
     *
     * Internal: public only so `breadcrumb.blade.php` can reach it from another
     * namespace, which is a PHP visibility necessity rather than an API promise. The
     * entry point a developer builds a trail with is `breadcrumbList()` above, and it
     * is the one the docs name.
     *
     * @internal
     *
     * @param  list<array{name: string, url: string|null}>  $steps
     * @return list<array<string, mixed>>
     */
    public static function breadcrumbItems(array $steps): array
    {
        $elements = [];
        $previousUrl = null;
        $last = array_key_last($steps);

        foreach ($steps as $index => $step) {
            $url = SafeUrl::href($step['url']);
            // A crawler reads `item` without the page around it, and the only form Google's
            // breadcrumb documentation shows is an absolute URL: a relative address is resolved
            // against the application's URL. `url()` leaves an absolute `http(s)` address, a
            // `mailto:`, a `tel:` and a fragment as they are.
            $url = $url === '' ? null : url($url);

            if ($url === null && $index !== $last) {
                continue;
            }

            if ($url !== null && $url === $previousUrl) {
                continue;
            }

            $elements[] = self::node('ListItem', [
                'position' => count($elements) + 1,
                'name' => $step['name'],
                'item' => $url,
            ]);
            $previousUrl = $url;
        }

        return $elements;
    }

    /**
     * The site itself. Pair it with `organization()` inside a `graph()` and give
     * each an `@id` so they can reference one another.
     *
     * @param  array<string, mixed>|null  $publisher  An `organization()` node, or `['@id' => '…#org']` to point at one already in the graph.
     * @param  list<string>|null  $sameAs
     * @param  array<string, mixed>|null  $potentialAction  An action node, built with `node()`.
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function webSite(
        string $name,
        string $url,
        ?string $id = null,
        ?string $description = null,
        ?string $inLanguage = null,
        ?array $publisher = null,
        ?array $sameAs = null,
        ?array $potentialAction = null,
        array $extra = [],
    ): array {
        return self::node('WebSite', [
            '@id' => $id,
            'name' => $name,
            'url' => $url,
            'description' => $description,
            'inLanguage' => $inLanguage,
            'publisher' => $publisher,
            'sameAs' => $sameAs,
            'potentialAction' => $potentialAction,
        ], $extra);
    }

    /**
     * The organization behind the site. `logo` takes a URL or a prebuilt
     * ImageObject; `sameAs` is the list of profile URLs that ties the entity to
     * its presence elsewhere, which is what search engines reconcile against.
     *
     * @param  list<string>|null  $sameAs
     * @param  array<string, mixed>  $extra
     * @param  string|array<string, mixed>|null  $logo
     * @return array<string, mixed>
     */
    public static function organization(
        string $name,
        ?string $url = null,
        ?string $id = null,
        string|array|null $logo = null,
        ?string $description = null,
        ?string $email = null,
        ?array $sameAs = null,
        array $extra = [],
    ): array {
        return self::node('Organization', [
            '@id' => $id,
            'name' => $name,
            'url' => $url,
            'logo' => $logo,
            'description' => $description,
            'email' => $email,
            'sameAs' => $sameAs,
        ], $extra);
    }

    /**
     * A piece of software — the type a library, app or SaaS product describes
     * itself with. `offers` takes an `offer()` fragment (use price `0` for free),
     * and `isAccessibleForFree` is what marks an open-source or free product.
     *
     * @param  array<string, mixed>|null  $offers
     * @param  array<string, mixed>|null  $aggregateRating
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function softwareApplication(
        string $name,
        ?string $applicationCategory = null,
        ?string $operatingSystem = null,
        ?string $url = null,
        ?string $id = null,
        ?string $description = null,
        ?string $softwareVersion = null,
        ?bool $isAccessibleForFree = null,
        ?array $offers = null,
        ?array $aggregateRating = null,
        array $extra = [],
    ): array {
        return self::node('SoftwareApplication', [
            '@id' => $id,
            'name' => $name,
            'applicationCategory' => $applicationCategory,
            'operatingSystem' => $operatingSystem,
            'url' => $url,
            'description' => $description,
            'softwareVersion' => $softwareVersion,
            // Explicit false is meaningful here ("this costs money"), so it must
            // survive the null-pruning that drops empty values.
            'isAccessibleForFree' => $isAccessibleForFree,
            'offers' => $offers,
            'aggregateRating' => $aggregateRating,
        ], $extra);
    }

    /**
     * Combine several nodes into one `@graph`.
     *
     * This is how a page describes more than one thing at once — the site, the
     * organization behind it, the product it offers — as one connected document
     * instead of a pile of separate scripts. Give the nodes `@id`s and they can
     * reference each other by it.
     *
     * `@context` is deliberately absent: `<x-wirekit::structured-data>` stamps it
     * once at the top level, on a `@graph` payload exactly as on a single node.
     *
     * ```php
     * Schema::graph([
     *     Schema::webSite(name: 'Acme', url: 'https://acme.test', id: 'https://acme.test/#site',
     *         publisher: ['@id' => 'https://acme.test/#org']),
     *     Schema::organization(name: 'Acme Inc', url: 'https://acme.test', id: 'https://acme.test/#org'),
     * ])
     * ```
     *
     * @param  list<array<string, mixed>|null>  $nodes  a node built only on a condition may be `null`, and is left out
     * @return array<string, mixed>
     */
    public static function graph(array $nodes): array
    {
        // Drop empties so a conditionally-built graph (`$showProduct ? … : null`)
        // does not emit a null entry that would make the whole document invalid. The
        // entries are whatever the caller built, null included, which is why the filter
        // takes `mixed`: this is where the non-nodes are sorted out.
        return ['@graph' => array_values(array_filter(
            $nodes,
            static fn (mixed $node): bool => is_array($node) && $node !== [],
        ))];
    }

    /**
     * Expand a bare availability token to its schema.org URL. Passing an
     * already-expanded URL is left untouched, so both spellings work.
     */
    private static function availabilityUrl(string $availability): string
    {
        if (str_starts_with($availability, 'http')) {
            return $availability;
        }

        return 'https://schema.org/'.ltrim($availability, '/');
    }

    /**
     * Build one node of any schema.org type — the escape hatch.
     *
     * The typed builders above cover the types WireKit models. This is how you
     * reach every other one without waiting for us to add it: same null-pruning
     * and same `@type`-first shape, no modeling required.
     *
     * ```php
     * Schema::node('Event', [
     *     'name' => 'WireKit Conf',
     *     'startDate' => '2026-09-01T09:00:00+02:00',
     *     'location' => Schema::node('Place', ['name' => 'Berlin']),
     * ])
     * ```
     *
     * `@type` is a parameter rather than a property so it can never be shadowed:
     * it is prepended after the merge, which means an `@type` inside `$props` or
     * `$extra` would be silently ignored.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function node(string $type, array $props, array $extra = []): array
    {
        // A schema property holds any value the vocabulary allows (text, a number, a
        // nested node, a list), so the filter that drops the empty ones takes `mixed`.
        $filtered = array_filter(
            $props,
            static fn (mixed $v): bool => $v !== null && $v !== [] && $v !== '',
        );

        // array_merge (not `+`) so `$extra` genuinely overrides a modeled
        // property — `+` keeps the left-hand key and would silently ignore the
        // override the docblock promises. `@type` is prepended with `+` after
        // the merge, so it stays first and cannot be clobbered.
        return ['@type' => $type] + array_merge($filtered, $extra);
    }
}
