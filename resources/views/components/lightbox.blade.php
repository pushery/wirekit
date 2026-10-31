{{-- optimistic-ui: n/a — client-only
     Its state is which image is shown. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    // Instance identifier. Any control can open this lightbox by dispatching
    // `wirekit-lightbox-open` with a matching `name` + `index`.
    'name' => null,
    // The slides. Each item is an array:
    //   ['src' => …, 'alt' => …, 'caption' => …, 'type' => 'image'|'video'|'embed']
    // plus 'altLang' and 'captionLang', the language of either text where it differs from the page's.
    // `type` defaults to 'image'. A plain string is treated as a decorative
    // image src.
    'items' => [],
    // Whether prev/next wraps around at the ends (true) or stops (false).
    'loop' => true,
    // Whether opening locks the page's scroll and makes the page behind inert. False for a
    // viewer that lives inside a page region, such as a preview, where the page around it has
    // to keep scrolling.
    'lockScroll' => true,
    // Show each item's caption inside the lightbox.
    'showCaptions' => true,
    // Backdrop color / opacity for THIS instance (any CSS color). Null → the
    // themeable --color-wk-overlay-media token, the darker backdrop for media.
    'overlay' => null,
    // Per-slide overlay render-callback: a closure `fn($slide, $i)` returning markup to lay
    // OVER the media of slide #$i in the viewer (a label, a watermark, a report control), shown
    // only while that slide is. The wrapper is pointer-events-none; interactive controls inside
    // opt back in with `pointer-events-auto`. Return a view or HtmlString for HTML (a plain
    // string is escaped). Null → no overlay.
    'slideOverlay' => null,
    // Zoom inside an open image: three buttons, the keys `+` `-` `0`, the wheel, a double click or
    // a double tap, a pinch, and dragging the enlarged image. Images only; a video or an embed
    // keeps its own controls.
    'zoomable' => false,
    // The largest scale, as a multiple of the size the image is fitted to. A number above 1.
    'maxZoom' => 4,
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
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $loop = BooleanProp::from($loop, true, 'lightbox', 'loop');
    $showCaptions = BooleanProp::from($showCaptions, true, 'lightbox', 'showCaptions');
    $lockScroll = BooleanProp::from($lockScroll, true, 'lightbox', 'lockScroll');
    $zoomable = BooleanProp::from($zoomable, false, 'lightbox', 'zoomable');

    // The factory's zoom keys, for a zoomable viewer only: every other lightbox renders exactly the
    // markup it did before the prop existed. The largest scale is a number above 1, as the factory
    // reads it; anything else is the default, and a text that holds no number is reported. The
    // announcement travels as a template for the reason the position does, below.
    $maxZoom = \Pushery\WireKit\Support\NumericProp::from($maxZoom, 4.0, 'lightbox', 'maxZoom');
    $maxZoom = $maxZoom > 1 ? (float) $maxZoom : 4.0;
    $zoomConfig = $zoomable
        ? ', zoomable: true, maxZoom: '.\Pushery\WireKit\Support\AlpinePayload::from($maxZoom)
            .', zoomAnnouncement: '.\Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Zoom :percent%'))
        : '';

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
     * renders an empty frame — visibly wrong, rather than quietly dangerous. The scheme is
     * read the way the browser reads it, by the same rule every data-driven link uses.
     */
    $safeEmbedSrc = static fn (string $src): string => \Pushery\WireKit\Support\SafeUrl::href($src, \Pushery\WireKit\Support\SafeUrl::LOAD_SCHEMES);

    $slides = [];
    foreach (\Pushery\WireKit\Support\ListProp::records($items, 'lightbox', 'items') as $item) {
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
                // Resolved here rather than in a `:title="item.alt || …"` binding, so the
                // fallback name comes from the locale file like every other string of the
                // slide.
                'alt' => ($type === 'embed' && trim((string) ($item['alt'] ?? '')) === '')
                    ? __('wirekit::Embedded content')
                    : (string) ($item['alt'] ?? ''),
                // The caption is drawn as text (`x-text`), so an Htmlable gives its text, not its markup.
                'caption' => isset($item['caption'])
                    ? ($item['caption'] instanceof \Illuminate\Contracts\Support\Htmlable ? \Pushery\WireKit\Support\FitText::plain($item['caption']->toHtml()) : (string) $item['caption'])
                    : null,
                'type' => in_array($type, ['image', 'video', 'embed'], true) ? $type : 'image',
                'poster' => isset($item['poster']) ? (string) $item['poster'] : null,
                // The language of `alt` and of `caption` where either differs from the page's.
                'altLang' => \Pushery\WireKit\Support\LanguageTag::of($item['altLang'] ?? null),
                'captionLang' => \Pushery\WireKit\Support\LanguageTag::of($item['captionLang'] ?? null),
            ];
        } else {
            $slides[] = ['src' => (string) $item, 'alt' => '', 'caption' => null, 'type' => 'image', 'poster' => null, 'altLang' => null, 'captionLang' => null];
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
    // One value for the backdrop's `background`, or the token: a text that carries a declaration
    // of its own would style the overlay with it (Support\CssValue), and is reported.
    $backdrop = \Pushery\WireKit\Support\CssValue::prop($overlay, 'lightbox', 'overlay', 'var(--color-wk-overlay-media)');

    $wrapperClasses = WireKit::resolveClasses('lightbox', 'base', '', $scope);
    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
    // A caller's `aria-labelledby` or `aria-label` names the element a reader meets, below; on this
    // wrapper, which has no role, ARIA prohibits a name (Support\CallerName).
    [$callerLabelledBy, $callerLabel, $attributes] = \Pushery\WireKit\Support\CallerName::split($attributes);
@endphp

<div
    x-data="wirekitLightbox({ name: {{ \Pushery\WireKit\Support\AlpinePayload::from($lightboxId) }}, count: {{ $count }}, loop: {{ \Pushery\WireKit\Support\AlpinePayload::from((bool) $loop) }}, lockScroll: {{ $lockScroll ? 'true' : 'false' }}, slides: {{ \Pushery\WireKit\Support\AlpinePayload::from($slides) }}, announcement: {{ \Pushery\WireKit\Support\AlpinePayload::from($positionTemplate) }}{{ $zoomConfig }} })"
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$wrapperClasses])) }}
>
    {{-- Optional trigger content (thumbnails / buttons). Anything here can call
         openAt(index) — it shares this component's Alpine scope. External
         controls elsewhere on the page can instead dispatch
         wirekit-lightbox-open { name, index }. --}}
    {{ $slot }}

    @if($count > 0)
        {{-- The overlay — teleported to the overlay root, focus-trapped, arrow/Escape keyboard.
             createFocusTrap returns focus to the trigger on close. `tabindex="-1"` makes the
             dialog the place focus stays after a click on something that takes none (a button
             in WebKit, the image in every engine), so the keys keep reaching it; it is no stop
             in the tab order, and `outline-hidden` keeps a key press after such a click from
             drawing a ring around the whole stage. --}}
        <template x-teleport="#wk-overlay-root">
            <div
                x-show="isOpen"
                x-cloak
                x-ref="stage"
                role="dialog"
                aria-modal="true"
                tabindex="-1"
                {{ \Pushery\WireKit\Support\CallerName::attribute($callerLabelledBy, $callerLabel, __('wirekit::Media viewer')) }}
                x-on:keydown.escape.prevent="close()"
