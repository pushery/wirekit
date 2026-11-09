<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Three house conventions a template can be checked against without rendering it.
 *
 *   - A button is filled. The rank of an action comes from its `intent`, so an outlined button
 *     beside a filled one is a second style rather than a lower rank.
 *   - The fields of a settings page stand in cards, each card one part of the settings.
 *   - A date or a time a reader sees does not break, so a narrow column moves the whole value to
 *     the next line instead of leaving the hour under the day.
 *
 * They are hints, not errors: every pattern here is valid markup, and the page renders either
 * way. `wirekit:doctor:props` reports them, and fails on them only when asked to.
 *
 * Which element stands inside which is read from the tags in order: an opening tag that is
 * neither void nor self-closing opens a scope, the closing tag of the same name ends it, and
 * whatever stands between them has the open tags as its ancestors. Blade comments are blanked
 * first, so markup written inside one is not read.
 */
final class ConventionHints
{
    /** Elements that hold no content, so they never open a scope. */
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

    /** The form fields whose place on a settings page is a card. */
    private const FIELDS = [
        'x-wirekit::input', 'x-wirekit::select', 'x-wirekit::textarea', 'x-wirekit::toggle',
        'x-wirekit::checkbox', 'x-wirekit::radio-group', 'x-wirekit::password-input',
        'x-wirekit::combobox', 'x-wirekit::number-input', 'x-wirekit::date-picker',
        'x-wirekit::time-picker', 'x-wirekit::datetime-picker', 'x-wirekit::field',
    ];

    /** The calls that turn a date into text for a reader. */
    private const DATE_CALL = '/->\s*(?:format|isoFormat|translatedFormat|diffForHumans|toDateString|toTimeString|toDateTimeString|toFormattedDateString|toDayDateTimeString)\s*\(/';

    /** A class that keeps an element's text on one line. */
    private const NOWRAP = '/(?<![\w:-])(?:whitespace-nowrap|text-nowrap)(?![\w-])/';

    /**
     * @return list<array{line: int, rule: string, message: string}>
     */
    public static function find(string $relativePath, string $contents): array
    {
        $source = BladeParser::blankComments($contents);
        $settingsPage = preg_match('#(?:^|/)settings(?:/|[._-]|$)#i', $relativePath) === 1;
        $hints = [];
        $stack = [];

        foreach (self::events($source) as $event) {
            if ($event['kind'] === 'close') {
                // Back to the last open tag of this name; a stray closing tag closes nothing.
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['name'] === $event['name']) {
                        array_splice($stack, $i);

                        break;
                    }
                }

                continue;
            }

            if ($event['kind'] === 'echo') {
                // A script or a stylesheet is no text a reader sees either.
                $kept = self::anyAncestor($stack, static fn (array $tag): bool => preg_match(self::NOWRAP, $tag['attributes']) === 1
                    || in_array(strtolower($tag['name']), ['script', 'style'], true));

                if (preg_match(self::DATE_CALL, $event['text']) === 1 && ! $kept) {
                    $hints[] = self::hint($source, $event['offset'], 'unbroken-time', 'A date or a time is written here into text that may break across lines. Keep it on one line, for example in an element with `whitespace-nowrap`.');
                }

                continue;
            }

            $name = $event['name'];

            if ($name === 'x-wirekit::button' && preg_match('/(?<![:\w-])surface\s*=\s*(["\'])outline\1/', $event['attributes']) === 1) {
                $hints[] = self::hint($source, $event['offset'], 'filled-button', 'An outlined button. Buttons are filled, and an action takes its rank from `intent`: the main one in the accent, another `intent="neutral"`, a destructive one `intent="danger"`.');
            }

            if ($settingsPage && in_array($name, self::FIELDS, true) && ! self::anyAncestor($stack, static fn (array $tag): bool => $tag['name'] === 'x-wirekit::card' || str_starts_with($tag['name'], 'x-wirekit::card.'))) {
                $hints[] = self::hint($source, $event['offset'], 'content-in-card', 'A field of a settings page outside a card. Each part of the settings stands in a card with a head: an icon, the name of the part and one sentence on what it is for.');
            }

            if (! $event['selfClosing'] && ! in_array(strtolower($name), self::VOID, true)) {
                $stack[] = ['name' => $name, 'attributes' => $event['attributes']];
            }
        }

        return $hints;
    }

    /**
     * The opening tags, closing tags and echoes of the source, in the order they stand.
     *
     * @return list<array{kind: string, offset: int, name: string, attributes: string, selfClosing: bool, text: string}>
     */
    private static function events(string $source): array
    {
        $events = [];
        $tags = [];

        foreach (BladeParser::tagsFromSource($source) as $tag) {
            // A tag another element interrupted, or one the file ended inside, is no element.
            if ($tag['terminator'] !== '>') {
                continue;
            }

            $region = substr($source, $tag['attrStart'], $tag['attrEnd'] - $tag['attrStart']);
            $tags[] = [$tag['start'], $tag['attrEnd']];

            $events[] = [
                'kind' => 'open',
                'offset' => $tag['start'],
                'name' => $tag['name'],
                'attributes' => $region,
                'selfClosing' => str_ends_with(rtrim($region), '/'),
                'text' => '',
            ];
        }

        preg_match_all('#</([a-zA-Z][\w:.-]*)\s*>#', $source, $closes, PREG_OFFSET_CAPTURE);

        foreach ($closes[1] as [$name, $offset]) {
            $events[] = ['kind' => 'close', 'offset' => (int) $offset - 2, 'name' => $name, 'attributes' => '', 'selfClosing' => false, 'text' => ''];
        }

        // An escaped echo, `@{{ … }}`, prints its braces and evaluates nothing.
        preg_match_all('/(?<!@)\{\{(.*?)\}\}/s', $source, $echoes, PREG_OFFSET_CAPTURE);

        foreach ($echoes[1] as [$text, $offset]) {
            $start = (int) $offset - 2;

            // An echo inside a tag writes an attribute's value, which is no text a reader sees.
            foreach ($tags as [$tagStart, $tagEnd]) {
                if ($start > $tagStart && $start < $tagEnd) {
                    continue 2;
                }
            }

            $events[] = ['kind' => 'echo', 'offset' => $start, 'name' => '', 'attributes' => '', 'selfClosing' => false, 'text' => $text];
        }

        usort($events, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        return $events;
    }

    /**
     * @param  list<array{name: string, attributes: string}>  $stack
     * @param  callable(array{name: string, attributes: string}): bool  $matches
     */
    private static function anyAncestor(array $stack, callable $matches): bool
    {
        foreach ($stack as $tag) {
            if ($matches($tag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{line: int, rule: string, message: string}
     */
    private static function hint(string $source, int $offset, string $rule, string $message): array
    {
        return ['line' => substr_count($source, "\n", 0, max(0, $offset)) + 1, 'rule' => $rule, 'message' => $message];
    }
}
