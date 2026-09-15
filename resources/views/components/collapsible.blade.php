{{-- optimistic-ui: n/a — client-only
     Its state is open state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    // Trigger label (string). For rich trigger content, pass a named "trigger" slot
    // instead — both surface as the $trigger variable, so the template renders either.
    'trigger' => null,
    'open' => false,
    // Closed content stays findable by the browser's find in page, which opens the disclosure a
    // match lands in. Only where the engine supports it; switch it off for a spoiler.
    'findable' => config('wirekit.components.collapsible.findable', true),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('collapsible', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $open = BooleanProp::from($open, false);
    $findable = BooleanProp::from($findable, true);

    // A standalone single disclosure: one trigger + one collapsible region. Unlike
    // <x-wirekit::accordion> there is no card chrome and no group coordination — it is
    // the bare WAI-ARIA Disclosure pattern (button[aria-expanded] controls a region).
    // For smooth height animation we lean on Alpine's x-collapse plugin, which every
    // full-catalog WireKit bundle registers. It says so because the sentence used to
    // be a guess: nothing imported the plugin, and the claim is why nobody checked.
    // A per-render random id makes Livewire's morph treat the content region as a NEW
    // node on every round trip: it keys by `el.id`, finds no match, and replaces the live
    // node with a clone of the server-rendered one. Three things follow from that single
    // event — focus inside the developer's slot moves to <body>, anything typed into an
    // input they did not bind with wire:model is gone, `x-collapse` replays its height
    // transition instead of holding the open height, and the clone still carries the
    // literal `x-cloak` (Alpine strips it only after init), so an OPEN panel flashes
    // hidden for a few frames.
    //
    // A caller-supplied id already solved it. The default is now seeded from the trigger
    // label, which is the only stable identity a collapsible has — and the one thing
    // about it that does not change between renders.
    // Stable across renders, and unique on the page — two properties one hash cannot give.
    //
    // `stableId()` derives the id from the TRIGGER TEXT, which is what makes it survive a
    // Livewire morph. It also makes two disclosures with the same trigger — "Details" twice
    // on one page, which is the ordinary case in a list — share an id, so the second
    // trigger's `aria-controls` resolved to the FIRST one's panel: expanding one announced
    // that the other had opened.
    //
    // The deduper takes the stable value as its base and appends `-2` to a repeat, so the
    // first keeps the readable id and the second stops pointing at it.
    $uid = \Pushery\WireKit\Support\DomId::unique(
        // A caller id NAMES THE COMPONENT and stays on the root, through the attribute bag. The panel
        // takes an id derived from it: taking the same id put two elements under one name, and
        // aria-controls pointed at whichever a lookup found first, which was the root.
        $attributes->get('id') ? $attributes->get('id').'-panel' : \Pushery\WireKit\WireKit::stableId(
            'wk-collapsible',
            is_string($trigger) || $trigger instanceof \Stringable ? trim((string) $trigger) : null
        ),
        'wk-collapsible-'
    );
    $openBool = (bool) $open;

    $rootClasses = WireKit::resolveClasses('collapsible', 'base', 'font-[family-name:var(--font-wk-sans)]', $scope);

    $triggerClasses = WireKit::resolveClasses('collapsible', 'trigger', implode(' ', [
        'flex items-center justify-between gap-[var(--padding-wk-x-sm)] w-full text-left',
        'text-[length:var(--text-wk-md)] font-[number:var(--font-wk-body-weight)]',
        'text-[color:var(--color-wk-text)]',
        'cursor-pointer',
        'rounded-[var(--radius-wk-sm)]',
        'focus-visible:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    // pt-[var(--padding-wk-y-lg)] (0.75rem): the trigger→content gap must read
    // at least as large as the VISUAL inter-item gap inside the content. A
    // `stack gap="sm"` is a nominal 0.5rem, but block content items (checkbox
    // rows, cards) inflate the perceived gap to ~0.9rem once their own box
    // heights are included — so a bare 0.5rem top padding left the first item
    // looking crammed against the trigger while the items below breathed twice
    // as much. 0.75rem closes that imbalance without over-spacing plain-text
    // disclosures (the FAQ case).
    $contentClasses = WireKit::resolveClasses('collapsible', 'content', implode(' ', [
        'pt-[var(--padding-wk-y-lg)]',
        'text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

{{-- `wk-collapsible` is a marker with no rules of its own. The reduced-motion clamp matches a `wk-`
     class token and its descendants, and the chevron and the collapsing panel sit under this root.
     It stays outside `resolveClasses()`, so a scoped base class list cannot drop it. --}}
<div x-data="{ open: {{ \Pushery\WireKit\Support\AlpinePayload::from($openBool) }} }" {{ $attributes->class(['wk-collapsible', $rootClasses]) }}>
    {{-- Trigger — a real <button> so it is keyboard-operable (Enter/Space) by default.
         aria-expanded announces state; aria-controls links it to the region below. --}}
    <button
        type="button"
        x-on:click="open = !open"
        :aria-expanded="open ? 'true' : 'false'"
        aria-controls="{{ $uid }}"
        class="{{ $triggerClasses }}"
    >
        <span class="flex-1 min-w-0">{{ $trigger }}</span>
        {{-- Chevron — rotates 180° when open. Decorative. --}}
        <svg
            class="w-4 h-4 shrink-0 transition-transform duration-[var(--transition-wk-duration)]"
            :class="open ? 'rotate-180' : ''"
            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    {{-- Collapsible region — smooth height transition via x-collapse. x-cloak hides it
         until Alpine initializes so a closed disclosure never flashes open on load. --}}
    @if($findable)
        {{-- Findable: `hidden="until-found"` while closed where the engine supports it, with the
             height animated by `x-wk-findable.collapse` in place of `x-collapse`, which only works
             with `x-show`. The padding moved to the element inside, since a closed until-found
             panel keeps its box. See utils/findable.js. --}}
        <div
            id="{{ $uid }}"
            x-wk-findable.collapse="open"
            x-on:beforematch="open = true"
            @unless($openBool) hidden="until-found" @endunless
        >
            <div class="{{ $contentClasses }}">
                {{ $slot }}
            </div>
        </div>
    @else
        <div id="{{ $uid }}" x-show="open" x-collapse x-cloak class="{{ $contentClasses }}">
            {{ $slot }}
        </div>
    @endif
</div>
