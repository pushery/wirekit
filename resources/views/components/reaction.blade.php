{{-- optimistic-ui: supported
     The case the plan called the textbook one, and it was blocked until the
     accessible name stopped being built on the server: `trans_choice` there
     names the count the SERVER saw, so an optimistic flip would show six and
     announce five, and the rollback would restore a number the reader was never
     told about. The forms and the locale travel now, and Intl.PluralRules
     chooses.

     Only `active` is state. The count is derived from it, which is what makes
     the rollback correct without anything having to remember to undo two
     things. --}}
@props([
    // The Livewire method this reaction should call, when it should show the new
    // state before the server has agreed to it. Null leaves the component
    // exactly as it has always rendered: server markup, no Alpine.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
    'emoji' => null,
    'count' => 0,
    'active' => false,
    'users' => [],
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('reaction', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $active = BooleanProp::from($active, false);

    $userList = is_array($users) ? $users : [];

    // The server-side name stays the FIRST paint and the no-JS answer. It is
    // replaced by the client's the moment Alpine runs, and only when this
    // component is optimistic — a reaction that cannot change has no reason to
    // compute its own name.
    $ariaLabel = $emoji . ', ' . trans_choice('wirekit::{0} no reactions|{1} :count person reacted|[2,*] :count people reacted', $count, ['count' => $count]);

    $reactionOptimistic = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'active' => (bool) $active,
        'count' => (int) $count,
        'emoji' => $emoji,
        'phrases' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{0} no reactions|{1} :count person reacted|[2,*] :count people reacted'),
        'locale' => str_replace('_', '-', app()->getLocale()),
    ]);

    $reactionLayer = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'active',
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
    ]);

    $baseClasses = WireKit::resolveClasses('reaction', 'base', implode(' ', [
        'inline-flex items-center gap-x-1',
        'h-7 px-2',
        'rounded-[var(--radius-wk-full)]',
        'border-[length:var(--border-wk-width)]',
        'text-[length:var(--text-wk-sm)]',
        'font-[family-name:var(--font-wk-sans)]',
        'transition-[background-color,border-color,box-shadow]',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'cursor-pointer select-none',
    ]), $scope);

    $stateClasses = $active
        ? implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-accent)_12%,var(--color-wk-bg))]',
            'border-[color-mix(in_srgb,var(--color-wk-accent)_35%,transparent)]',
            'text-[color:var(--color-wk-accent-content)]',
        ])
        : implode(' ', [
            'bg-[var(--color-wk-bg-muted)]',
            'border-transparent',
            'text-[color:var(--color-wk-text-muted)]',
            'hover:border-[var(--color-wk-border)]',
            'hover:bg-[var(--color-wk-bg-elevated)]',
        ]);
@endphp

@if($reactionOptimistic)
    {{-- The component owns `active`; the optimistic layer nests inside it and
         binds to that one boolean, so the count and the name follow a single
         rollback. The button sits inside the layer to reach its run(). --}}
<span x-data="wirekitReaction({{ $reactionOptimistic }})" class="inline-flex">
<span x-data="wirekitOptimistic({{ $reactionLayer }})" class="inline-flex items-center gap-x-1">
@endif
<button
    type="button"
    aria-pressed="{{ $active ? 'true' : 'false' }}"
    @if($reactionOptimistic)
        x-bind:aria-pressed="active ? 'true' : 'false'"
        x-bind:aria-label="label"
        x-bind:aria-busy="isPending"
        x-on:click="toggle()"
    @endif
    @if(count($userList) > 0)
        aria-describedby="reaction-users-{{ md5($emoji . implode(',', $userList)) }}"
    @endif
    {{-- aria-label via merge so a caller's aria-label OVERRIDES the default —
         a hardcoded attribute plus a separate $attributes bag renders a
         duplicate aria-label that the browser ignores (first wins). --}}
    {{ $attributes->merge(['aria-label' => $ariaLabel])->class([$baseClasses, $stateClasses]) }}
>
    <span class="font-[font-variant-emoji:emoji]" aria-hidden="true">{{ $emoji }}</span>
    @if($reactionOptimistic)
        {{-- Shown whenever the live count is above zero, which the server-only
             variant decides once at render time. --}}
        <span class="font-[number:var(--font-wk-heading-weight)] tabular-nums" x-show="count > 0" x-text="count">{{ $count > 0 ? $count : '' }}</span>
    @elseif($count > 0)
        <span class="font-[number:var(--font-wk-heading-weight)] tabular-nums">{{ $count }}</span>
    @endif
    @if(count($userList) > 0)
        <span id="reaction-users-{{ md5($emoji . implode(',', $userList)) }}" class="sr-only">
            {{ implode(', ', $userList) }}
        </span>
    @endif
</button>
@if($reactionOptimistic)
    {{-- Pre-existing and empty: a live region that arrives with its text is a
         new node, and nothing is announced at all. --}}
    <span class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></span>
</span>
</span>
@endif
