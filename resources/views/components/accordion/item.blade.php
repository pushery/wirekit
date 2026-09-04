{{-- optimistic-ui: n/a — sub-component
     Open state lives on the accordion. --}}
@props([
    'id' => null,
    'title' => '',
    'scope' => null,
])

{{-- Read the parent accordion's `variant` + `size` so item-level chrome (the
     `separated` card outline) and density (`lg` padding) follow the container's
     choice. @aware is Laravel's canonical parent→child prop bridge; the defaults
     mirror the same config keys the parent reads, so a global config override of
     the accordion variant/size stays consistent between container and items
     even when the developer doesn't pass the prop on the tag. --}}
@aware([
    'variant' => config('wirekit.components.accordion.variant', 'bordered'),
    'size' => config('wirekit.components.accordion.size', 'md'),
    // The heading level the trigger is wrapped in. It comes from the CONTAINER
    // because an outline is a property of the page, not of one row: every item in
    // one accordion sits at the same depth, so letting rows disagree would only
    // let a developer build a broken outline one item at a time.
    'level' => 3,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('accordion.item', $attributes->getAttributes());

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['variant', 'size', 'level']);
@endphp


@php
    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // Each item needs a stable id so Alpine can track open/closed state in the
    // parent's `opened` array, and so aria-controls / aria-labelledby can pair.
    // STABLE across re-renders, which the old `Str::random(6)` was not. Both halves of
    // BOTH pairings descend from this one value — the button carries id + aria-controls,
    // the panel carries id + aria-labelledby — so a re-mint breaks the accordion's entire
    // relationship in one step, while every attribute stays well-formed. An item inside a
    // keyed loop or behind an @if is re-rendered on its own, which is the ordinary case.
    $itemId = \Pushery\WireKit\Support\DomId::unique($id, 'wk-accordion-item-');
    $buttonId = $itemId . '-button';
    $panelId = $itemId . '-panel';

    // Density. 'lg' bumps trigger/panel padding one step and the trigger text
    // size; 'md' is the original padding (back-compat). NOTE: full literal class
    // strings (not interpolated) so Tailwind's text scanner compiles them — a
    // built class like "px-[var(--padding-wk-x-lg)]" must appear verbatim in the
    // file (the dynamic-class-composition pitfall from the drift-audit system).
    $itemSize = $size === 'lg' ? 'lg' : 'md';
    $buttonPad = $itemSize === 'lg'
        ? 'px-[var(--padding-wk-x-lg)] py-[var(--padding-wk-y-lg)] text-[length:var(--text-wk-lg)]'
        : 'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] text-[length:var(--text-wk-md)]';
    $panelPad = $itemSize === 'lg'
        ? 'px-[var(--padding-wk-x-lg)] py-[var(--padding-wk-y-lg)]'
        : 'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]';

    // In `separated` mode each item is its own card (the container draws no
    // border/bg), so the item carries the chrome. bordered/flush leave this
    // empty — the container's border + row dividers handle separation.
    $itemVariant = in_array($variant, ['bordered', 'flush', 'separated'], true) ? $variant : 'bordered';
    $wrapperClasses = $itemVariant === 'separated' ? implode(' ', [
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-lg)]',
        'overflow-hidden',
        'bg-[var(--color-wk-bg-elevated)]',
    ]) : '';

    // Trigger button — full-width, left-aligned, clickable row that toggles
    // the item and swaps the chevron direction based on open state.
    $buttonClasses = WireKit::resolveClasses('accordion.item', 'button', implode(' ', [
        'flex items-center justify-between w-full gap-[var(--padding-wk-x-md)]',
        $buttonPad,
        'text-left',
        'text-[color:var(--color-wk-text)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-inset',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
    ]), $scope);

    // Panel — revealed content region. Uses aria role="region" + aria-labelledby
    // so AT announces it as a titled region when user arrows into it.
    $panelClasses = WireKit::resolveClasses('accordion.item', 'panel', implode(' ', [
        $panelPad,
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'border-t-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
    ]), $scope);

    // Decorative chevron — rotates 180° when item is open. aria-hidden
    // because the open state is already conveyed by aria-expanded on the button.
    // shrink-0 pins the icon at its intrinsic 16x16 even when the title text
    // wraps to multiple lines (without it, flexbox shrinks the SVG to a sliver
    // because <svg> has no min-width constraint).
    $chevronClasses = 'shrink-0 w-4 h-4 text-[color:var(--color-wk-text-subtle)] transition-transform duration-[var(--transition-wk-duration)]';

    // Fall back to 3 rather than to validateProp's first allowed value: a bad
    // `level` must never turn a row's trigger into the page's <h1>, which is a
    // worse outline than the one the default gives. Same shape, and the same
    // reason, as the empty-state title.
    $levelValue = in_array((int) $level, [1, 2, 3, 4, 5, 6], true) ? (int) $level : 3;
    if ($levelValue !== (int) $level) {
        WireKit::validateProp('accordion', 'level', (string) $level, ['1', '2', '3', '4', '5', '6']);
    }
@endphp

<div {{ $attributes->class([$wrapperClasses]) }}>
    {{-- Header button: delegates to toggle() defined on the parent accordion's x-data scope.
         Alpine resolves methods via scope chain — no $root prefix needed. --}}
    <h{{ $levelValue }}>
        <button
            type="button"
            id="{{ $buttonId }}"
            aria-controls="{{ $panelId }}"
            :aria-expanded="isOpen({{ \Pushery\WireKit\Support\AlpinePayload::from($itemId) }}) ? 'true' : 'false'"
            @click="toggle({{ \Pushery\WireKit\Support\AlpinePayload::from($itemId) }})"
            class="{{ $buttonClasses }}"
        >
            {{-- Title takes the remaining row width and wraps; min-w-0 unlocks
                 text wrapping inside a flex child (default min-content prevents
                 break inside long unbroken words). --}}
            <span class="flex-1 min-w-0">{{ $title !== '' ? $title : ($header ?? '') }}</span>
            <svg
                class="{{ $chevronClasses }}"
                :class="isOpen({{ \Pushery\WireKit\Support\AlpinePayload::from($itemId) }}) ? 'rotate-180' : ''"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>
    </h{{ $levelValue }}>

    {{-- Panel: only rendered when open. x-show toggles display; role=region
         pairs with aria-labelledby so screen readers know which heading names it. --}}
    <div
        id="{{ $panelId }}"
        role="region"
        aria-labelledby="{{ $buttonId }}"
        x-show="isOpen({{ \Pushery\WireKit\Support\AlpinePayload::from($itemId) }})"
        x-cloak
        class="{{ $panelClasses }}"
    >
        {{ $slot }}
    </div>
</div>
