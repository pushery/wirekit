@props([
    // What the trigger does. REQUIRED in spirit: the trigger is an icon, so
    // without this it announces as "button" and nothing else.
    'label' => 'Actions',
    // Where it floats. 'end' is the inline-end corner — it follows the writing
    // direction rather than assuming everyone reads left-to-right.
    'position' => config('wirekit.components.fab.position', 'end'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $position = WireKit::validateProp('fab', 'position', $position, ['end', 'start', 'center']);

    $positionClass = match ($position) {
        'start' => 'start-[var(--padding-wk-x-lg)]',
        'center' => 'left-1/2 -translate-x-1/2',
        default => 'end-[var(--padding-wk-x-lg)]',
    };

    $classes = WireKit::resolveClasses('fab', 'base', implode(' ', [
        'wk-fab',
        'fixed z-40',
        'flex flex-col-reverse items-center gap-[var(--gap-wk-sm)]',
        $positionClass,
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

{{-- Escape is bound on the whole component, not on the trigger: by the time the
     reader wants out, focus is on an action, and a handler on the trigger would
     never see the key. --}}
<div
    x-data="wirekitFab()"
    x-on:keydown.escape.prevent="close()"
    x-on:keydown.arrow-up.prevent="open && move(-1)"
    x-on:keydown.arrow-down.prevent="open && move(1)"
    data-wk-fab
    data-position="{{ $position }}"
    {{ $attributes->class([$classes]) }}
>
    {{-- flex-col-reverse above means the actions render BEFORE the trigger in the
         DOM but appear above it. That is deliberate: the trigger stays the first
         thing in the tab order, which is what the reader reaches for, and the
         actions follow it in the order they are read. --}}
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="menu"
        aria-label="{{ $label }}"
        data-wk-fab-trigger
        class="flex h-14 w-14 cursor-pointer items-center justify-center rounded-[var(--radius-wk-full)] bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)] shadow-[var(--shadow-wk-lg)] transition-transform duration-[var(--transition-wk-duration)] hover:brightness-110 focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-2"
    >
        {{-- The plus turns into a close mark. Both icons stay in the DOM so the
             rotate can cross between them, which means the inactive one must be
             hidden from assistive tech — otherwise the trigger announces both
             states at once.

             This is hand-rolled rather than composed from the swap primitive on
             purpose: swap lives on another branch, and the icon crossfade is
             cosmetic. Building a hard cross-branch dependency for a nicety would
             drag an unrelated review into this one. Once both land, this block
             becomes one swap tag. --}}
        <span class="relative inline-grid place-items-center">
            <span
                class="wk-fab-icon col-start-1 row-start-1"
                :aria-hidden="open ? 'false' : 'true'"
                :data-shown="open ? 'true' : 'false'"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </span>
            <span
                class="wk-fab-icon col-start-1 row-start-1"
                :aria-hidden="open ? 'true' : 'false'"
                :data-shown="open ? 'false' : 'true'"
            >
                {{ $trigger ?? '' }}
                @unless(isset($trigger))
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                @endunless
            </span>
        </span>
    </button>

    {{-- role="menu" with the trigger's aria-haspopup="menu": the two have to
         agree, or a screen reader announces a popup that never arrives. --}}
    <div
        x-show="open"
        x-cloak
        role="menu"
        aria-label="{{ $label }}"
        data-wk-fab-actions
        class="wk-fab-actions flex flex-col-reverse items-center gap-[var(--gap-wk-sm)]"
    >
        {{ $slot }}
    </div>
</div>
