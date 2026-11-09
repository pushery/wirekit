{{-- optimistic-ui: n/a — query
     In the browser the query and the rows it hides stay there. With `server` the query goes to the
     application's own property, and the rows it renders are its answer: nothing to anticipate. --}}
@props([
    // The id of the table whose rows the field shortens: the `id` on the table component, which
    // lands on its `<table>`. The id of an element around the table works as well.
    'for' => null,
    // The field's name for a screen reader, and its placeholder unless `placeholder` names
    // another.
    'label' => null,
    'placeholder' => null,
    // What the field says beside itself, and announces, when no row matches the query.
    'emptyText' => null,
    // What the status region says when rows match, in place of "3 results": a translated line
    // with `:count`, the rows that match, and `:total`, the rows the field searches, whose forms
    // follow `:count`, such as "{1} :count of :total steps matches|[2,*] :count of :total steps match".
    'found' => null,
    // The application filters the rows, every one of them, also those the page does not render: the
    // field binds a property of the Livewire component with `wire:model`, and `matches` and `total`
    // give the counts of the answer, which the field says as it does in the browser.
    'server' => false,
    'matches' => null,
    'total' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.search', $attributes->getAttributes());

    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BlankName;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\IcuLocale;
    use Pushery\WireKit\Support\PluralPhrases;
    use Pushery\WireKit\Support\SlotContent;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\WireKit;

    // A field without a table searches nothing, so it says so in development and stays an
    // ordinary search field without effect.
    $for = SlotContent::textOf(is_scalar($for) || $for instanceof \Stringable ? (string) $for : null);

    if ($for === '') {
        StrictnessGate::reject('table.search', 'for', '', 'the id of the table the field searches', 'a field that filters nothing');
    }

    // A blank label is no name, so the field keeps the default one.
    $labelText = BlankName::first($label, __('wirekit::Search…'));
    $placeholderText = BlankName::first($placeholder, $labelText);
    $emptyText = BlankName::first($emptyText, __('wirekit::No results'));
    // A blank sentence is none, so the region keeps the library's.
    $foundLine = BlankName::first($found, 'wirekit::{1} :count result|[2,*] :count results');

    $server = BooleanProp::from($server, false, 'table.search', 'server');
    // A count that holds no number is reported, and read as PHP casts it, the number it starts with.
    // One no integer holds reads as none: `(int)` on it ends the page in PHP 8.5, and below 8.5 it
    // gives an unrelated number.
    \Pushery\WireKit\Support\NumericProp::check($matches, 'table.search', 'matches', whole: true);
    \Pushery\WireKit\Support\NumericProp::check($total, 'table.search', 'total', whole: true);
    $matches = is_numeric($matches) || is_string($matches) ? \Pushery\WireKit\Support\NumericProp::cast($matches) : null;
    $total = is_numeric($total) || is_string($total) ? \Pushery\WireKit\Support\NumericProp::cast($total) : null;
    $matches = $matches === null ? null : max(0, $matches);
    $total = $total === null ? null : max(0, $total);

    // A `wire:model` binds the field, where the query is typed; every other attribute stays on the
    // root, so a caller's class, id and listeners keep the element they were written for.
    $isModel = static fn ($value, $key): bool => is_string($key) && str_starts_with($key, 'wire:model');
    $fieldAttributes = $attributes->filter($isModel);
    $attributes = $attributes->filter(static fn ($value, $key): bool => ! $isModel($value, $key));

    $config = [
        'table' => $for,
        'server' => $server,
        // The count only exists in the browser, so the plural forms travel there, with `:total`
        // left for the browser to fill as well.
        'phrases' => PluralPhrases::from($foundLine),
        'empty' => $emptyText,
        'locale' => IcuLocale::tag(),
    ];

    $classes = WireKit::resolveClasses('table.search', 'base', 'flex flex-wrap items-center gap-[var(--gap-wk-sm)]', $scope);

    // The look of the search field of `data-table`, so the two searches of the kit read alike. A
    // form control (WCAG 1.4.11): its border is the communicating `--color-wk-border-strong`
    // (3:1), never the decorative `--color-wk-border`. `wk-field`, which keeps a phone from
    // zooming into the field, stands on the tag, where no theme's classes replace it.
    $fieldClasses = WireKit::resolveClasses('table.search', 'field', implode(' ', [
        'w-[16rem] max-w-full',
        'bg-[var(--color-wk-bg-input)] text-[color:var(--color-wk-text)] text-[length:var(--text-wk-sm)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] rounded-[var(--radius-wk-md)]',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]',
        'transition-colors duration-[var(--transition-wk-duration)] hover:border-[var(--color-wk-border-strong-hover)]',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    $emptyClasses = WireKit::resolveClasses('table.search', 'empty', 'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]', $scope);
@endphp

{{-- The field names the table it filters (`aria-controls`). Beside it stands the sentence for no
     match, which the eye reads, and a status region the ear hears: the count of matching rows,
     once the typing pauses. The visible sentence is no live region, so it is not read twice. The
     root hosts that region (`data-wk-sr-host`), so the hidden text stays inside the field's box
     and never widens a page that scrolls sideways. --}}
<div
    x-data="wirekitTableSearch({{ AlpinePayload::from($config) }})"
    data-wk-table-search
    data-wk-sr-host
    {{-- The counts of the server's answer, written on every render and read once it is rendered. --}}
    @if($server) data-wk-search-counts="{{ \Pushery\WireKit\Support\AlpinePayload::json(['matches' => $matches ?? 0, 'total' => $total ?? 0]) }}" @endif
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    <input
        type="search"
        x-model="query"
        x-on:input="search()"
        aria-label="{{ $labelText }}"
        placeholder="{{ $placeholderText }}"
        @if($for !== '') aria-controls="{{ $for }}" @endif
        autocomplete="off"
        class="wk-field {{ $fieldClasses }}"
        {{ $fieldAttributes }}
    >
    <p data-wk-prose-skip x-show="isEmpty" x-cloak class="{{ $emptyClasses }}">{{ $emptyText }}</p>
    <p data-wk-prose-skip class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="announcement"></p>
</div>
