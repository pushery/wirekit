<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * A hotkey written the way the command palette reads it, in the form `aria-keyshortcuts` takes.
 *
 * The palette's `hotkey` is lowercase and joined with `+`: `cmd+k`, `ctrl+shift+p`, `/`. ARIA wants
 * the modifier names of the UI Events key values (`Control`, `Alt`, `Shift`, `Meta`) and the key as
 * a key value, alternatives separated by spaces. `cmd` and `meta` open the palette with Meta or
 * with Control, so they become two alternatives: `cmd+k` is `Meta+K Control+K`.
 */
final class KeyShortcuts
{
    /** The palette's modifier words, by the key value ARIA names them with. */
    private const MODIFIERS = [
        'ctrl' => 'Control',
        'control' => 'Control',
        'alt' => 'Alt',
        'option' => 'Alt',
        'shift' => 'Shift',
    ];

    /** Keys whose key value is a word rather than the character they type. */
    private const NAMED = [
        'esc' => 'Escape',
        'escape' => 'Escape',
        'enter' => 'Enter',
        'return' => 'Enter',
        'space' => 'Space',
        'tab' => 'Tab',
        'backspace' => 'Backspace',
        'delete' => 'Delete',
        'up' => 'ArrowUp',
        'down' => 'ArrowDown',
        'left' => 'ArrowLeft',
        'right' => 'ArrowRight',
        'arrowup' => 'ArrowUp',
        'arrowdown' => 'ArrowDown',
        'arrowleft' => 'ArrowLeft',
        'arrowright' => 'ArrowRight',
        'home' => 'Home',
        'end' => 'End',
        'pageup' => 'PageUp',
        'pagedown' => 'PageDown',
        'plus' => 'Plus',
    ];

    /**
     * The `aria-keyshortcuts` value of a palette hotkey, or null where there is none to state.
     */
    public static function aria(?string $hotkey): ?string
    {
        if ($hotkey === null || trim($hotkey) === '') {
            return null;
        }

        $parts = explode('+', strtolower(trim($hotkey)));
        $key = (string) array_pop($parts);

        // `ctrl++` ends in the plus key itself: the last part is empty and the one before it too.
        // ARIA names that key `Plus`, since `+` joins the keys of one shortcut.
        if ($key === '' && $parts !== [] && end($parts) === '') {
            array_pop($parts);
            $key = 'plus';
        }

        if ($key === '') {
            return null;
        }

        $keyValue = self::NAMED[$key] ?? (mb_strlen($key) === 1 ? mb_strtoupper($key) : ucfirst($key));
        $meta = false;
        $modifiers = [];

        foreach ($parts as $part) {
            if ($part === 'cmd' || $part === 'meta') {
                $meta = true;
            } elseif (isset(self::MODIFIERS[$part])) {
                $modifiers[self::MODIFIERS[$part]] = true;
            }
        }

        $join = static fn (array $names): string => implode('+', [...$names, $keyValue]);
        // One order whatever the hotkey's: `shift+ctrl+p` and `ctrl+shift+p` state one shortcut.
        $others = array_values(array_filter(['Control', 'Alt', 'Shift'], static fn (string $name): bool => isset($modifiers[$name])));

        if (! $meta) {
            return $join($others);
        }

        // Meta, and Control in its place, which the palette accepts for `cmd` as well. A hotkey that
        // names Control beside `cmd` already needs it, so it has the one form.
        $withMeta = $join(['Meta', ...$others]);

        if (in_array('Control', $others, true)) {
            return $withMeta;
        }

        return $withMeta.' '.$join(['Control', ...$others]);
    }
}
