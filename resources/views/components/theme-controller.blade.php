@props([
    // How the control looks.
    //   'button' — an icon button that flips light/dark (the compact default)
    //   'switch' — a labeled switch, for a settings row
    //   'select' — System / Light / Dark, the only shape that can say "system"
    'variant' => config('wirekit.components.theme-controller.variant', 'button'),
    // Accessible name. The control has no visible text in the button variant, so
    // without this it announces as "button" and nothing else.
    'label' => 'Dark mode',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $variant = WireKit::validateProp('theme-controller', 'variant', $variant, ['button', 'switch', 'select']);

    // The control and the head script must read the same key, or the page paints
    // one theme and then switches to the other in front of the reader.
    $storageKey = config('wirekit.theme.storage_key', 'wirekit-theme');

    $classes = WireKit::resolveClasses('theme-controller', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<div
    x-data="wirekitThemeController({ storageKey: @js($storageKey) })"
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
            class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-[var(--radius-wk)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] transition-colors duration-[var(--transition-wk-duration)] hover:bg-[var(--color-wk-bg-subtle)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
        >
            <x-wirekit::swap effect="rotate" expression="isDark">
                <x-slot:on>
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                </x-slot:on>
                <x-slot:off>
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
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
            <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">{{ $label }}</span>
        </label>
    @else
        {{-- The only shape that can say "system". A two-state control cannot: it
             has to freeze the reader onto an explicit choice the moment they touch
             it, and then their machine going dark at sunset leaves this page
             behind. --}}
        <label class="inline-flex items-center gap-[var(--gap-wk-sm)]">
            <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">{{ $label }}</span>
            <select
                x-model="theme"
                x-on:change="select($event.target.value)"
                data-wk-theme-toggle
                class="wk-field cursor-pointer rounded-[var(--radius-wk)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >
                <option value="system">System</option>
                <option value="light">Light</option>
                <option value="dark">Dark</option>
            </select>
        </label>
    @endif
</div>