@if($zoomable)
                x-on:keydown="onZoomKey($event)"
@endif
                x-on:keydown.arrow-right.prevent="next()"
                x-on:keydown.arrow-left.prevent="prev()"
                class="wk-overlay-fixed wk-overlay-layer-modal fixed inset-0 z-[var(--z-wk-modal)] flex items-center justify-center p-[var(--space-wk-md)] outline-hidden"
            >
                {{-- Backdrop — click closes. Per-instance color via inline style. --}}
                <div
                    x-show="isOpen"
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
                    x-show="isOpen"
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
@if($zoomable)
                                        <div :data-wk-lightbox-zoom="idx" class="flex items-start touch-none select-none">
@endif
                                        <img data-wk-prose-skip
                                            :src="item.src"
                                            :alt="item.alt"
                                            :lang="item.altLang"
                                            loading="lazy"
                                            decoding="async"
                                            x-init="loaded = $el.complete && $el.naturalWidth > 0"
                                            x-on:load="loaded = true"
                                            x-on:error="loaded = true"
                                            :class="loaded ? 'opacity-100' : 'opacity-0'"
@if($zoomable)
                                            :style="zoomStyle(idx)"
                                            draggable="false"
                                            x-on:wheel="onZoomWheel($event)"
                                            x-on:dblclick="onZoomDoubleClick($event)"
                                            x-on:pointerdown="onZoomPointerDown($event)"
                                            x-on:pointermove.passive="onZoomPointerMove($event)"
                                            x-on:pointerup="onZoomPointerUp($event)"
                                            x-on:pointercancel="onZoomPointerUp($event)"
                                            x-on:gesturestart="onZoomGesture($event)"
                                            x-on:gesturechange="onZoomGesture($event)"
                                            x-on:gestureend="onZoomGesture($event)"
