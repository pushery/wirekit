<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * A hotkey written the way the command palette reads it, in the form `aria-keyshortcuts` takes,
 * and a shortcut of `x-wk-shortcut` as its keys are drawn for a reader (`display()`).
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

    /** The modifier words of the shortcut grammar `x-wk-shortcut` reads, as in utils/shortcuts.js. */
    private const SHORTCUT_MODIFIERS = ['cmd', 'meta', 'ctrl', 'control', 'alt', 'option', 'shift'];

    /** What a shortcut of that grammar looks like, for the report on one `display()` does not read. */
    public const EXPECTED = 'a shortcut such as cmd+s, shift+? or g d: modifiers (cmd, ctrl, alt, shift) joined to one key with +, the steps of a sequence apart by a space';

    /** Key names of that grammar, by the value `KeyboardEvent.key` reports for the key. */
    private const KEY_VALUES = [
        'enter' => 'Enter',
        'return' => 'Enter',
        'esc' => 'Escape',
        'escape' => 'Escape',
        'space' => ' ',
        'tab' => 'Tab',
        'backspace' => 'Backspace',
        'delete' => 'Delete',
        'del' => 'Delete',
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
    ];

    /** How a key that types no character is drawn: on a Mac, and elsewhere. */
    private const SHOWN_KEYS = [
        'Enter' => ['↩', 'Enter'],
        'Escape' => ['Esc', 'Esc'],
        'Tab' => ['⇥', 'Tab'],
        'Backspace' => ['⌫', 'Backspace'],
        'Delete' => ['⌦', 'Del'],
        'ArrowUp' => ['↑', '↑'],
        'ArrowDown' => ['↓', '↓'],
        'ArrowLeft' => ['←', '←'],
        'ArrowRight' => ['→', '→'],
        ' ' => ['Space', 'Space'],
    ];

    /**
     * The words a drawn key may be, in the reader's language: `Ctrl` is `Strg` on a German
     * keyboard. The overview of keyboard shortcuts takes the same map, so both draw a key alike.
     *
     * @return array<string, string>
     */
    public static function keyNames(): array
    {
        // Each key written out, so the catalog's guard finds every one it ships.
        return [
            'Ctrl' => (string) __('wirekit::Ctrl'),
            'Alt' => (string) __('wirekit::Alt'),
            'Shift' => (string) __('wirekit::Shift'),
            'Enter' => (string) __('wirekit::Enter'),
            'Esc' => (string) __('wirekit::Esc'),
            'Tab' => (string) __('wirekit::Tab'),
            'Backspace' => (string) __('wirekit::Backspace'),
            'Del' => (string) __('wirekit::Del'),
            'Space' => (string) __('wirekit::Space'),
        ];
    }

    /**
     * The keys of a shortcut hint as a menu entry takes it, written as one text (`⌘K`) or as a list
     * of its keys (`['Ctrl', 'K']`), each trimmed. A key of white space alone holds none, so a blank
     * hint draws nothing. An object that writes itself as text is one key, and any other is none.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $shortcut  whatever the tag carried
     * @return list<string>
     */
    public static function hintKeys(array|object|string|int|float|bool|null $shortcut): array
    {
        if (is_object($shortcut)) {
            $shortcut = method_exists($shortcut, '__toString') ? (string) $shortcut : [];
        }

        $keys = [];

        foreach ((array) $shortcut as $key) {
            $key = is_scalar($key) ? trim((string) $key) : '';

            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * A shortcut in the grammar `x-wk-shortcut` reads (`cmd+s`, `shift+?`, `g d`), as its keys are
     * drawn: one list of keys per step, for a Mac (⌘ ⌥ ⇧) and for every other platform (Ctrl Alt
     * Shift, named in the reader's language), and whether it is a single-key shortcut, which the
     * reader's switch turns off. The same rules as `parseShortcut()` and `displayKeys()` in
     * utils/shortcuts.js. Null for text that names no key.
     *
     * @return array{mac: list<list<string>>, other: list<list<string>>, single: bool}|null
     */
    public static function display(?string $shortcut): ?array
    {
        $source = trim((string) $shortcut);

        if ($source === '') {
            return null;
        }

        $names = self::keyNames();
        $word = static fn (string $key): string => $names[$key] ?? $key;
        $mac = [];
        $other = [];
        $single = true;

        foreach (preg_split('/\s+/', $source) ?: [] as $chunk) {
            // A `+` on its own, or at the end after another `+`, is the key itself (`cmd++`).
            if ($chunk === '+') {
                $parts = ['+'];
            } elseif (str_ends_with($chunk, '++')) {
                $parts = [...array_values(array_filter(explode('+', substr($chunk, 0, -2)), static fn (string $part): bool => $part !== '')), '+'];
            } else {
                $parts = array_values(array_filter(explode('+', $chunk), static fn (string $part): bool => $part !== ''));
            }

            $key = $parts === [] ? '' : (string) end($parts);

            if ($key === '' || in_array(mb_strtolower($key), self::SHORTCUT_MODIFIERS, true)) {
                return null;
            }

            $modifiers = array_map(static fn (string $part): string => mb_strtolower($part), array_slice($parts, 0, -1));

            if (array_diff($modifiers, self::SHORTCUT_MODIFIERS) !== []) {
                return null;
            }

            $cmd = in_array('cmd', $modifiers, true) || in_array('meta', $modifiers, true);
            $ctrl = in_array('ctrl', $modifiers, true) || in_array('control', $modifiers, true);
            $alt = in_array('alt', $modifiers, true) || in_array('option', $modifiers, true);
            $shift = in_array('shift', $modifiers, true);
            $single = $single && ! $cmd && ! $ctrl && ! $alt;

            $value = self::keyValue($key);
            $shown = self::SHOWN_KEYS[$value] ?? null;
            $drawn = static fn (bool $forMac): string => $shown !== null
                ? ($forMac ? $word($shown[0]) : $word($shown[1]))
                : (mb_strlen($value) === 1 ? mb_strtoupper($value) : $value);

            $mac[] = self::held([$cmd ? '⌘' : null, $ctrl ? '⌃' : null, $alt ? '⌥' : null, $shift ? '⇧' : null, $drawn(true)]);
            $other[] = self::held([$cmd || $ctrl ? $word('Ctrl') : null, $alt ? $word('Alt') : null, $shift ? $word('Shift') : null, $drawn(false)]);
        }

        return ['mac' => $mac, 'other' => $other, 'single' => $single];
    }

    /**
     * The keys a step holds, in order. Null stands for a modifier it does not hold; a key such as
     * `0` is falsy and stays.
     *
     * @param  list<string|null>  $keys
     * @return list<string>
     */
    private static function held(array $keys): array
    {
        return array_values(array_filter($keys, static fn (?string $key): bool => $key !== null));
    }

    /** A key as written, in the form it is compared in: a character in lower case, a name as the browser reports it. */
    private static function keyValue(string $key): string
    {
        if (mb_strlen($key) === 1) {
            return mb_strtolower($key);
        }

        $lower = mb_strtolower($key);

        if (isset(self::KEY_VALUES[$lower])) {
            return self::KEY_VALUES[$lower];
        }

        return preg_match('/^f([1-9]|1[0-9]|2[0-4])$/', $lower) === 1 ? mb_strtoupper($lower) : $key;
    }

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
