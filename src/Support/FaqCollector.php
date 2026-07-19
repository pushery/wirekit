<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Collects the questions a <x-wirekit::faq> actually rendered, so the FAQPage
 * JSON-LD can be derived from them instead of being written a second time by
 * hand.
 *
 * Why this exists at all: Google only shows FAQ rich results for a page that
 * emits FAQPage structured data, and it treats structured data describing
 * content the reader cannot see as a policy violation. Both halves are normally
 * the developer's problem — they write the accordion, then write the JSON-LD
 * again, and the two drift apart on the first copy edit. Here the JSON-LD is
 * built from the same strings that were just rendered into the page, so a
 * question can be reworded, added, or deleted and the schema simply follows. It
 * cannot describe an invisible answer, because it is made out of the visible one.
 *
 * How the timing works: Blade renders a component's children BEFORE the parent
 * component's own view body runs (verified, not assumed). So every faq-item has
 * already pushed itself by the time the surrounding faq drains — which is what
 * makes a plain collector sufficient here, with no compile-time magic.
 */
final class FaqCollector
{
    /**
     * Questions pushed by faq-items that have rendered but not yet been drained
     * by their surrounding faq.
     *
     * @var list<array{question: string, answer: string}>
     */
    private static array $pending = [];

    /**
     * Record one rendered question. Called by faq-item as it renders.
     */
    public static function push(string $question, string $answer): void
    {
        $question = trim($question);
        $answer = trim($answer);

        // A half-empty pair would produce a Question node with no name or no
        // answer — invalid schema, and Schema::faqPage would drop it anyway.
        // Refusing it here keeps the collector honest about what it holds.
        if ($question === '' || $answer === '') {
            return;
        }

        self::$pending[] = ['question' => $question, 'answer' => $answer];
    }

    /**
     * Take everything collected so far and clear the buffer.
     *
     * Draining (rather than reading) is what keeps two FAQs on one page from
     * bleeding into each other: the first one takes its own questions and leaves
     * the buffer empty for the second.
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function drain(): array
    {
        $questions = self::$pending;
        self::$pending = [];

        return $questions;
    }

    /**
     * Discard anything pending without emitting it.
     *
     * Used when a faq is told not to emit schema: its items still rendered and
     * still pushed, so the buffer must be cleared or their questions would
     * surface in the NEXT faq's JSON-LD — schema describing answers that live
     * somewhere else on the page.
     */
    public static function reset(): void
    {
        self::$pending = [];
    }
}
