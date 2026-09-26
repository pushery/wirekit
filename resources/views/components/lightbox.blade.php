{{-- optimistic-ui: n/a — client-only
     Its state is which image is shown. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
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
    // Per-slide overlay render-callback: a closure `fn($slide, $i)` returning markup to lay
    // OVER the media of slide #$i in the viewer (a label, a watermark, a report control), shown
    // only while that slide is. The wrapper is pointer-events-none; interactive controls inside
    // opt back in with `pointer-events-auto`. Return a view or HtmlString for HTML (a plain
    // string is escaped). Null → no overlay.
    'slideOverlay' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('lightbox', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $loop = BooleanProp::from($loop, true);
    $showCaptions = BooleanProp::from($showCaptions, true);

    // Normalize each entry to ['src','alt','caption','type','poster']. `poster`
    // is a video-only still shown before the clip paints its first frame (so the
    // surface is not blank while it buffers); null for non-video items.
    /*
     * An `embed` item becomes an iframe `src`, and a `javascript:` URL in an iframe `src`
     * runs in the PARENT document's context — so an item built from a database row was a
     * script-execution channel, not merely a bad link. `data:` is the same class one step
     * removed: a `data:text/html` frame gets its own origin but can still paint a
     * convincing overlay on top of the page it was opened from.
     *
     * Allowed: http, https, protocol-relative, and anything without a scheme (a path the
     * application resolves itself). Everything else is dropped to an empty src, which
     * renders an empty frame — visibly wrong, rather than quietly dangerous.
     */
    $safeEmbedSrc = static function (string $src): string {
        $src = trim($src);

        if ($src === '' || str_starts_with($src, '//')) {
            return $src;
        }

        // A scheme is what precedes the first colon, and only when no slash comes first —
        // `path/to:file` is a relative path, not a scheme.
        $colon = strpos($src, ':');
        $slash = strpos($src, '/');

        if ($colon === false || ($slash !== false && $slash < $colon)) {
            return $src;
        }

        return in_array(strtolower(substr($src, 0, $colon)), ['http', 'https'], true) ? $src : '';
    };

    $slides = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $type = $item['type'] ?? 'image';
            $slides[] = [
                'src' => ($type === 'embed')
                    ? $safeEmbedSrc((string) ($item['src'] ?? ''))
                    : (string) ($item['src'] ?? ''),
                // An embed's `alt` becomes the iframe's `title`, and `title=""` is a NAMELESS
                // frame — announced as an unlabeled region the reader has no way to identify.
                // Images may legitimately be decorative, so the fallback is scoped to embeds.
                //
                // Resolved here rather than as a `:title="item.alt || …"` binding: that
                // operator is outside Alpine's CSP grammar, so the expression is never
                // evaluated on the CSP bundle and the frame loses its title there entirely.
                'alt' => ($type === 'embed' && trim((string) ($item['alt'] ?? '')) === '')
                    ? __('wirekit::Embedded content')
                    : (string) ($item['alt'] ?? ''),
                'caption' => isset($item['caption']) ? (string) $item['caption'] : null,
                'type' => in_array($type, ['image', 'video', 'embed'], true) ? $type : 'image',
                'poster' => isset($item['poster']) ? (string) $item['poster'] : null,
            ];
        } else {
            $slides[] = ['src' => (string) $item, 'alt' => '', 'caption' => null, 'type' => 'image', 'poster' => null];
        }
    }

    // Translated on the server and handed to the factory as a TEMPLATE. A sentence
    // assembled from fragments in JavaScript cannot be translated, and "of" is not a
    // word every language puts in the middle — the same route the carousel's position
    // announcement takes.
    $positionTemplate = __('wirekit::Slide :current of :total');

    // Counted, not random, for the nameless case. Alpine keys its component state to the
    // element; a fresh id on every render is a fresh component to a Livewire morph, so an
    // unrelated update closed the lightbox and lost which slide the reader was on. A caller
    // who passes `name` was already stable — this makes the default stable too.
    $lightboxId = $name ?: \Pushery\WireKit\Support\DomId::unique(null, 'wk-lightbox-');
    $count = count($slides);
    $backdrop = $overlay ?: 'var(--color-wk-overlay)';

    $wrapperClasses = WireKit::resolveClasses('lightbox', 'base', '', $scope);
@endphp

<div
    x-data="wirekitLightbox({ name: {{ \Pushery\WireKit\Support\AlpinePayload::from($lightboxId) }}, count: {{ $count }}, loop: {{ \Pushery\WireKit\Support\AlpinePayload::from((bool) $loop) }}, slides: {{ \Pushery\WireKit\Support\AlpinePayload::from($slides) }}, announcement: {{ \Pushery\WireKit\Support\AlpinePayload::from($positionTemplate) }} })"
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
        <template x-teleport="#wk-overlay-root">
            <div
                x-show="open"
                x-cloak
                x-ref="stage"
                role="dialog"
                aria-modal="true"
                aria-label="{{ __('wirekit::Media viewer') }}"
                x-on:keydown.escape.prevent="close()"
                x-on:keydown.arrow-right.prevent="next()"
                x-on:keydown.arrow-left.prevent="prev()"
                class="wk-overlay-fixed wk-overlay-layer-modal fixed inset-0 z-[var(--z-wk-modal)] flex items-center justify-center p-[var(--space-wk-md)]"
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
                    {{-- The media box: exactly the visible slide, so an overlay laid over it covers the
                         media and not the caption below. --}}
                    <div data-wk-lightbox-media class="relative">
                        <template x-for="(item, idx) in {{ \Pushery\WireKit\Support\AlpinePayload::from($slides) }}" :key="idx">
                            <div x-show="current === idx" class="flex items-center justify-center">
                                <template x-if="item.type === 'video'">
                                    <video :src="item.src" :poster="item.poster" controls preload="metadata" class="max-h-[85vh] w-auto max-w-[90vw] rounded-[var(--radius-wk-md)] shadow-[var(--shadow-wk-lg)]"></video>
                                </template>
                                <template x-if="item.type === 'embed'">
                                    {{-- The frame carries no binding: Alpine's CSP build evaluates nothing on an
                                         `<iframe>`. The wrapper hands it its `src` and `title` through the
                                         factory, which runs under both builds, and `contents` leaves the
                                         layout to the frame. --}}
                                    <div class="contents" x-init="embed($el, item)">
                                        <iframe loading="lazy" sandbox="allow-scripts allow-same-origin allow-presentation allow-popups allow-popups-to-escape-sandbox" class="aspect-video w-[90vw] max-w-[90vw] max-h-[85vh] rounded-[var(--radius-wk-md)] shadow-[var(--shadow-wk-lg)]" allowfullscreen></iframe>
                                    </div>
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
                                        <img data-wk-prose-skip
                                            :src="item.src"
                                            :alt="item.alt"
                                            loading="lazy"
                                            decoding="async"
                                            x-init="loaded = $el.complete && $el.naturalWidth > 0"
                                            x-on:load="loaded = true"
                                            x-on:error="loaded = true"
                                            :class="loaded ? 'opacity-100' : 'opacity-0'"
                                            class="max-h-[85vh] w-auto max-w-[90vw] rounded-[var(--radius-wk-md)] object-contain shadow-[var(--shadow-wk-lg)] transition-opacity duration-[var(--transition-wk-duration)]"
                                        />
                                    </div>
                                </template>
                            </div>
                        </template>
                        @if(is_callable($slideOverlay))
                            {{-- One overlay per slide, rendered on the server and shown only while its
                                 slide is: the callback's markup cannot live inside the client-side x-for.
                                 pointer-events-none like the gallery's thumbnail overlay; a control inside
                                 opts back in and sits in the dialog's focus trap. --}}
                            @foreach($slides as $i => $slide)
                                <div data-wk-slide-overlay="{{ $i }}" x-show="current === {{ $i }}" class="pointer-events-none absolute inset-0">
                                    {{ $slideOverlay($slide, $i) }}
                                </div>
                            @endforeach
                        @endif
                    </div>

                    @if($showCaptions)
                        {{-- Caption sits on a semi-transparent dark scrim (the themeable
                             --color-wk-overlay token, i.e. translucent black) over the dark
                             backdrop, plus a subtle text-shadow — together the white text
                             stays legible even over bright media, where the translucent
                             scrim alone would dip below AA on the light theme. Wraps +
                             centers so a long caption never overruns the media. --}}
                        <figcaption
                            x-show="currentCaption"
                            x-text="currentCaption"
                            class="max-w-3xl wk-lines-balanced rounded-[var(--radius-wk-md)] bg-[var(--color-wk-overlay)] px-[var(--space-wk-sm)] py-[var(--space-wk-xs)] text-center text-[length:var(--text-wk-sm)] leading-relaxed text-white [text-shadow:0_1px_2px_rgba(0,0,0,0.6)]"
                        ></figcaption>
                    @endif
                </figure>

                @if($count > 1)
                    {{-- `aria-disabled`, not the native attribute, and the difference is
                         where the reader ends up. With `loop="false"` the control the
                         reader is standing on is the one that turns unavailable — step to
                         the first slide and Prev disables itself under the focus it is
                         holding. A disabled button leaves the tab order, so focus falls
                         out of the controls and back to the trap's fallback, inside a
                         modal where there is nothing on screen to say so. `aria-disabled`
                         announces the same state and keeps the button where it is; the
                         clamp in prev() is what actually refuses the step. --}}
                    <button
                        type="button"
                        x-on:click="prev()"
                        :aria-disabled="hasPrev ? null : 'true'"
                        :class="hasPrev ? 'cursor-pointer' : 'cursor-not-allowed opacity-[var(--opacity-wk-disabled)]'"
                        aria-label="{{ __('wirekit::Previous') }}"
                        class="absolute left-[var(--space-wk-md)] top-1/2 z-20 -translate-y-1/2 flex h-10 w-10 items-center justify-center rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    >
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M12.5 4L7 10l5.5 6"/></svg>
                    </button>
                    {{-- Same reason as Prev above: the end of the set must not take the
                         focused control out of the tab order with it. --}}
                    <button
                        type="button"
                        x-on:click="next()"
                        :aria-disabled="hasNext ? null : 'true'"
                        :class="hasNext ? 'cursor-pointer' : 'cursor-not-allowed opacity-[var(--opacity-wk-disabled)]'"
                        aria-label="{{ __('wirekit::Next') }}"
                        class="absolute right-[var(--space-wk-md)] top-1/2 z-20 -translate-y-1/2 flex h-10 w-10 items-center justify-center rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    >
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7.5 4L13 10l-5.5 6"/></svg>
                    </button>
                @endif

                <button
                    type="button"
                    x-on:click="close()"
                    aria-label="{{ __('wirekit::Close') }}"
                    class="absolute right-[var(--space-wk-md)] top-[var(--space-wk-md)] z-20 flex h-10 w-10 items-center justify-center cursor-pointer rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                >
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>

                {{-- Which slide is showing, announced politely on every change. Stepping
                     with Next or the arrow keys swaps the media under a reader who cannot
                     see it and leaves focus on the control that did it, so without this
                     the gallery is fully navigable and says nothing about where it now
                     is. Its own always-present region rather than a marker on the slides:
                     a live region added to the page at the moment it has something to say
                     is frequently never announced at all. --}}
                <div aria-live="polite" aria-atomic="true" class="sr-only">
                    <span x-text="announcement"></span>
                </div>
            </div>
        </template>
    @endif
</div>
