{{-- optimistic-ui: n/a — sub-component
     Not a component at all but an @include shared by the two branches of
     app-rail/item.blade.php. It renders a navigation link; nothing here has a value a
     server could accept or refuse. --}}
{{-- The rail module's link, factored out because the item renders it EITHER inside a
     tooltip or bare, and the two branches must not become two copies of the same anchor.
     Every variable it reads ($linkAttributes, $icon, $iconClasses, $labelClasses,
     $labelText, $badge, $opensNewTab) is resolved in the including view — @include shares
     the including scope, so nothing has to be passed.

     `$linkAttributes` in particular is resolved there ON PURPOSE: inside a component's
     slot `$attributes` is the WRAPPER's bag, so reading it here would put the tooltip's
     attributes on the link in one branch and this component's in the other. --}}
{{-- href / aria-current / rel are written HERE rather than folded into $linkAttributes,
     and they are plain locals from the including view — @include shares that scope, and
     unlike `$attributes` these are not rebound by the surrounding component.
     They belong on the element explicitly for the same reason sidebar.item writes them
     explicitly: `$attributes->merge` treats a value as a DEFAULT, so a caller's own `rel`
     (even `rel="prev"`) would win and silently defeat the tabnabbing injection, and a
     caller's own `aria-current` would beat the component's own notion of "current". --}}
{{-- The element is chosen by the including view's `as` prop and defaults to `a`. A rail
     module is a destination, so a link is right for nearly all of them; a `button` is for
     the entry that OPENS something — our own console-shell blueprint builds a dropdown
     trigger out of one, and as a link that trigger does not activate on Space.
     `href` and `rel` are written only in the link branch: they mean nothing on a button,
     and an `href` there is invalid rather than merely useless. `aria-current` is written
     in both, because it is defined for any element and an "active" module is active
     whichever tag draws it. --}}
<{{ $railTag }}
    {{-- The marker the square-app-icon rule addresses. --}}
    data-wk-rail-item
    @if($railTag === 'button') type="button" @endif
    @if($railTag === 'a') href="{{ $href }}" @endif
    @if($active) aria-current="page" @endif
    @if($railTag === 'a' && $computedRel) rel="{{ $computedRel }}" @endif
    {{ $linkAttributes }}
>
    @if($icon)
        {{-- Decorative: the label below is the accessible name. --}}
        <span class="shrink-0" aria-hidden="true">
            @if(is_string($icon) && ! str_contains($icon, '<') && function_exists('svg'))
                {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => $iconClasses]) }}
            @else
                {{ $icon }}
            @endif
        </span>
    @endif
    <span data-wk-rail-label class="{{ $labelClasses }}">{{ $labelText }}</span>
    @if(filled($badge))
        {{-- Going `absolute` in the icon-only rail is what keeps the lone glyph centered —
             sharing the flex row pushes it visibly off-center, which is what the browser
             test measures. In the wide rail it rejoins the row as a pill. The digits move
             to sr-only rather than being removed, so the count stays part of the link's
             accessible name in both states. --}}
        <span class="pointer-events-none absolute end-[calc(var(--padding-wk-x-sm)/2)] top-[calc(var(--padding-wk-y-sm)/2)] h-2 w-2 rounded-[var(--radius-wk-full)] bg-[var(--color-wk-rail-badge)]
            group-data-[labels=inline]/wk-rail:pointer-events-auto
            group-data-[labels=inline]/wk-rail:static
            group-data-[labels=inline]/wk-rail:ms-auto
            group-data-[labels=inline]/wk-rail:inline-flex
            group-data-[labels=inline]/wk-rail:h-auto
            group-data-[labels=inline]/wk-rail:w-auto
            group-data-[labels=inline]/wk-rail:items-center
            group-data-[labels=inline]/wk-rail:px-[var(--padding-wk-x-sm)]
            group-data-[labels=inline]/wk-rail:text-[length:var(--text-wk-xs)]
            group-data-[labels=inline]/wk-rail:font-[number:var(--font-wk-heading-weight)]
            group-data-[labels=inline]/wk-rail:text-[color:var(--color-wk-rail-badge-fg)]"><span class="sr-only group-data-[labels=inline]/wk-rail:not-sr-only">{{ $badge }}</span></span>
    @endif
    {{-- Link branch only. `$opensNewTab` is derived from a `target` attribute, and a
         `<button>` has no target — so on a button this promised a new tab that never
         opens, which is worse than saying nothing: it is a claim made only to the
         people who cannot check it. --}}
    @if($railTag === 'a' && $opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $railTag }}>
