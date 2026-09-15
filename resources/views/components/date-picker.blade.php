{{-- optimistic-ui: supported (single-date mode)
     Pass `optimistic="method"` and the date lands the moment it is picked. A
     native <input type="date">, so the layer owns the value exactly as it does
     on time-picker — the previous date is the server's, and an undo destroys
     nothing the user typed.

     `range` mode stays OUT, and not for want of wiring: a range is TWO values,
     so an undo has to answer what it restores when only one end moved, and the
     two inputs constrain each other — rolling one back can leave the other
     holding a bound that no longer applies. That is a contract question, not a
     prop. Passing `optimistic` together with `range` is ignored rather than
     half-applied. --}}
@props([
    // Livewire method to call optimistically. The date appears immediately and
    // is put back if the call fails. Single-date mode only — see the note above.
    // Absent -> this component renders exactly as it did before, down to the byte.
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
    'name' => null,
    'id' => null,
    'label' => null,
    'value' => null,
    // false (default) renders one native date input. true renders a linked
    // start + end pair (two native inputs) — see the range parsing below.
    'range' => false,
    'min' => null,
    'max' => null,
    'size' => config('wirekit.components.date-picker.size', 'md'),
    'disabled' => false,
    'required' => false,
    'placeholder' => null,
    'error' => null,
    'hint' => null,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('date-picker', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $range = BooleanProp::from($range, false);
    $disabled = BooleanProp::from($disabled, false);
    $required = BooleanProp::from($required, false);

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

    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    // Date picker wraps native <input type="date"> for maximum accessibility
    // and zero-dependency operation. The browser provides its own calendar
    // popup + keyboard navigation (arrow keys, PageUp/PageDown for months,
    // etc.), and ships localized to the user's OS locale automatically.
    $isRange = filter_var($range, FILTER_VALIDATE_BOOLEAN);

    // Range value can be an array ['start' => .., 'end' => ..] or a slash string
    // "Y-m-d/Y-m-d" (e.g. "2025-01-20/2025-02-09"). Single mode keeps `value`.
    $startValue = null;
    $endValue = null;
    if ($isRange) {
        if (is_array($value)) {
            $startValue = $value['start'] ?? ($value[0] ?? null);
            $endValue = $value['end'] ?? ($value[1] ?? null);
        } elseif (is_string($value) && str_contains($value, '/')) {
            [$startValue, $endValue] = array_pad(explode('/', $value, 2), 2, null);
        }
    }

    $dateId = $id ?? ($name ? 'wk-date-' . $name : 'wk-date-' . Str::random(6));
    $errorId = $dateId . '-error';
    $hintId = $dateId . '-hint';

    // The bag read is guarded on the name, exactly as field.blade.php does.
    // `MessageBag::has(null)` falls through to `any()`, so an unguarded read
    // makes a date-picker with no `name` report itself invalid the moment ANY
    // unrelated field on the page fails validation — a red border and an
    // `aria-invalid` on a control nobody validated.
    $hasError = $error || ($name && ($errors ?? null)?->has($name));
    $errorMessage = $error ?? ($hasError && $name ? $errors->first($name) : null);

    // The paragraph and the idref pointing at it move together. `$hasError` can
    // be true with nothing to say (`error=""` plus a bag hit), and a described-by
    // resolving to an empty element announces the control as invalid without
    // saying why — a WCAG 3.3.1 failure the markup looks fine in.
    $showsError = $hasError && $errorMessage;

    // Sizing shared with other form controls for visual consistency.
    $heightClasses = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
    };

    $inputClasses = WireKit::resolveClasses('date-picker', 'input', implode(' ', [
        'w-full',
        'px-[var(--padding-wk-x-md)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
        'border-[length:var(--border-wk-width)]',
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]',
        'rounded-[var(--radius-wk-md)]',
        'focus:outline-hidden',
        'focus:ring-[length:var(--ring-wk-width)]',
        'focus:ring-[var(--color-wk-ring)]',
        'focus:border-[var(--color-wk-accent)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $heightClasses,
    ]), $scope);

    // Build aria-describedby from hint + error ids conditionally.
    // The hint paragraph below renders only `@if($hint && ! $hasError)`, so an
    // error state must not keep naming it: an idref whose element is not in the
    // document is dropped silently by assistive technology, and the field is then
    // described by less than the markup claims — or, with only a hint set, by
    // nothing at all. Compose from what this render actually emits.
    $describedBy = trim(($hint && ! $hasError ? $hintId : '') . ' ' . ($showsError ? $errorId : ''));
    // A caller's aria-describedby joins this list, because the control is what it describes
    // and an attribute is written once: the parser keeps the first copy of a duplicate. Own
    // ids first, then the caller's.
    $describedBy = trim($describedBy.' '.((string) $attributes->get('aria-describedby', '')));

    // Accessible-name fallback. WCAG 2.1 (4.1.2) requires every input to
    // have a programmatically-determinable name. When no visible `label`
    // prop is set AND no `aria-label` / `aria-labelledby` is passed via
    // attributes, derive a sr-only fallback from `name` (humanized) so
    // screen readers always announce something. Caller-provided
    // `aria-label` always wins.
    $hasExplicitAriaName = $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $needsSrOnlyFallback = ! $label && ! $hasExplicitAriaName;
    $fallbackLabel = $name ? Str::headline((string) $name) : __('wirekit::Date');

    // ⚠️ The range arm below never rendered the attribute bag — `$attributes` reached
    // exactly one element in this file, the single-date `<input>`. So `class`, `style`,
    // every `data-*`, every `aria-*` and, expensively, `wire:model` were parsed off the
    // tag and dropped: `<x-wirekit::date-picker range wire:model="stay" />` bound nothing
    // at all, with no error, no console entry and a control that looks and behaves
    // normally. Native form submission still works through the `name[start]` /
    // `name[end]` fields, which is exactly what hid it.
    //
    // A range has two controls and one wrapper, so the bag is split three ways rather
    // than splatted onto whichever element came first.
    $rangeStartAttributes = null;
    $rangeEndAttributes = null;
    $rangeWrapperAttributes = null;
    $rangeName = $fallbackLabel;

    if ($isRange) {
        // 1. `wire:model` is a BINDING, and a binding cannot be duplicated: both inputs
        //    writing one scalar property would resolve by whichever commit landed last.
        //    It therefore follows the shape this component already emits and documents
        //    for the native post — `name="stay[start]"` / `name="stay[end]"` becomes
        //    `wire:model="stay.start"` / `wire:model="stay.end"`, which is Livewire's own
        //    path notation for the same array. The DIRECTIVE is copied verbatim so the
        //    modifiers survive (`wire:model.live`, `wire:model.blur.debounce.500ms`);
        //    only the property path gains its end.
        $startPairs = [];
        $endPairs = [];

        foreach ($attributes->whereStartsWith('wire:model')->getAttributes() as $directive => $property) {
            if (! is_string($property) || $property === '') {
                continue;
            }

            $startPairs[$directive] = $property.'.start';
            $endPairs[$directive] = $property.'.end';
        }

        // 2. `aria-label` / `aria-labelledby` are NAMES, and a name on the flex row reaches
        //    nothing: it carries no role and is not focusable, so assistive technology
        //    ignores it there. The caller's name goes onto the two inputs instead.
        //
        //    An idref cannot be suffixed the way a string can, so a caller-supplied
        //    `aria-labelledby` lands on both ends unchanged and names them alike; a `label`
        //    prop or an `aria-label` is what tells the two apart.
        $callerAriaLabel = $attributes->get('aria-label');
        $rangeName = filled($label) ? $label : (filled($callerAriaLabel) ? (string) $callerAriaLabel : $fallbackLabel);

        if ($attributes->has('aria-labelledby')) {
            $startPairs['aria-labelledby'] = $attributes->get('aria-labelledby');
            $endPairs['aria-labelledby'] = $attributes->get('aria-labelledby');
        }

        $rangeStartAttributes = new \Illuminate\View\ComponentAttributeBag($startPairs);
        $rangeEndAttributes = new \Illuminate\View\ComponentAttributeBag($endPairs);

        // 3. Everything else lands on the flex row — the element that IS the range control,
        //    which is what the single-date `<input>` is to the other arm. The outer `w-full`
        //    div also wraps the label, the hint and the error, none of which the bag has
        //    ever reached.
        $rangeWrapperAttributes = $attributes
            ->whereDoesntStartWith('wire:model')
            ->except(['aria-label', 'aria-labelledby', 'aria-describedby']);
    }
@endphp

@php
    // Range mode is excluded HERE rather than at the call site, so a developer
    // who passes both gets the component they had rather than a half-applied
    // layer that rolls back one end of a range and not the other.
    $optimisticConfig = ($optimistic === null || $isRange || $disabled) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        // `value` IS a declared prop here, unlike on time-picker where it passes
        // through the attribute bag — read it from the prop, not from the bag,
        // or the layer mounts empty and the first rollback restores nothing.
        'value' => (string) ($value ?? ''),
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // A second pick while one is in flight would resolve by whichever answer
        // arrives last — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
        'errorRegion' => '#'.$errorId,
    ]);
