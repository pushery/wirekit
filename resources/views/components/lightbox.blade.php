@props([
    // Instance identifier. Any control can open this lightbox by dispatching
    // `wirekit-lightbox-open` with a matching `name` + `index`.
    'name' => null,
    // The slides. Each item is an array:
    //   ['src' => …, 'alt' => …, 'caption' => …, 'type' => 'image'|'video'|'embed']
    // `type` defaults to 'image'. A plain string is treated as a decorative
    // image src.
    'items' => [],
    // Whether prev/next wraps around at the ends (true) or stops (false).
    'loop' => true,
    // Show each item's caption inside the lightbox.
    'showCaptions' => true,
    // Backdrop color / opacity for THIS instance (any CSS color). Null → the
    // themeable --color-wk-overlay token.
    'overlay' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $loop = BooleanProp::from($loop, true);
    $showCaptions = BooleanProp::from($showCaptions, true);

    // Normalize each entry to ['src','alt','caption','type','poster']. `poster`
    // is a video-only still shown before the clip paints its first frame (so the
    // surface is not blank while it buffers); null for non-video items.
    $slides = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $type = $item['type'] ?? 'image';
            $slides[] = [
                'src' => (string) ($item['src'] ?? ''),
                'alt' => (string) ($item['alt'] ?? ''),
                'caption' => isset($item['caption']) ? (string) $item['caption'] : null,
                'type' => in_array($type, ['image', 'video', 'embed'], true) ? $type : 'image',
                'poster' => isset($item['poster']) ? (string) $item['poster'] : null,
            ];
        } else {
            $slides[] = ['src' => (string) $item, 'alt' => '', 'caption' => null, 'type' => 'image', 'poster' => null];
        }
    }

    $lightboxId = $name ?: 'wk-lightbox-'.Str::random(6);
    $count = count($slides);
    $backdrop = $overlay ?: 'var(--color-wk-overlay)';

    $wrapperClasses = WireKit::resolveClasses('lightbox', 'base', '', $scope);
@endphp

<div
    x-data="wirekitLightbox({ name: @js($lightboxId), count: {{ $count }}, loop: @js((bool) $loop) })"
    {{ $attributes->class([$wrapperClasses]) }}
