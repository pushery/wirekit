{{-- optimistic-ui: n/a — navigation
     A trail of links. Each one navigates; none changes state. --}}
@props([
    'items' => [],
    'separator' => config('wirekit.components.breadcrumb.separator', 'chevron'),
    // Emit BreadcrumbList JSON-LD for this trail.
    //
    // Turn it OFF when the page already carries a breadcrumb elsewhere — a shell's top bar and
    // the content body is the common case. A page must carry exactly ONE BreadcrumbList, and
    // two of them compete rather than combine: a crawler shown two trails for one URL picks
    // one, or neither.
    //
    // Defaults ON so nothing about an existing single breadcrumb changes. Same shape and same
    // reasoning as the FAQ component's own `schema` prop, which is the house pattern for a
    // component that emits structured data without being asked.
    //
    // (Its tag is deliberately NOT written here. Blade is a text preprocessor and does not
    // know it is inside a PHP comment: an `x-wirekit::` tag in one gets COMPILED, and the
    // compiled construct lands in the middle of this array. The failure reads "Undefined
    // variable $component" and points at the render rather than at the sentence.)
    'schema' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\ListProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `schema="false"` would otherwise KEEP emitting. Normalized against the prop's own
    // default so a cast never leaves a second BreadcrumbList competing with the first.
    $schema = BooleanProp::from($schema, true);

    // A trail usually arrives as a Collection; `array_key_last()` below takes an array.
    $items = ListProp::records($items);

    // An application's trail is empty on its home page. An empty list inside the landmark reads
    // out as a breadcrumb with nothing in it, so the list is left out, and the landmark is hidden
    // unless the slot beside the trail holds a control, `aria-hidden` too, so a stylesheet that
    // gives the nav a display of its own cannot bring the empty landmark back. It stays in the
    // markup: the caller's attributes, a `wire:key` among them, still have their element.
    $hasItems = is_countable($items) && count($items) > 0;

    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('breadcrumb', $attributes->getAttributes());

    // Wrapper: nav landmark with "Breadcrumb" label so screen readers announce
    // the region as a breadcrumb trail (not just a generic nav).
    $navClasses = WireKit::resolveClasses('breadcrumb', 'nav', 'flex', $scope);

    // Ordered list — the semantic container for breadcrumb items.
    // list-none + m-0 + p-0 strip the browser-default <ol> decimal markers
    // and the marker-indent (browser default ~40px). Without these the
    // breadcrumb would render "1. Home / 2. Section / 3. Item".
    // `flex-wrap`, so a trail that does not fit breaks between items rather than inside them. Without
    // it each item shrank to its longest word, wrapped its own label, and a long trail still ran
    // past a phone's edge.
    $listClasses = WireKit::resolveClasses('breadcrumb', 'list', 'list-none m-0 p-0 flex flex-wrap items-center gap-[var(--padding-wk-x-xs)] text-[length:var(--text-wk-sm)]', $scope);

    // Link classes (for non-final items with href).
    $linkClasses = WireKit::resolveClasses('breadcrumb', 'link', implode(' ', [
        // A breadcrumb link is 20px tall — its line box — and that is below the 24x24
        // WCAG 2.5.8 AA minimum, with its siblings close enough that the spacing exception
        // does not rescue it either.
        //
        // `wk-touch-target` is the wrong tool here, though it is the library's own answer
        // everywhere else. It centers a 44x44 pseudo-element on the host, and inside a trail
        // that already sits in a narrow scroll strip that hit box reaches past the strip's
        // edge. The expander is for a control with room around it.
        //
        // Growing the LINE BOX instead adds four pixels of height and not one of width, so
        // the trail keeps its density and nothing reaches past anything. `inline-flex`
        // makes the min-height apply to an inline element at all.
        'inline-flex items-center min-h-[1.5rem]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:text-[color:var(--color-wk-text)]',
        'hover:underline',
        'underline-offset-2',
        'focus-visible:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'rounded-[var(--radius-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
    ]), $scope);

    // Current page: rendered as <span> with aria-current, slightly emphasized.
    $currentClasses = WireKit::resolveClasses('breadcrumb', 'current', 'text-[color:var(--color-wk-text)] font-[number:var(--font-wk-body-weight)]', $scope);

    // A step with no page of its own — a navigation group between the home page and this one.
    // It reads as plain text like the current page, but it is NOT the current page: borrowing
    // that block would show two crumbs in the emphasis color, and personalizing `current`
    // would restyle both. Muted like the links around it, without the
    // hover and focus a link carries, because there is nothing to activate.
    $ancestorClasses = WireKit::resolveClasses('breadcrumb', 'ancestor', 'text-[color:var(--color-wk-text-muted)]', $scope);

    // A control that belongs to the trail without being a step in it.
    //
    // The slot sits beside the `<ol>`, not inside it, and that is the whole point. The list
    // is mirrored one-to-one into the BreadcrumbList JSON-LD below, where every entry is a
    // `ListItem` — a position in the trail. A favorite toggle or a copy-link button is not a
    // position, so putting it in the list would either publish a crumb that leads nowhere or
    // force the schema loop to learn which children to skip.
    //
    // Without a place beside the trail, a single control there (a `<button aria-pressed>`, say)
    // would force a full local copy of this component, including a second implementation of the
    // JSON-LD.
    //
    // `self-center` rather than `items-center` on the nav — see the class list below.
    //
    // Written as an implode array rather than one string, which is what `$linkClasses` above
    // does and is not only style: the drift inventory reads class literals out of an
    // `implode(' ', [...])` and out of a `class="…"` attribute, not out of a bare
    // `resolveClasses()` string argument. `self-center` is the catalog's first BARE use — the
    // sidebar only has it behind a `group-data-` variant — so it compiled into a selector the
    // reverse diff could not trace back to any source.
    $actionsClasses = WireKit::resolveClasses('breadcrumb', 'actions', implode(' ', [
        'inline-flex items-center shrink-0',
        // The wrapper aligns ITSELF, so the nav keeps its default and a trail without this
        // slot renders exactly as it did before.
        'self-center',
        'gap-[var(--padding-wk-x-xs)]',
        'ms-[var(--padding-wk-x-sm)]',
    ]), $scope);

    // Separator glyph between items. Decorative — aria-hidden so AT doesn't
    // read "chevron" or "slash" between crumb labels.
    $separatorClasses = 'text-[color:var(--color-wk-text-subtle)] select-none';

    // Separator character mapping. `slot` means: use the {{ $separator }} slot
    // that the caller may provide (custom SVG/icon), fallback to chevron.
    $separatorChar = match ($separator) {
        'slash' => '/',
        'arrow' => '→',
        'dot' => '·',
        'chevron' => '›',
        default => $separator, // allow any literal string passed in
    };
@endphp

<nav @if($hasItems || isset($actions)) @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-label="{{ __('wirekit::Breadcrumb') }}" @endunless @else hidden aria-hidden="true" @endif {{ $attributes->class([$navClasses]) }}>
    @if($hasItems)
    <ol data-wk-prose-skip role="list" class="{{ $listClasses }}" style="list-style: none; margin: 0; padding: 0;">
        @foreach($items as $i => $item)
            @php
                // Normalize item: accept ['label' => .., 'href' => .., 'icon' => ..] or just a string label.
                $label = is_array($item) ? ($item['label'] ?? '') : (string) $item;
                // A trail is often built from data (a category tree, a folder path), and escaping
                // does not stop a `javascript:` URL, so the target passes SafeUrl like every link a
                // component reads from its items. A refused target leaves the crumb without a link,
                // the shape an item without `href` already has.
                // Compared with '' rather than tested for truth: `0` is a relative URL.
                $href = is_array($item) ? \Pushery\WireKit\Support\SafeUrl::href($item['href'] ?? null) : '';
                $href = $href === '' ? null : $href;
                // Optional decorative icon alias (e.g. 'home') rendered before the
                // label. The label stays the accessible text, so the icon is
                // aria-hidden. When present, the crumb element becomes an
                // inline-flex row so the glyph + label align.
                $icon = is_array($item) ? ($item['icon'] ?? null) : null;
                $iconWrap = $icon ? 'inline-flex items-center gap-[var(--padding-wk-x-xs)]' : '';
                $isLast = $i === array_key_last($items);
            @endphp
            <li data-wk-prose-skip class="flex items-center gap-[var(--padding-wk-x-xs)]">
                @if($isLast)
                    {{-- Current page: no link, aria-current tells AT "this is where you are" --}}
                    <span class="{{ $currentClasses }} {{ $iconWrap }}" aria-current="page">
                        @if($icon)<x-wirekit::icon :name="$icon" size="sm" aria-hidden="true" class="shrink-0" />@endif
                        {{ $label }}
                    </span>
                @elseif($href === null)
                    {{-- An unlinked crumb that is NOT the last one: a real position in
                         the trail that simply has no page to point at. It reads as plain
                         text and carries no aria-current, because only one crumb in a
                         trail can be where the reader is. This branch was folded into
                         the one above, so a trail like ['Home', 'Docs' => /docs, 'Page']
                         announced "Home, current page" on a crumb two levels up. Its
                         classes are the `ancestor` block's rather than the current
                         page's, for the same reason. --}}
                    <span class="{{ $ancestorClasses }} {{ $iconWrap }}">
                        @if($icon)<x-wirekit::icon :name="$icon" size="sm" aria-hidden="true" class="shrink-0" />@endif
                        {{ $label }}
                    </span>
                @else
                    <a data-wk-prose-skip href="{{ $href }}" class="{{ $linkClasses }} {{ $iconWrap }}">
                        @if($icon)<x-wirekit::icon :name="$icon" size="sm" aria-hidden="true" class="shrink-0" />@endif
                        {{ $label }}
                    </a>
                @endif

                @unless($isLast)
                    {{-- Separator rendered BETWEEN items only (never after the last). --}}
                    <span class="{{ $separatorClasses }}" aria-hidden="true">{{ $separatorChar }}</span>
                @endunless
            </li>
        @endforeach
    </ol>
    @endif

    @if(isset($actions))
        {{-- Inside the nav landmark, outside the list: the control belongs to the trail, but it
             is not a step in it and must never reach the structured data. --}}
        <div class="{{ $actionsClasses }}">{{ $actions }}</div>
    @endif
</nav>

{{-- Schema.org BreadcrumbList structured data (JSON-LD), delegated to
     <x-wirekit::structured-data>, which bakes JSON_HEX_TAG in: without it a
     user-controlled item label containing </script> could break out of the
     JSON-LD block. --}}
@if($schema && $hasItems)
    @php
        // Built by Schema::breadcrumbItems(), the one rule every producer shares: a step with no
        // page of its own stays in the visible trail above but not in here, because Google
        // discards a whole BreadcrumbList over one ListItem without a URL that is not the last.
        // Consecutive steps at the same URL fold into one, and the positions are counted over
        // what remains.
        $breadcrumbLdData = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => \Pushery\WireKit\Schema\Schema::breadcrumbItems(array_values(array_map(
                static fn ($item): array => [
                    'name' => is_array($item) ? (string) ($item['label'] ?? '') : (string) $item,
                    // The same target as the visible crumb: one the trail refuses is no page here
                    // either. A refused target is '', which the schema reads as no page.
                    'url' => is_array($item) ? \Pushery\WireKit\Support\SafeUrl::href($item['href'] ?? null) : null,
                ],
                $items,
            ))),
        ];
    @endphp
    <x-wirekit::structured-data :data="$breadcrumbLdData" />
@endif
