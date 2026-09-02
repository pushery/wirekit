<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\ComponentSlot;

/**
 * Reads the attribute bag of a Blade slot that may not be a slot object at all.
 *
 * How a slot is WRITTEN decides its TYPE, and nothing at the call site suggests
 * that it would:
 *
 *     <x-wirekit::shell-bar><x-slot:start>Brand</x-slot:start>body</x-wirekit::shell-bar>
 *         → $start is a string
 *
 *     <x-wirekit::shell-bar>
 *         <x-slot:start>Brand</x-slot:start>
 *         body
 *     </x-wirekit::shell-bar>
 *         → $start is an Illuminate\View\ComponentSlot
 *
 * The cause is one missing call, and it is worth naming exactly because the obvious
 * explanation is wrong. Both forms compile to the SAME opening call —
 * `$__env->slot('sidebar', null, [])`, three arguments — so the two-argument branch
 * of `ManagesComponents::slot()` is not what separates them. Measured with
 * `Blade::compileString()`:
 *
 *     inline  →  slot() x1,  endSlot() x0
 *     block   →  slot() x1,  endSlot() x1
 *
 * `slot()` seeds the entry with an empty STRING and opens an output buffer, and only
 * `endSlot()` replaces that string with the `ComponentSlot`. The inline form never
 * emits it, so the placeholder survives — and the buffer stays open, which is the
 * "did not close its own output buffers" warning that accompanies it. It holds for
 * slot content with markup in it too.
 *
 * So `$start->attributes` is a fatal — "Attempt to read property attributes on
 * string" — at the shortest and most ordinary way to write the call. This is the
 * same shape as {@see BooleanProp}: Blade hands the view a string where the view
 * expected something richer, and the spelling that breaks is the terse one.
 *
 * ⚠️ A test suite is close to blind to it. Test Blade is nearly always written in
 * a `<<<'BLADE'` heredoc, where every slot already sits on its own line — which is
 * the route that yields the object. Five sites across two components shipped this
 * way and exactly one had a red test, from the single inline-written line in the
 * corpus. A red proof for this class MUST be written on one line.
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
