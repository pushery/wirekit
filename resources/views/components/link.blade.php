{{-- optimistic-ui: n/a — navigation
     The anchor primitive. Where a developer puts a wire:click on it, the optimism belongs to whatever that action changes — not to the link. --}}
@props([
    'href' => null,
    'variant' => 'default',
    // Font size, in `text`'s vocabulary — `xs`, `sm`, `base`, `lg`, `xl`.
    //
    // `null` takes the size and the font family of the text around the link: a
    // link sits inside a sentence far more often than it stands alone, and a class
    // here would resize or restyle every link in body copy, a monospaced or a serif
    // one included. A named size is for a link that stands on its own, in a footer
    // or a preferences row, and sets the kit's sans family with it.
    //
    // `text`'s rung names, `base` and not `md`, so one vocabulary covers both
    // ladders and renaming a rung in one cannot resize the other.
    'size' => null,
    'external' => false,
    // Whether a link that opens a new tab says so: the arrow icon `external` draws and the
    // screen-reader hint "(opens in new tab)" that every `target="_blank"` carries. `false`
    // drops both and keeps the target and the forced `rel`. It is for an anchor whose script
    // turns it into something else, a document link that opens a dialog when script runs and
    // a new tab when it does not: there the automatic announcement is wrong half the time, and
    // the call site states what the reader gets instead.
    'announceNewTab' => true,
    // Words that are a link only for a reader who may follow it: an order or a customer named
    // on another screen, whose own page answers "not found" to a reader without the right.
    // Without an `href`, `true` draws the slot as text, a `<span>` that keeps the caller's
    // attributes and the size, and has neither the link's color, underline, pointer and focus
    // ring nor the attributes only an anchor reads. `false` keeps the `<a>` without an `href`
    // this component has always drawn, which a script can give its address later.
    'plainWithoutHref' => false,
    'underline' => 'always',
    'as' => 'a',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('link', $attributes->getAttributes());
    // Echoed into the tag or bound, the URL is written escaped once (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);

    // Blade compiles an unbound attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $external = BooleanProp::from($external, false, 'link', 'external');
    $announceNewTab = BooleanProp::from($announceNewTab, true, 'link', 'announceNewTab');
    $plainWithoutHref = BooleanProp::from($plainWithoutHref, false, 'link', 'plainWithoutHref');

    $variantClasses = match (WireKit::validateProp('link', 'variant', $variant, ['default', 'subtle', 'muted', 'text'])) {
        'default' => 'text-[color:var(--color-wk-accent-text)]',
        'subtle' => 'text-[color:var(--color-wk-text-subtle)]',
        'muted' => 'text-[color:var(--color-wk-text-muted)]',
        // The color of the running text. The underline stays, and inside a sentence it is what
        // marks the words as a link; `underline="hover"` suits a link that stands on its own,
        // such as a number in a table column.
        'text' => 'text-[color:var(--color-wk-text)]',
    };

    // Mirrors `text`'s map rung for rung, including its `var()` fallbacks, so
    // the two cannot drift into disagreeing about what a rung is worth.
    // No size is the default and inherits the text around the link, so it skips the validator:
    // `(string) null` is an empty string, which the list rejects.
    $sizeClasses = match ($size === null || $size === '' ? null : WireKit::validateProp('link', 'size', (string) $size, ['xs', 'sm', 'base', 'lg', 'xl'])) {
        null => '',
        'xs' => 'text-[length:var(--text-wk-xs,0.75rem)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'base' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl,1.25rem)]',
    };

    // Without a size the link stands in copy and takes its family from it, as it takes its size;
    // `text`'s `inherit` family emits no class either.
    $familyClasses = $sizeClasses === '' ? '' : 'font-[family-name:var(--font-wk-sans)]';

    $underlineClasses = match (WireKit::validateProp('link', 'underline', $underline, ['always', 'hover', 'none'])) {
        'always' => 'underline underline-offset-2',
        'hover' => 'hover:underline underline-offset-2',
        'none' => 'no-underline',
    };

    // `array_filter` because `size` and the family default to no class at all: an
    // empty entry inside the implode would put a double space into the class list.
    $classes = WireKit::resolveClasses('link', 'base', implode(' ', array_filter([
        // A typed token with no break point, an email address or a URL, breaks rather than running
        // out of the box. Inherited by everything inside; `break-word` leaves every min-content width
        // alone, so a table or a flex row in the content keeps the layout it had.
        '[overflow-wrap:break-word]',
        $familyClasses,
        // Unconditional, and not narrowed to the non-anchor branch. A link is defined by what
        // it does rather than by the tag it happens to render, and this component renders a
        // `<button>` whenever the action has to be a POST or a Livewire call — which is exactly
        // what `as="button"` is for. On an anchor this repeats what the user-agent stylesheet
        // already says and costs nothing; on a button it replaces the browser's default arrow,
        // which Tailwind v4's preflight no longer overrides (v3 set `cursor: pointer`). Without
        // it a link rendered as a button shows the arrow, and the underline is its only affordance.
        'cursor-pointer',
        // Opacity is in the list: `transition-colors` leaves it out, and the hover dim jumped.
        'transition-[color,text-decoration-color,opacity]',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'hover:opacity-[var(--opacity-wk-hover)]',
        // The kit's focus ring rather than the browser's, which follows neither the ring tokens
        // nor a preset. No offset: a link sits inside a line of text, and a ring standing off it
        // would reach into the lines above and below.
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)]',
        $sizeClasses,
        $variantClasses,
        $underlineClasses,
    ])), $scope);

    // Auto-detect new-tab behavior from either the $external prop or an
    // attribute-passed target="_blank". Both paths converge to the same
    // rel="noopener noreferrer" + SR-hint output.
    //
    // Careful: $attributes->merge(['rel' => ...]) treats rel as a default —
    // if the caller passed their own rel (even rel="prev"), theirs wins and
    // our auto-injection would silently fail, re-introducing tabnabbing.
    // To force-override, we remove rel from the bag and render it separately.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $external || str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('link', (string) $as);

    // Only an anchor has an address to lose: `as="button"` is an action and never had one.
    $plainWords = $plainWithoutHref && $href === null && strtolower($as) === 'a';
    $plainClasses = '';
    $plainAttributes = $attributes;

    if ($plainWords) {
        // The words keep their size, their family and their breaking, which shape the line they
        // stand in, and nothing that marks a link. A block of its own, so a personalization of the link's look
        // leaves them alone, and one of `plain` reaches them.
        $plainClasses = WireKit::resolveClasses('link', 'plain', implode(' ', array_filter([
            '[overflow-wrap:break-word]',
            $familyClasses,
            $sizeClasses,
        ])), $scope);

        // What only an anchor reads goes with the anchor: its own attributes, and the two
        // Livewire directives that read its `href`. A name goes too, unless the caller gave the
        // words a role: ARIA prohibits naming an element that has none (axe `aria-prohibited-attr`).
        $plainAttributes = $attributes
            ->except([
                'target', 'rel', 'download', 'hreflang', 'ping', 'referrerpolicy', 'type',
                ...($attributes->has('role') ? [] : ['aria-label', 'aria-labelledby', 'aria-roledescription']),
            ])
            ->whereDoesntStartWith(['wire:navigate', 'wire:current']);
    }
