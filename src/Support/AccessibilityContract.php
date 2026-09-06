<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Pushery\WireKit\ComponentRegistry;

/**
 * What a component already does for accessibility, and what its caller still owes it.
 *
 * ## The question this answers
 *
 * Not "which WCAG rules apply to a tab" — an assistant carries that. The one it
 * cannot answer from general knowledge is **what this particular component has
 * already wired**, and both wrong answers ship markup that looks right:
 *
 *   Adding `role="tab"` to something that already carries it is a second,
 *   competing contract on one element.
 *
 *   Omitting a name the component waits for leaves an unnamed landmark — which
 *   is worse than no landmark, because it is announced and says nothing.
 *
 * ## Derived, never listed
 *
 * Every field below is read out of the sources the package ships. A hand-kept
 * checklist per component would be a second thing to update whenever a
 * component's markup changes, and its failure mode is the quiet one: it keeps
 * answering, and the answer is last year's.
 *
 * ## Why comments are stripped first, and it is not hypothetical
 *
 * Key names appear in these factories in three shapes — as an object key
 * (`ArrowDown: 'next'`), inside a `case 'ArrowRight':`, and in PROSE explaining
 * why a key is deliberately NOT handled. `accordion.js` carries exactly that
 * third form. A scanner reading raw text reports keys the component ignores,
 * and an assistant then assumes a keyboard model that is not there.
 *
 * The same class cost this repository a red drift suite on 2026-09-05, one level
 * down: a comment naming two utility classes put them into the compiled
 * stylesheet with no source emitting them.
 */
final class AccessibilityContract
{
    /**
     * The contract for one component, or null when the registry does not know it.
     *
     * @return array{
     *     component: string,
     *     roles: list<string>,
     *     aria: list<string>,
     *     caller_supplies: list<string>,
     *     keys: list<string>,
     *     tokens: list<string>,
     * }|null
     */
    public static function for(string $name): ?array
    {
        if (ComponentRegistry::resolve($name) === null) {
            return null;
        }

        $path = ComponentRegistry::existingBladeFilePath($name);
        $markup = $path !== null ? SourceComments::strip((string) file_get_contents($path)) : '';

        return [
            'component' => $name,
            'roles' => self::roles($markup),
            'aria' => self::ariaAttributes($markup),
            'caller_supplies' => self::callerSupplied($markup, $name),
            'keys' => self::keys($name, $markup),
            'tokens' => self::tokens($markup),
        ];
    }

    /**
     * ARIA roles the component's own markup sets.
     *
     * Both spellings, because half of them are Alpine bindings rather than
     * literal attributes — a component whose role changes with its state writes
     * `:role="…"`, and a scan that knew only `role="…"` would report the most
     * dynamic components as having none.
     *
     * @return list<string>
     */
    private static function roles(string $markup): array
    {
        preg_match_all('/\brole="([a-z]+)"/', $markup, $literal);
        preg_match_all('/[:x-]bind:?role="[^"]*?\'([a-z]+)\'/', $markup, $bound);

        return self::sortedUnique(array_merge($literal[1], $bound[1]));
    }

    /**
     * The `aria-*` attributes it sets, by name.
     *
     * The VALUE is deliberately not reported. It is a Blade expression more
     * often than a constant, and an assistant that read one would be reading
     * this component's internals rather than its contract.
     *
     * @return list<string>
     */
    private static function ariaAttributes(string $markup): array
    {
        preg_match_all('/\b(?::|x-bind:)?(aria-[a-z-]+)=/', $markup, $m);

        return self::sortedUnique($m[1]);
    }

    /**
     * The accessible names the CALLER has to pass, rather than the component.
     *
     * Derived from the shape rather than from a list: an `aria-*` attribute
     * interpolating a prop is one the component cannot fill by itself. That is
     * the difference between "already handled" and "waiting for you", and it is
     * the half an assistant gets wrong in the expensive direction — a landmark
     * with an empty name is announced and says nothing.
     *
     * @return list<string>
     */
    private static function callerSupplied(string $markup, string $name): array
    {
        // ⚠️ ONLY declared props count, and the first version did not check.
        //
        // A Blade template interpolates its own locals as freely as its props —
        // `tabs` writes `aria-controls="…{{ $key }}"` where `$key` is a loop
        // variable, and reporting it told a caller to pass something the
        // component makes for itself. The declared list is what separates the
        // two, and it is already parsed for every component.
        $props = [];

        foreach (ComponentRegistry::extractProps($name) as $prop) {
            if (isset($prop['name']) && is_string($prop['name'])) {
                $props[$prop['name']] = true;
            }
        }

        if ($props === []) {
            return [];
        }

        preg_match_all('/\b(?::|x-bind:)?(aria-[a-z-]+)="[^"]*\$([a-zA-Z_][a-zA-Z0-9_]*)/', $markup, $m);

        $supplied = [];

        foreach ($m[1] as $i => $attribute) {
            $variable = $m[2][$i];

            if (! isset($props[$variable])) {
                continue;
            }

            $supplied[] = $attribute.' (from $'.$variable.')';
        }

        return self::sortedUnique($supplied);
    }

