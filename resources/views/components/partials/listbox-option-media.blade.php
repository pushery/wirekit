{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include shared by `combobox` and `multi-select`. It draws an
     option's decorative medium and accepts nothing a server could refuse. --}}
{{-- ONE OPTION'S MEDIUM: an icon, an image, an avatar or a flag, drawn by Alpine from the option object.

     The whole box is `aria-hidden`. The option's label already names it, and a photo's alt text
     or an avatar's initials would read the same name twice, or read letters nobody wrote.

     An icon is a `<use>` into the sprite the component rendered on the server (see IconSprite for
     why, with the measurements). An avatar shows its photo until the photo fails to load, and its
     initials from then on, or from the start when it has no photo at all.

     ⚠️ Every <img> here is `loading="lazy"`, and the reason is the list rather than the image. An
     option list is a SCROLL region: every row exists in the DOM the moment the panel opens, while
     roughly eight of them are on screen.

     Two measurements, 2026-09-18, and they are deliberately kept apart. The SIZE of the problem is
     read off the render: a `phone` picker offering every country puts 243 flag URLs into its
     options. The EFFECT is measured in a browser, where the flag path is redirected at a
     three-artwork fixture and only two rows carry an image at all — eager fetched both, including
     the one far below the fold; lazy fetched one. That is the behavior; the first number is what
     makes it worth having, and nobody has measured 243 requests in one place.

     The icon is not an <img> and is unaffected: it is a `<use>` into a sprite the server already
     rendered, so it costs no request at all.

     Params: $option (the JS name of the option in scope), $boxClasses (the box size),
     $iconClasses (the glyph size), $initialsClasses (the initials' font size). --}}
<span aria-hidden="true" class="inline-flex shrink-0 items-center justify-center {{ $boxClasses }}">
    <template x-if="{{ $option }}.media === 'icon'">
        <svg focusable="false" class="{{ $iconClasses }}"><use x-bind:href="{{ $option }}.iconRef"></use></svg>
    </template>
    <template x-if="{{ $option }}.media === 'image'">
        <img data-wk-prose-skip loading="lazy" x-bind:src="{{ $option }}.src" alt="" class="size-full rounded-[var(--radius-wk-sm)] object-cover" />
    </template>
    {{-- A flag is its 4:3 artwork across the box's width, with the hairline the flag component
         draws. Without a URL, because there is no flag for that code, the
         same box is drawn empty. --}}
    <template x-if="{{ $option }}.media === 'flag'">
        <span class="inline-flex size-full items-center">
            <template x-if="{{ $option }}.src">
                <img data-wk-prose-skip loading="lazy" x-bind:src="{{ $option }}.src" alt="" data-wk-flag class="aspect-[4/3] w-full rounded-[calc(var(--radius-wk-sm)/2)]" />
            </template>
            <template x-if="! {{ $option }}.src">
                <span data-wk-flag class="block aspect-[4/3] w-full rounded-[calc(var(--radius-wk-sm)/2)] bg-[var(--color-wk-bg-muted)]"></span>
            </template>
        </span>
    </template>
    <template x-if="{{ $option }}.media === 'avatar'">
        <span class="inline-flex size-full items-center justify-center overflow-hidden rounded-full border-[length:var(--border-wk-width)] border-[var(--color-wk-border-subtle)] bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)] font-[number:var(--font-wk-heading-weight)] leading-none {{ $initialsClasses }}">
            <template x-if="! showsInitials({{ $option }})">
                <img data-wk-prose-skip loading="lazy" x-bind:src="{{ $option }}.src" alt="" x-on:error="markMediaBroken({{ $option }})" class="size-full object-cover" />
            </template>
            <template x-if="showsInitials({{ $option }})">
                <span x-text="{{ $option }}.initials"></span>
            </template>
        </span>
    </template>
</span>
