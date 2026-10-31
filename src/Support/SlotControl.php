<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Whether rendered slot markup holds a control a reader reaches with the keyboard, and what that
 * control is called.
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
        $document = self::fragment($html);

        if ($document === null) {
            return false;
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if (self::isReachable($element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the slot holds an element that takes the focus itself: one the Tab key reaches, or an
     * enabled control left out of the tab order, as the buttons of a toolbar are, which its arrow
     * keys focus. A wrapper around either is no stop of its own, and certainly no button: that
     * would put one control inside another. Only a slot whose controls are all disabled, or that
     * holds none, leaves the focus to the wrapper.
     */
    public static function holdsFocus(string $html): bool
    {
        $document = self::fragment($html);

        if ($document === null) {
            return false;
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if (self::isReachable($element) || self::isEnabledControl($element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The name of the first element in the slot that takes the focus, or null where the slot does
     * not hold it.
     *
     * The element is the one a tooltip's script hands its description to: a link, an enabled
     * control, or an element with a tab index other than -1, which counts a toolbar's buttons that
     * the arrow keys reach. Its name is read as it is read from content: `aria-label` first, else
     * the element's words, without what is hidden from a reader and with an image's `alt`. A name
     * given from elsewhere, through `aria-labelledby` or the label of a field, is not in the slot,
     * so it answers null.
     */
    public static function name(string $html): ?string
    {
        $document = self::fragment($html);

        if ($document === null) {
            return null;
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if (! self::takesFocus($element)) {
                continue;
            }

            if ($element->hasAttribute('aria-labelledby') || in_array(strtolower($element->tagName), ['input', 'select', 'textarea'], true)) {
                return null;
            }

            $label = trim($element->getAttribute('aria-label'));
            $name = trim((string) preg_replace('/\s+/u', ' ', $label !== '' ? $label : self::words($element)));

            return $name === '' ? null : $name;
        }

        return null;
    }

    /**
     * The fragment as a document, or null where it holds no markup or does not parse.
     */
    private static function fragment(string $html): ?DOMDocument
    {
        if (! str_contains($html, '<')) {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The encoding declaration keeps multibyte text intact; the wrapper gives the fragment a
        // single root, so the parser adds no html and body around it.
        $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    /**
     * The words a reader hears inside the node.
     */
    private static function words(DOMNode $node): string
    {
        $words = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $words .= $child->textContent;
            } elseif ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if ($child->getAttribute('aria-hidden') === 'true' || $child->hasAttribute('hidden') || in_array($tag, ['template', 'script', 'style', 'svg'], true)) {
                    continue;
                }

                $words .= $tag === 'img' ? ' '.$child->getAttribute('alt').' ' : self::words($child);
            }
        }

        return $words;
    }

    private static function takesFocus(DOMElement $element): bool
    {
        $tag = strtolower($element->tagName);

        if (in_array($tag, ['button', 'input', 'select', 'textarea'], true)) {
            return ! $element->hasAttribute('disabled');
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            return true;
        }

        return $element->hasAttribute('tabindex') && trim($element->getAttribute('tabindex')) !== '-1';
    }

    /** A native control a script can focus, whatever its tab index says. */
    private static function isEnabledControl(DOMElement $element): bool
    {
        $tag = strtolower($element->tagName);

        if (! in_array($tag, ['button', 'input', 'select', 'textarea'], true) || $element->hasAttribute('disabled')) {
            return false;
        }

        return $tag !== 'input' || strtolower($element->getAttribute('type')) !== 'hidden';
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
