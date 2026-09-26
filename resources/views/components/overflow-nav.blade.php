{{-- optimistic-ui: n/a — navigation
     A row of links. Following one loads a page; nothing is written that could be anticipated. --}}
@props([
    // The links, in order: `label`, `href`, and optionally `current` (the page on screen, which
    // never goes into the menu) and `attributes` (extra attributes for the anchor, such as
    // `wire:navigate`).
    'items' => [],
    // How many rows the links may take before the rest go into the menu.
    'lines' => 2,
    // The landmark's name. A `<nav>` is rendered only with one, so two rows on a page never
    // share a nameless landmark; without it the row is a plain list.
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    \Pushery\WireKit\WireKit::warnUnknownProps('overflow-nav', $attributes->getAttributes());

    $lines = max(1, (int) $lines);

    // Each entry as the template needs it. The index is the key the component hides and shows
    // by, so the rows and the menu name the same entry without depending on the label.
    $entries = [];
    foreach (array_values((array) $items) as $index => $item) {
        $item = (array) $item;
        $entries[] = [
            'index' => $index,
            'label' => (string) ($item['label'] ?? ''),
            'href' => (string) ($item['href'] ?? '#'),
            'current' => (bool) ($item['current'] ?? false),
            'attributes' => new \Illuminate\View\ComponentAttributeBag((array) ($item['attributes'] ?? [])),
        ];
    }

    $named = filled($label);
    $tag = $named ? 'nav' : 'div';

    $rowClasses = WireKit::resolveClasses('overflow-nav', 'row', implode(' ', [
        'flex flex-wrap items-center',
        'gap-[var(--gap-wk-xs)]',
        'm-0 p-0 list-none',
    ]), $scope);

    $linkClasses = WireKit::resolveClasses('overflow-nav', 'link', implode(' ', [
        'inline-flex items-center whitespace-nowrap',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)]',
        'rounded-[var(--radius-wk-md)]',
        'text-[length:var(--text-wk-sm)] no-underline',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)]',
        'aria-[current=page]:bg-[var(--color-wk-bg-muted)] aria-[current=page]:text-[color:var(--color-wk-text)]',
        'aria-[current=page]:font-[number:var(--font-wk-heading-weight)]',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    $menuLinkClasses = WireKit::resolveClasses('overflow-nav', 'menu-link', implode(' ', [
        'block px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)]',
        'rounded-[var(--radius-wk-md)]',
        'text-[length:var(--text-wk-sm)] no-underline text-[color:var(--color-wk-text)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    // Both plural forms travel to the browser, which counts the menu. The placeholder is swapped
    // there for the number.
    $moreOne = trans_choice('wirekit:::count more link|:count more links', 1, ['count' => '__COUNT__']);
    $moreMany = trans_choice('wirekit:::count more link|:count more links', 2, ['count' => '__COUNT__']);
@endphp

<{{ $tag }} data-wk-prose-skip data-wk-overflow-nav
    @if($named) aria-label="{{ $label }}" @endif
    x-data="wirekitOverflowNav({
        lines: {{ $lines }},
        moreOne: {{ \Pushery\WireKit\Support\AlpinePayload::string($moreOne) }},
        moreMany: {{ \Pushery\WireKit\Support\AlpinePayload::string($moreMany) }}
    })"
    {{ $attributes->class(['min-w-0']) }}
>
    <ul data-wk-prose-skip role="list" x-ref="row" class="{{ $rowClasses }}" style="list-style: none; margin: 0; padding: 0;">
        @foreach($entries as $entry)
            <li data-wk-prose-skip data-wk-overflow-index="{{ $entry['index'] }}" @if($entry['current']) data-wk-overflow-current @endif x-show="shownHere({{ $entry['index'] }})">
                <a data-wk-prose-skip href="{{ $entry['href'] }}" @if($entry['current']) aria-current="page" @endif {{ $entry['attributes']->class([$linkClasses]) }}>{{ $entry['label'] }}</a>
            </li>
        @endforeach
        {{-- Hidden until the row has measured itself, so a page without script shows every link
             in as many rows as it takes, and no button that opens nothing. --}}
        <li data-wk-prose-skip x-ref="more" x-show="overflowing" style="display: none;">
            <x-wirekit::popover placement="bottom-end" :label="__('wirekit::More links')">
                <x-slot:trigger>
                    <x-wirekit::button intent="neutral" surface="ghost" size="sm" x-bind:aria-label="moreName">
                        <span data-wk-overflow-count x-text="moreText">+0</span>
                    </x-wirekit::button>
                </x-slot:trigger>
                <ul data-wk-prose-skip role="list" class="m-0 p-0 list-none flex flex-col gap-[var(--gap-wk-xs)]" style="list-style: none; margin: 0; padding: 0;">
                    @foreach($entries as $entry)
                        <li data-wk-prose-skip x-show="menuHere({{ $entry['index'] }})" style="display: none;">
                            <a data-wk-prose-skip href="{{ $entry['href'] }}" {{ $entry['attributes']->class([$menuLinkClasses]) }}>{{ $entry['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </x-wirekit::popover>
        </li>
    </ul>
</{{ $tag }}>
