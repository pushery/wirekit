{{-- optimistic-ui: n/a — client-only
     Its state is the chosen theme, stored in the browser. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    // How the control looks.
    //   'button' — an icon button that flips light/dark (the compact default)
    //   'switch' — a labeled switch, for a settings row
    //   'select' — System / Light / Dark in a native control
    //   'menu'   — a trigger naming the mode that is on, with a menu of all three
    // `select` and `menu` are the three-valued shapes: only they can say "system". A
    // two-state control cannot, and that is not a gap to fill — it has to freeze the reader
    // onto an explicit choice the moment they touch it, and then their machine going dark at
    // sunset leaves this page behind.
    'variant' => config('wirekit.components.theme-controller.variant', 'button'),
    // Button-variant chrome (ignored by switch/select/menu).
    //   size:    'sm' (32px) | 'md' (36px, default) — matches the button size scale
    //   surface: 'filled' (bordered, elevated — the default) | 'ghost' (borderless,
    //            transparent, muted) so the toggle sits flush next to
    //            surface="ghost" size="sm" buttons in a top bar.
    'size' => config('wirekit.components.theme-controller.size', 'md'),
    'surface' => config('wirekit.components.theme-controller.surface', 'filled'),
    // Accessible name. The control has no visible text in the button variant, so
    // without this it announces as "button" and nothing else.
    'label' => __('wirekit::Dark mode'),
    // Render the switch/select label sr-only (kept as the control's accessible
    // name) — for a header or toolbar where the surrounding chrome already says
    // what the control is. The <label> WRAPS the control here, so the name is
    // associated with the element rather than with the visible text and survives
    // being taken off the screen. No-op on the button variant, which has no
    // visible text at all. Mirrors input / select / textarea / combobox /
    // checkbox `hideLabel`.
    //
    // On `menu` it hides something else, and the difference is worth knowing: there is no
    // <label> and no `label` prop in play at all. What it takes off the screen is the mode
    // NAME beside the trigger's glyph, leaving the icon alone — for a bar where the word is
    // a line too wide. The trigger keeps its accessible name either way, because the name
    // is that same word rendered sr-only rather than removed.
    'hideLabel' => false,
    // Wording for the three modes, used by the `select` and `menu` variants. Translated by
    // default;
    // pass your own to change the WORDS rather than just the language — an app
    // may want "Automatic / Day / Night", which no locale file can express.
    'options' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('theme-controller', $attributes->getAttributes());

    $variant = WireKit::validateProp('theme-controller', 'variant', $variant, ['button', 'switch', 'select', 'menu']);
    $size = WireKit::validateProp('theme-controller', 'size', $size, ['sm', 'md']);
    $surface = WireKit::validateProp('theme-controller', 'surface', $surface, ['filled', 'ghost']);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy, so
    // `hideLabel="false"` would mean the opposite of what the call site reads as.
    $hideLabel = BooleanProp::from($hideLabel, false);

    // Control (button-variant) chrome. Full literal class strings so the Tailwind
    // scanner sees them. The inner <button> routes through resolveClasses with its
    // OWN 'control' block (the wrapper 'base' block only ever reached the outer
    // <div>), so config/scoped classes can now restyle the button too.
    $controlSize = match ($size) {
        'sm' => 'h-8 w-8',
        default => 'h-9 w-9',
    };
    $iconSize = match ($size) {
        'sm' => 'h-4 w-4',
        default => 'h-5 w-5',
    };
    // filled (default) reproduces the original chrome so existing usage renders
    // unchanged; ghost is the new borderless/transparent surface.
    $surfaceChrome = match ($surface) {
        'ghost' => 'border-transparent bg-transparent text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-subtle)] hover:text-[color:var(--color-wk-text)]',
        default => 'border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-subtle)]',
    };
    $controlClasses = WireKit::resolveClasses('theme-controller', 'control', implode(' ', [
        'wk-touch-target inline-flex cursor-pointer items-center justify-center rounded-[var(--radius-wk)] border-[length:var(--border-wk-width)] transition-colors duration-[var(--transition-wk-duration)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        $controlSize,
        $surfaceChrome,
    ]), $scope);

    // The control and the head script must read the same key, or the page paints
    // one theme and then switches to the other in front of the reader.
    $storageKey = config('wirekit.theme.storage_key', 'wirekit-theme');

    // Storage driver: 'local' (localStorage, client-only) or 'cookie' (the server
    // can read it and render the theme itself). The control and the head script
    // must use the SAME driver — the head script's reader is driven by the same
    // config value in WireKitServiceProvider — or one writes where the other never
    // looks. The cookie attributes only matter for the cookie driver.
    $storage = config('wirekit.theme.storage', 'local') === 'cookie' ? 'cookie' : 'local';
    $cookieAttributes = config('wirekit.theme.cookie_attributes', []);

    // Caller wording wins per key, so overriding one option does not silently
    // drop the other two.
    $optionLabels = array_merge(
        ['system' => __('wirekit::System'), 'light' => __('wirekit::Light'), 'dark' => __('wirekit::Dark')],
        is_array($options) ? $options : [],
    );

    $classes = WireKit::resolveClasses('theme-controller', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<div
    x-data="wirekitThemeController({ storageKey: {{ \Pushery\WireKit\Support\AlpinePayload::from($storageKey) }}, storage: {{ \Pushery\WireKit\Support\AlpinePayload::from($storage) }}, cookieAttributes: {{ \Pushery\WireKit\Support\AlpinePayload::from($cookieAttributes) }} })"
    data-wk-theme-controller
    data-variant="{{ $variant }}"
    {{ $attributes->class([$classes]) }}
>
    @if($variant === 'button')
        {{-- aria-pressed, not just an icon: "the page is dark" is a state, and a
             reader who cannot see the icon still needs to know which way the
             switch is thrown. The icon alone would be color-and-shape only. --}}
        <button
            type="button"
            x-on:click="toggle()"
            :aria-pressed="isDark ? 'true' : 'false'"
            aria-label="{{ $label }}"
            data-wk-theme-toggle
            class="{{ $controlClasses }}"
        >
            {{-- icon-on / icon-off slots let a developer supply their own glyphs —
                 and choose the POLARITY (show the current state, or the action).
                 Unset falls back to the built-in moon (on/dark) + sun (off/light),
                 sized to match the control. --}}
            <x-wirekit::swap effect="rotate" expression="isDark">
                <x-slot:on>
                    @isset($iconOn)
                        {{ $iconOn }}
                    @else
                        <svg class="{{ $iconSize }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    @endisset
                </x-slot:on>
                <x-slot:off>
                    @isset($iconOff)
                        {{ $iconOff }}
                    @else
                        <svg class="{{ $iconSize }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        </svg>
                    @endisset
                </x-slot:off>
            </x-wirekit::swap>
        </button>
    @elseif($variant === 'switch')
        <label class="inline-flex cursor-pointer items-center gap-[var(--gap-wk-sm)]">
            {{-- A real checkbox with role="switch": it is focusable, it toggles on
                 Space, and it announces its state — all of which a styled div has
                 to reimplement badly. --}}
            <input
                type="checkbox"
                role="switch"
                x-on:change="toggle()"
                :checked="isDark"
                data-wk-theme-toggle
                class="peer sr-only"
            />
            {{-- The knob's travel lives in dist/wirekit.css, keyed off the
                 input's :checked. Not peer-checked: that variant only reaches
                 SIBLINGS of the peer, and the knob is a grandchild — the class
                 sat here doing nothing while an Alpine :class binding quietly did
                 the work. One mechanism, in the place that can actually see the
                 checkbox. --}}
            <span
                aria-hidden="true"
                class="wk-theme-switch-track relative h-6 w-11 shrink-0 rounded-[var(--radius-wk-full)] bg-[var(--color-wk-bg-muted)] transition-colors duration-[var(--transition-wk-duration)] peer-checked:bg-[var(--color-wk-accent)] peer-focus-visible:ring-[length:var(--ring-wk-width)] peer-focus-visible:ring-[var(--color-wk-ring)]"
            >
                <span class="wk-theme-switch-knob absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-[var(--color-wk-bg-elevated)] shadow-[var(--shadow-wk-sm)] transition-transform duration-[var(--transition-wk-duration)]"></span>
            </span>
            <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]{{ $hideLabel ? ' sr-only' : '' }}">{{ $label }}</span>
        </label>
    @elseif($variant === 'select')
        {{-- Three-valued, so it can say "system" — the `menu` variant below is the other
             one. A two-state control cannot: it has to freeze the reader onto an explicit
             choice the moment they touch it, and then their machine going dark at sunset
             leaves this page behind.
             What this shape cannot do is carry an icon, because a native <option> takes no
             markup. That is the browser rather than an omission, and it is why `menu`
             exists beside it rather than instead of it. --}}
        <label class="inline-flex items-center gap-[var(--gap-wk-sm)]">
            <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]{{ $hideLabel ? ' sr-only' : '' }}">{{ $label }}</span>
            <select
                x-model="theme"
                x-on:change="select($event.target.value)"
                data-wk-theme-toggle
                {{-- A <select> is a form control under WCAG 1.4.11: its border must
                     clear 3:1 against the fill. --color-wk-border is the DECORATIVE
                     token (~1.29:1) — the canonical select.blade.php reaches for
                     --color-wk-border-strong (+ -strong-hover) instead, and so must
                     this one. --}}
                class="wk-field cursor-pointer rounded-[var(--radius-wk)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] bg-[var(--color-wk-bg-elevated)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] transition-colors duration-[var(--transition-wk-duration)] hover:border-[var(--color-wk-border-strong-hover)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >
                {{-- __() so a translated app can localize the only visible copy of
                     the select variant (the keys ship in lang/en.json), and the
                     `options` prop so it can re-word them entirely. --}}
                @foreach($optionLabels as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
        </label>
    @elseif($variant === 'menu')
        {{-- The fourth shape, and it exists because the other three each answer only part of
             one question: what mode is on RIGHT NOW.

               button   carries an icon and cannot say "system" — and a lone sun glyph is
                        ambiguous anyway: it may mean the current state or the one a click
                        would produce, which are opposites.
               switch   says which of two, and cannot say "system" either.
               select   is the only three-valued one and a native <option> takes no markup,
                        so it can never carry an icon. That is the browser, not an omission.

             So this is a real button with a real menu: the trigger names the active mode AND
             shows its glyph, and all three modes are listed with theirs. The dropdown brings
             the keyboard model, the focus trap and the escape handling with it rather than
             this file rebuilding them.

             THREE SPANS RATHER THAN ONE EXPRESSION. `x-show` per mode keeps every string in
             the markup where a translator and a CSP evaluator can both see it; building the
             label from `theme` would put copy inside an Alpine expression, which the CSP
             build cannot evaluate and no locale file can reach. --}}
        <x-wirekit::dropdown placement="bottom-start">
            <x-slot:trigger>
                <x-wirekit::button intent="neutral" surface="outline" size="sm" data-wk-theme-toggle>
                    @foreach(['light' => 'sun', 'dark' => 'moon', 'system' => 'system'] as $modeValue => $modeIcon)
                        <span x-show="theme === {{ \Pushery\WireKit\Support\AlpinePayload::string($modeValue) }}" x-cloak class="inline-flex items-center gap-[var(--gap-wk-sm)]">
                            <x-wirekit::icon :name="$modeIcon" class="h-4 w-4" />
                            <span @class(['sr-only' => $hideLabel])>{{ $optionLabels[$modeValue] ?? $modeValue }}</span>
                        </span>
                    @endforeach
                </x-wirekit::button>
            </x-slot:trigger>

            @foreach(['light' => 'sun', 'dark' => 'moon', 'system' => 'system'] as $modeValue => $modeIcon)
                {{-- BOTH HALVES OF THE STATE ARE BOUND, and for one reason: the stored mode lives
                     in the reader's browser, so the server does not know which of the three is on.
                     Same reason the trigger's label is bound.

                     That is also why `dropdown.item`'s own `active` prop cannot carry this. It is
                     a server-side boolean, evaluated at render time, and there is no true value to
                     give it here — every row would be handed `false` and the item's `$activeClasses`
                     would resolve to the empty string for all three. So the item's active pair is
                     reproduced as an Alpine binding instead.

                     `aria-current` cannot hold this on its own, and the reason is worth writing
                     down so the class binding does not get deleted as redundant: the only
                     `[aria-current]` rules the package ships are scoped to the reading spine and
                     the table of contents. On a menu row the attribute paints nothing at all — it
                     is announced, and it is invisible. Both lines stay, one per audience.

                     The class pair is copied verbatim from `dropdown.item`'s non-danger `$activeClasses`
                     — weight and text color, never a background, because hover and focus already own
                     the surface and a third one would be indistinguishable from them at exactly the
                     moment it matters. Keep the two in step: if that pair changes there, it changes
                     here. Written literally rather than assembled so the Tailwind scanner sees it. --}}
                <x-wirekit::dropdown.item
                    :icon="$modeIcon"
                    x-on:click="select({{ \Pushery\WireKit\Support\AlpinePayload::string($modeValue) }})"
                    x-bind:aria-current="theme === {{ \Pushery\WireKit\Support\AlpinePayload::string($modeValue) }} ? 'true' : null"
                    x-bind:class="theme === {{ \Pushery\WireKit\Support\AlpinePayload::string($modeValue) }} ? 'font-medium text-[color:var(--color-wk-accent-text)]' : ''"
                >{{ $optionLabels[$modeValue] ?? $modeValue }}</x-wirekit::dropdown.item>
            @endforeach
        </x-wirekit::dropdown>
    @endif
</div>
