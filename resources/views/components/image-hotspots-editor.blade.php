{{-- optimistic-ui: n/a — client-only
     Its state is the list of points the reader is placing, which reaches the server through
     `wire:model` like the value of a field. There is nothing to anticipate and nothing to roll back. --}}
@props([
    // The image the points stand on, its text alternative and its size in pixels.
    'src' => null,
    'alt' => '',
    'width' => null,
    'height' => null,
    // The phone's file. With it, a switch edits either image, and each point has a position of its
    // own on the phone's, `phoneX` and `phoneY`.
    'phoneSrc' => null,
    'phoneWidth' => null,
    'phoneHeight' => null,
    // The points to start with where no `wire:model` hands them over: the list `image-hotspots`
    // takes, each an array with `x` and `y` in percent and whatever else the page keeps on it.
    'value' => [],
    // The name of the list of points, and the sentence that says how to edit them.
    'label' => null,
    'help' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\DomId;
    use Pushery\WireKit\Support\IcuLocale;
    use Pushery\WireKit\Support\NumericProp;
    use Pushery\WireKit\Support\SlotContent;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\Support\UrlProp;
    use Pushery\WireKit\Support\UtilityClasses;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list auto-derived from
    // this component's @props. Fully qualified: this view's imports may live in a later @php block.
    \Pushery\WireKit\WireKit::warnUnknownProps('image-hotspots-editor', $attributes->getAttributes());

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
    // A caller's `x-model` on this root is read in the scope around it, where the caller's names
    // are: on the root it would find this component's own names first (CallerModel).
    $attributes = \Pushery\WireKit\Support\CallerModel::aroundRoot($attributes);

    $src = UrlProp::source(is_string($src) || $src instanceof \Stringable || is_bool($src) ? $src : null);
    $phoneSrc = UrlProp::source(is_string($phoneSrc) || $phoneSrc instanceof \Stringable || is_bool($phoneSrc) ? $phoneSrc : null);
    $alt = SlotContent::textOf($alt);
    $label = SlotContent::textOf($label);
    $label = $label !== '' ? $label : __('wirekit::Points');
    // The sentence above the image, as text or as markup, as the help of a field takes it.
    $help = SlotContent::textOf($help) !== '' ? $help : __('wirekit::Click the image to set a point. Drag a point or use the arrow keys to move it.');
    $width = NumericProp::intOrNull($width, 'image-hotspots-editor', 'width');
    $height = NumericProp::intOrNull($height, 'image-hotspots-editor', 'height');
    $phoneWidth = NumericProp::intOrNull($phoneWidth, 'image-hotspots-editor', 'phoneWidth');
    $phoneHeight = NumericProp::intOrNull($phoneHeight, 'image-hotspots-editor', 'phoneHeight');

    // The starting points as given: a list of arrays, a Collection or a query's models, each read
    // as the array it holds. The script holds the positions to 0–100 and keeps every other key of a
    // point as it is.
    $points = \Pushery\WireKit\Support\ListProp::renumbered(\Pushery\WireKit\Support\ListProp::records($value, 'image-hotspots-editor', 'value'));
    $points = array_values(array_filter(is_array($points) ? $points : [], 'is_array'));

    $helpId = DomId::unique(null, 'wk-hotspots-editor-help-');

    $config = [
        'pointName' => __('wirekit::Point :number'),
        'labels' => [
            'up' => __('wirekit::Move :item up'),
            'down' => __('wirekit::Move :item down'),
            'remove' => __('wirekit::Remove :name'),
            'x' => __('wirekit:::name, X in percent'),
            'y' => __('wirekit:::name, Y in percent'),
        ],
        'announcements' => [
            'selected' => __('wirekit:::name, :x% across, :y% down'),
            'moved' => __('wirekit:::x% across, :y% down'),
            'added' => __('wirekit::Added :name, :x% across, :y% down'),
            'removed' => __('wirekit::Removed :name'),
            'position' => __('wirekit::Position :position of :total.'),
        ],
        'locale' => IcuLocale::tag(),
    ];

    $rootClasses = WireKit::resolveClasses('image-hotspots-editor', 'base', 'grid gap-[var(--gap-wk-sm)] font-[family-name:var(--font-wk-sans)] text-[color:var(--color-wk-text)]', $scope);

    $focusRing = 'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]';

    // The image being edited is the chosen one, `data-active`, which the stylesheet's chosen surface follows.
    $switchClasses = 'wk-touch-row inline-flex items-center px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-sm)] rounded-[var(--radius-wk-md)] cursor-pointer data-[active]:bg-[var(--color-wk-bg-active)] data-[active]:font-[number:var(--font-wk-heading-weight)] '.$focusRing;

    // A point on the image: the number of its row on a surface of its own, as a marker of the shop
    // stands, so the reader places it where it will be seen.
    $pointClasses = WireKit::resolveClasses('image-hotspots-editor', 'point', implode(' ', [
        'absolute -translate-x-1/2 -translate-y-1/2 flex items-center justify-center rounded-full',
        'size-[var(--size-wk-sm)]',
        'bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] text-[length:var(--text-wk-xs)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-text)]',
        'shadow-[var(--shadow-wk-md)] cursor-grab data-[wk-dragging]:cursor-grabbing touch-none',
        'aria-pressed:ring-[length:var(--ring-wk-width)] aria-pressed:ring-[var(--color-wk-accent)]',
        $focusRing,
    ]), $scope);

    $rowClasses = WireKit::resolveClasses('image-hotspots-editor', 'row', 'flex flex-wrap items-center gap-[var(--gap-wk-xs)] p-[var(--padding-wk-x-xs)] rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] data-[wk-selected]:border-[var(--color-wk-accent)]', $scope);

    $nameClasses = 'wk-touch-row basis-full min-w-0 text-start text-[length:var(--text-wk-sm)] cursor-pointer rounded-[var(--radius-wk-sm)] '.$focusRing;

    $rowButtonClasses = 'wk-touch-row inline-flex items-center justify-center size-[var(--size-wk-xs)] rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] cursor-pointer disabled:cursor-not-allowed disabled:opacity-[var(--opacity-wk-disabled)] '.$focusRing;

    $axisClasses = 'inline-flex items-center gap-[var(--gap-wk-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]';

    // `wk-field` stands on each field itself: on a touch screen its text is large enough that
    // focusing it does not zoom the page.
    $fieldClasses = 'w-[var(--size-wk-xl)] min-w-0 px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)] rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] bg-[var(--color-wk-bg)] text-[color:var(--color-wk-text)] text-[length:var(--text-wk-sm)] '.$focusRing;
@endphp

@if($src !== null)
{{-- The points travel on `data-wk-server-value` and leave through `x-modelable`, which
     `wire:model` on this root answers. --}}
<div
    data-wk-image-hotspots-editor
    data-wk-sr-host
    data-wk-server-value="{{ AlpinePayload::json($points) }}"
    x-data="wirekitImageHotspotsEditor({{ AlpinePayload::from($config) }})"
    x-modelable="points"
    {{ $attributes->class(UtilityClasses::yieldToCaller($attributes, [$rootClasses])) }}
>
    @if($phoneSrc !== null)
    <div role="group" aria-label="{{ __('wirekit::Image to edit') }}" class="inline-flex gap-[var(--gap-wk-xs)]">
        <button type="button" class="{{ $switchClasses }}" aria-pressed="true" data-active x-bind:aria-pressed="isShown('desktop') ? 'true' : 'false'" x-bind:data-active="isShown('desktop')" x-on:click="show('desktop')">{{ __('wirekit::Desktop') }}</button>
        <button type="button" class="{{ $switchClasses }}" aria-pressed="false" x-bind:aria-pressed="isShown('phone') ? 'true' : 'false'" x-bind:data-active="isShown('phone')" x-on:click="show('phone')">{{ __('wirekit::Phone') }}</button>
    </div>
    @endif

    <p data-wk-prose-skip id="{{ $helpId }}" class="m-0 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $help }}</p>

    <div class="grid gap-[var(--gap-wk-md)] md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] items-start">
        {{-- No ratio and no fit, as in image-hotspots: the box is the image, so a click's percentage
             of the box is its percentage of the image. --}}
        <div x-ref="area" class="relative select-none" x-on:click="add($event)">
            <img data-wk-prose-skip src="{{ $src }}" alt="{{ $alt }}" @if($width !== null) width="{{ $width }}" @endif @if($height !== null) height="{{ $height }}" @endif draggable="false" class="block w-full h-auto cursor-crosshair" x-show="isShown('desktop')">
            @if($phoneSrc !== null)
            <img data-wk-prose-skip src="{{ $phoneSrc }}" alt="{{ $alt }}" @if($phoneWidth !== null) width="{{ $phoneWidth }}" @endif @if($phoneHeight !== null) height="{{ $phoneHeight }}" @endif draggable="false" class="block w-full h-auto cursor-crosshair" x-show="isShown('phone')" x-cloak>
            @endif
            <template x-for="(point, index) in points" x-bind:key="index">
                <button
                    type="button"
                    class="wk-touch-target {{ $pointClasses }}"
                    x-bind:data-wk-hotspot-point="index"
                    x-bind:data-wk-dragging="isDragged(index)"
                    x-bind:style="styleOf(point, index)"
                    x-bind:aria-label="nameOf(point, index)"
                    x-bind:aria-pressed="isSelected(index) ? 'true' : 'false'"
                    aria-describedby="{{ $helpId }}"
                    x-on:click.stop="select(index, $el)"
                    x-on:pointerdown="startDrag(index, $event)"
                    x-on:keydown="onKey(index, $event)"
                ><span aria-hidden="true" x-text="index + 1"></span></button>
            </template>
        </div>

        <div class="grid gap-[var(--gap-wk-sm)]">
            {{-- `role="list"`: WebKit reads a list without numbers as plain text. An empty list is
                 hidden, so a screen reader meets no named list with nothing in it. --}}
            <ol data-wk-prose-skip role="list" aria-label="{{ $label }}" class="m-0 p-0 list-none grid gap-[var(--gap-wk-xs)]" style="list-style: none; margin: 0; padding: 0;" x-show="points.length">
                <template x-for="(point, index) in points" x-bind:key="index">
                    <li data-wk-prose-skip class="{{ $rowClasses }}" x-bind:data-wk-hotspots-row="index" x-bind:data-wk-selected="isSelected(index)">
                        <button type="button" class="{{ $nameClasses }}" data-wk-row-control="name" x-on:click="select(index, $el)" x-text="(index + 1) + '. ' + nameOf(point, index)"></button>
                        <label class="{{ $axisClasses }}">
                            <span aria-hidden="true">{{ __('wirekit::X') }}</span>
                            <input type="number" min="0" max="100" step="0.1" inputmode="decimal" class="wk-field {{ $fieldClasses }}" x-bind:value="xOf(point, index)" x-bind:aria-label="labelFor('x', point, index)" x-on:change="setAxis(index, 'x', $event.target.value)">
                            <span aria-hidden="true">%</span>
                        </label>
                        <label class="{{ $axisClasses }}">
                            <span aria-hidden="true">{{ __('wirekit::Y') }}</span>
                            <input type="number" min="0" max="100" step="0.1" inputmode="decimal" class="wk-field {{ $fieldClasses }}" x-bind:value="yOf(point, index)" x-bind:aria-label="labelFor('y', point, index)" x-on:change="setAxis(index, 'y', $event.target.value)">
                            <span aria-hidden="true">%</span>
                        </label>
                        <button type="button" class="{{ $rowButtonClasses }}" data-wk-row-control="up" x-bind:aria-label="labelFor('up', point, index)" x-bind:disabled="index === 0" x-on:click="moveUp(index)"><x-wirekit::icon name="arrow-up" size="sm" aria-hidden="true" /></button>
                        <button type="button" class="{{ $rowButtonClasses }}" data-wk-row-control="down" x-bind:aria-label="labelFor('down', point, index)" x-bind:disabled="index === points.length - 1" x-on:click="moveDown(index)"><x-wirekit::icon name="arrow-down" size="sm" aria-hidden="true" /></button>
                        <button type="button" class="{{ $rowButtonClasses }}" data-wk-row-control="remove" x-bind:aria-label="labelFor('remove', point, index)" x-on:click="remove(index, 'list')"><x-wirekit::icon name="trash" size="sm" aria-hidden="true" /></button>
                    </li>
                </template>
            </ol>

            {{-- A soft surface, without a border: the neutral outline's border stands at 1.3:1 against
                 the page, under the 3:1 a control's boundary needs (WCAG 1.4.11), and the button's
                 words name it without one. --}}
            <div>
                <x-wirekit::button intent="neutral" surface="soft" size="sm" data-wk-hotspots-add x-on:click="addAtCenter()">
                    <x-slot:iconLeft><x-wirekit::icon name="plus" size="sm" aria-hidden="true" /></x-slot:iconLeft>
                    {{ __('wirekit::Add a point') }}
                </x-wirekit::button>
            </div>
        </div>
    </div>

    <div role="status" class="sr-only" x-text="announcement"></div>
</div>
@else
{{-- An editor without an image has nothing to set points on. --}}
@php(StrictnessGate::reject('image-hotspots-editor', 'src', '', 'the address of the image', 'nothing rendered'))
@endif
