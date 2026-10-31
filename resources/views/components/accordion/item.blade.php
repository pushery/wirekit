{{-- optimistic-ui: n/a — sub-component
     Open state lives on the accordion. --}}
@props([
    'id' => null,
    'title' => '',
    // The language of `title` where it differs from the page's, written on the title alone, so the
    // panel keeps the page's language.
    'titleLang' => null,
    // Starts open, and is rendered open, so nothing moves when the script starts. In single mode
    // the first item with `open` is the one that stays open. Unset, it follows
    // `components.accordion.item.open` in multiple mode; in single mode, where one panel is open at
    // a time, a default cannot say which, so only an item that names `open` starts open.
    'open' => null,
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
    // Whether a closed panel stays findable by the browser's find in page; set on the accordion.
    'findable' => config('wirekit.components.accordion.findable', true),
    // Whether this panel animates its height. Set on the accordion, read here through @aware.
    'animate' => config('wirekit.components.accordion.animate', true),
    // One panel at a time or several, set on the accordion: a default for `open` applies to several only.
    'mode' => config('wirekit.components.accordion.mode', 'single'),
])

@php
    // `@aware` takes a key from any ancestor that holds it; these are read from the family only
    // (Support\AwareProp).
    $variant = \Pushery\WireKit\Support\AwareProp::read('variant', ['accordion', 'faq'], config('wirekit.components.accordion.variant', 'bordered'));
    $size = \Pushery\WireKit\Support\AwareProp::read('size', ['accordion', 'faq'], config('wirekit.components.accordion.size', 'md'));
    $level = \Pushery\WireKit\Support\AwareProp::read('level', ['accordion', 'faq'], 3);
    $findable = \Pushery\WireKit\Support\AwareProp::read('findable', ['accordion', 'faq'], config('wirekit.components.accordion.findable', true));
    $animate = \Pushery\WireKit\Support\AwareProp::read('animate', ['accordion', 'faq'], config('wirekit.components.accordion.animate', true));
    $mode = \Pushery\WireKit\Support\AwareProp::read('mode', ['accordion'], config('wirekit.components.accordion.mode', 'single'));

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('accordion.item', $attributes->getAttributes());

    // The language of the title as HTML reads it: `pt_BR` is the tag `pt-BR`, and a blank one is none.
    $titleLang = \Pushery\WireKit\Support\LanguageTag::of($titleLang);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['variant', 'size', 'level', 'findable', 'animate', 'mode']);
    $findable = \Pushery\WireKit\Support\BooleanProp::from($findable, true);
    $animate = \Pushery\WireKit\Support\BooleanProp::from($animate, true);
    // `open="false"` on an unbound tag is the string "false", which is truthy. Unset, the default
    // from the configuration opens the item in multiple mode only.
    $open = $open === null
        ? $mode === 'multiple' && \Pushery\WireKit\Support\BooleanProp::from(config('wirekit.components.accordion.item.open', false))
        : \Pushery\WireKit\Support\BooleanProp::from($open, false, 'accordion.item', 'open');
@endphp


@php
    use Pushery\WireKit\WireKit;

    // Each item needs a stable id so Alpine can track open/closed state in the
    // parent's `opened` array, and so aria-controls / aria-labelledby can pair.
    // It is the same on every render, and a random id would not be. Both halves of
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
        ? 'px-[var(--padding-wk-x-lg)] py-[var(--padding-wk-x-lg)]'
        : 'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-x-md)]';

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
        'hover:bg-[var(--color-wk-bg-active)]',
        'focus-visible:outline-hidden',
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
    $levelValue = in_array(\Pushery\WireKit\Support\NumericProp::int($level, 0), [1, 2, 3, 4, 5, 6], true) ? \Pushery\WireKit\Support\NumericProp::int($level, 0) : 3;
    if ($levelValue !== \Pushery\WireKit\Support\NumericProp::int($level, 0)) {
        WireKit::validateProp('accordion', 'level', \Pushery\WireKit\Support\NumericProp::text($level), ['1', '2', '3', '4', '5', '6']);
    }
@endphp

{{-- The item's id on its own element, so a link to `#…` reaches the item; the accordion opens it.
     `data-wk-accordion-open` tells the accordion which items start open (resources/js/components/accordion.js). --}}