@endif
                                            class="max-h-[85vh] w-auto max-w-[90vw] rounded-[var(--radius-wk-md)] object-contain shadow-[var(--shadow-wk-lg)] transition-opacity duration-[var(--transition-wk-duration)]"
                                        />
@if($zoomable)
                                        </div>
@endif
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
                                <div data-wk-slide-overlay="{{ $i }}" x-show="current === {{ (int) $i }}" class="pointer-events-none absolute inset-0">
                                    {{ $slideOverlay($slide, $i) }}
                                </div>
                            @endforeach
                        @endif
                    </div>

                    @if($showCaptions)
                        {{-- Caption sits on a semi-transparent dark scrim (the themeable
                             --color-wk-overlay-media token, i.e. translucent black) over the dark
                             backdrop, plus a subtle text-shadow — together the white text
                             stays legible even over bright media, where the translucent
                             scrim alone would dip below AA on the light theme. Wraps +
                             centers so a long caption never overruns the media. --}}
                        <figcaption
                            x-show="currentCaption"
                            x-text="currentCaption"
                            :lang="currentCaptionLang"
                            class="max-w-3xl wk-lines-balanced rounded-[var(--radius-wk-md)] bg-[var(--color-wk-overlay-media)] px-[var(--space-wk-sm)] py-[var(--space-wk-xs)] text-center text-[length:var(--text-wk-sm)] leading-relaxed text-white [text-shadow:0_1px_2px_rgba(0,0,0,0.6)]"
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
                         clamp in prev() is what actually refuses the step.

                         Every control stands its spacing plus the screen's inset on its
                         edge in from that edge: under `viewport-fit=cover` it clears a
                         notch, a camera island or a home indicator, and the inset is 0
                         on a screen without one. --}}
                    <button
                        type="button"
                        x-on:click="prev()"
                        :aria-disabled="hasPrev ? null : 'true'"
                        :class="hasPrev ? 'cursor-pointer' : 'cursor-not-allowed opacity-[var(--opacity-wk-disabled)]'"
                        aria-label="{{ __('wirekit::Previous') }}"
                        class="wk-touch-target absolute left-[calc(var(--space-wk-md)+env(safe-area-inset-left,0px))] top-1/2 z-20 -translate-y-1/2 flex h-[var(--size-wk-md)] w-[var(--size-wk-md)] items-center justify-center rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
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
                        class="wk-touch-target absolute right-[calc(var(--space-wk-md)+env(safe-area-inset-right,0px))] top-1/2 z-20 -translate-y-1/2 flex h-[var(--size-wk-md)] w-[var(--size-wk-md)] items-center justify-center rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    >
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7.5 4L13 10l-5.5 6"/></svg>
                    </button>
                @endif

                <button
                    type="button"
                    x-on:click="close()"
                    aria-label="{{ __('wirekit::Close') }}"
                    class="wk-touch-target absolute right-[calc(var(--space-wk-md)+env(safe-area-inset-right,0px))] top-[calc(var(--space-wk-md)+env(safe-area-inset-top,0px))] z-20 flex h-[var(--size-wk-md)] w-[var(--size-wk-md)] items-center justify-center cursor-pointer rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                >
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>

