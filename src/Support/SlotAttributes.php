<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\ComponentSlot;

/**
 * Reads the attribute bag of a Blade slot that may not be a slot object at all.
 *
 * One character after the closing tag decides the slot's TYPE, and nothing at the
 * call site suggests that it would:
 *
 *     <x-wirekit::shell-bar><x-slot:start>Brand</x-slot:start>body</x-wirekit::shell-bar>
 *         → $start is a string
 *
 *     <x-wirekit::shell-bar><x-slot:start>Brand</x-slot:start> body</x-wirekit::shell-bar>
 *         → $start is an Illuminate\View\ComponentSlot
 *
 * Blade's component compiler rewrites every `</x-slot…>` to ` @endslot`, with a space
 * before and nothing after. Glued to `body`, that becomes `@endslotbody`, which the
 * directive compiler reads as ONE unknown directive and leaves in the page as text, so
 * `endSlot()` never runs. `slot()` seeds the entry with an empty STRING and opens an
 * output buffer, and only `endSlot()` replaces that string with the `ComponentSlot`.
 * Without it the placeholder survives, the buffer stays open — the "did not close its
 * own output buffers" warning in a test — and the slot's content lands in the DEFAULT
 * slot, next to the literal `@endslotbody`. A line break after the closing tag
 * separates the two just as a space does, which is why this reads like a
 * one-line-versus-block difference and is not one.
 *
 * So `$start->attributes` is a fatal — "Attempt to read property attributes on
 * string" — on a spelling that looks entirely ordinary. This class keeps the page from
 * crashing. It cannot bring the slot's content back: Blade moved it before the
 * component ran. Same shape as {@see BooleanProp} — Blade hands the view a string where
 * the view expected something richer.
 *
 * ⚠️ A test suite is close to blind to it. Test Blade is nearly always written in a
 * `<<<'BLADE'` heredoc, where a line break follows every closing tag. Five sites across
 * two components shipped reading `->attributes` directly, and exactly one had a red
 * test. A red proof for this class MUST glue the closing tag to the text after it.
 */
final class SlotAttributes
{
    /**
     * The slot's own attributes, or an empty bag when it carries none.
     *
     * Deliberately returns the BAG rather than normalizing the slot itself. Turning
     * a string slot into a ComponentSlot would also change how `{{ $slot }}` renders:
     * a ComponentSlot is `Htmlable` and is emitted unescaped, a string is escaped. That
     * is a second, invisible behavior change riding along with a crash fix, so the
     * repair stays where the crash is — the attribute access — and the content path is
     * left exactly as it was.
     */
    // `mixed` rather than a closed union, and it is the point rather than laziness: a slot is
    // whatever Blade decided to store. A ComponentSlot from the buffered route, a plain string
    // from the inline one, null when the slot was never given — and through `@slot('x', $value)`
    // any value a caller cares to pass. The whole contract of this helper is that it never
    // throws, so narrowing the parameter would turn a defensive read back into the fatal it
    // exists to prevent, at a call site that has no way to know which route Blade took.
    public static function of(mixed $slot): ComponentAttributeBag
    {
        return $slot instanceof ComponentSlot
            ? $slot->attributes
            : new ComponentAttributeBag;
    }
}