@endphp

@php
    // Whitespace control: build the trailing HTML (external icon + SR hint) as
    // a single string so the rendered <a> has no whitespace between $slot and
    // </a>. Newlines inside the tag would render as a trailing space and
    // extend the underline past the link text.
    $extLink = $external && $announceNewTab
        ? '<svg class="inline-block h-3.5 w-3.5 ms-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>'
        : '';
    $newTabHint = $opensNewTab && $announceNewTab ? '<span class="relative"><span class="sr-only">'.e(__('wirekit::(opens in new tab)')).'</span></span>' : '';

    // Each branch ends on its closing tag with `@else` or `@endif` right after it, for the same
    // reason: a line break between them would be text after every link this component renders.
@endphp

@if($plainWords)
<span {{ $plainAttributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$plainClasses])) }}>{{ $slot }}</span>@else
<{{ $as }} data-wk-prose-skip
    @if($href !== null) href="{{ $href }}" @endif
    @if($external) target="_blank" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{-- `type` on a button, and only on a button.

         HTML puts a `<button>` with no `type` in the submit state, so a link-styled action
         inside a form — "Resend code", "Cancel" — submitted it on click and on Enter. Silent
         data loss for anyone who followed the prose rather than copying the `type="button"`
         out of the docs example.

         Resolved through the bag rather than emitted before it. A hardcoded `type="button"`
         here would come first, and HTML keeps the first
         occurrence of a repeated attribute — so it would silently beat a caller's own
         `type="submit"`. Reading the bag and excluding `type` from it emits exactly one
         attribute, and the caller's value wins when they gave one. --}}
    @if($as === 'button') type="{{ $attributes->get('type', 'button') }}" @endif
    {{ $attributes->except($as === 'button' ? ['rel', 'type'] : ['rel'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>{{ $slot }}{!! $extLink !!}{!! $newTabHint !!}</{{ $as }}>@endif