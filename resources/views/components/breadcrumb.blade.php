@props([
    'items' => [],
    'separator' => config('wirekit.components.breadcrumb.separator', 'chevron'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Wrapper: nav landmark with "Breadcrumb" label so screen readers announce
    // the region as a breadcrumb trail (not just a generic nav).
    $navClasses = WireKit::resolveClasses('breadcrumb', 'nav', 'flex', $scope);

    // Ordered list — the semantic container for breadcrumb items.
    // list-none + m-0 + p-0 strip the browser-default <ol> decimal markers
    // and the marker-indent (browser default ~40px). Without these the
    // breadcrumb would render "1. Home / 2. Section / 3. Item".
    $listClasses = WireKit::resolveClasses('breadcrumb', 'list', 'list-none m-0 p-0 flex items-center gap-[var(--padding-wk-x-xs)] text-[length:var(--text-wk-sm)]', $scope);

    // Link classes (for non-final items with href).
    $linkClasses = WireKit::resolveClasses('breadcrumb', 'link', implode(' ', [
        'text-[color:var(--color-wk-text-muted)]',
        'hover:text-[color:var(--color-wk-text)]',
        'hover:underline',
        'underline-offset-2',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'rounded-[var(--radius-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
    ]), $scope);

    // Current page: rendered as <span> with aria-current, slightly emphasized.
    $currentClasses = WireKit::resolveClasses('breadcrumb', 'current', 'text-[color:var(--color-wk-text)] font-[number:var(--font-wk-body-weight)]', $scope);

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

<nav aria-label="Breadcrumb" {{ $attributes->class([$navClasses]) }}>
    <ol class="{{ $listClasses }}" style="list-style: none; margin: 0; padding: 0;">
        @foreach($items as $i => $item)
            @php
                // Normalize item: accept ['label' => .., 'href' => .., 'icon' => ..] or just a string label.
                $label = is_array($item) ? ($item['label'] ?? '') : (string) $item;
                $href = is_array($item) ? ($item['href'] ?? null) : null;
                // Optional decorative icon alias (e.g. 'home') rendered before the
                // label. The label stays the accessible text, so the icon is
                // aria-hidden. When present, the crumb element becomes an
                // inline-flex row so the glyph + label align.
                $icon = is_array($item) ? ($item['icon'] ?? null) : null;
                $iconWrap = $icon ? 'inline-flex items-center gap-[var(--padding-wk-x-xs)]' : '';
                $isLast = $i === array_key_last($items);
            @endphp
            <li class="flex items-center gap-[var(--padding-wk-x-xs)]">
                @if($isLast || !$href)
                    {{-- Current page: no link, aria-current tells AT "this is where you are" --}}
                    <span class="{{ $currentClasses }} {{ $iconWrap }}" aria-current="page">
                        @if($icon)<x-wirekit::icon :name="$icon" size="sm" aria-hidden="true" class="shrink-0" />@endif
                        {{ $label }}
                    </span>
                @else
                    <a href="{{ $href }}" class="{{ $linkClasses }} {{ $iconWrap }}">
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
</nav>

{{-- Schema.org BreadcrumbList structured data (JSON-LD). — Delegated to <x-wirekit::structured-data> so
     the JSON_HEX_TAG safety flag is applied consistently. Previously
     this component called json_encode() directly with a flag set that
     omitted JSON_HEX_TAG — a user-controlled item label containing
     </script> could break out of the JSON-LD block (real XSS). The
     structured-data component bakes JSON_HEX_TAG in. --}}
@if(count($items) > 0)
    @php
        $breadcrumbLdData = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [],
        ];
        foreach ($items as $i => $item) {
            $itemLabel = is_array($item) ? ($item['label'] ?? '') : (string) $item;
            $itemHref = is_array($item) ? ($item['href'] ?? null) : null;
            $entry = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $itemLabel,
            ];
            if ($itemHref) {
                $entry['item'] = $itemHref;
            }
            $breadcrumbLdData['itemListElement'][] = $entry;
        }
    @endphp
    <x-wirekit::structured-data :data="$breadcrumbLdData" />
@endif
