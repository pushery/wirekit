@props([
    // What this action does. The button is an icon, so this is its whole name.
    'label' => '',
    // Drop the label from the SCREEN while keeping it as the accessible name —
    // the plain icon-only speed dial. Same meaning the prop carries on checkbox,
    // so the name is reused rather than reinvented.
    'hideLabel' => false,
    'icon' => null,
    'href' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy, so
    // `hideLabel="false"` would mean the opposite of what the call site reads as.
    $hideLabel = BooleanProp::from($hideLabel, false);

    $tag = $href ? 'a' : 'button';

    // An icon-only action with no label has no accessible name at all — a menu
    // item a screen reader can only announce as "button". Nothing downstream can
    // recover that, so say it where the developer will see it (throws in debug,
    // logs in production — the house strictness gate).
    if ($label === '' && $icon !== null) {
        WireKit::validateProp('fab.action', 'label', '', ['a non-empty label describing the action']);
    }

    $classes = WireKit::resolveClasses('fab.action', 'base', implode(' ', [
        'wk-fab-action',
        // `relative` anchors the hover/focus label below, which is absolutely
        // positioned so it never affects the layout of the action column.
        'relative flex h-11 w-11 cursor-pointer items-center justify-center rounded-[var(--radius-wk-full)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)]',
        'shadow-[var(--shadow-wk-md)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-2',
    ]), $scope);
@endphp

{{-- role="menuitem" to match the parent's role="menu": the two have to agree, or
     a screen reader announces a popup whose contents are not menu items.

     h-11/w-11 is 44px — the touch minimum, and these are the smallest targets on
     the screen. --}}
<{{ $tag }}
    @if($href) href="{{ $href }}" @else type="button" @endif
    role="menuitem"
    {{-- Only emit the name when there IS one. `aria-label=""` is worse than no
         aria-label at all: it suppresses the fallback to the element's own text
         content, so an action rendering a text slot would lose the name it
         already had. With no label AND no text (the icon-only case) nothing can
         invent one — the strict-mode validation below says so out loud instead of
         shipping a nameless menu item. --}}
    @if($label !== '') aria-label="{{ $label }}" @endif
    data-wk-fab-action
    {{ $attributes->class([$classes.' wk-fab-action-labeled']) }}
>
    @if($icon)
        <x-wirekit::icon :name="$icon" class="h-5 w-5" />
    @else
        {{ $slot }}
    @endif

    {{-- The label, made visible on hover and on keyboard focus.

         An icon-only action carries its name in aria-label, so a screen reader
         has always been fine here. A sighted person using a mouse had nothing:
         three circles, and the only way to learn what they do was to click one.

         It lives INSIDE the menuitem rather than wrapping it, and that is the
         constraint the whole shape follows from. The parent is role="menu" and
         its children must be role="menuitem"; a wrapper element between the two
         breaks that relationship, which is exactly why the obvious answer —
         putting a tooltip component around the action — is not available here.

         On focus as well as hover, or it is a mouse-only affordance and the
         keyboard user is back where the screen-reader user started.

         pointer-events-none so the label can never intercept a click meant for
         the button beneath it, and so it does not obscure interaction with
         whatever it overlaps (WCAG 1.4.13's concern). It cannot be hovered
         itself, which is fine: it is anchored to the trigger and stays for as
         long as the trigger is hovered.

         Rendered only when there is a name to show — an action using the slot
         for its own markup already reads as whatever that markup says — and only
         when the caller has not asked for the icon-only treatment. `hideLabel`
         takes the label off the SCREEN; it never touches the accessible name,
         which stays required. --}}
    @if($label !== '' && ! $hideLabel)
        <span
            aria-hidden="true"
            class="wk-fab-action-label pointer-events-none absolute end-full me-[var(--gap-wk-sm)] whitespace-nowrap rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-inverse)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-inverse)] opacity-0 shadow-[var(--shadow-wk-md)] transition-opacity duration-[var(--transition-wk-duration)]"
        >{{ $label }}</span>
    @endif
</{{ $tag }}>
