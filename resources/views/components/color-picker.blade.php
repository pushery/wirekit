@props([
    'name' => null,
    'id' => null,
    'value' => '#000000',
    'size' => config('wirekit.components.color-picker.size', 'md'),
    'showValue' => true,
    'disabled' => false,
    // Popover mode — a from-scratch HSV picker (SV plane + hue + alpha + format
    // input + swatches + eyedropper + copy + recents). Default false keeps the
    // native <input type="color"> render byte-identical. Boolean flag (not a `mode`
    // prop) per the prop-naming convention — same call the date-picker `range` made.
    'popover' => false,
    'format' => config('wirekit.components.color-picker.format', 'hex'), // hex | rgb | hsl | oklch
    // Popover mode only: on touch-primary devices (pointer: coarse) open the
    // platform's native color sheet instead of the custom panel — the same
    // dialog the default (non-popover) variant gets for free. Opt-in; desktop
    // pointers always get the popover.
    'nativeOnMobile' => config('wirekit.components.color-picker.native-on-mobile', false),
    'withAlpha' => true,
    'withEyedropper' => true,
    // Popover mode only: render a "clear" (no color) button that empties the
    // bound form value. The native <input type="color"> cannot represent an
    // empty value (HTML coerces it to #000000), so this is popover-only. Off by
    // default — "no color" is rarely a valid state on a color control.
    'withClear' => false,
    'withRecents' => true,
    'presets' => [],
    'recentsKey' => null,
    'scope' => null,
])

@php
    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    $pickerId = $id ?? ($name ? 'wk-color-' . $name : 'wk-color-' . Str::random(6));

    $swatchSize = match ($size) {
        'sm' => 'w-8 h-8',
        'lg' => 'w-12 h-12',
        default => 'w-10 h-10',
    };

    $popoverValue = (bool) $popover;
    $formatValue = match ($format) {
        'hex', 'rgb', 'hsl', 'oklch' => $format,
        default => WireKit::validateProp('color-picker', 'format', $format, ['hex', 'rgb', 'hsl', 'oklch']),
    };

    $wrapperClasses = WireKit::resolveClasses('color-picker', 'wrapper', 'inline-flex items-center gap-[var(--padding-wk-x-sm)]', $scope);

    $swatchClasses = WireKit::resolveClasses('color-picker', 'swatch', implode(' ', [
        'relative inline-block',
        'rounded-full',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'overflow-hidden',
        'cursor-pointer',
        'focus-within:ring-[length:var(--ring-wk-width)]',
        'focus-within:ring-[var(--color-wk-ring)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $swatchSize,
    ]), $scope);

    // The input extends 8px BELOW the visible swatch (height = label + 4px top
    // overhang + 12px bottom): the browser anchors its native color panel to the
    // INPUT's element box, so the extra (overflow-clipped, invisible) zone pushes
    // the panel the same 8px off the swatch as the house popover offset (without
    // it the native panel sat flush on the circle). The label's overflow-hidden keeps
    // the visual swatch byte-identical; the hit area grows 8px downward.
    $inputClasses = WireKit::resolveClasses('color-picker', 'input', implode(' ', [
        // Replaced elements don't stretch between insets — height must be
        // EXPLICIT: label height + 4px top overhang + 12px bottom extension.
        'absolute -inset-x-1 -top-1',
        'w-[calc(100%+0.5rem)] h-[calc(100%+1rem)]',
        'cursor-pointer',
        'border-0 p-0 bg-transparent',
        'disabled:cursor-not-allowed',
    ]), $scope);

    $valueClasses = WireKit::resolveClasses('color-picker', 'value', implode(' ', [
        'font-[family-name:var(--font-wk-mono)]',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text)]',
        'uppercase tracking-wider',
    ]), $scope);
@endphp

