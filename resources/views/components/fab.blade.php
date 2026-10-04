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

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:keydown.escape', 'x-on:keydown.arrow-up', 'x-on:keydown.arrow-down']);

    $position = WireKit::validateProp('fab', 'position', $position, ['end', 'start', 'center']);

    $positionClass = match ($position) {
        'start' => 'start-[var(--padding-wk-x-lg)]',
        'center' => 'left-1/2 -translate-x-1/2',
        // The gutter term is on the END arm only: a classic scrollbar sits on the inline-end
        // edge in both directions, so the start arm has no gutter under it. Same shape as
        // `fab/button`, which explains when the term is non-zero.
        default => 'end-[calc(var(--padding-wk-x-lg)_+_var(--wk-scrollbar-inset,0px))]',
    };

    $classes = 'wk-fab '.WireKit::resolveClasses('fab', 'base', implode(' ', [
        // The TOKEN, not its number. This was `z-40` — the value --z-wk-sticky happens to
        // hold — while scroll-to-top, a fixed overlay in the same corner of the same page,
        // reads the token. Retheming the layer moved everything except this one.
        'fixed z-[var(--z-wk-sticky)]',
        'flex flex-col-reverse items-center gap-[var(--gap-wk-sm)]',
        $positionClass,
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
@endphp

{{-- Escape is bound on the whole component, not on the trigger: by the time the
     reader wants out, focus is on an action, and a handler on the trigger would
     never see the key. `escapeMenu()` marks the press only when it closed the menu, so
     an Escape on the closed trigger still reaches what holds the page. --}}
<div
    x-data="wirekitFab()"
    x-on:keydown.escape="escapeMenu($event)"
    {{-- No `.prevent` on the arrows, and the reason is the order Alpine applies its
         modifiers. `.prevent` wraps the handler and calls `preventDefault()` before the
         expression is evaluated, so an `open &&` guard inside the expression would decide
         whether focus moves, never whether the default is canceled. On a component that is
         `fixed z-40`, reachable by Tab on every page that renders one, ArrowUp and ArrowDown
         would then do nothing while the menu is closed instead of scrolling the page. So the
         cancellation lives inside `move()`, the one place that knows whether the menu is
         open. --}}
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
        :aria-expanded="isOpen ? 'true' : 'false'"
        {{-- `true`, not `menu`. `aria-haspopup="menu"` promises the APG menu keyboard model —
             one tab stop on the menu, arrow keys between items, Escape and a focus exit that
             close it — and this panel implements none of that: its actions are ordinary links
             and buttons, each with its own tab stop. Announcing a model a reader then cannot
             use is the same defect the comment below warns about, one level up. --}}
        aria-haspopup="true"
        aria-label="{{ $label }}"
        data-wk-fab-trigger
        {{-- The box reads --size-wk-fab rather than a literal, for the reason `fab.button`
             states beside its own copy of this line: a developer laying out around a fixed
             control has to be able to read its size, and `wk-fab-clearance` does exactly that.
             With a literal here the speed dial's trigger would stay 56px on every viewport
             while the standalone button steps down on a phone. --}}
        class="flex h-[var(--size-wk-fab)] w-[var(--size-wk-fab)] cursor-pointer items-center justify-center rounded-[var(--radius-wk-full)] bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)] shadow-[var(--shadow-wk-lg)] transition-transform duration-[var(--transition-wk-duration)] hover:brightness-110 focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-offset-[var(--color-wk-ring-offset)]"
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
                :aria-hidden="isOpen ? 'false' : 'true'"
                :data-shown="isOpen ? 'true' : 'false'"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </span>
            <span
                class="wk-fab-icon col-start-1 row-start-1"
                :aria-hidden="isOpen ? 'true' : 'false'"
                :data-shown="isOpen ? 'false' : 'true'"
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
        x-show="isOpen"
        x-cloak
        role="group"
        {{-- A single method call, not an inline `if`. Alpine's CSP build parses expressions,
             not statements, so an `if` here would never be evaluated on that bundle and the
             panel would stop closing. `php artisan wirekit:csp-audit` reports such a binding. --}}
        @focusout="closeIfFocusLeft($event)"
        aria-label="{{ $label }}"
        data-wk-fab-actions
        class="wk-fab-actions flex flex-col-reverse items-center gap-[var(--gap-wk-sm)]"
    >
        {{ $slot }}
    </div>
</div>