>
    {{-- Optional trigger content (thumbnails / buttons). Anything here can call
         openAt(index) — it shares this component's Alpine scope. External
         controls elsewhere on the page can instead dispatch
         wirekit-lightbox-open { name, index }. --}}
    {{ $slot }}

    @if($count > 0)
        {{-- The overlay — teleported to body, focus-trapped, arrow/Escape keyboard.
             createFocusTrap returns focus to the trigger on close. --}}
        <template x-teleport="body">
            <div
                x-show="open"
                x-cloak
                x-ref="stage"
                role="dialog"
                aria-modal="true"
                aria-label="{{ __('Media viewer') }}"
                x-on:keydown.escape.prevent="close()"
                x-on:keydown.arrow-right.prevent="next()"
                x-on:keydown.arrow-left.prevent="prev()"
                class="fixed inset-0 z-[var(--z-wk-modal)] flex items-center justify-center p-[var(--space-wk-md)]"
            >
                {{-- Backdrop — click closes. Per-instance color via inline style. --}}
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-on:click="close()"
                    aria-hidden="true"
                    class="absolute inset-0"
                    style="background: {{ $backdrop }}"
                ></div>

                {{-- The media surface. The focus trap lives on the dialog wrapper
                     above (it holds the prev/next/close controls); this figure
                     holds only the slides + caption. --}}
                <figure
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="relative z-10 m-0 flex max-h-[90vh] max-w-[92vw] flex-col items-center gap-[var(--space-wk-sm)]"
                >
                    <template x-for="(item, idx) in {{ Js::from($slides) }}" :key="idx">
                        <div x-show="current === idx" class="flex items-center justify-center">
                            <template x-if="item.type === 'video'">
                                <video :src="item.src" :poster="item.poster" controls preload="metadata" class="max-h-[85vh] w-auto max-w-[90vw] rounded-[var(--radius-wk-md)] shadow-[var(--shadow-wk-lg)]"></video>
                            </template>
                            <template x-if="item.type === 'embed'">
                                <iframe :src="item.src" :title="item.alt" loading="lazy" class="aspect-video w-[90vw] max-w-[90vw] max-h-[85vh] rounded-[var(--radius-wk-md)] shadow-[var(--shadow-wk-lg)]" allowfullscreen></iframe>
                            </template>
                            <template x-if="! item.type || item.type === 'image'">
                                {{-- Large images scale to fit the viewport: object-contain +
                                     max-h-[85vh] (vertical cap) + max-w-[90vw] (horizontal
                                     cap) keeps the aspect ratio for BOTH very tall and very
                                     wide images, using most of the screen (not a fixed box).
                                     Off-screen slides load lazily (loading="lazy"); a spinner
                                     shows while a large image downloads, then the image
                                     fades in on load. --}}
                                <div class="relative flex items-center justify-center" x-data="{ loaded: false }" :class="! loaded ? 'min-h-[10rem] min-w-[10rem]' : ''">
                                    <span x-show="! loaded" x-cloak class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                        <svg class="h-8 w-8 animate-spin text-[color:var(--color-wk-bg)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </span>
                                    <img
                                        :src="item.src"
                                        :alt="item.alt"
                                        loading="lazy"
                                        decoding="async"
                                        x-init="if ($el.complete && $el.naturalWidth > 0) loaded = true"
                                        x-on:load="loaded = true"
                                        x-on:error="loaded = true"
                                        :class="loaded ? 'opacity-100' : 'opacity-0'"
                                        class="max-h-[85vh] w-auto max-w-[90vw] rounded-[var(--radius-wk-md)] object-contain shadow-[var(--shadow-wk-lg)] transition-opacity duration-200"
                                    />
                                </div>
                            </template>
                        </div>
                    </template>

                    @if($showCaptions)
                        {{-- Caption sits on a semi-transparent dark scrim (the themeable
                             --color-wk-overlay token, i.e. translucent black) over the dark
                             backdrop, plus a subtle text-shadow — together the white text
                             stays legible even over bright media, where the translucent
                             scrim alone would dip below AA on the light theme. Wraps +
                             centers so a long caption never overruns the media. --}}
                        <figcaption
                            x-show="{{ Js::from($slides) }}[current]?.caption"
                            x-text="{{ Js::from($slides) }}[current]?.caption"
                            class="max-w-3xl text-balance rounded-[var(--radius-wk-md)] bg-[var(--color-wk-overlay)] px-[var(--space-wk-sm)] py-[var(--space-wk-xs)] text-center text-[length:var(--text-wk-sm)] leading-relaxed text-white [text-shadow:0_1px_2px_rgba(0,0,0,0.6)]"
                        ></figcaption>
                    @endif
                </figure>

                @if($count > 1)
                    <button
                        type="button"
                        x-on:click="prev()"
                        :disabled="! hasPrev"
                        aria-label="{{ __('Previous') }}"
                        class="absolute left-[var(--space-wk-md)] top-1/2 z-20 -translate-y-1/2 flex h-10 w-10 items-center justify-center cursor-pointer rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed"
                    >
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M12.5 4L7 10l5.5 6"/></svg>
                    </button>
                    <button
                        type="button"
                        x-on:click="next()"
                        :disabled="! hasNext"
                        aria-label="{{ __('Next') }}"
                        class="absolute right-[var(--space-wk-md)] top-1/2 z-20 -translate-y-1/2 flex h-10 w-10 items-center justify-center cursor-pointer rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed"
                    >
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7.5 4L13 10l-5.5 6"/></svg>
                    </button>
                @endif

                <button
                    type="button"
                    x-on:click="close()"
                    aria-label="{{ __('Close') }}"
                    class="absolute right-[var(--space-wk-md)] top-[var(--space-wk-md)] z-20 flex h-10 w-10 items-center justify-center cursor-pointer rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                >
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>
        </template>
    @endif
</div>
