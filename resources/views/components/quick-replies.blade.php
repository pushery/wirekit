{{-- optimistic-ui: n/a — client-only
     A press dispatches the chosen reply and mutates nothing, so there is no result that
     could be shown early. Measured rather than asserted: the guard refutes this reason
     for any file that performs a mutation of its own. --}}
@props([
    // The suggested replies, in the order they are offered. An entry is either a string —
    // the label, which is then also the value — or an array with `label`, an optional
    // `value` (defaults to the label) and an optional `disabled`.
    'replies' => [],
    // Names the group. A group of buttons with no name is announced as a group of
    // nothing, so an unset label falls back to a translated default rather than to
    // silence.
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Fully qualified: this
    // view's imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('quick-replies', $attributes->getAttributes());

    // Normalized here rather than inside the loop, so the markup below stays markup and a
    // malformed entry is answered once. A plain string is the common case: the words the
    // reader sees are the value the application gets back.
    $items = [];

    foreach ($replies as $reply) {
        $entry = is_array($reply) ? $reply : ['label' => $reply];
        $itemLabel = (string) ($entry['label'] ?? '');

        // A chip with no words is a target that says nothing — skipped rather than
        // rendered as an empty pill the pointer can still hit.
        if ($itemLabel === '') {
            continue;
        }

        $items[] = [
            'label' => $itemLabel,
            'value' => (string) ($entry['value'] ?? $itemLabel),
            'disabled' => BooleanProp::from($entry['disabled'] ?? false, false),
        ];
    }

    $groupLabel = filled($label) ? (string) $label : __('wirekit::Suggested replies');

    $classes = WireKit::resolveClasses('quick-replies', 'base', implode(' ', [
        // Wrapping is unconditional, and that is the mobile answer: three or four
        // suggestions are wider than a phone, and a horizontal scroller would hide the
        // last one behind an edge that announces nothing.
        'flex flex-wrap items-center',
        'gap-[var(--gap-wk-xs)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    $chipClasses = WireKit::resolveClasses('quick-replies', 'chip', implode(' ', [
        'inline-flex items-center justify-center',
        // The WCAG 2.5.8 floor, from the same token the segmented control takes. On a
        // phone a suggestion is a primary way to answer, so it is a target rather than a
        // caption.
        'min-h-[var(--size-wk-target-min)]',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)]',
        'rounded-[var(--radius-wk-full)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg)] text-[color:var(--color-wk-text)]',
        'text-[length:var(--text-wk-sm)]',
        'cursor-pointer',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'transition-colors duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]',
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed',
    ]), $scope);
@endphp

{{-- Nothing is rendered without replies. An empty named group is still a group to a
     screen reader, so it would announce a set of suggestions and then offer none. --}}
@if ($items !== [])
    <div
        data-wk-quick-replies
        {{-- A group of buttons, named. NOT `toolbar`: that role promises an arrow-key
             model, and this has none — a reader who tries the arrows would find the
             promise broken rather than the widget rich. --}}
        role="group"
        aria-label="{{ $groupLabel }}"
        {{-- `x-data` makes this element an Alpine root, and without one the `x-on:click`
             below never initializes: a directive only runs inside a tree Alpine walks, so
             in a plain Blade page every chip would be a button that does nothing at all.
             Emitted only when the call site brought no `x-data` of its own — HTML keeps
             the FIRST of two identical attributes, so hardcoding it would silently
             discard theirs. --}}
        @unless($attributes->has('x-data')) x-data @endunless
        {{ $attributes->class([$classes]) }}
    >
        @foreach ($items as $index => $item)
            <button
                type="button"
                data-wk-quick-reply
                data-value="{{ $item['value'] }}"
                @disabled($item['disabled'])
                {{-- The press hands the answer to the application and performs nothing
                     itself: what a reply DOES is the application's decision, and a
                     component that sent it would have to know how. --}}
                x-on:click="$dispatch('wirekit:quick-replies-select', { value: {{ AlpinePayload::string($item['value']) }}, label: {{ AlpinePayload::string($item['label']) }}, index: {{ $index }} })"
                class="{{ $chipClasses }}"
            >{{ $item['label'] }}</button>
        @endforeach
    </div>
@endif
