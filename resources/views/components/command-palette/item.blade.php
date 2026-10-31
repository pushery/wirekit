{{-- optimistic-ui: n/a — passthrough
     Same: the palette owns selection, the item carries the developer's action. --}}
@props([
    'href' => null,
    'icon' => null,
    'shortcut' => null,
    'disabled' => false,
    // The `name` of a `command-palette.page` this entry opens. The entry then shows that page in
    // the same palette instead of closing it, and draws a chevron. It is a button, not a link.
    'opens' => null,
    // Stable DOM id for this item. It anchors the combobox's
    // aria-activedescendant, so it must survive a re-render: with server-side
    // search the list is rebuilt on every keystroke, and an id that changes each
    // time keeps moving the screen reader's announcement target and makes
    // Livewire's morph patch every row. Pass one when you have a natural key.
    'id' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('command-palette.item', $attributes->getAttributes());

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);
    // Echoed into the tag or bound, the URL is written escaped once (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false, 'command-palette.item', 'disabled');

    // An entry that opens a page chooses nothing yet, so it never navigates.
    $opens = filled($opens) ? trim(\Pushery\WireKit\Support\NumericProp::text($opens)) : null;
    $opens = $opens === '' ? null : $opens;

    // Command item — selectable option in the command palette.
    //
    // The id is DERIVED, in this order: an explicit `id`, then the href (the
    // natural key for a link item), then the item's own text. Only when none of
    // those exist is there nothing to derive from at all. An entry that opens a
    // page is no link, so its href takes no part.
    $wkCmdSlug = static function (string $value): string {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '');

        return trim($slug, '-');
    };

    $wkCmdDerivedId = $id
        ?? ($href !== null && $href !== '' && $opens === null
            ? 'wk-cmd-item-'.$wkCmdSlug((string) $href)
            : null);

    if ($wkCmdDerivedId === null) {
        $label = $wkCmdSlug(trim(strip_tags((string) $slot)));
        $wkCmdDerivedId = $label !== '' ? 'wk-cmd-item-'.$label : null;
    }

    // The derivation above is stable but NOT unique, and a palette is the one
    // place that shape hurts. The same verb under two groups — "Settings" under
    // Docs and under Admin, "Open" under Files and Projects — derives ONE id for
    // two rows, and `document.getElementById` answers with the first: arrowing
    // onto the second row publishes an `aria-activedescendant` that resolves to
    // the first, so the reader is told about a row it is not standing on, and
    // because the string does not change between the two some readers announce
    // nothing at all. DomId::unique hands the FIRST sight back verbatim — a lone
    // item keeps the clean, readable id the paragraph above argues for — and
    // counts only the collisions, so both properties hold instead of one being
    // traded for the other. It also covers the item that has neither href nor
    // text: a counter survives a re-render, where a random string would make the
    // row a different element every time. Its registry is per request, which is
    // what a re-render is.
    $itemId = \Pushery\WireKit\Support\DomId::unique($wkCmdDerivedId, 'wk-cmd-item-');

    $classes = WireKit::resolveClasses('command-palette.item', 'base', implode(' ', [
        'flex items-center gap-x-[var(--gap-wk-sm)] w-full',
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        'font-[family-name:var(--font-wk-sans)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
        'hover:bg-[var(--color-wk-bg-hover)]',
        'focus:outline-hidden',
        // `data-active`, not `focus`, and this is the one menu-ish component where
        // that distinction matters. The option is `tabindex="-1"` under the list's
        // `aria-activedescendant`, and command-palette.js never calls `.focus()` on
        // it — the highlight is written by `_paintActive()`. So a `focus:` background here
        // would be inert, and there is none.
        //
        // The RING is the mark, for the reason dropdown/item.blade.php records. The
        // background is the hover's shade, --color-wk-bg-hover, about 1.1:1 against the
        // palette panel: it shows which row the pointer is on and is too faint to mark
        // the highlighted one alone. The ring measures 17.2:1 dark and 19.8:1 light.
        'data-[active=true]:bg-[var(--color-wk-bg-hover)]',
        'data-[active=true]:ring-[length:var(--ring-wk-width)]',
        'data-[active=true]:ring-inset',
        'data-[active=true]:ring-[var(--color-wk-ring)]',
    ]), $scope);

    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';

    $tag = $href !== null && $opens === null ? 'a' : 'button';

    // Which level the entry belongs to, and whether a page's search hides it, is the palette's
    // to say, read from the entry's place in the list (`optionShows`). A binding rather than a
    // script's write, so a Livewire render keeps it. A caller's own `x-show` still applies.
    $callerShow = \Pushery\WireKit\Support\AttributeText::get($attributes, 'x-show');
    $show = is_string($callerShow) && trim($callerShow) !== ''
        ? 'optionShows($el) && ('.$callerShow.')'
        : 'optionShows($el)';

    // The digit a page gives the entry is a key the palette binds, so it is stated as
    // `aria-keyshortcuts`, as every key the kit binds is, beside a shortcut of the caller's own.
    // The caller's stands written for the first render, and the binding keeps it with the digit.
    $callerShortcuts = trim((string) \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-keyshortcuts'));
    $attributes = $attributes->except(['x-show', 'aria-keyshortcuts']);

    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // See dropdown/item.blade.php for rationale on except('rel') + explicit
    // rel render (avoids $attributes->merge treating rel as a default).
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $tag === 'a' && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

<{{ $tag }} data-wk-prose-skip
    id="{{ $itemId }}"
    @if($tag === 'a') href="{{ $href }}" @endif
    @if($tag === 'button') type="button" @endif
    @if($opens !== null) data-wk-opens="{{ $opens }}" @endif
    x-show="{{ $show }}"
    role="option"
    {{-- The ARIA state for this role, written statically so it exists before the first
         keystroke. `_paintActive()` in the factory keeps it in step with the highlight —
         both are set there, so a Livewire morph that re-runs the paint restores the state
         and the styling hook together rather than one of them. --}}
    aria-selected="false"
    tabindex="-1"
    @if($callerShortcuts !== '') aria-keyshortcuts="{{ $callerShortcuts }}" @endif
    x-bind:aria-keyshortcuts="keyShortcutsOf($el, {{ \Pushery\WireKit\Support\AlpinePayload::string($callerShortcuts) }})"
    @if($disabled) aria-disabled="true" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except($tag === 'button' ? ['rel', 'role', 'tabindex', 'type'] : ['rel', 'role', 'tabindex'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['wk-listbox-option', 'wk-touch-row', $classes, $disabledClasses])) }}
>
    @if($icon)
        <span class="shrink-0 w-5 h-5 text-[color:var(--color-wk-text-muted)]" aria-hidden="true">
            {{ \Pushery\WireKit\Support\IconSvg::html($icon, ['class' => 'w-5 h-5']) }}
        </span>
    @endif

    {{-- One line; a label the line cuts is whole in its title, as plain text, since the slot may
         carry markup such as a highlighted match. --}}
    <span data-wk-command-label class="flex-1 truncate" title="{{ \Pushery\WireKit\Support\SlotContent::plain($slot) }}">{{ $slot }}</span>

    {{-- On a page with an empty field, the digit that chooses this entry; the palette counts it
         from the entry's place on the page. Decorative like the shortcut caps: a screen reader
         hears the key from the entry's `aria-keyshortcuts`, and the entry's name stays its text. --}}
    <span x-show="digitOf($el)" x-cloak class="ms-auto flex items-center" aria-hidden="true">
        <x-wirekit::kbd size="sm" x-text="digitOf($el)" />
    </span>

    @if($shortcut)
        <span class="ml-auto flex items-center gap-1 text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">
            @foreach((array) $shortcut as $key)
                {{-- The component, not a copy of it. The hand-rolled chip reproduced most of
                     kbd's classes and dropped two: `border-b-2`, which is the keycap edge that
                     makes it read as a key, and the `resolveClasses('kbd', …)` seam a developer
                     overrides to retheme every shortcut chip at once. Neither absence is
                     visible next to the other chips until you put them side by side. --}}
                <x-wirekit::kbd size="sm">{{ $key }}</x-wirekit::kbd>
            @endforeach
        </span>
    @endif

    @if($opens !== null)
        {{-- Points to the page the entry opens. Decorative: the page announces itself when it opens. --}}
        <span class="shrink-0 inline-flex text-[color:var(--color-wk-text-muted)] rtl:-scale-x-100" aria-hidden="true">
            <x-wirekit::icon name="chevron-right" size="sm" />
        </span>
    @endif

    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
