<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/**
 * The editor's first paint: the saved document, rebuilt so that nothing in it can run.
 *
 * `<x-wirekit::editor format="html">` renders its value into the page before the engine mounts, so
 * the body is not empty while the scripts load, and the factory removes that copy when it mounts.
 * The browser parses the copy in the LIVE document. The engine parses its own copy in an inert
 * one, so its schema never sees this one: an `<img src=x onerror=…>` in the value would run on
 * first paint, whatever the engine would have made of it. And a value is often not a stored
 * document at all but `old('body')`, the text of a request that just failed validation.
 *
 * So the copy is rebuilt rather than echoed. The value is parsed with PHP's HTML5 parser, the
 * grammar the browser uses, and written out again element by element: an element a document
 * model knows keeps its tag and a few inert attributes, every text node and attribute value is
 * escaped, and anything else is dropped with its content (elements whose content is not document
 * text, such as `script`, `template` or `svg`) or unwrapped (the rest, so their text still shows).
 * The output is plain enough that a browser can only parse it back into what was written.
 *
 * This makes the preview inert and nothing more. The value the form submits is untouched, and it
 * still has to be sanitized where it is stored.
 */
final class EditorSeed
{
    /**
     * The elements kept, each with the attributes it may carry.
     *
     * StarterKit's nodes and marks, and the common extensions (link, underline, image, table,
     * highlight, subscript and superscript). A document using anything else still shows its text.
     */
    private const ALLOWED = [
        'a' => ['href'],
        'b' => [],
        'blockquote' => [],
        'br' => [],
        'code' => [],
        'del' => [],
        'em' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'hr' => [],
        'i' => [],
        'img' => ['src', 'alt', 'width', 'height'],
        'li' => [],
        'mark' => [],
        'ol' => ['start'],
        'p' => [],
        'pre' => [],
        's' => [],
        'strike' => [],
        'strong' => [],
        'sub' => [],
        'sup' => [],
        'table' => [],
        'tbody' => [],
        'td' => ['colspan', 'rowspan'],
        'tfoot' => [],
        'th' => ['colspan', 'rowspan'],
        'thead' => [],
        'tr' => [],
        'u' => [],
        'ul' => [],
    ];

    /** Written without a closing tag. */
    private const VOID = ['br', 'hr', 'img'];

    /** Dropped with everything inside: what they contain is not document text. */
    private const DROPPED = [
        'audio', 'button', 'canvas', 'embed', 'frame', 'frameset', 'iframe', 'input', 'link', 'math',
        'meta', 'noembed', 'noframes', 'noscript', 'object', 'script', 'select', 'style', 'svg',
        'template', 'textarea', 'title', 'video', 'xmp',
    ];

    private const HTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';

    public static function render(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Without the DOM extension there is no parser to rebuild from, and no preview is safer
        // than a wrong one: the body stays empty until the engine mounts, as it does for JSON.
        if (! class_exists(HTMLDocument::class)) {
            return '';
        }

        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head></head><body>'.$html.'</body></html>',
            LIBXML_NOERROR,
            'UTF-8'
        );

        return $document->body === null ? '' : self::children($document->body);
    }

    private static function children(Node $parent): string
    {
        $out = '';

        foreach ($parent->childNodes as $node) {
            if ($node instanceof Text) {
                $out .= self::escape($node->data);

                continue;
            }

            // A comment, a processing instruction, a doctype: nothing a reader sees.
            if (! $node instanceof Element) {
                continue;
            }

            $name = $node->localName;

            // Foreign content (`svg`, `math`) parses by other rules, and none of it is text.
            if ($node->namespaceURI !== self::HTML_NAMESPACE || in_array($name, self::DROPPED, true)) {
                continue;
            }

            if (! array_key_exists($name, self::ALLOWED)) {
                $out .= self::children($node);

                continue;
            }

            $out .= '<'.$name.self::attributes($node, self::ALLOWED[$name]).'>';

            if (in_array($name, self::VOID, true)) {
                continue;
            }

            // The parser drops a newline that directly follows `<pre>`, so one that belongs to
            // the content has to be written twice to survive the next parse.
            $inner = self::children($node);
            $out .= ($name === 'pre' && str_starts_with($inner, "\n") ? "\n" : '').$inner.'</'.$name.'>';
        }

        return $out;
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function attributes(Element $element, array $allowed): string
    {
        $out = '';

        foreach ($allowed as $attribute) {
            $value = $element->getAttribute($attribute);

            if ($value === null) {
                continue;
            }

            $keep = match ($attribute) {
                // A link may use http, https, mailto and tel, an image only http and https; a
                // URL without a scheme is relative and kept. SafeUrl reads the scheme the way
                // the browser does, so `java\tscript:` counts as `javascript:`.
                'href' => SafeUrl::allows($value, SafeUrl::LINK_SCHEMES),
                'src' => SafeUrl::allows($value, SafeUrl::LOAD_SCHEMES),
                'alt' => true,
                default => preg_match('/^\d{1,4}$/', trim($value)) === 1,
            };

            if ($keep) {
                $out .= ' '.$attribute.'="'.self::escape($value).'"';
            }
        }

        return $out;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
