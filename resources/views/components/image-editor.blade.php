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
    // The kinds of mark the editor draws on the image: `arrow`, `line`, `ellipse`, `rect` and
    // `freehand`, as a list or a comma-separated text, or `true` for all five. Without any the
    // editor is a crop alone, and its value the crop.
    'tools' => null,
    // The colors a mark can take, each `['color' => …, 'label' => …]`: a hex color, `rgb()`,
    // `hsl()` or `oklch()`, and the name a screen reader says for it. None is five named colors.
    // The first one is the color a new mark starts in.
    'palette' => null,
    // How many steps undo goes back.
    'history' => 50,
    // The largest area the edited image is drawn at, in pixels. A larger crop is drawn smaller by
    // one factor on both sides, its marks with it: WebKit draws nothing into a larger canvas.
    'maxArea' => 16700000,
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

    // The kinds of mark offered, in the toolbar's order. A kind the editor does not draw is
    // reported and left out; another value than a list, a text or `true` leaves a crop alone.
    $wkKinds = [
        'arrow' => __('wirekit::Arrow'),
        'line' => __('wirekit::Line'),
        'ellipse' => __('wirekit::Ellipse'),
        'rect' => __('wirekit::Rectangle'),
        'freehand' => __('wirekit::Freehand line'),
    ];
    $tools = ListProp::renumbered($tools);
    $wkAsked = [];
    if ($tools === true) {
        $wkAsked = \Pushery\WireKit\Support\ImageCrop::MARK_TYPES;
    } elseif (is_string($tools)) {
        $wkAsked = array_values(array_filter(array_map('trim', explode(',', $tools)), static fn (string $kind): bool => $kind !== ''));
    } elseif (is_array($tools)) {
        $wkAsked = $tools;
    } elseif ($tools !== null && $tools !== false) {
        StrictnessGate::reject('image-editor', 'tools', get_debug_type($tools), 'a list of arrow, line, ellipse, rect and freehand, or true for all five', 'a crop alone');
    }
    foreach ($wkAsked as $wkKind) {
        if (! is_string($wkKind) || ! in_array($wkKind, \Pushery\WireKit\Support\ImageCrop::MARK_TYPES, true)) {
            StrictnessGate::reject('image-editor', 'tools', is_string($wkKind) ? $wkKind : get_debug_type($wkKind), 'arrow, line, ellipse, rect or freehand', 'left out');
        }
    }
    $wkTools = array_values(array_filter(\Pushery\WireKit\Support\ImageCrop::MARK_TYPES, static fn (string $kind): bool => in_array($kind, $wkAsked, true)));

    // The palette: each color as a swatch takes it, each with its name. An entry that is no color
    // or has no name is reported and left out, and a palette left empty is the five colors. They
    // read on a light image and on a dark one, each strong against the light seam under a stroke.
    $wkDefaultPalette = [
        ['color' => '#e11d48', 'label' => __('wirekit::Red')],
        ['color' => '#f59e0b', 'label' => __('wirekit::Orange')],
        ['color' => '#16a34a', 'label' => __('wirekit::Green')],
        ['color' => '#2563eb', 'label' => __('wirekit::Blue')],
        ['color' => '#111827', 'label' => __('wirekit::Black')],
    ];
    $wkPalette = [];
    $wkGiven = ListProp::renumbered($palette, 'image-editor', 'palette');
    if ($wkGiven !== null && ! is_array($wkGiven)) {
        StrictnessGate::reject('image-editor', 'palette', get_debug_type($wkGiven), "a list of colors with their names, [['color' => '#e11d48', 'label' => 'Red'], …]", 'the five colors');
    }
    foreach (is_array($wkGiven) ? $wkGiven : [] as $wkEntry) {
        $wkColor = is_array($wkEntry) && isset($wkEntry['color']) && is_string($wkEntry['color']) ? \Pushery\WireKit\Support\CssColor::value($wkEntry['color']) : '';
        $wkName = is_array($wkEntry) ? trim(SlotContent::textOf($wkEntry['label'] ?? '')) : '';
        if ($wkColor === '' || $wkName === '') {
            StrictnessGate::reject('image-editor', 'palette', is_array($wkEntry) ? (string) json_encode($wkEntry) : get_debug_type($wkEntry), "a color with its name, ['color' => '#e11d48', 'label' => 'Red']", 'left out');

            continue;
        }
        $wkPalette[] = ['color' => $wkColor, 'label' => $wkName];
    }
    $wkPalette = $wkPalette !== [] ? $wkPalette : $wkDefaultPalette;
    $history = max(1, NumericProp::intOrNull($history, 'image-editor', 'history') ?? 50);
    $maxArea = max(1, NumericProp::intOrNull($maxArea, 'image-editor', 'maxArea') ?? 16700000);

    // The sentence above the image, as text or as markup, as the help of a field takes it. With
    // marks offered it says how one is drawn as well.
    $help = SlotContent::textOf($help) !== '' ? $help : ($wkTools !== []
        ? __("wirekit::Drag the crop or its handles, or use the arrow keys: they move the crop, and with Shift they change its size. With a mark's tool, drag on the image to draw the mark, or tap to place one.")
        : __('wirekit::Drag the crop or its handles, or use the arrow keys: they move the crop, and with Shift they change its size.'));
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
    // With marks offered the value carries them beside the crop, each held as the editor holds it.
    if ($wkTools !== [] && $crop !== null) {
        $wkMarks = ListProp::renumbered($value['marks'] ?? null);
        $crop += ['marks' => \Pushery\WireKit\Support\ImageCrop::marks(is_array($wkMarks) ? $wkMarks : null)];
    }

    $helpId = DomId::unique(null, 'wk-image-editor-help-');
    $valueId = DomId::unique(null, 'wk-image-editor-value-');
    $toolHelpId = DomId::unique(null, 'wk-image-editor-tool-help-');
    $markHelpId = DomId::unique(null, 'wk-image-editor-mark-help-');

    $config = [
        'width' => $width,
        'height' => $height,
        'ratio' => $ratio,
        'min' => $min,
        'valueText' => __('wirekit::Crop: :x% across, :y% down, :w% wide, :h% high'),
        'locale' => IcuLocale::tag(),
    ];

    // The crop the script shows as it starts, for the fields and the crop in words the page loads
    // with, before the script runs and on a page that runs none (ImageCrop::settled()). The words
    // are the script's `valueText()`: each side in the locale, to one decimal.
    $startCrop = \Pushery\WireKit\Support\ImageCrop::settled($crop === null ? null : ['x' => $crop['x'], 'y' => $crop['y'], 'w' => $crop['w'], 'h' => $crop['h']], $width, $height, $ratio, $min);
    $startValueText = (string) preg_replace_callback('/:(x|y|w|h)\b/', static fn (array $side): string => \Pushery\WireKit\Support\LocalizedNumber::format($startCrop[$side[1]], maxPrecision: 1, locale: $config['locale']), $config['valueText']);
    if ($wkTools !== []) {
        $config += [
            'tools' => $wkTools,
            'palette' => $wkPalette,
            'history' => $history,
            'maxArea' => $maxArea,
            'texts' => [
                'mark' => __('wirekit:::mark, :x% across, :y% down'),
                'added' => __('wirekit:::mark added.'),
                'removed' => __('wirekit:::mark removed.'),
                'start' => __('wirekit::Moving the start.'),
                'end' => __('wirekit::Moving the end.'),
                'undone' => __('wirekit::Undone.'),
                'redone' => __('wirekit::Redone.'),
                'kinds' => $wkKinds,
            ],
        ];
    }

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
    @if($wkTools !== []) x-on:keydown="onEditorKey($event)" @endif
    {{ $attributes->class(UtilityClasses::yieldToCaller($attributes, [$rootClasses])) }}
