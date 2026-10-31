{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // A sentence under the heading. A `description` slot takes markup.
    'description' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('card.header', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Header section — title area with bottom border separator
    $classes = WireKit::resolveClasses('card.header', 'base', implode(' ', [
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-x-lg)]',
        'border-b-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-lg)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

@php
    // — iconSlot symmetry: optional named slot for a header
    // icon/avatar/illustration. Renders before the default slot content.
    $hasIconSlot = isset($iconSlot) && $iconSlot->hasActualContent();

    // The buttons at the end of the head, and the sentence under the heading. With either, the head
    // lays out as `page-header` does: the heading column asks for 16rem, and the buttons stand
    // beside it while the head has room and drop under it where it has not. The break is the
    // head's own width, not the window's, since a card stands in a column of its own. Without
    // either the head is the row it always was.
    $hasActions = isset($actions) && $actions->hasActualContent();

    // A card that is a link or a button is one control, and its head takes no buttons. With `href`
    // the card renders as an `<a>`, with `as="button"` as a `<button>`, and `role="button"` makes it
    // one: a button inside is a control inside a control, which HTML does not allow, and a screen
    // reader reads what a button holds as its name, so the buttons inside are not reached as
    // controls of their own. Read from this head's own card, the address as the card reads it, and
    // reported as `collapsible` is on a card with `href`: the card stays the one control it was
    // written as. A heading that links gives such a card its destination and keeps the buttons.
    $cardHref = \Pushery\WireKit\Support\AwareProp::read('href', ['card']);
    $cardHref = is_int($cardHref) || is_float($cardHref) ? (string) $cardHref : $cardHref;
    $cardAs = \Pushery\WireKit\Support\AwareProp::read('as', ['card']);
    $cardIsControl = ((is_string($cardHref) || $cardHref instanceof \Stringable || $cardHref instanceof \BackedEnum) && \Pushery\WireKit\Support\UrlProp::text($cardHref) !== null)
        || (is_string($cardAs) && $cardAs === 'button')
        || \Pushery\WireKit\Support\AwareProp::read('role', ['card']) === 'button';

    if ($hasActions && $cardIsControl) {
        \Pushery\WireKit\Support\StrictnessGate::reject('card.header', 'actions', 'a slot', 'a card without href and not a button: such a card is one control, and a button inside it would be a control inside a control', '');
        $hasActions = false;
    }

    $hasDescription = $description instanceof \Illuminate\View\ComponentSlot ? $description->hasActualContent() : filled($description);
    $laidOut = $hasActions || $hasDescription;

    $descriptionClasses = WireKit::resolveClasses('card.header', 'description', implode(' ', [
        'mt-[var(--space-wk-xs)]',
        'font-[number:var(--font-wk-body-weight)]',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
    ]), $scope);

    // The buttons keep the body's type, so a link or a badge among them does not take the heading's.
    $actionsClasses = WireKit::resolveClasses('card.header', 'actions', implode(' ', [
        'flex flex-wrap items-center gap-[var(--gap-wk-sm)]',
        'font-[number:var(--font-wk-body-weight)]',
        'text-[length:var(--text-wk-md)]',
    ]), $scope);
@endphp

@if($laidOut)
    {{-- With a sentence under the heading, the buttons keep to the top of the column; with the
         heading alone, to its middle. --}}
    <div data-wk-card-header {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes, 'flex flex-wrap justify-between gap-[var(--gap-wk-sm)]', $hasDescription ? 'items-start' : 'items-center'])) }}>
        <div class="flex min-w-0 grow basis-[16rem] items-center gap-[var(--gap-wk-sm)]">
            @if($hasIconSlot)
                <div class="shrink-0">{{ $iconSlot }}</div>
            @endif
            <div class="min-w-0 flex-1">
                <div>{{ $slot }}</div>
                @if($hasDescription)
                    <div data-wk-card-header-description class="{{ $descriptionClasses }}">{{ $description }}</div>
                @endif
            </div>
        </div>
        @if($hasActions)
            <div data-wk-card-header-actions class="{{ $actionsClasses }}">{{ $actions }}</div>
        @endif
    </div>
@else
    <div {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes, 'flex items-center gap-[var(--gap-wk-sm)]'])) }}>
        @if($hasIconSlot)
            <div class="shrink-0">{{ $iconSlot }}</div>
        @endif
        <div class="flex-1 min-w-0">{{ $slot }}</div>
    </div>
@endif
