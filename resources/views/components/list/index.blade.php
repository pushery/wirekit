{{-- optimistic-ui: n/a — navigation
     Its only interactive elements are the links of rows drawn from `items`, each leading to its
     address. What the slot holds is the developer's markup. --}}
@props([
    'type' => 'disc',
    'spacing' => 'sm',
    'as' => null,
    // Rows as data, drawn in this one render rather than one `list.item` (and a `link` and a `text`
    // in it) per row: a long list of a thousand rows renders three thousand components otherwise.
    // A row is its text, or `['text' => …, 'href' => …, 'meta' => …]`: the text, the address the
    // text links to, and a short note after it in the small muted text. The slot follows the rows.
    'items' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('list', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Ordered types render as <ol>; everything else (incl. 'disc' / 'none') uses <ul>.
    $orderedTypes = ['decimal', 'lower-roman', 'upper-roman', 'lower-alpha', 'upper-alpha'];
    // `as` is rendered straight into the opening tag — see heading.blade.php for the
    // payload this admitted. tagName() accepts a tag name and rejects everything else.
    $tag = $as === null
        ? (in_array($type, $orderedTypes, true) ? 'ol' : 'ul')
        : WireKit::tagName('list', (string) $as);

    $typeClasses = match (WireKit::validateProp('list', 'type', $type, [
            'disc', 'decimal', 'none',
            'lower-roman', 'upper-roman', 'lower-alpha', 'upper-alpha',
        ])) {
        'disc' => 'list-disc',
        'decimal' => 'list-decimal',
        'none' => 'list-none',
        // Roman / alpha types use Tailwind v4's arbitrary-value syntax; the
        // CSS list-style-type property has supported these values since IE6.
        'lower-roman' => 'list-[lower-roman]',
        'upper-roman' => 'list-[upper-roman]',
        'lower-alpha' => 'list-[lower-alpha]',
        'upper-alpha' => 'list-[upper-alpha]',
    };

    // wk-list-spacing-{sm|md|none} pairs with the CSS rules in wirekit.css
    // that win by doubled-class specificity (no `!important`) over developer
    // typography wrappers
    // (`.prose`, `.docs-prose`, etc.) which otherwise inject vertical
    // margin into <ol>/<ul>/<li> and compound across nesting depth.
    // A list without markers is still a list, but WebKit does not treat it as one: VoiceOver on
    // iPhone and iPad reads a `ul` or `ol` whose markers are removed as plain text, without
    // "list" and without the number of items. `role="list"` keeps the semantics there and
    // changes nothing in the engines that keep them anyway. A role on the tag replaces it.
    $listRole = $type === 'none'
        && in_array(strtolower($tag), ['ul', 'ol'], true)
        && ! $attributes->has('role');

    $spacingClasses = match (WireKit::validateProp('list', 'spacing', $spacing, ['none', 'sm', 'md'])) {
        'none' => 'wk-list-spacing-none',
        'sm' => 'wk-list-spacing-sm',
        'md' => 'wk-list-spacing-md',
    };

    $classes = WireKit::resolveClasses('list', 'base', implode(' ', [
        // A typed token with no break point, an email address or a URL, breaks rather than running
        // out of the box. Inherited by everything inside; `break-word` leaves every min-content width
        // alone, so a table or a flex row in the content keeps the layout it had.
        '[overflow-wrap:break-word]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'text-[length:var(--text-wk-md)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        // `wk-list` is the marker class that the wirekit.css override
        // rules target. Resets browser-default + developer typography
        // margins on <ol>/<ul>/<li> so the spacing prop is the single
        // source of truth for inter-item vertical rhythm at every depth.
        'wk-list',
        $type !== 'none' ? 'pl-[var(--space-wk-lg,1.5rem)]' : '',
        $typeClasses,
        $spacingClasses,
    ]), $scope);
@endphp

@php
    // The rows, each as its text, its address and its note. Their link and their note carry
    // exactly the classes `<x-wirekit::link>` and `<x-wirekit::text as="span" size="sm"
    // intent="muted">` render with, through the same personalization, so a row looks as those two
    // components would draw it.
    $rows = [];

    $items = \Pushery\WireKit\Support\ListProp::records($items, 'list', 'items');

    foreach (is_iterable($items) ? $items : [] as $row) {
        $row = is_array($row) ? $row : ['text' => $row];

        // A row's address is data, often typed by somebody else. One the browser would run as
        // script, `javascript:` and the like, leaves the row as text rather than a link. A number
        // is a relative path, as it is to every component that reads its links from a list.
        $href = $row['href'] ?? null;
        $href = is_int($href) || is_float($href) ? (string) $href : $href;
        $href = is_string($href) || $href instanceof \Stringable || $href instanceof \BackedEnum
            ? \Pushery\WireKit\Support\SafeUrl::href(\Pushery\WireKit\Support\UrlProp::text($href))
            : '';

        $rows[] = [
            'text' => (string) ($row['text'] ?? ''),
            'href' => $href !== '' ? $href : null,
            'meta' => filled($row['meta'] ?? null) ? (string) $row['meta'] : null,
        ];
    }

    if ($rows !== []) {
        $rowClasses = WireKit::resolveClasses('list-item', 'base', '', $scope);
        $rowLinkClasses = WireKit::resolveClasses('link', 'base', implode(' ', [
            '[overflow-wrap:break-word]',
            'font-[family-name:var(--font-wk-sans)]',
            'cursor-pointer',
            'transition-[color,text-decoration-color,opacity]',
            'duration-[var(--transition-wk-duration)]',
            'ease-[var(--transition-wk-easing)]',
            'hover:opacity-[var(--opacity-wk-hover)]',
            'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)]',
            'text-[color:var(--color-wk-accent-text)]',
            'underline underline-offset-2',
        ]));
        $rowMetaClasses = WireKit::resolveClasses('text', 'base', implode(' ', [
            'font-[family-name:var(--font-wk-sans)] tracking-[var(--font-wk-letter-spacing)]',
            'leading-[var(--font-wk-line-height,1.5)]',
            'text-[length:var(--text-wk-sm)]',
            'text-[color:var(--color-wk-text-muted)]',
            'font-[number:var(--font-wk-body-weight)]',
        ]));
    }
@endphp

<{{ $tag }} data-wk-prose-skip @if($listRole) role="list" @endif {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    @foreach($rows as $row)
        <li data-wk-prose-skip class="{{ $rowClasses }}">@if($row['href'] !== null)<a data-wk-prose-skip href="{{ $row['href'] }}" class="{{ $rowLinkClasses }}">{{ $row['text'] }}</a>@else{{ $row['text'] }}@endif @if($row['meta'] !== null)<span class="{{ $rowMetaClasses }}">{{ $row['meta'] }}</span>@endif</li>
    @endforeach
    {{ $slot }}
</{{ $tag }}>
