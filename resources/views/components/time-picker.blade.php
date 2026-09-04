{{-- optimistic-ui: supported
     A native time input bound to a server value — the same shape as the other
     form controls. --}}
@props([
    // The Livewire method this component should call, when it should show the
    // new value before the server has agreed to it. Null leaves the component
    // exactly as it has always rendered.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'label' => null,
    'hint' => null,
    'error' => null,
    // Declared intent only. A native `<input type="time">` takes its 12-hour or
    // 24-hour rendering from the browser and the operating system locale, and
    // there is no HTML attribute that overrides it — so this prop cannot change
    // what the reader sees. It stays declared because it is a documented part of
    // the surface and because an undeclared `format` would land on the control as
    // a stray HTML attribute instead of being absorbed here.
    'format' => '24h', // 24h | 12h
    // The native `step` attribute, in SECONDS, exactly as HTML defines it:
    // 900 is a quarter of an hour, 60 is one minute, null leaves the browser's
    // own default. It read as MINUTES here for a quick-pick list that was
    // computed on every render and never emitted, and the value never reached the
    // control at all — so a caller writing the documented `step="900"` got a
    // field that still accepted 09:07, and the arrow keys still stepped by one.
    'step' => null,
    'size' => config('wirekit.components.time-picker.size', 'md'),
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('time-picker', $attributes->getAttributes());

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors']);
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'time-picker-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);
    // Strip the caller's `id` AND `name` from the bag: both are rendered explicitly
    // below, so leaving either in the bag emits a second, conflicting attribute on the
    // same element. `id` was stripped from the start; `name` was not, and a caller that
    // passed one got two name attributes on one control — invalid HTML the browser
    // accepts silently by keeping the first, which is why nothing ever went red over it.
    $attributes = $attributes->except(['id', 'name']);

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Base input classes — matches standard input styling
    $inputClasses = WireKit::resolveClasses('time-picker', 'base', implode(' ', [
        'block w-full',
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'tabular-nums',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'hover:border-[var(--color-wk-border-strong-hover)]',
        'focus:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
    ]), $scope);

    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]'
        : 'border-[var(--color-wk-border-strong)]';

    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-[var(--size-wk-sm)]',
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'lg' => implode(' ', [
            'h-[var(--size-wk-lg)]',
            'px-[var(--padding-wk-x-lg)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => implode(' ', [
            'h-[var(--size-wk-md)]',
            'px-[var(--padding-wk-x-md)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
    };

    // Build aria-describedby
    $describedBy = trim(($hint && !$hasError ? $id . '-hint' : '') . ' ' . ($hasError ? $id . '-error' : ''));
@endphp

@php
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        // `value` is NOT a declared prop here — it passes through the bag. Reading
        // it as a prop mounts the layer with an empty value, which is what the
        // first assertion catches.
        'value' => (string) ($attributes->get('value') ?? ''),
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
        'errorRegion' => '#'.$id.'-error',
    ]);
@endphp

<div class="space-y-1.5" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    @if($label)
        <x-wirekit::label :for="$id">{{ $label }}</x-wirekit::label>
    @endif

    <input
        type="time"
        id="{{ $id }}"
        name="{{ $name }}"
        {{-- Seconds, per the HTML attribute. It also decides the arrow-key
             increment, so a control documented as quarter-hourly steps by a
             quarter of an hour rather than by one minute. --}}
        @if($step !== null) step="{{ $step }}" @endif
        @if($hasError) aria-invalid="true" @endif
            @if($optimisticConfig)
                x-ref="control"
                x-bind:aria-busy="isPending"
                x-on:change="commitFromControl()"
            @endif
        @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
        {{ $attributes->class(['wk-field', $inputClasses, $stateClasses, $sizeClasses]) }}
    />

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif

    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