@if(! $popoverValue)
    {{-- ── Native mode (default). ── Slightly wider swatch↔readout gap than the
         shared wrapper default: the hex pill sits inline next to the swatch, and
         the base --padding-wk-x-sm (10px) read as crowding it. --gap-wk-md (12px)
         gives the readout clear separation. The appended gap-* utility wins over
         the base one in $wrapperClasses (later source order); the popover branch
         keeps the base gap untouched. --}}
    <div x-data="{ current: @js($value) }" class="{{ $wrapperClasses }} gap-[var(--gap-wk-md)]">
        <label for="{{ $pickerId }}" class="{{ $swatchClasses }}">
            <input
                type="color"
                @if($name) name="{{ $name }}" @endif
                id="{{ $pickerId }}"
                :value="current"
                @input="current = $event.target.value"
                @if($disabled) disabled @endif
                {{ $attributes->class([$inputClasses]) }}
            />
            @if(trim((string) $slot) !== '')
                <span class="sr-only">{{ $slot }}</span>
            @else
                <span class="sr-only">{{ $name ? Str::headline((string) $name) . ' color' : 'Color picker' }}</span>
            @endif
        </label>
        @if($showValue)
            <span class="{{ $valueClasses }}" x-text="current" aria-live="polite"></span>
        @endif
    </div>
