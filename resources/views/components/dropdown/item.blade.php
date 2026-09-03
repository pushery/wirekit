{{-- optimistic-ui: n/a — passthrough
     The action is the developer's, arriving through the attribute bag. Optimism belongs to whatever that action changes. --}}
@props([
    'href' => null,
    'danger' => false,
    'disabled' => false,
    'icon' => null,
    'shortcut' => null, // keyboard-shortcut hint shown at the inline-end (e.g. "⌘K")
    // "This is the page you are on." A navigation menu's most common state, and until now
    // there was no way to say it — so the only mark on screen was the focus ring the menu
    // puts on its first item when it opens, which reads as a selection to everyone who did
    // not put it there.
    //
    // Deliberately NOT painted as a background. Hover and focus already use the subtle
    // surface, and a third thing that looks like those two would not tell them apart. The
    // current entry is marked by weight and text color, which survives beside either.
    'active' => false,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('dropdown.item', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $danger = BooleanProp::from($danger, false);
    $disabled = BooleanProp::from($disabled, false);
    $active = BooleanProp::from($active, false);

    // Base item classes — full-width flex row with hover state
    $classes = WireKit::resolveClasses('dropdown.item', 'base', implode(' ', [
        'flex items-center gap-x-[var(--gap-wk-sm)] w-full',
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-none',
        // `focus-visible`, not `focus`, and a RING rather than only a surface.
        //
        // The menu focuses its first item on open so keyboard users land inside it. With a
        // plain `focus:` background that meant every mouse-opened menu painted its first entry
        // the moment it appeared — and a filled row reads as "you are here", not as "the
        // caret is here". Reported by a user sitting on a page that was not in the menu at
        // all: "why is that highlighted when I am not even on that page?"
        //
        // `focus-visible` is the heuristic the browser already maintains: it matches after a
        // programmatic focus when the last interaction was the keyboard, and does not after a
        // click. So the keyboard user keeps the mark and the mouse user, who never asked for
        // it, no longer gets one. The ring is what separates focus from the `active` state
        // below, which is a weight-and-color change and can sit under it without conflict.
        'focus-visible:bg-[var(--color-wk-bg-subtle)]',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-inset',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'cursor-pointer',
    ]), $scope);

    // Color classes — danger variant or default neutral text
    $colorClasses = $danger
        ? 'text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)]'
        : 'text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-subtle)]';

    // Disabled state — muted appearance, no pointer events
    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';

    // The current entry. Weight and color only: hover and focus own the surface, and a third
    // background would be indistinguishable from them at exactly the moment it matters. This
    // stays legible while an item is hovered AND while it holds focus, which a surface cannot.
    // `danger` keeps its own color — an active destructive item is still destructive.
    $activeClasses = $active && ! $danger
        ? 'font-medium text-[color:var(--color-wk-accent-text)]'
        : ($active ? 'font-medium' : '');

    // Render as <a> when href is provided, otherwise <button>
    $tag = $href ? 'a' : 'button';

    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // CAREFUL: $attributes->merge(['rel' => ...]) treats rel as a DEFAULT —
    // if caller passed rel="prev", theirs wins and our auto-injection is lost,
    // silently re-introducing the tabnabbing vulnerability. Hence except('rel')
    // + explicit rel attribute render.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{-- Honor a caller-provided type so a no-href item can drive a form
         (e.g. type="submit" for a CSRF logout inside a wrapping <form>);
         defaults to "button". The bag's `type` is stripped below so it
         renders exactly once. --}}
    @if($tag === 'button') type="{{ $attributes->get('type', 'button') }}" @endif
    role="menuitem"
    tabindex="-1"
    @if($disabled) aria-disabled="true" @endif
    {{-- The state said out loud, not only drawn. `page` when the item navigates, `true` when it
         does not — an item without an href is an action, and "page" would be a claim about a
         document that is not being visited. A caller who set `aria-current` themselves keeps
         theirs: this fills a gap rather than overriding an author. --}}
    @if($active && ! $attributes->has('aria-current')) aria-current="{{ $href ? 'page' : 'true' }}" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except(['rel', 'type'])->class([$classes, $colorClasses, $activeClasses, $disabledClasses]) }}
>
    {{-- Optional icon (resolved via WireKit icon system) --}}
    @if($icon)
        <span class="shrink-0 w-5 h-5" aria-hidden="true">
            @if(function_exists('svg'))
                {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
            @endif
        </span>
    @endif

    {{ $slot }}

    {{-- Keyboard-shortcut hint, pushed to the inline-end. Decorative. --}}
    @if($shortcut)
        <span class="ms-auto ps-[var(--padding-wk-x-md)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] tabular-nums" aria-hidden="true">{{ $shortcut }}</span>
    @endif

    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
