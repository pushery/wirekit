{{-- optimistic-ui: n/a — client-only
     Its state is the crop the reader is drawing, which reaches the server through `wire:model`
     like the value of a field. There is nothing to anticipate and nothing to roll back. --}}
@props([
    // The image to crop, its text alternative and its size in pixels. The size holds the crop to
    // its ratio and its smallest side before the file arrives; without it the editor waits for the
    // image's natural size.
    'src' => null,
    'alt' => '',
    'width' => null,
    'height' => null,
    // The crop to start with where no `wire:model` hands it over: `x`, `y`, `w` and `h` in percent
    // of the image. None is the whole image, or with a ratio the largest crop of it, centered.
    'value' => null,
    // The crop's width over its height in pixels, as `9/16`, `1/1` or `16/9`; null for a free crop.
    'ratio' => null,
    // The crop's smallest side, in pixels of the image.
    'min' => 16,
    // The name of the crop, and the sentence that says how to move and size it.
    'label' => null,
    'help' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\DomId;
    use Pushery\WireKit\Support\IcuLocale;
    use Pushery\WireKit\Support\ListProp;
    use Pushery\WireKit\Support\NumericProp;
    use Pushery\WireKit\Support\SlotContent;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\Support\UrlProp;
    use Pushery\WireKit\Support\UtilityClasses;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list auto-derived from
    // this component's @props. Fully qualified: this view's imports may live in a later @php block.
    \Pushery\WireKit\WireKit::warnUnknownProps('image-editor', $attributes->getAttributes());

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
    // A caller's `x-model` on this root is read in the scope around it, where the caller's names
    // are: on the root it would find this component's own names first (CallerModel).
    $attributes = \Pushery\WireKit\Support\CallerModel::aroundRoot($attributes);

    // A caller's name and description belong on the crop, which is what takes the focus. On the
    // root, a `div` with no role, a name is not read and a description describes nothing.
    [$callerLabelledBy, $callerLabel, $attributes] = \Pushery\WireKit\Support\CallerName::split($attributes);
    [$callerDescribedBy, $attributes] = \Pushery\WireKit\Support\CallerName::description($attributes);

    $src = UrlProp::source(is_string($src) || $src instanceof \Stringable || is_bool($src) ? $src : null);
    $alt = SlotContent::textOf($alt);
    $label = SlotContent::textOf($label);
    $label = $label !== '' ? $label : __('wirekit::Crop');
    // The sentence above the image, as text or as markup, as the help of a field takes it.
    $help = SlotContent::textOf($help) !== '' ? $help : __('wirekit::Drag the crop or its handles, or use the arrow keys: they move the crop, and with Shift they change its size.');
    $width = NumericProp::intOrNull($width, 'image-editor', 'width');
    $height = NumericProp::intOrNull($height, 'image-editor', 'height');
    $min = NumericProp::intOrNull($min, 'image-editor', 'min') ?? 16;

    // `9/16` or `0.5625`; another text is reported, and the crop is free.
    if ($ratio !== null && ! is_string($ratio) && ! is_int($ratio) && ! is_float($ratio)) {
        StrictnessGate::reject('image-editor', 'ratio', get_debug_type($ratio), 'a width over a height, such as "9/16", or a positive number', 'a free crop');
        $ratio = null;
    }
    $ratio = \Pushery\WireKit\Support\ImageCrop::ratio($ratio);

    // The starting crop as given: an array, a Collection or a model, read as the array it holds.
    // The script holds it to the image, its smallest side and its ratio. A text or a number is no
    // crop, and the report says what one is; an empty text is no value.
    $wkCropShape = "a crop, ['x' => …, 'y' => …, 'w' => …, 'h' => …] in percent";
    if (is_scalar($value) && ! (is_string($value) && trim($value) === '')) {
        StrictnessGate::reject('image-editor', 'value', (string) $value, $wkCropShape, 'the whole image');
        $value = null;
    }
    $value = ListProp::from($value, 'image-editor', 'value');
    if ($value !== null && ! is_array($value)) {
        StrictnessGate::reject('image-editor', 'value', get_debug_type($value), $wkCropShape, 'the whole image');
        $value = null;
    }
    $crop = \Pushery\WireKit\Support\ImageCrop::from($value);

    $helpId = DomId::unique(null, 'wk-image-editor-help-');
    $valueId = DomId::unique(null, 'wk-image-editor-value-');

    $config = [
        'width' => $width,
        'height' => $height,
        'ratio' => $ratio,
        'min' => $min,
        'valueText' => __('wirekit::Crop: :x% across, :y% down, :w% wide, :h% high'),
        'locale' => IcuLocale::tag(),
    ];

    $rootClasses = WireKit::resolveClasses('image-editor', 'base', 'grid gap-[var(--gap-wk-sm)] font-[family-name:var(--font-wk-sans)] text-[color:var(--color-wk-text)]', $scope);

    $focusRing = 'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]';

    // The crop's frame: a dark edge inside a light one, so it shows on a dark image and a light
    // one, the light edge on the dimmed part of the image outside the crop. The focus ring takes the
    // place of the light edge while the frame has the focus.
    $regionClasses = WireKit::resolveClasses('image-editor', 'crop', implode(' ', [
        'absolute cursor-move touch-none',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-text)]',
        'ring-[length:var(--border-wk-width)] ring-[var(--color-wk-bg-elevated)]',
        $focusRing,
    ]), $scope);

    // A handle: a target of the small control size around a square on a surface of its own, on
    // a corner or the middle of an edge of the crop. `wk-touch-target` gives it the touch floor.
    $handleClasses = WireKit::resolveClasses('image-editor', 'handle', 'absolute -translate-x-1/2 -translate-y-1/2 flex items-center justify-center size-[var(--size-wk-xs)] touch-none', $scope);
    $gripClasses = 'size-[calc(var(--size-wk-xs)/2)] rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-elevated)] border-[length:var(--border-wk-width)] border-[var(--color-wk-text)] shadow-[var(--shadow-wk-md)]';
    // The cursor of a handle names the directions it sizes the crop in.
    $handleCursor = static fn (string $handle): string => match ($handle) {
        'nw', 'se' => 'cursor-nwse-resize',
        'ne', 'sw' => 'cursor-nesw-resize',
        'n', 's' => 'cursor-ns-resize',
        default => 'cursor-ew-resize',
    };

    $axisClasses = 'inline-flex items-center gap-[var(--gap-wk-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]';

    // `wk-field` stands on each field itself: on a touch screen its text is large enough that
    // focusing it does not zoom the page.
    $fieldClasses = 'w-[var(--size-wk-xl)] min-w-0 px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)] rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] bg-[var(--color-wk-bg)] text-[color:var(--color-wk-text)] text-[length:var(--text-wk-sm)] '.$focusRing;

    $axes = [
        'x' => ['text' => __('wirekit::X'), 'name' => __('wirekit::Crop, X in percent')],
        'y' => ['text' => __('wirekit::Y'), 'name' => __('wirekit::Crop, Y in percent')],
        'w' => ['text' => __('wirekit::Width'), 'name' => __('wirekit::Crop, width in percent')],
        'h' => ['text' => __('wirekit::Height'), 'name' => __('wirekit::Crop, height in percent')],
    ];
