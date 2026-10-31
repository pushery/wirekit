{{-- optimistic-ui: n/a — client-only
     Its state is the marker pressed last. That is not a value a server owns, so there is nothing
     to anticipate and nothing to roll back. --}}
@props([
    // The image, its text alternative and its size in pixels, which reserves the box before the
    // file arrives.
    'src' => null,
    'alt' => '',
    'srcset' => null,
    'sizes' => null,
    'width' => null,
    'height' => null,
    // The file a phone shows, below 48rem: often another crop, with a size of its own. Each marker
    // takes a position of its own on it, `phoneX` and `phoneY`.
    'phoneSrc' => null,
    'phoneSrcset' => null,
    'phoneWidth' => null,
    'phoneHeight' => null,
    'loading' => 'lazy',
    // `high` for an image at the top of the page, which the browser then fetches first.
    'fetchpriority' => null,
    // The markers in the order Tab reaches them. Each one an array with `x` and `y`, the position
    // of its center in percent of the image, and `label`, the name of its target, and as it needs
    // them `phoneX`, `phoneY`, `target`, handed back in the event, `href` and `image` for its card.
    'markers' => [],
    // The icon each marker shows.
    'icon' => 'plus',
    // The name of the markers as a group, and of the strip of their targets on the phone.
    'label' => null,
    'stripLabel' => null,
    // The strip of targets under the image on the phone, each a link where it has an `href`.
    'strip' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\NumericProp;
    use Pushery\WireKit\Support\SlotContent;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\Support\UrlProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list auto-derived from
    // this component's @props. Fully qualified: this view's imports may live in a later @php block.
    \Pushery\WireKit\WireKit::warnUnknownProps('image-hotspots', $attributes->getAttributes());

    // The root listens for Escape on the window; a caller's own listener for it runs beside it.
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:keydown.escape.window.capture']);
    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

    $strip = BooleanProp::from($strip, true, 'image-hotspots', 'strip');
    $alt = SlotContent::textOf($alt);
    $label = SlotContent::textOf($label);
    $label = $label !== '' ? $label : __('wirekit::Hotspots');
    $stripLabel = SlotContent::textOf($stripLabel);
    $stripLabel = $stripLabel !== '' ? $stripLabel : __('wirekit::In this image');

    $src = UrlProp::source(is_string($src) || $src instanceof \Stringable || is_bool($src) ? $src : null);
    $phoneSrc = UrlProp::source(is_string($phoneSrc) || $phoneSrc instanceof \Stringable || is_bool($phoneSrc) ? $phoneSrc : null);
    $srcset = UrlProp::text(is_string($srcset) || $srcset instanceof \Stringable ? $srcset : null);
    $phoneSrcset = UrlProp::text(is_string($phoneSrcset) || $phoneSrcset instanceof \Stringable ? $phoneSrcset : null);
    $width = NumericProp::intOrNull($width, 'image-hotspots', 'width');
    $height = NumericProp::intOrNull($height, 'image-hotspots', 'height');
    $phoneWidth = NumericProp::intOrNull($phoneWidth, 'image-hotspots', 'phoneWidth');
    $phoneHeight = NumericProp::intOrNull($phoneHeight, 'image-hotspots', 'phoneHeight');
    $loading = in_array($loading, ['lazy', 'eager'], true) ? $loading : WireKit::validateProp('image-hotspots', 'loading', NumericProp::text($loading), ['lazy', 'eager']);
    // Unset is the browser's own choice; an unknown value is reported and renders `auto`, which is that choice.
    $fetchpriority = match (true) {
        $fetchpriority === null || $fetchpriority === '' => null,
        in_array($fetchpriority, ['auto', 'high', 'low'], true) => $fetchpriority,
        default => WireKit::validateProp('image-hotspots', 'fetchpriority', NumericProp::text($fetchpriority), ['auto', 'high', 'low']),
    };

    // A position in percent of the image, 0 to 100. A value outside would stand the marker off
    // the image, so it is reported and held at the edge.
    $percent = static function (mixed $value, ?float $fallback, string $key): ?float {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $number = (float) NumericProp::from($value, $fallback ?? 0, 'image-hotspots', 'markers.'.$key);

        if ($number < 0 || $number > 100) {
            StrictnessGate::reject('image-hotspots', 'markers.'.$key, (string) $number, 'a percentage from 0 to 100', (string) max(0, min(100, $number)));

            return max(0.0, min(100.0, $number));
        }

        return $number;
    };

    // The markers as the view draws them. One without a name is a button nobody can identify
    // (WCAG 4.1.2), and one without a position has no place: both are reported and left out.
    // A Collection, a paginator or a query's models arrive as the list of arrays they hold.
    $markers = \Pushery\WireKit\Support\ListProp::renumbered(\Pushery\WireKit\Support\ListProp::records($markers, 'image-hotspots', 'markers'));
    $items = [];
    foreach (is_array($markers) ? $markers : [] as $marker) {
        if (! is_array($marker)) {
            StrictnessGate::reject('image-hotspots', 'markers', get_debug_type($marker), 'a list of arrays with x, y and label', 'the entry left out');

            continue;
        }

        $name = SlotContent::textOf(is_scalar($marker['label'] ?? null) || ($marker['label'] ?? null) instanceof \Stringable ? $marker['label'] : null);
        $x = $percent($marker['x'] ?? null, null, 'x');
        $y = $percent($marker['y'] ?? null, null, 'y');

        if (trim($name) === '' || $x === null || $y === null) {
            StrictnessGate::reject('image-hotspots', 'markers', trim($name) === '' ? 'a marker without label' : 'a marker without x or y', 'a label and a position for every marker', 'the marker left out');

            continue;
        }

        $target = $marker['target'] ?? null;
        $phoneX = $percent($marker['phoneX'] ?? null, $x, 'phoneX');
        $phoneY = $percent($marker['phoneY'] ?? null, $y, 'phoneY');

        $items[] = [
            'label' => $name,
            'x' => $x,
            'y' => $y,
            'phoneX' => $phoneX,
            'phoneY' => $phoneY,
            'target' => is_scalar($target) ? $target : null,
            // A link and a picture taken out of data: a scheme other than the house's own is no link
            // and no picture (Support\SafeUrl), so `javascript:` never reaches the card.
            'href' => \Pushery\WireKit\Support\SafeUrl::href(UrlProp::text(is_string($marker['href'] ?? null) || ($marker['href'] ?? null) instanceof \Stringable ? $marker['href'] : null)) ?: null,
            'image' => \Pushery\WireKit\Support\SafeUrl::href(UrlProp::text(is_string($marker['image'] ?? null) || ($marker['image'] ?? null) instanceof \Stringable ? $marker['image'] : null), \Pushery\WireKit\Support\SafeUrl::LOAD_SCHEMES) ?: null,
            // The label stands on the side with more room, decided for each image on its own. The
            // sides are physical on purpose: they are sides of the image, which does not mirror
            // under `dir="rtl"`, and a marker near its right edge has its room on the left.
            'side' => $x > 60 ? 'md:right-full md:left-auto md:mr-[var(--space-wk-xs)] md:ml-0' : 'md:left-full md:right-auto md:ml-[var(--space-wk-xs)] md:mr-0',
            'phoneSide' => $phoneX > 60 ? 'max-md:right-full max-md:left-auto max-md:mr-[var(--space-wk-xs)] max-md:ml-0' : 'max-md:left-full max-md:right-auto max-md:ml-[var(--space-wk-xs)] max-md:mr-0',
        ];
    }

    // A number for a custom property, with a point as its decimal separator whatever the locale.
    $css = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

    $rootClasses = WireKit::resolveClasses('image-hotspots', 'base', 'font-[family-name:var(--font-wk-sans)]', $scope);

    // The marker: a round button on a surface of its own, an elevated fill inside a ring in the
    // text color. One of the two stands against any image with 3:1 (WCAG 1.4.11), and the circle
    // measures 2rem, past the 24px of WCAG 2.5.8.
    $markerClasses = WireKit::resolveClasses('image-hotspots', 'marker', implode(' ', [
        'relative flex items-center justify-center rounded-full',
        'size-[var(--size-wk-sm)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[color:var(--color-wk-text)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-text)]',
        'shadow-[var(--shadow-wk-md)]',
        'cursor-pointer',
        'focus-visible:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
    ]), $scope);

    // The name beside the marker, shown on hover and focus. It is the button's own text, so it is
    // the button's name whether it shows or not; without the pointer over it, it takes no clicks
    // meant for the image or another marker.
    $labelClasses = WireKit::resolveClasses('image-hotspots', 'label', implode(' ', [
        'absolute top-1/2 -translate-y-1/2 whitespace-nowrap',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)]',
        'rounded-[var(--radius-wk-md)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'shadow-[var(--shadow-wk-md)]',
        'opacity-0 pointer-events-none',
        'group-hover/hotspot:opacity-100 group-hover/hotspot:pointer-events-auto',
        'group-focus-visible/hotspot:opacity-100 group-focus-visible/hotspot:pointer-events-auto',
        'transition-opacity duration-[var(--transition-wk-duration)]',
    ]), $scope);

    $cardClasses = WireKit::resolveClasses('image-hotspots', 'card', implode(' ', [
        'flex items-center gap-[var(--gap-wk-sm)]',
        'p-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]',
        'data-[wk-active]:border-[var(--color-wk-accent)]',
        'data-[wk-active]:ring-[length:var(--ring-wk-width)] data-[wk-active]:ring-[var(--color-wk-accent)]',
    ]), $scope);
