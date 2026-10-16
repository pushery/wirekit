{{-- optimistic-ui: n/a — sub-component
     An @include of `pagination`, which owns it. A page size is a query round trip like a page
     turn: nobody can show the rows of a size nobody has fetched. --}}
{{-- The pager's choice of page size, drawn beside its summary.

     Expects from the pager: $perPageChoices (value => label, ascending), $perPageCurrent (the
     paginator's own size), $perPageLabelId, $perPageSelectAttributes (the bag for the select, which
     carries the forwarded `wire:model`), $perPageLinks (outside Livewire, one per size, each with
     its label, its URL and whether it is the size in use), and the pager's $buttonBase and
     $buttonActive.

     The visible words name the select or the list of links through `aria-labelledby` rather than a
     label element with `for`: the select derives its own page-unique id, and a second pager on the
     page would leave a `for` pointing at the first one's select. --}}
<div data-wk-pagination-per-page class="flex items-center gap-[var(--gap-wk-sm)]">
    <span id="{{ $perPageLabelId }}" class="text-[color:var(--color-wk-text-muted)]">{{ __('wirekit::Per page') }}</span>
    @if($perPageLinks !== [])
        {{-- Outside Livewire: a link per size and no form, so a pager inside a form of the
             developer's own leaves that form whole. The size in use is no link and says so with
             `aria-current`, as the current page does. `role="list"` because a list drawn without
             bullets is no list to Safari's accessibility tree. --}}
        <ul data-wk-prose-skip role="list" aria-labelledby="{{ $perPageLabelId }}" class="m-0 p-0 list-none flex flex-wrap items-center gap-1" style="list-style: none; margin: 0; padding: 0;">
            @foreach($perPageLinks as $choice)
                <li data-wk-prose-skip class="flex">
                    @if($choice['current'])
                        <span class="{{ $buttonActive }}" aria-current="true">{{ $choice['label'] }}</span>
                    @else
                        <a data-wk-prose-skip href="{{ $choice['url'] }}" class="{{ $buttonBase }}">{{ $choice['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <x-wirekit::select size="sm" :options="$perPageChoices" :value="$perPageCurrent" :attributes="$perPageSelectAttributes" />
    @endif
</div>