@endphp

@if($src !== null)
{{-- The crop travels on `data-wk-server-value` and leaves through `x-modelable`, which
     `wire:model` on this root answers. --}}
<div
    data-wk-image-editor
    data-wk-sr-host
    data-wk-server-value="{{ AlpinePayload::json($crop) }}"
    x-data="wirekitImageEditor({{ AlpinePayload::from($config) }})"
    x-modelable="crop"
    {{ $attributes->class(UtilityClasses::yieldToCaller($attributes, [$rootClasses])) }}
>
    <p data-wk-prose-skip id="{{ $helpId }}" class="m-0 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $help }}</p>

    {{-- No ratio and no fit on the box: it is the image, so a percentage of the box is a
         percentage of the image. On a touch screen a handle answers a finger in a square of the
         touch target around it, half of which lies past the image; the margin keeps that half off
         the fields below and off a link in the help above. --}}
    <div x-ref="area" class="relative select-none [@media(pointer:coarse)]:my-[calc(var(--size-wk-touch-target)/2)] [[data-wk-touch]_&]:my-[calc(var(--size-wk-touch-target)/2)]">
        <img data-wk-prose-skip x-ref="image" src="{{ $src }}" alt="{{ $alt }}" @if($width !== null) width="{{ $width }}" @endif @if($height !== null) height="{{ $height }}" @endif draggable="false" class="block w-full h-auto">

        {{-- The image outside the crop, dimmed: the box with the crop cut out of it. --}}
        <svg aria-hidden="true" focusable="false" class="absolute inset-0 size-full pointer-events-none" viewBox="0 0 100 100" preserveAspectRatio="none">
            <path fill-rule="evenodd" class="fill-[var(--color-wk-overlay-media)]" x-bind:d="shadePath()" />
        </svg>

        <div
            role="group"
            tabindex="0"
            {{ \Pushery\WireKit\Support\CallerName::attribute($callerLabelledBy, $callerLabel, $label) }}
            aria-describedby="{{ trim($valueId.' '.$helpId.' '.($callerDescribedBy ?? '')) }}"
            data-wk-image-editor-crop
            class="{{ $regionClasses }}"
            x-bind:style="regionStyle()"
            x-on:pointerdown="startDrag('move', $event)"
            x-on:keydown="onKey($event)"
        ></div>

        @foreach(\Pushery\WireKit\Support\ImageCrop::HANDLES as $handle)
        <span aria-hidden="true" data-wk-crop-handle="{{ $handle }}" class="wk-touch-target {{ $handleClasses }} {{ $handleCursor($handle) }}" x-bind:style="handleStyle({{ AlpinePayload::string($handle) }})" x-on:pointerdown.stop="startDrag({{ AlpinePayload::string($handle) }}, $event)"><span class="{{ $gripClasses }}"></span></span>
        @endforeach
    </div>

    <span id="{{ $valueId }}" class="sr-only" x-text="valueText()"></span>

    <fieldset class="m-0 p-0 border-0 min-w-0 flex flex-wrap items-center gap-[var(--gap-wk-sm)]">
        <legend class="sr-only">{{ __('wirekit::Crop in percent') }}</legend>
        @foreach($axes as $axis => $text)
        <label class="{{ $axisClasses }}">
            <span aria-hidden="true">{{ $text['text'] }}</span>
            <input type="number" min="0" max="100" step="0.1" inputmode="decimal" class="wk-field {{ $fieldClasses }}" aria-label="{{ $text['name'] }}" x-bind:value="fieldValue({{ AlpinePayload::string($axis) }})" x-on:change="setField({{ AlpinePayload::string($axis) }}, $event.target.value, $event.target)">
            <span aria-hidden="true">%</span>
        </label>
        @endforeach
        <x-wirekit::button intent="neutral" surface="soft" size="sm" data-wk-image-editor-reset x-on:click="reset()">{{ __('wirekit::Reset the crop') }}</x-wirekit::button>
    </fieldset>

    <div role="status" class="sr-only" x-text="announcement"></div>
</div>
@else
{{-- An editor without an image has nothing to crop. --}}
@php(StrictnessGate::reject('image-editor', 'src', '', 'the address of the image', 'nothing rendered'))
@endif
