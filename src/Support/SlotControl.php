<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use DOMDocument;
use DOMElement;

/**
 * Whether rendered slot markup holds a control a reader reaches with the keyboard.
 *
 * A wrapper that could make itself a tab stop asks this first. A slot that already holds a
 * reachable control is the stop the reader lands on, and a wrapper made focusable around it would
 * be a second stop with no name of its own.
 *
 * The markup is read as a document rather than matched as text, because what decides is an
 * attribute and its value: `disabled:opacity-50` in a class list is not a `disabled` attribute,
 * and `tabindex="-1"` keeps an element out of the tab order where `tabindex="0"` puts it in.
 *
 * @internal Public because Blade views call it; not a promise to a developer.
 */
final class SlotControl
{
    public static function reachable(string $html): bool
    {
        if (! str_contains($html, '<')) {
            return false;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The encoding declaration keeps multibyte text intact; the wrapper gives the fragment a
        // single root, so the parser adds no html and body around it.
        $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return false;
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if (self::isReachable($element)) {
                return true;
            }
        }

        return false;
    }

    private static function isReachable(DOMElement $element): bool
    {
        if ($element->hasAttribute('disabled') && in_array(strtolower($element->tagName), ['button', 'input', 'select', 'textarea'], true)) {
            return false;
        }

        // An explicit tab index decides on its own, in both directions.
        if ($element->hasAttribute('tabindex')) {
            return (int) $element->getAttribute('tabindex') >= 0;
        }

        if ($element->hasAttribute('contenteditable')) {
            return strtolower($element->getAttribute('contenteditable')) !== 'false';
        }

        return match (strtolower($element->tagName)) {
            'button', 'select', 'textarea', 'summary' => true,
            'a', 'area' => $element->hasAttribute('href'),
            'input' => strtolower($element->getAttribute('type')) !== 'hidden',
            default => false,
        };
    }
}
