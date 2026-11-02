{{-- optimistic-ui: n/a — client-only
     The query and the rows it hides live in the browser, and nothing of it reaches a server. --}}
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
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.search', $attributes->getAttributes());

    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BlankName;
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

    $config = [
        'table' => $for,
        // The count only exists in the browser, so the plural forms travel there.
        'phrases' => PluralPhrases::from('wirekit::{1} :count result|[2,*] :count results'),
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
    >
    <p data-wk-prose-skip x-show="isEmpty" x-cloak class="{{ $emptyClasses }}">{{ $emptyText }}</p>
    <p data-wk-prose-skip class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="announcement"></p>
</div>
