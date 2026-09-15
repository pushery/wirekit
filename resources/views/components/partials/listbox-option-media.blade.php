{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include shared by `combobox` and `multi-select`. It draws an
     option's decorative medium and accepts nothing a server could refuse. --}}
{{-- ONE OPTION'S MEDIUM: an icon, an image, an avatar or a flag, drawn by Alpine from the option object.

     The whole box is `aria-hidden`. The option's label already names it, and a photo's alt text
     or an avatar's initials would read the same name twice, or read letters nobody wrote.

     An icon is a `<use>` into the sprite the component rendered on the server (see IconSprite for
     why, with the measurements). An avatar shows its photo until the photo fails to load, and its
     initials from then on, or from the start when it has no photo at all.

     Params: $option (the JS name of the option in scope), $boxClasses (the box size),
     $iconClasses (the glyph size), $initialsClasses (the initials' font size). --}}
<span aria-hidden="true" class="inline-flex shrink-0 items-center justify-center {{ $boxClasses }}">
    <template x-if="{{ $option }}.media === 'icon'">
        <svg focusable="false" class="{{ $iconClasses }}"><use x-bind:href="{{ $option }}.iconRef"></use></svg>
    </template>
    <template x-if="{{ $option }}.media === 'image'">
        <img data-wk-prose-skip x-bind:src="{{ $option }}.src" alt="" class="size-full rounded-[var(--radius-wk-sm)] object-cover" />
    </template>
    {{-- A flag is its 4:3 artwork across the box's width, with the hairline the flag component
         draws. Without a URL, because the flags package is missing or has no flag for the code, the
         same box is drawn empty. --}}
    <template x-if="{{ $option }}.media === 'flag'">
        <span class="inline-flex size-full items-center">
            <template x-if="{{ $option }}.src">
                <img data-wk-prose-skip x-bind:src="{{ $option }}.src" alt="" data-wk-flag class="aspect-[4/3] w-full rounded-[calc(var(--radius-wk-sm)/2)]" />
            </template>
            <template x-if="! {{ $option }}.src">
                <span data-wk-flag class="block aspect-[4/3] w-full rounded-[calc(var(--radius-wk-sm)/2)] bg-[var(--color-wk-bg-muted)]"></span>
            </template>
        </span>
    </template>
    <template x-if="{{ $option }}.media === 'avatar'">
        <span class="inline-flex size-full items-center justify-center overflow-hidden rounded-full border-[length:var(--border-wk-width)] border-[var(--color-wk-border-subtle)] bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)] font-[number:var(--font-wk-heading-weight)] leading-none {{ $initialsClasses }}">
            <template x-if="! showsInitials({{ $option }})">
                <img data-wk-prose-skip x-bind:src="{{ $option }}.src" alt="" x-on:error="markMediaBroken({{ $option }})" class="size-full object-cover" />
            </template>
            <template x-if="showsInitials({{ $option }})">
                <span x-text="{{ $option }}.initials"></span>
            </template>
        </span>
    </template>
</span>