@else
    @php
        $jsConfig = [
            'value' => $value,
            'format' => $formatValue,
            'withAlpha' => (bool) $withAlpha,
            'withClear' => (bool) $withClear,
            'recentsKey' => $recentsKey,
            'nativeOnMobile' => (bool) $nativeOnMobile,
        ];
        // With withClear on, the readout binds the displayValue getter (which
        // shows "No color" while cleared); otherwise the plain formattedValue,
        // byte-identical to before withClear existed.
        $readoutExpr = $withClear ? 'displayValue' : 'formattedValue';
        // Checkerboard backdrop so alpha is legible — a structural transparency
        // grid (like the SV-plane white/black axes, it is NOT themeable).
        $checker = 'background-image: linear-gradient(45deg, var(--color-wk-border) 25%, transparent 25%), linear-gradient(-45deg, var(--color-wk-border) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, var(--color-wk-border) 75%), linear-gradient(-45deg, transparent 75%, var(--color-wk-border) 75%); background-size: 8px 8px; background-position: 0 0, 0 4px, 4px -4px, -4px 0;';
    @endphp

    <div
        x-data="wirekitColorPicker(@js($jsConfig))"
        class="relative {{ $wrapperClasses }}"
        {{-- escape is window-scoped: the panel teleports to <body>, so a keydown
             inside it bubbles to body (not this root) — a non-window escape here
             would never fire while focus is in the panel. --}}
        @keydown.escape.window="open && (open = false)"
    >
        @if($nativeOnMobile)
            {{-- nativeOnMobile trigger: on touch-primary devices (useNative, decided
                 once at init via `(pointer: coarse)`) the swatch IS the native-mode
                 label + <input type="color"> — a direct tap opens the OS color sheet,
                 no programmatic click needed, so it works in every mobile browser.
                 x-if (not x-show) keeps exactly ONE interactive trigger in the live
                 DOM. The input's own color well fills the clipped circle, and its
                 input/change events feed the same HSV state + hidden form field the
                 popover path uses (change commits to recents). Native sheets pick
                 opaque sRGB hex — alpha keeps its current value. --}}
            <template x-if="useNative">
                <label for="{{ $pickerId }}-native" class="{{ $swatchClasses }}">
                    <input
                        type="color"
                        id="{{ $pickerId }}-native"
                        :value="hex"
                        @input="onInput($event.target.value)"
                        @change="pickColor($event.target.value)"
                        @if($disabled) disabled @endif
                        class="{{ $inputClasses }} disabled:opacity-[var(--opacity-wk-disabled)]"
                    />
                    <span class="sr-only">{{ $name ? Str::headline((string) $name) . ' color' : 'Color picker' }}</span>
                </label>
            </template>
        @endif

        {{-- Trigger. A custom `trigger` slot (popover mode) replaces the default
             swatch — WireKit still wires the open toggle, the x-ref Floating-UI
             anchor, and the aria-haspopup/aria-expanded dialog semantics; the
             slot supplies the visible content (its text / icon is the accessible
             name, like any icon-button). Without the slot, the default swatch
             shows the live color over a transparency checker. --}}
        @if($nativeOnMobile)<template x-if="!useNative">@endif
        @isset($trigger)
            <button
                type="button"
                id="{{ $pickerId }}"
                x-ref="trigger"
                @click="open = ! open"
                :aria-expanded="open ? 'true' : 'false'"
                aria-haspopup="dialog"
                @if($disabled) disabled @endif
                class="inline-flex items-center cursor-pointer rounded-[var(--radius-wk-sm)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed"
            >
                {{ $trigger }}
            </button>
        @else
            <button
                type="button"
                id="{{ $pickerId }}"
                x-ref="trigger"
                @click="open = ! open"
                :aria-expanded="open ? 'true' : 'false'"
                aria-haspopup="dialog"
                aria-label="{{ $name ? Str::headline((string) $name) . ' color' : 'Color picker' }}"
                @if($disabled) disabled @endif
                class="{{ $swatchClasses }} disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed"
                style="{{ $checker }}"
            >
                <span class="absolute inset-0" :style="`background-color: ${cssColor}`"@if($withClear) x-show="!cleared"@endif></span>
            </button>
        @endisset
        @if($nativeOnMobile)</template>@endif

        @if($showValue)
            <span class="{{ $valueClasses }}" x-text="{{ $readoutExpr }}" aria-live="polite"></span>
        @endif

        {{-- Hidden form field — mirrors the picked value in the active format. --}}
        <input type="hidden" x-ref="input" @if($name) name="{{ $name }}" @endif value="{{ $value }}" />

        {{-- Picker panel — teleported to <body> + Floating-UI positioned (see
             wirekitColorPicker._anchor) so it escapes any clipping/stacking
             ancestor and sits a clear gap below the swatch (no longer flush
             against the trigger circle). click.outside lives HERE (on the panel),
             not the root, because teleporting moves the panel out of the subtree. --}}
        <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-ref="panel"
            x-transition.opacity
            @click.outside="open = false"
            role="dialog"
            aria-label="Color picker"
            class="fixed z-[var(--z-wk-dropdown,50)] w-[18rem] space-y-3 rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] p-[var(--padding-wk-x-md)] shadow-[var(--shadow-wk-lg)]"
        >
            {{-- Saturation / value plane. The hue tints the base; white→transparent
                 (left→right) is saturation, transparent→black (top→bottom) is value.
                 The #fff/#000 axes are intrinsic to an HSV plane, not themeable. --}}
            <div
                x-ref="plane"
                @pointerdown="startPlane($event)"
                role="slider"
                tabindex="0"
                aria-label="Saturation and brightness"
                :aria-valuetext="`saturation ${s}%, brightness ${v}%`"
                @keydown.arrow-left.prevent="nudgePlane(-1, 0)"
                @keydown.arrow-right.prevent="nudgePlane(1, 0)"
                @keydown.arrow-up.prevent="nudgePlane(0, 1)"
                @keydown.arrow-down.prevent="nudgePlane(0, -1)"
                class="relative h-40 w-full cursor-crosshair touch-none overflow-hidden rounded-[var(--radius-wk-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                :style="`background-color: ${hueCss}`"
            >
                <div class="pointer-events-none absolute inset-0" style="background: linear-gradient(to right, #fff, transparent);"></div>
                <div class="pointer-events-none absolute inset-0" style="background: linear-gradient(to top, #000, transparent);"></div>
                {{-- Thumb ring stays theme-independent white (+ shadow) on all three
                     sliders: it must contrast against ARBITRARY colors underneath, so a
                     themed border would vanish against same-toned regions. --}}
                <div class="pointer-events-none absolute h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[var(--shadow-wk-sm)]" :style="`left: ${planeLeft}; top: ${planeTop}; background-color: ${hex}`"></div>
            </div>

            {{-- Hue slider — the full spectrum (structural, not themeable). --}}
            <div
                x-ref="hue"
                @pointerdown="startHue($event)"
                role="slider"
                tabindex="0"
                aria-label="Hue"
                :aria-valuenow="h"
                aria-valuemin="0"
                aria-valuemax="360"
                @keydown.arrow-left.prevent="nudgeHue(-2)"
                @keydown.arrow-right.prevent="nudgeHue(2)"
                class="relative h-3 w-full cursor-pointer touch-none rounded-full focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                style="background: linear-gradient(to right, #f00 0%, #ff0 17%, #0f0 33%, #0ff 50%, #00f 67%, #f0f 83%, #f00 100%);"
            >
                <div class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-[var(--color-wk-bg-elevated)] shadow-[var(--shadow-wk-sm)]" :style="`left: ${hueLeft}`"></div>
            </div>

            @if($withAlpha)
                {{-- Alpha slider over a transparency checker. --}}
                <div
                    x-ref="alpha"
                    @pointerdown="startAlpha($event)"
                    role="slider"
                    tabindex="0"
                    aria-label="Opacity"
                    :aria-valuenow="Math.round(a * 100)"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    @keydown.arrow-left.prevent="nudgeAlpha(-0.05)"
                    @keydown.arrow-right.prevent="nudgeAlpha(0.05)"
                    class="relative h-3 w-full cursor-pointer touch-none rounded-full focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    style="{{ $checker }}"
                >
                    <div class="pointer-events-none absolute inset-0 rounded-full" :style="`background: linear-gradient(to right, transparent, ${hex})`"></div>
                    <div class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-[var(--color-wk-bg-elevated)] shadow-[var(--shadow-wk-sm)]" :style="`left: ${alphaLeft}`"></div>
                </div>
            @endif

            {{-- Format toggle + editable value field. --}}
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="cycleFormat()"
                    class="shrink-0 rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-muted)] px-[var(--padding-wk-x-sm)] py-1 text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)] uppercase hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    aria-label="Cycle color format"
                    x-text="format"
                ></button>
                <input
                    type="text"
                    :value="{{ $withClear ? 'popoverValue' : 'formattedValue' }}"
                    @change="onInput($event.target.value)"
                    aria-label="Color value"
                    :aria-invalid="invalidInput ? 'true' : 'false'"
                    spellcheck="false"
                    class="wk-field w-full rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] bg-[var(--color-wk-bg-input)] px-[var(--padding-wk-x-sm)] py-1 font-[family-name:var(--font-wk-mono)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    :class="invalidInput ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border)]'"
                />
                @if($withEyedropper)
                    <button
                        type="button"
                        x-show="hasEyeDropper"
                        @click="eyedropper()"
                        class="shrink-0 rounded-[var(--radius-wk-sm)] p-1 text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                        aria-label="Pick a color from the screen"
                    >
                        {{-- A recognizable PIPETTE silhouette (angled dropper barrel + tip).
                             The previous glyph was a pencil path — read as "edit", never as
                             "pick a color from the screen". --}}
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2 22 1-1h3l9-9"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-3l9-9"/><path stroke-linecap="round" stroke-linejoin="round" d="m15 6 3.4-3.4a2.1 2.1 0 1 1 3 3L18 9l.4.4a2.1 2.1 0 1 1-3 3l-3.8-3.8a2.1 2.1 0 1 1 3-3l.4.4Z"/></svg>
                    </button>
                @endif
                <button
                    type="button"
                    @click="copy()"
                    class="shrink-0 rounded-[var(--radius-wk-sm)] p-1 text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    :aria-label="copied ? 'Copied' : 'Copy color value'"
                >
                    {{-- Canonical clipboard glyph (matches <x-wirekit::clipboard-button>);
                         stroke-width 2 on a clean single shape renders crisp at 16px —
                         the old two-rect "duplicate" glyph at 1.8 read as blurry. --}}
                    <svg x-show="!copied" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                    {{-- Copied confirmation — a success-colored checkmark (CP-2). --}}
                    <svg x-show="copied" x-cloak class="h-4 w-4 text-[color:var(--color-wk-success)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <span class="sr-only" aria-live="polite" x-text="copied ? 'Copied to clipboard' : ''"></span>
                </button>
                @if($withClear)
                    {{-- Clear to "no color": empties the bound form value (popover
                         mode only — the native input cannot be empty). --}}
                    <button
                        type="button"
                        @click="clear()"
                        class="shrink-0 rounded-[var(--radius-wk-sm)] p-1 text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                        aria-label="Clear color"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        <span class="sr-only" aria-live="polite" x-text="cleared ? 'Color cleared' : ''"></span>
                    </button>
                @endif
            </div>

            @if(! empty($presets))
                {{-- Developer preset swatches. --}}
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Preset colors">
                    @foreach($presets as $preset)
                        <button
                            type="button"
                            @click="pickColor(@js($preset))"
                            class="h-6 w-6 rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                            style="background-color: {{ $preset }};"
                            aria-label="Use {{ $preset }}"
                        ></button>
                    @endforeach
                </div>
            @endif

            @if($withRecents)
                {{-- Recent colors (localStorage, capped at 8). --}}
                <div x-show="recents.length" class="flex flex-wrap gap-1.5" role="group" aria-label="Recent colors">
                    <template x-for="recent in recents" :key="recent">
                        <button
                            type="button"
                            @click="pickColor(recent)"
                            class="h-6 w-6 rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                            :style="`background-color: ${recent}`"
                            :aria-label="`Use ${recent}`"
                        ></button>
                    </template>
                </div>
            @endif
        </div>
        </template>
    </div>
@endif
