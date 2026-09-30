{{-- optimistic-ui: n/a — navigation
     A row of links. Following one loads a page; nothing is written that could be anticipated. --}}
@props([
    // The links, in order: `label`, `href`, and optionally `current` (the page on screen, which
    // never goes into the menu), `attributes` (extra attributes for the anchor, such as
    // `wire:navigate`), `key` (what `menuOrder` names the entry by, its position otherwise) and
    // `action` (a button beside the link: `label`, which names it, `icon`, `close` by default,
    // and `attributes`, such as the `wire:click` that closes the entry).
    'items' => [],
    // How many rows the links may take before the rest go into the menu.
    'lines' => 2,
    // The landmark's name. A `<nav>` is rendered only with one, so two rows on a page never
    // share a nameless landmark; without it the row is a plain list.
    'label' => null,
    // The order of the menu, as a list of entry keys. Which entries move into the menu is still
    // decided by the width; this only orders them there. Keys it does not name follow in the
    // order of `items`.
    'menuOrder' => null,
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
            // A link row built from data (open records, recent pages). A target that could run
            // script leaves the entry without an href, so it navigates nowhere.
            'href' => \Pushery\WireKit\Support\SafeUrl::href((string) ($item['href'] ?? '#')),
            'current' => (bool) ($item['current'] ?? false),
            'attributes' => new \Illuminate\View\ComponentAttributeBag((array) ($item['attributes'] ?? [])),
            'key' => (string) ($item['key'] ?? $index),
            'action' => $item['action'] ?? null,
        ];
    }

    // The action beside an entry is a button of its own, a sibling of the link rather than inside
    // it, where it would be a control within a control. Its label is its name, and an action
    // without one would be a button nobody can name, so it is reported and left out.
    foreach ($entries as $position => $entry) {
        $action = $entry['action'];

        if ($action === null) {
            continue;
        }

        $action = (array) $action;
        $actionLabel = trim((string) ($action['label'] ?? ''));

        if ($actionLabel === '') {
            WireKit::validateProp('overflow-nav', 'items.action.label', '', ['the name of the action, such as "Close Order 1042"']);
            $entries[$position]['action'] = null;

            continue;
        }

        $entries[$position]['action'] = [
            'label' => $actionLabel,
            'icon' => (string) ($action['icon'] ?? 'close'),
            'attributes' => new \Illuminate\View\ComponentAttributeBag((array) ($action['attributes'] ?? [])),
        ];
    }

    // The menu in the order the caller gives, the rest after it in the order of the rows.
    $menuEntries = $entries;
    if (is_array($menuOrder) && $menuOrder !== []) {
        $rank = array_flip(array_map('strval', array_values($menuOrder)));
        usort($menuEntries, static fn (array $a, array $b): int => [$rank[$a['key']] ?? PHP_INT_MAX, $a['index']] <=> [$rank[$b['key']] ?? PHP_INT_MAX, $b['index']]);
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
    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
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
            <li data-wk-prose-skip data-wk-overflow-index="{{ $entry['index'] }}" @if($entry['current']) data-wk-overflow-current @endif @if($entry['action']) class="inline-flex items-center" @endif x-show="shownHere({{ $entry['index'] }})">
                <a data-wk-prose-skip @if($entry['href'] !== '') href="{{ $entry['href'] }}" @endif @if($entry['current']) aria-current="page" @endif {{ $entry['attributes']->class([$linkClasses]) }}>{{ $entry['label'] }}</a>
                @if($entry['action'])
                    @include('wirekit::components.partials.overflow-nav-action', ['overflowAction' => $entry['action']])
                @endif
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
                    @foreach($menuEntries as $entry)
                        <li data-wk-prose-skip @if($entry['action']) class="flex items-center gap-[var(--gap-wk-xs)]" @endif x-show="menuHere({{ $entry['index'] }})" style="display: none;">
                            <a data-wk-prose-skip @if($entry['href'] !== '') href="{{ $entry['href'] }}" @endif {{ $entry['attributes']->class([$menuLinkClasses, 'min-w-0 flex-1' => $entry['action'] !== null]) }}>{{ $entry['label'] }}</a>
                            @if($entry['action'])
                                @include('wirekit::components.partials.overflow-nav-action', ['overflowAction' => $entry['action']])
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-wirekit::popover>
        </li>
    </ul>
</{{ $tag }}>