@if($zoomable)
                {{-- Zoom, in the corner opposite Close. `aria-disabled` at the ends for the reason Prev
                     and Next give above: the button under the reader's focus stays in the tab order.
                     After Close in the markup, so the control that takes focus on opening is the one
                     it was without zoom. A physical `left`, as Close's `right` is physical: the corner
                     opposite Close stays opposite it in either reading direction, until the viewer's
                     controls move to logical sides together. --}}
                <div role="group" aria-label="{{ __('wirekit::Zoom') }}" class="absolute left-[calc(var(--space-wk-md)+env(safe-area-inset-left,0px))] top-[calc(var(--space-wk-md)+env(safe-area-inset-top,0px))] z-20 flex gap-[var(--gap-wk-sm)]">
                    <button
                        type="button"
                        x-on:click="zoomIn()"
                        :aria-disabled="canZoomIn ? null : 'true'"
                        :class="canZoomIn ? 'cursor-pointer' : 'cursor-not-allowed opacity-[var(--opacity-wk-disabled)]'"
                        aria-label="{{ __('wirekit::Zoom in') }}"
                        class="wk-touch-target flex h-[var(--size-wk-md)] w-[var(--size-wk-md)] items-center justify-center rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    >
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M12.5 12.5L17 17M8.5 6v5M6 8.5h5"/></svg>
                    </button>
                    <button
                        type="button"
                        x-on:click="zoomOut()"
                        :aria-disabled="canZoomOut ? null : 'true'"
                        :class="canZoomOut ? 'cursor-pointer' : 'cursor-not-allowed opacity-[var(--opacity-wk-disabled)]'"
                        aria-label="{{ __('wirekit::Zoom out') }}"
                        class="wk-touch-target flex h-[var(--size-wk-md)] w-[var(--size-wk-md)] items-center justify-center rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    >
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M12.5 12.5L17 17M6 8.5h5"/></svg>
                    </button>
                    <button
                        type="button"
                        x-on:click="resetZoom()"
                        :aria-disabled="canZoomOut ? null : 'true'"
                        :class="canZoomOut ? 'cursor-pointer' : 'cursor-not-allowed opacity-[var(--opacity-wk-disabled)]'"
                        aria-label="{{ __('wirekit::Reset zoom') }}"
                        class="wk-touch-target flex h-[var(--size-wk-md)] w-[var(--size-wk-md)] items-center justify-center rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    >
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V3h4M13 3h4v4M17 13v4h-4M7 17H3v-4"/></svg>
                    </button>
                </div>

                {{-- Pan, one row under Zoom while the image is enlarged: each button moves it the step
                     of its arrow key, so a pointer that cannot drag reaches every part of it (WCAG
                     2.5.7). Hidden at the fitted size, where there is nothing to move. A physical
                     side on purpose, the one of the Zoom row it stands under. --}}
                <div role="group" aria-label="{{ __('wirekit::Pan') }}" x-show="canPan" x-cloak class="absolute left-[calc(var(--space-wk-md)+env(safe-area-inset-left,0px))] top-[calc(var(--space-wk-md)+var(--size-wk-md)+var(--gap-wk-sm)+env(safe-area-inset-top,0px))] z-20 flex gap-[var(--gap-wk-sm)]">
                    @foreach ([
                        'left' => [__('wirekit::Pan left'), 'M12 5l-5 5 5 5'],
                        'up' => [__('wirekit::Pan up'), 'M5 12l5-5 5 5'],
                        'down' => [__('wirekit::Pan down'), 'M5 8l5 5 5-5'],
                        'right' => [__('wirekit::Pan right'), 'M8 5l5 5-5 5'],
                    ] as $side => [$panLabel, $panPath])
                        <button
                            type="button"
                            x-on:click="pan({{ \Pushery\WireKit\Support\AlpinePayload::string($side) }})"
                            aria-label="{{ $panLabel }}"
                            class="wk-touch-target flex h-[var(--size-wk-md)] w-[var(--size-wk-md)] cursor-pointer items-center justify-center rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                        >
                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $panPath }}"/></svg>
                        </button>
                    @endforeach
                </div>

                {{-- What a button or a key did to the scale, said politely: the reader who pressed it
                     cannot see the image change. Its own region beside the slide's, present from the
                     start for the reason given there. --}}
                <div aria-live="polite" aria-atomic="true" class="sr-only">
                    <span x-text="zoomAnnouncement"></span>
                </div>

@endif
                {{-- Which slide is showing, announced politely on every change. Stepping
                     with Next or the arrow keys swaps the media under a reader who cannot
                     see it and leaves focus on the control that did it, so without this
                     the gallery is fully navigable and says nothing about where it now
                     is. Its own always-present region rather than a marker on the slides:
                     a live region added to the page at the moment it has something to say
                     is frequently never announced at all. The slide's own text stands in a
                     span of its own, which carries its language where it differs from the
                     page's; the position stays in the page's. --}}
                <div aria-live="polite" aria-atomic="true" class="sr-only">
                    <span x-text="announcementLead"></span><span x-bind:lang="announcementLang" x-text="announcementLabel"></span>
                </div>
            </div>
        </template>
    @endif
</div>
