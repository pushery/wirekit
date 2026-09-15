{{-- optimistic-ui: n/a — passthrough
     Renders the developer's link or action; the item itself has no result to anticipate. --}}
@aware(['interactive' => false])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('bottom-nav.item', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['interactive']);
    // Auto-inject rel="noopener noreferrer" when target="_blank". This component
    // takes an href and echoes the caller's bag onto the element that carries it,
    // so the caller's target passed straight through to a bare anchor. The house
    // rule makes the injection unconditional for exactly that shape. Rendered
    // explicitly with the bag echoed via except('rel'), because
    // $attributes->merge() treats rel as a DEFAULT and a caller-supplied rel
    // would replace the computed value.
    $targetAttr = $attributes->get('target', '');
    // Unconditional, unlike the sibling in `fab.action` which gates on `$href`: this
    // component's `href` carries a default, so it always renders the anchor. The
    // conjunct was written as a literal `true &&` to mirror that sibling's shape and
    // reads as an unfinished edit; the behavior is identical without it.
    $opensNewTab = str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp


@props([
    'href' => '#',
    'label' => '',
    // Which page this is. Marks the item as the current one. In interactive mode
    // it is the INITIAL current tab; clicks then move it client-side.
    'active' => false,
    // Optional icon name — see the icon component.
    'icon' => null,
    // A small count on the icon ("3 unread"). It is announced as part of the
    // item's name, never as bare punctuation.
    'badge' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $active = BooleanProp::from($active, false);

    // The accessible name has to carry the badge: "Inbox" and "Inbox, 3" are
    // different claims, and a reader who cannot see the dot deserves the number.
    $accessibleName = ($badge !== null && $badge !== '')
        ? trim($label).', '.$badge
        : $label;

    // The other half of the target="_blank" rule: rel protects the opener, this
    // warns the person who cannot see the new tab appear. The house pattern puts
    // that hint in an sr-only span inside the link, which works for a link named
    // by its own content — and this one is not. It carries aria-label, and name
    // computation stops there, so a span inside would sit in the DOM and outside
    // the announcement. The hint therefore rides the label itself.
    if ($opensNewTab) {
        $accessibleName = trim($accessibleName.' '.__('wirekit::(opens in new tab)'));
    }

    $classes = WireKit::resolveClasses('bottom-nav.item', 'base', implode(' ', [
        'wk-bottom-nav-item',
        'relative flex flex-1 flex-col items-center justify-center gap-1',
        // Vertical padding gives the corner badge (-top-1.5 on the icon) room so it
        // does not break through the bar's top border line.
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-lg)]',
        'text-[length:var(--text-wk-xs)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset',
        // The current-tab look (accent color + heavier weight) is CSS keyed on
        // data-active (see dist/wirekit.css), NOT baked in here — so it follows the
        // state whether the server sets `active` or the interactive mode's Alpine
        // binding does. The marker line above it is the second, color-independent
        // signal (WCAG 1.4.1).
    ]), $scope);

    // Interactive mode (parent's `interactive`, via @aware): the tab flips its own
    // data-active on click and seeds the initial current tab, all against the
    // parent nav's `active` Alpine state. Merged into the bag rather than written
    // as @if inside the tag. Keyed by label — nav labels are distinct.
    $isInteractive = filter_var($interactive, FILTER_VALIDATE_BOOLEAN);
    if ($isInteractive) {
        // AlpinePayload, not Js::from: this literal is spliced into four Alpine
        // directives, and Js::from's \u escaping does not survive the CSP tokenizer —
        // a label with an umlaut would compare against mangled text and never match.
        $key = \Pushery\WireKit\Support\AlpinePayload::from($label);
        $attributes = $attributes->merge([
            'x-init' => 'if ('.($active ? 'true' : 'false').' && active === null) active = '.$key,
            'x-on:click.prevent' => 'active = '.$key,
            'x-bind:data-active' => 'active === '.$key.' ? \'true\' : \'false\'',
            'x-bind:aria-current' => 'active === '.$key.' ? \'page\' : null',
        ]);
    }
@endphp

<a data-wk-prose-skip
    href="{{ $href }}"
    @if($active) aria-current="page" @endif
    {{-- `aria-label=""` is not "no label", it is an EMPTY name, and it overrides the text
         content that would otherwise have named the link. With no `label` prop this emitted
         exactly that, so the item became a nameless link rather than falling back to
         anything. Omitted entirely when there is nothing to say. --}}
    @if(filled($accessibleName)) aria-label="{{ $accessibleName }}" @endif
    data-wk-bottom-nav-item
    data-active="{{ $active ? 'true' : 'false' }}"
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes]) }}
>
    @if($icon)
        <span class="relative inline-flex" aria-hidden="true">
            <x-wirekit::icon :name="$icon" class="h-6 w-6" />
            @if($badge !== null && $badge !== '')
                {{-- aria-hidden via the wrapper: the number is already in the
                     link's accessible name, and announcing it twice is worse
                     than not at all. --}}
                <span data-wk-bottom-nav-badge class="absolute -end-1.5 -top-1.5 inline-flex min-w-4 items-center justify-center rounded-[var(--radius-wk-full)] bg-[var(--color-wk-danger)] px-1 text-[length:var(--text-wk-xs)] leading-4 text-[color:var(--color-wk-danger-fg)]">{{ $badge }}</span>
            @endif
        </span>
    @endif

    <span class="truncate">{{ $label }}</span>
</a>