<div id="{{ $itemId }}" data-wk-accordion-item{{ $open ? ' data-wk-accordion-open' : '' }} {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$wrapperClasses])) }}>
    {{-- Header button: delegates to toggle() defined on the parent accordion's x-data scope.
         Alpine resolves methods via scope chain — no $root prefix needed. --}}
    <h{{ $levelValue }} data-wk-prose-skip>
        <button
            type="button"
            id="{{ $buttonId }}"
            aria-controls="{{ $panelId }}"
            aria-expanded="{{ $open ? 'true' : 'false' }}"
            :aria-expanded="isOpen({{ \Pushery\WireKit\Support\AlpinePayload::from($itemId) }}) ? 'true' : 'false'"
            @click="toggle({{ \Pushery\WireKit\Support\AlpinePayload::from($itemId) }}, $el)"
            {{-- wk-accordion-trigger is a marker, carried outside the class seam so an
                 override cannot remove it: the stylesheet floors its height to the touch
                 target under `pointer: coarse`, where the row is otherwise shorter.

                 A height floor rather than `wk-touch-target`, and the difference matters for a
                 full-width row: that class centers a 44x44 box, so on a wide trigger the tap
                 area would grow in the middle and stay short at both ends -- a T, not a
                 rectangle. A row is not an icon. --}}
            class="wk-accordion-trigger {{ $buttonClasses }}"
        >
            {{-- Title takes the remaining row width and wraps; min-w-0 unlocks
                 text wrapping inside a flex child (default min-content prevents
                 break inside long unbroken words). --}}
            <span class="flex-1 min-w-0" @if(filled($titleLang) && $title !== '') lang="{{ $titleLang }}" @endif>{{ $title !== '' ? $title : ($header ?? '') }}</span>
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

    @if($findable)
    {{-- FINDABLE PANEL. Closed, it is `hidden="until-found"` where the engine supports it, so the
         browser's find in page can match its text and open it; `x-on:beforematch` opens the item's
         own state before the browser takes the attribute off, so the next render keeps it open.
         Where the engine does not support it, `x-wk-findable` hides it exactly as `x-show` did.

         The padding and the border moved to the element inside: a closed until-found panel keeps
         its box, and they would paint as an empty strip under every closed item. No `x-cloak`,
         which would stop the browser from revealing it. See utils/findable.js. --}}
    <div
        id="{{ $panelId }}"
        role="region"
        aria-labelledby="{{ $buttonId }}"
        {{-- Both spellings are written out rather than assembled, and the duplication is the
             point. A directive NAME built in PHP is invisible to every scanner that looks for
             this one by name — the CSP audit among them — so the modifier would be unreachable
             to exactly the checks that exist to find it. Two literal lines cost nothing. --}}
        @if($animate)
        x-wk-findable.collapse="isOpen({{ \Pushery\WireKit\Support\AlpinePayload::from($itemId) }})"
        @else
        x-wk-findable="isOpen({{ \Pushery\WireKit\Support\AlpinePayload::from($itemId) }})"
        @endif
        x-on:beforematch="reveal({{ \Pushery\WireKit\Support\AlpinePayload::from($itemId) }})"
        {{-- Drawn open, it says so: in single mode the start closes the second item asking for
             it, and without `hidden` the panel would read as one found before the script ran. --}}
        @if($open) data-wk-rendered-open @else hidden="until-found" @endif
    >
        <div class="{{ $panelClasses }}">
            {{ $slot }}
        </div>
    </div>
    @else
    {{-- Panel: only rendered when open. x-show toggles display; role=region
         pairs with aria-labelledby so screen readers know which heading names it. --}}
    <div
        id="{{ $panelId }}"
        role="region"
        aria-labelledby="{{ $buttonId }}"
        x-show="isOpen({{ \Pushery\WireKit\Support\AlpinePayload::from($itemId) }})"
        {{-- The other half of the same prop. `x-wk-findable.collapse` exists because `x-collapse`
             only works with `x-show` — and this branch IS `x-show`, so here the plugin is the
             right tool and the same prop reaches both paths rather than one of them. --}}
        @if($animate) x-collapse @endif
        {{-- A closed panel stays hidden until the script starts; an open one paints with the page. --}}
        @unless($open) x-cloak @endunless
        class="{{ $panelClasses }}"
    >
        {{ $slot }}
    </div>
    @endif
</div>
