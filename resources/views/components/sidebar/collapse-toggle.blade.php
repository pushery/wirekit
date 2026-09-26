{{-- optimistic-ui: n/a — client-only
     It asks the sidebar to fold or unfold. The state is the sidebar's, it never leaves the
     browser, and there is no server answer for an optimistic update to anticipate. --}}
@props([
    // The `id` of the sidebar this button folds. Unset, it folds every sidebar on the page, which
    // is right for the shell with one sidebar that nearly every application is. With two, name
    // the one this button belongs to, or both would fold together.
    'for' => null,
    // The side the sidebar stands on, the value passed to the sidebar's own `side`. It turns the
    // arrow the way the sidebar's own control turns it, so the two never read as different
    // buttons.
    'side' => 'start',
    // What to draw before the sidebar has announced itself. The sidebar announces its state once it
    // starts, so these four only decide the first paint, and they take exactly the values the
    // sidebar takes: pass the same `collapsed`, `default-collapsed`, `persist` and
    // `persist-driver`, and with the cookie driver the first paint is already the reader's choice.
    'collapsed' => null,
    'defaultCollapsed' => null,
    'persist' => null,
    'persistDriver' => 'local',
    // Show the button at every width. By default it is hidden below the shell's breakpoint, where
    // a sidebar in the shell's drawer shows its names whatever the reader chose and has nothing to
    // fold. Set it for a sidebar that does not sit in an app shell.
    'always' => false,
    // Where the button's name appears on hover. In a rail's foot `right` keeps it clear of the
    // entries above.
    'tooltipPlacement' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('sidebar.collapse-toggle', $attributes->getAttributes());

    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\PersistedCookie;
    use Pushery\WireKit\WireKit;

    $side = WireKit::validateProp('sidebar.collapse-toggle', 'side', $side, ['start', 'end']);
    $persistDriver = WireKit::validateProp('sidebar.collapse-toggle', 'persistDriver', $persistDriver, ['local', 'cookie']);
    $always = BooleanProp::from($always, false);
    $for = is_string($for) && $for !== '' ? $for : null;

    // The first paint, resolved in the sidebar's order: an explicit value, then the stored one the
    // server can read, then the first-visit default. Before the boolean cast, because the cast
    // turns "not set" into false and a stored value could then no longer fill in.
    if ($collapsed === null && $persistDriver === 'cookie' && $persist !== null) {
        $collapsed = PersistedCookie::flag((string) $persist);
    }

    $defaultCollapsed = BooleanProp::from($defaultCollapsed, false);
    $collapsed = BooleanProp::from($collapsed, $defaultCollapsed);

    $expandName = __('wirekit::Expand sidebar');
    $collapseName = __('wirekit::Collapse sidebar');

    $placement = is_string($tooltipPlacement) && $tooltipPlacement !== ''
        ? $tooltipPlacement
        : config('wirekit.components.tooltip.placement', 'top');

    // The sidebar's own control, class for class, so the two are one button in two places.
    $classes = WireKit::resolveClasses('sidebar.collapse-toggle', 'base', implode(' ', [
        'inline-flex items-center justify-center shrink-0',
        'p-1 rounded-[var(--radius-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors duration-[var(--transition-wk-duration)] cursor-pointer',
    ]), $scope);
@endphp

{{-- The scope sits on the wrapper rather than on the button because the name is shown twice: on
     the button and in the tooltip, whose panel is teleported out of the button's subtree and
     resolves `collapsed` through this scope.

     `wk-sidebar-collapse-toggle` is also what the reduced-motion clamp matches, which reaches an
     element only through a `wk-` class on it or an ancestor. --}}
<div
    x-data="wirekitSidebarCollapseToggle({ id: {{ $for === null ? 'null' : AlpinePayload::from($for) }}, collapsed: {{ $collapsed ? 'true' : 'false' }} })"
    {{ $attributes->class(['wk-sidebar-collapse-toggle inline-flex', 'max-lg:hidden' => ! $always]) }}
>
    {{-- Named on hover as well as to a screen reader, like the sidebar's own control: the glyph
         alone is a guess until it is pressed. The button is already a tab stop, so the tooltip
         adds none. --}}
    <x-wirekit::tooltip :focusable-trigger="false" :placement="$placement">
        <x-slot:content>
            <span x-text="collapsed ? {{ AlpinePayload::from($expandName) }} : {{ AlpinePayload::from($collapseName) }}">{{ $collapsed ? $expandName : $collapseName }}</span>
        </x-slot:content>
        <button
            type="button"
            x-on:click="toggle()"
            data-wk-sidebar-collapse-toggle
            {{-- Static as well as bound: until Alpine starts, the button holds nothing but a
                 decorative glyph, and a screen reader, or a server-side accessibility check,
                 would meet a nameless control. Alpine owns both attributes after that. --}}
            aria-expanded="{{ $collapsed ? 'false' : 'true' }}"
            aria-label="{{ $collapsed ? $expandName : $collapseName }}"
            :aria-expanded="collapsed ? 'false' : 'true'"
            :aria-label="collapsed ? {{ AlpinePayload::from($expandName) }} : {{ AlpinePayload::from($collapseName) }}"
            @if($for !== null) aria-controls="{{ $for }}" @endif
            class="{{ $classes }}"
        >
            @include('wirekit::components.partials.sidebar-collapse-glyph')
        </button>
    </x-wirekit::tooltip>
</div>