@endphp

@if($src !== null)
{{-- Escape on the window, in the capture phase, as a tooltip hears it: a label under the pointer
     shows while the focus stands elsewhere, and a press that hid a label is marked, so a modal
     around the image leaves it alone. --}}
<div x-data="wirekitImageHotspots()" data-wk-image-hotspots x-on:keydown.escape.window.capture="dismiss($event)" {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$rootClasses])) }}>
    {{-- No ratio and no fit: the box is the image, so a percentage of the box is a percentage of
         the image, on the phone's file as well. --}}
    <div class="relative">
        <picture class="contents">
            @if($phoneSrc !== null)
            <source media="(width < 48rem)" srcset="{{ $phoneSrcset ?? $phoneSrc }}" @if($phoneWidth !== null) width="{{ $phoneWidth }}" @endif @if($phoneHeight !== null) height="{{ $phoneHeight }}" @endif>
            @endif
            <img data-wk-prose-skip
                src="{{ $src }}"
                alt="{{ $alt }}"
                @if($srcset !== null) srcset="{{ $srcset }}" @endif
                @if(filled($sizes)) sizes="{{ SlotContent::textOf(is_string($sizes) ? $sizes : null) }}" @endif
                @if($width !== null) width="{{ $width }}" @endif
                @if($height !== null) height="{{ $height }}" @endif
                loading="{{ $loading }}"
                @if($fetchpriority !== null) fetchpriority="{{ $fetchpriority }}" @endif
                class="block w-full h-auto"
            >
        </picture>

        @if($items !== [])
        {{-- `role="list"`: WebKit reads a list without bullets as plain text. A marker's place is
             physical on purpose: its position counts from the image's left edge, and an image
             does not mirror under `dir="rtl"`. --}}
        <ul data-wk-prose-skip role="list" aria-label="{{ $label }}" class="absolute inset-0 m-0 p-0 list-none" style="list-style: none; margin: 0; padding: 0;">
            @foreach($items as $index => $item)
            <li data-wk-prose-skip
                class="absolute -translate-x-1/2 -translate-y-1/2 left-[var(--wk-hotspot-x)] top-[var(--wk-hotspot-y)] max-md:left-[var(--wk-hotspot-phone-x)] max-md:top-[var(--wk-hotspot-phone-y)]"
                style="--wk-hotspot-x: {{ $css($item['x']) }}%; --wk-hotspot-y: {{ $css($item['y']) }}%; --wk-hotspot-phone-x: {{ $css($item['phoneX']) }}%; --wk-hotspot-phone-y: {{ $css($item['phoneY']) }}%"
            >
                <button
                    type="button"
                    data-wk-hotspot="{{ $index }}"
                    class="wk-touch-target group/hotspot {{ $markerClasses }}"
                    x-on:click="select({{ $loop->index }}, {{ AlpinePayload::from($item['target']) }}, {{ AlpinePayload::from($item['label']) }}, $el)"
                    x-on:focusout="loud({{ $loop->index }})"
                    x-on:mouseleave="loud({{ $loop->index }})"
                >
                    <x-wirekit::icon :name="$icon" class="h-4 w-4" aria-hidden="true" />
                    {{-- A label Escape hid goes by its opacity, never its visibility: it is the button's
                         name, and `visibility: hidden` would take that name from the accessibility tree.
                         Written inline, so it wins over the hover and focus classes that show it. --}}
                    <span class="{{ $labelClasses }} {{ $item['side'] }} {{ $item['phoneSide'] }}" x-bind:style="labelStyle({{ $loop->index }})">{{ $item['label'] }}</span>
                </button>
            </li>
            @endforeach
        </ul>
        @endif
    </div>

    @if($strip && $items !== [])
    {{-- The targets as a list of links: on the phone under the image, and in the markup for every
         reader, also where the stylesheet hides it. A scroll region of its own, named, with a stop
         for the keyboard and a ring. On paper the row of cards wraps onto the next line. --}}
    <div
        role="region"
        aria-label="{{ $stripLabel }}"
        tabindex="0"
        data-wk-image-hotspots-strip
        class="md:hidden relative mt-[var(--space-wk-sm)] overflow-x-auto overscroll-x-contain snap-x snap-mandatory rounded-[var(--radius-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
    >
        <ul data-wk-prose-skip role="list" data-wk-print-wrap class="flex gap-[var(--gap-wk-sm)] m-0 p-0 list-none" style="list-style: none; margin: 0; padding: 0;">
            @foreach($items as $index => $item)
            <li data-wk-prose-skip data-wk-hotspot-card="{{ $index }}" :data-wk-active="isActive({{ $loop->index }})" class="snap-start shrink-0 basis-3/5 {{ $cardClasses }}">
                @if($item['image'] !== null)
                <img data-wk-prose-skip src="{{ $item['image'] }}" alt="" loading="lazy" class="size-[var(--size-wk-lg)] shrink-0 rounded-[var(--radius-wk-sm)] object-cover">
                @endif
                @if($item['href'] !== null)
                <a data-wk-prose-skip href="{{ $item['href'] }}" class="min-w-0 font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] underline-offset-2 hover:underline focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)]">{{ $item['label'] }}</a>
                @else
                <span class="min-w-0">{{ $item['label'] }}</span>
                @endif
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
@else
{{-- An image without an address has nothing to show markers on. --}}
@php(StrictnessGate::reject('image-hotspots', 'src', '', 'the address of the image', 'nothing rendered'))
@endif