    /**
     * Keys the component handles — from its Alpine factory AND from its template.
     *
     * Empty is a real answer: a component with no keyboard model of its own is
     * relying on the browser's, which is the correct design for a native control
     * and the wrong thing to re-implement on top of.
     *
     * ⚠️ IT READ THE FACTORY ALONE, AND FOR A WHOLE CLASS OF COMPONENTS THAT IS
     * THE FILE THE KEYS ARE NOT IN. Alpine takes a key as a MODIFIER on the
     * binding — `@keydown.arrow-down.prevent="focusTab('next')"` — so a keyboard
     * model declared that way lives in the Blade template and never appears in
     * any `.js`. Measured on `tabs`, which is the canonical roving-tabindex
     * widget in this package: it reported no keys at all while handling six.
     *
     * That answer is worse than no answer, and it is the exact failure this class
     * exists to prevent, one field over: an assistant told a tablist has no
     * keyboard model adds its own, and two roving-tabindex implementations on one
     * element fight over `tabindex` and focus.
     *
     * @return list<string>
     */
    private static function keys(string $name, string $markup): array
    {
        $handled = self::keysFromTemplate($markup);

        $path = \dirname(__DIR__, 2).'/resources/js/components/'.$name.'.js';

        if (is_file($path)) {
            $source = SourceComments::strip((string) file_get_contents($path));

            // The two shapes a handled key actually takes in a factory. A bare
            // mention in a string elsewhere is not one, which is why this does
            // not simply search for the names.
            preg_match_all('/\bcase\s+[\'"]([A-Za-z][A-Za-z0-9]*|\s)[\'"]\s*:/', $source, $cases);
            preg_match_all('/^\s*(Arrow(?:Up|Down|Left|Right)|Home|End|Enter|Escape|Tab|PageUp|PageDown|Space)\s*:/m', $source, $keys);

            $handled = array_merge($handled, $cases[1], $keys[1]);
        }

        // `case ' ':` is the Space key, and left as a literal space it prints as
        // an empty entry — a reader sees a stray comma and learns nothing.
        $handled = array_map(
            static fn (string $key): string => trim($key) === '' ? 'Space' : $key,
            $handled,
        );

        return self::sortedUnique($handled);
    }

    /**
     * Keys bound as Alpine modifiers in the template, in their DOM spelling.
     *
     * Alpine writes a key in kebab-case (`arrow-down`, `page-up`) and the reader
     * of this contract is going to compare it against `event.key`, so the two
     * have to agree — `ArrowDown`, not `arrow-down`. The map is closed rather
     * than a title-caser: `escape` is `Escape` but Alpine's `space` is the `' '`
     * that `event.key` actually reports, and a general rule gets one of those
     * two wrong.
     *
     * @return list<string>
     */
    private static function keysFromTemplate(string $markup): array
    {
        $spellings = [
            'arrow-up' => 'ArrowUp',
            'arrow-down' => 'ArrowDown',
            'arrow-left' => 'ArrowLeft',
            'arrow-right' => 'ArrowRight',
            'page-up' => 'PageUp',
            'page-down' => 'PageDown',
            'home' => 'Home',
            'end' => 'End',
            'enter' => 'Enter',
            'escape' => 'Escape',
            'esc' => 'Escape',
            'tab' => 'Tab',
            'space' => 'Space',
            'delete' => 'Delete',
            'backspace' => 'Backspace',
        ];

        // `keydown` and `keyup` both, and every modifier on the binding — Alpine
        // chains them (`.arrow-down.prevent`, `.window.escape`), so the key is
        // not reliably the first one.
        preg_match_all('/(?:@|x-on:)key(?:down|up)((?:\.[a-z-]+)+)/', $markup, $bindings);

        $found = [];

        foreach ($bindings[1] as $chain) {
            foreach (explode('.', trim($chain, '.')) as $modifier) {
                if (isset($spellings[$modifier])) {
                    $found[] = $spellings[$modifier];
                }
            }
        }

        return $found;
    }

    /**
     * The design tokens this component's markup reads.
     *
     * The useful form of "does it comply with the token system". A per-component
     * sentence saying colors go through `--color-wk-*` is the generic checklist
     * this contract exists instead of — every component would carry the same one,
     * and a repository-wide guard already fails the build on a hardcoded value.
     *
     * The list of knobs is the part that differs per component and that an
     * assistant cannot get anywhere else: it is what to set to restyle this one
     * thing without touching it.
     *
     * @return list<string>
     */
    private static function tokens(string $markup): array
    {
        preg_match_all('/var\(\s*(--[a-z0-9-]*-wk-[a-z0-9-]+)/i', $markup, $found);

        return self::sortedUnique($found[1]);
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function sortedUnique(array $values): array
    {
        $values = array_values(array_unique(array_filter($values, static fn (string $v): bool => $v !== '')));
        sort($values);

        return $values;
    }
}
