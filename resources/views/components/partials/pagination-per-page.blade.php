{{-- optimistic-ui: n/a — sub-component
     An @include of `pagination`, which owns it. A page size is a query round trip like a page
     turn: nobody can show the rows of a size nobody has fetched. --}}
{{-- The pager's choice of page size, drawn beside its summary.

     Expects from the pager: $perPageChoices (value => label, ascending), $perPageCurrent (the
     paginator's own size), $perPageLabelId, $perPageSelectAttributes (the bag for the select, which
     carries the forwarded `wire:model` or the change handler), and, for the form, $perPageUsesForm,
     $perPageAction and $perPageQuery (name/value pairs of every other parameter to keep).

     The visible words name the select through `aria-labelledby` rather than a label element with
     `for`: the select derives its own page-unique id, and a second pager on the page would leave a
     `for` pointing at the first one's select. --}}
<div data-wk-pagination-per-page class="flex items-center gap-[var(--gap-wk-sm)]">
    <span id="{{ $perPageLabelId }}" class="text-[color:var(--color-wk-text-muted)]">{{ __('wirekit::Per page') }}</span>
    @if($perPageUsesForm)
        <form method="get" action="{{ $perPageAction }}" x-data>
            @foreach($perPageQuery as [$queryName, $queryValue])
                <input type="hidden" name="{{ $queryName }}" value="{{ $queryValue }}">
            @endforeach
            <x-wirekit::select size="sm" :options="$perPageChoices" :value="$perPageCurrent" :attributes="$perPageSelectAttributes" />
            {{-- Without script the change sends nothing, so the form keeps a way to send it. --}}
            <noscript><x-wirekit::button type="submit" size="sm" intent="neutral">{{ __('wirekit::Apply') }}</x-wirekit::button></noscript>
        </form>
    @else
        <x-wirekit::select size="sm" :options="$perPageChoices" :value="$perPageCurrent" :attributes="$perPageSelectAttributes" />
    @endif
</div>
