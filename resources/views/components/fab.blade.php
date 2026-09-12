{{-- optimistic-ui: n/a — client-only
     Its state is whether the action stack is open. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    // What the trigger does. REQUIRED in spirit: the trigger is an icon, so
    // without this it announces as "button" and nothing else.
    'label' => __('wirekit::Actions'),
    // Where it floats. 'end' is the inline-end corner — it follows the writing
    // direction rather than assuming everyone reads left-to-right.
    'position' => config('wirekit.components.fab.position', 'end'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('fab', $attributes->getAttributes());

    $position = WireKit::validateProp('fab', 'position', $position, ['end', 'start', 'center']);

    $positionClass = match ($position) {
        'start' => 'start-[var(--padding-wk-x-lg)]',
        'center' => 'left-1/2 -translate-x-1/2',
        default => 'end-[var(--padding-wk-x-lg)]',
    };

    $classes = WireKit::resolveClasses('fab', 'base', implode(' ', [
        'wk-fab',
        // The TOKEN, not its number. This was `z-40` — the value --z-wk-sticky happens to
        // hold — while scroll-to-top, a fixed overlay in the same corner of the same page,
        // reads the token. Retheming the layer moved everything except this one.
        'fixed z-[var(--z-wk-sticky)]',
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
    {{-- ⚠️ NO `.prevent` ON THE ARROWS, and the reason is the order Alpine applies its
         modifiers. `.prevent` wraps the handler and calls `preventDefault()` BEFORE the
         expression is evaluated, so an `open &&` guard inside the expression decides
         whether focus moves — never whether the default is canceled. On a component that
         is `fixed z-40` and therefore reachable by Tab on every page that renders one,
         that meant ArrowUp and ArrowDown did NOTHING while the menu was closed, instead of
         scrolling the page: a control that is not a composite widget in that state had
         taken a global key.
         Every other arrow binding in the catalog sits on the widget itself, where
         swallowing the key IS the correct behavior. This root was the only exception.
         The cancellation now lives inside `move()`, which is the one place that knows
         whether the menu is open. --}}
    x-on:keydown.arrow-up="move(-1, $event)"
    x-on:keydown.arrow-down="move(1, $event)"
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
        {{-- `true`, not `menu`. `aria-haspopup="menu"` promises the APG menu keyboard model —
             one tab stop on the menu, arrow keys between items, Escape and a focus exit that
             close it — and this panel implements none of that: its actions are ordinary links
             and buttons, each with its own tab stop. Announcing a model a reader then cannot
             use is the same defect the comment below warns about, one level up. --}}
        aria-haspopup="true"
        aria-label="{{ $label }}"
        data-wk-fab-trigger
        {{-- The box reads --size-wk-fab rather than a literal, for the reason `fab.button`
             states beside its own copy of this line: a developer laying out AROUND a fixed
             control has to be able to read its size, and `wk-fab-clearance` does exactly that.
             ⚠️ THE SPEED DIAL HAS ITS OWN TRIGGER AND IT KEPT THE LITERAL when the standalone
             button was moved onto the token, so this one stayed 56px on every viewport while
             its sibling stepped down on a phone. Two implementations of one control is why
             the browser case measures the trigger the DOCS render rather than the one this
             file happens to be about. --}}
        class="flex h-[var(--size-wk-fab)] w-[var(--size-wk-fab)] cursor-pointer items-center justify-center rounded-[var(--radius-wk-full)] bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)] shadow-[var(--shadow-wk-lg)] transition-transform duration-[var(--transition-wk-duration)] hover:brightness-110 focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-2"
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

    {{-- `role="group"`, not `role="menu"`.
         
         A `menu` is a keyboard CONTRACT, not a shape: one tab stop for the whole menu, arrow
         keys to move inside it, Escape to leave, and focus leaving it closes it. This panel
         does none of those — every action is an ordinary link or button with its own tab
         stop — so the role promised a model that was not there, and a reader following it
         pressed the arrow keys and got nothing.

         A named group is what this actually is, and it is announced honestly. The panel also
         closes when focus leaves it now, which it did not: a keyboard user could tab out of
         an open panel and leave it hanging over the page behind them. --}}
    <div
        x-show="open"
        x-cloak
        role="group"
        {{-- A single method call, not an inline `if`. Alpine's CSP build parses a call and
             nothing more — no `if`, no `!`, no member access — so an inline condition here is
             never evaluated on that bundle and the panel silently stops closing. The repo's
             own csp-expression-audit catches it, which is how this line was found. --}}
        @focusout="closeIfFocusLeft($event)"
        aria-label="{{ $label }}"
        data-wk-fab-actions
        class="wk-fab-actions flex flex-col-reverse items-center gap-[var(--gap-wk-sm)]"
    >
        {{ $slot }}
    </div>
</div>
