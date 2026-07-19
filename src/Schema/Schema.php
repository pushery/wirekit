<?php

declare(strict_types=1);

namespace Pushery\WireKit\Schema;

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
 * WHAT THESE DO NOT DO: they cannot tell whether the page actually SHOWS what
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
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function offer(
        float|int|string $price,
        string $priceCurrency,
        ?string $availability = null,
        ?string $url = null,
        ?string $priceValidUntil = null,
        array $extra = [],
    ): array {
        return self::node('Offer', [
            // A price is a STRING in schema.org, and it must not carry a
            // currency symbol or thousands separator.
            'price' => (string) $price,
            'priceCurrency' => $priceCurrency,
            'availability' => $availability !== null ? self::availabilityUrl($availability) : null,
            'url' => $url,
            'priceValidUntil' => $priceValidUntil,
        ], $extra);
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
            'ratingValue' => (string) $ratingValue,
            'reviewCount' => $reviewCount,
            'bestRating' => (string) $bestRating,
            'worstRating' => (string) $worstRating,
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
                    'ratingValue' => (string) $ratingValue,
                    'bestRating' => (string) $bestRating,
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
     * ONLY emit this where the questions and answers are actually visible on the
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
     * A breadcrumb trail. Pass a name => url map, in order.
     *
     * @param  array<string, string>  $items
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function breadcrumbList(array $items, array $extra = []): array
    {
        $elements = [];
        $position = 1;

        foreach ($items as $name => $url) {
            $elements[] = self::node('ListItem', [
                'position' => $position++,
                'name' => (string) $name,
                'item' => $url,
            ]);
        }

        return self::node('BreadcrumbList', ['itemListElement' => $elements], $extra);
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
     * Build one node: `@type` first, null properties dropped, `$extra` merged
     * last so a developer can always override or add a property we do not model.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function node(string $type, array $props, array $extra = []): array
    {
        $filtered = array_filter(
            $props,
            static fn (mixed $v): bool => $v !== null && $v !== [] && $v !== '',
        );

        // array_merge (not `+`) so `$extra` genuinely OVERRIDES a modeled
        // property — `+` keeps the left-hand key and would silently ignore the
        // override the docblock promises. `@type` is prepended with `+` after
        // the merge, so it stays first AND cannot be clobbered.
        return ['@type' => $type] + array_merge($filtered, $extra);
    }
}