>
    <p data-wk-prose-skip id="{{ $helpId }}" class="m-0 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $help }}</p>

    @if($wkTools !== [])
    {{-- The tools, the steps back and forth, the width and the color. A tool pressed with a
         pointer picks what the pointer draws; pressed from the keyboard it also places its mark
         in the middle of the crop. A color or a width applies to the selected mark too. --}}
    <div data-wk-image-editor-toolbar x-on:click="focusPressed($event)" class="flex flex-wrap items-center gap-x-[var(--gap-wk-md)] gap-y-[var(--gap-wk-sm)]">
        <div role="group" aria-label="{{ __('wirekit::Tools') }}" aria-describedby="{{ $toolHelpId }}" class="flex flex-wrap items-center gap-[var(--gap-wk-xs)]">
            <x-wirekit::toggle-button size="sm" data-wk-image-editor-tool="crop" x-bind:aria-pressed="toolPressed('crop')" x-on:click="pickTool('crop', $event)">{{ __('wirekit::Crop') }}</x-wirekit::toggle-button>
            @foreach($wkTools as $wkTool)
            <x-wirekit::toggle-button size="sm" data-wk-image-editor-tool="{{ $wkTool }}" x-bind:aria-pressed="toolPressed({{ AlpinePayload::string($wkTool) }})" x-on:click="pickTool({{ AlpinePayload::string($wkTool) }}, $event)">{{ $wkKinds[$wkTool] }}</x-wirekit::toggle-button>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-[var(--gap-wk-xs)]">
            <x-wirekit::button intent="neutral" size="sm" data-wk-image-editor-undo x-bind:disabled="undoDisabled()" x-on:click="undo()">{{ __('wirekit::Undo') }}</x-wirekit::button>
            <x-wirekit::button intent="neutral" size="sm" data-wk-image-editor-redo x-bind:disabled="redoDisabled()" x-on:click="redo()">{{ __('wirekit::Redo') }}</x-wirekit::button>
            <x-wirekit::button intent="neutral" size="sm" data-wk-image-editor-remove x-bind:disabled="removeDisabled()" x-on:click="removeMark()">{{ __('wirekit::Remove the mark') }}</x-wirekit::button>
        </div>
        <div role="group" aria-label="{{ __('wirekit::Line width') }}" class="flex flex-wrap items-center gap-[var(--gap-wk-xs)]">
            @foreach(['thin' => __('wirekit::Thin'), 'medium' => __('wirekit::Medium'), 'thick' => __('wirekit::Thick')] as $wkWidth => $wkWidthName)
            <x-wirekit::toggle-button size="sm" data-wk-image-editor-width="{{ $wkWidth }}" x-bind:aria-pressed="widthPressed({{ AlpinePayload::string($wkWidth) }})" x-on:click="pickWidth({{ AlpinePayload::string($wkWidth) }})">{{ $wkWidthName }}</x-wirekit::toggle-button>
            @endforeach
        </div>
        <div role="group" aria-label="{{ __('wirekit::Color') }}" class="flex flex-wrap items-center gap-[var(--gap-wk-xs)]">
            @foreach(array_values($wkPalette) as $wkIndex => $wkEntry)
            {{-- The swatch shows the color and the tooltip names it: a color alone says nothing to a
                 screen reader, and the swatch, hidden from one, leaves the button its tooltip's name. --}}
            <x-wirekit::toggle-button size="sm" :tooltip="$wkEntry['label']" data-wk-image-editor-color="{{ $wkIndex }}" x-bind:aria-pressed="colorPressed({{ $wkIndex }})" x-on:click="pickColor({{ $wkIndex }})"><x-wirekit::swatch :color="$wkEntry['color']" size="sm" /></x-wirekit::toggle-button>
            @endforeach
        </div>
    </div>
    @endif

    {{-- No ratio and no fit on the box: it is the image, so a percentage of the box is a
         percentage of the image. A handle stands on the crop's edge, half of it past the image,
         and a crop of the whole image sets the side handles on the image's side edges: the margin
         at either side keeps those halves inside the editor. On a touch screen a handle answers a
         finger in a square of the touch target around it, half of which lies past the image; the
         margin above and below keeps that half off the fields below and off a link in the help
         above. --}}
    <div x-ref="area" class="relative select-none mx-[calc(var(--size-wk-xs)/2)] [@media(pointer:coarse)]:my-[calc(var(--size-wk-touch-target)/2)] [[data-wk-touch]_&]:my-[calc(var(--size-wk-touch-target)/2)]">
        <img data-wk-prose-skip x-ref="image" src="{{ $src }}" alt="{{ $alt }}" @if($width !== null) width="{{ $width }}" @endif @if($height !== null) height="{{ $height }}" @endif draggable="false" class="block w-full h-auto">

        @if($wkTools !== [])
        {{-- The marks, in the image's pixels, under the shade, so a mark outside the crop shows
             dimmed as the part of the image the result leaves out. The script draws them; the two
             outlines mark the selected one, a light edge under a dark one. --}}
        <svg aria-hidden="true" focusable="false" data-wk-image-editor-marks class="absolute inset-0 size-full pointer-events-none" preserveAspectRatio="none" x-effect="renderMarks()">
            <g x-ref="marks"></g>
            <rect x-ref="selectionLight" visibility="hidden" fill="none" vector-effect="non-scaling-stroke" class="stroke-[var(--color-wk-bg-elevated)] [stroke-width:calc(var(--border-wk-width)*3)]" />
            <rect x-ref="selectionDark" visibility="hidden" fill="none" vector-effect="non-scaling-stroke" class="stroke-[var(--color-wk-text)] [stroke-width:var(--border-wk-width)]" />
        </svg>
        @endif

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
            x-ref="cropRegion"
            class="{{ $regionClasses }}"
            x-bind:style="regionStyle()"
            x-on:pointerdown="startDrag('move', $event)"
            x-on:keydown="onKey($event)"
        ></div>

        @foreach(\Pushery\WireKit\Support\ImageCrop::HANDLES as $handle)
        <span aria-hidden="true" data-wk-crop-handle="{{ $handle }}" class="wk-touch-target {{ $handleClasses }} {{ $handleCursor($handle) }}" @if($wkTools !== []) x-show="cropping()" @endif x-bind:style="handleStyle({{ AlpinePayload::string($handle) }})" x-on:pointerdown.stop="startDrag({{ AlpinePayload::string($handle) }}, $event)"><span class="{{ $gripClasses }}"></span></span>
        @endforeach

        @wirekitLast($wkTools !== [])
        {{-- With a mark's tool the image takes the pointer: a press on a mark's stroke selects and
             moves it, a press elsewhere draws. --}}
        <div data-wk-image-editor-surface class="absolute inset-0 touch-none cursor-crosshair" x-show="drawing()" x-on:pointerdown="startMark($event)"></div>

        {{-- One focus target per mark, over its box. A press passes through to the image; the
             keyboard reaches a mark here. --}}
        <div data-wk-image-editor-targets class="absolute inset-0 pointer-events-none">
            <template x-for="target in markTargets()" x-bind:key="target.index">
                <button type="button" data-wk-image-editor-mark x-bind:data-wk-mark-target="target.index" x-bind:aria-label="target.label" x-bind:aria-pressed="target.pressed" aria-describedby="{{ $markHelpId }}" class="absolute -translate-x-1/2 -translate-y-1/2 min-w-[var(--size-wk-xs)] min-h-[var(--size-wk-xs)] p-0 border-0 bg-transparent rounded-[var(--radius-wk-sm)] {{ $focusRing }}" x-bind:style="target.style" x-on:focus="select(target.index)" x-on:keydown="onMarkKey(target.index, $event)"></button>
            </template>
        </div>

        <template x-for="handle in markHandleList()" x-bind:key="handle.name">
            <span aria-hidden="true" class="wk-touch-target {{ $handleClasses }}" x-bind:class="handle.cursor" x-bind:data-wk-mark-handle="handle.name" x-bind:style="handle.style" x-on:pointerdown.stop="startMarkResize(handle.name, $event)"><span class="{{ $gripClasses }}"></span></span>
        </template>
        @endwirekitLast
    </div>

    <span id="{{ $valueId }}" class="sr-only" x-text="valueText()">{{ $startValueText }}</span>
    @if($wkTools !== [])
    <span id="{{ $toolHelpId }}" class="sr-only">{{ __('wirekit::A tool pressed from the keyboard places its mark in the middle of the crop.') }}</span>
    <span id="{{ $markHelpId }}" class="sr-only">{{ __('wirekit::On a mark, the arrow keys move it, with Shift they change its size, Space switches the end of a line or an arrow, and Delete removes it.') }}</span>
    @endif

    <fieldset class="m-0 p-0 border-0 min-w-0 flex flex-wrap items-center gap-[var(--gap-wk-sm)]">
        <legend class="sr-only">{{ __('wirekit::Crop in percent') }}</legend>
        @foreach($axes as $axis => $text)
        <label class="{{ $axisClasses }}">
            <span aria-hidden="true">{{ $text['text'] }}</span>
            <input type="number" min="0" max="100" step="0.1" inputmode="decimal" class="wk-field {{ $fieldClasses }}" aria-label="{{ $text['name'] }}" value="{{ \Pushery\WireKit\Support\ScriptText::of($startCrop[$axis]) }}" x-bind:value="fieldValue({{ AlpinePayload::string($axis) }})" x-on:change="setField({{ AlpinePayload::string($axis) }}, $event.target.value, $event.target)">
            <span aria-hidden="true">%</span>
        </label>
        @endforeach
        <x-wirekit::button intent="neutral" size="sm" data-wk-image-editor-reset x-on:click="reset()">{{ __('wirekit::Reset the crop') }}</x-wirekit::button>
    </fieldset>

    {{-- The page's own controls, inside the editor's scope: a button here can call rasterize(),
         which draws the edited image into a file. --}}
    @if(SlotContent::given($actions ?? null))
    <div data-wk-image-editor-actions class="flex flex-wrap items-center gap-[var(--gap-wk-sm)]">{{ $actions }}</div>
    @endif

    <div role="status" class="sr-only" x-text="announcement"></div>
</div>
@else
{{-- An editor without an image has nothing to crop. --}}
@php(StrictnessGate::reject('image-editor', 'src', '', 'the address of the image', 'nothing rendered'))
@endif