@endphp

<div class="w-full" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    @if($label)
        <label for="{{ $dateId }}" class="block mb-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">
            {{ $label }}@if($required)<span class="text-[color:var(--color-wk-danger-text)] ms-0.5" aria-hidden="true">*</span>@endif
            @if($required)<span aria-hidden="true" class="text-[color:var(--color-wk-danger-text)]">&nbsp;*</span>@endif
        </label>
    @elseif($needsSrOnlyFallback)
        {{-- Screen-reader-only label fallback. Visible-label-less demos still
             pass WCAG 4.1.2 because the input has a programmatically-
             determinable name. --}}
        <label for="{{ $dateId }}" class="sr-only">{{ $fallbackLabel }}</label>
    @endif

    @if($isRange)
        {{-- Range = two native date inputs. A tiny Alpine scope holds the current
             start (s) + end (e) so each input can constrain the other reactively:
             the end can't precede the start, the start can't follow the end. This
             is additive — it doesn't touch the values, so native form submission of
             the {name}[start] / {name}[end] fields is untouched, and `wire:model`
             binds the same two keys as `{property}.start` / `{property}.end` (see
             the split above). --}}
        <div
            x-data="{ s: {{ \Pushery\WireKit\Support\AlpinePayload::from($startValue) }}, e: {{ \Pushery\WireKit\Support\AlpinePayload::from($endValue) }} }"
            {{-- min-w-0 on the two fields is what makes this row survive a narrow
                 container. A flex item defaults to `min-width: auto`, so it cannot shrink
                 past its intrinsic content width — and a native date input has a large one
                 (the format text plus the picker button). Two of those plus the separator
                 exceed anything under roughly 330px, and a preview frame that does not
                 scroll CLIPS the excess: the end date becomes unreachable on a phone.
                 Measured at a 310px frame before the fix: 25px past the edge, and the
                 fields at 139/155 rather than an even split. --}}
            {{ $rangeWrapperAttributes->class('flex items-center gap-[var(--padding-wk-x-sm)]') }}
        >
            <input
                type="date"
                @if($name) name="{{ $name }}[start]" @endif
                id="{{ $dateId }}"
                value="{{ $startValue }}"
                x-on:change="s = $event.target.value"
                :min="{{ \Pushery\WireKit\Support\AlpinePayload::from($min) }}"
                :max="e || {{ \Pushery\WireKit\Support\AlpinePayload::from($max) }}"
                @if($disabled) disabled @endif
                @if($required) required aria-required="true" @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                @unless($label) aria-label="{{ __('wirekit:::label start', ['label' => $rangeName]) }}" @endunless
                {{ $rangeStartAttributes }}
                class="wk-field min-w-0 flex-1 {{ $inputClasses }}"
            />
            <span aria-hidden="true" class="shrink-0 text-[color:var(--color-wk-text-muted)]">&ndash;</span>
            <input
                type="date"
                @if($name) name="{{ $name }}[end]" @endif
                id="{{ $dateId }}-end"
                value="{{ $endValue }}"
                x-on:change="e = $event.target.value"
                :min="s || {{ \Pushery\WireKit\Support\AlpinePayload::from($min) }}"
                :max="{{ \Pushery\WireKit\Support\AlpinePayload::from($max) }}"
                @if($disabled) disabled @endif
                @if($required) required aria-required="true" @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                aria-label="{{ __('wirekit:::label end', ['label' => $rangeName]) }}"
                {{ $rangeEndAttributes }}
                class="wk-field min-w-0 flex-1 {{ $inputClasses }}"
            />
        </div>
    @else
        <input
            type="date"
            @if($name) name="{{ $name }}" @endif
            id="{{ $dateId }}"
            @if($value) value="{{ $value }}" @endif
            @if($min) min="{{ $min }}" @endif
            @if($max) max="{{ $max }}" @endif
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            @if($disabled) disabled @endif
            @if($required) required aria-required="true" @endif
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if($optimisticConfig)
                x-ref="control"
                x-bind:aria-busy="isPending"
                x-on:change="commitFromControl()"
            @endif
            {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->except('aria-describedby')->class(['wk-field', $inputClasses]) }}
        />
    @endif

    @if($hint && !$hasError)
        <p data-wk-prose-skip id="{{ $hintId }}" class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    @if($showsError)
        {{-- Error message linked via aria-describedby for assistive tech. --}}
        <p data-wk-prose-skip id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. It comes after the error paragraph, which is the
             one the layer yields to when both would speak. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif
</div>
