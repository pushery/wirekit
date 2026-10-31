{{-- optimistic-ui: n/a — sub-component
     The date picker's `popover` form, whose state lives in the date picker's factory; its
     optimistic layer belongs to the native field, and `optimistic` is left out here as it is
     with `range`. --}}
{{-- The date picker with `popover`, included from date-picker.blade.php with its variables.

     A trigger shaped like the field, the hidden fields a form sends and `wire:model` binds, and a
     dialog with the calendar component, teleported to the overlay root and placed below the
     trigger (resources/js/components/date-picker.js). A single day is one field, `name`; a range
     is two, `name[start]` and `name[end]`, bound to `property.start` and `property.end`, which is
     what the native form sends, so the server cannot tell the two forms apart. The calendar takes
     the limits and the options of the date picker, and two months for a range. --}}
@php
    $popoverPanelId = $dateId.'-dialog';
    $popoverLabelId = $dateId.'-label';
    $popoverValueId = $dateId.'-value';

    // The value on the root and for the calendar: the day, or the range written `start/end`.
    $popoverValue = $isRange
        ? ($startValue ? $startValue.($endValue ? '/'.$endValue : '') : '')
        : (string) ($value ?? '');

    // With `presets`, a range carries the preset it was chosen by in a third field, `name[preset]`,
    // bound to `property.preset` beside its two ends, and the calendar is told it to mark it.
    $popoverPresets = $isRange && \Pushery\WireKit\Support\DatePresets::listed($presets) !== [];
    $popoverPreset = $popoverPresets && is_string($namedPreset) && in_array($namedPreset, \Pushery\WireKit\Support\DatePresets::KEYS, true) ? $namedPreset : '';
    $popoverCalendarValue = $popoverPresets ? ['start' => $startValue, 'end' => $endValue, 'preset' => $popoverPreset] : $popoverValue;

    // What the trigger shows before the script starts, in the application's language.
    $popoverDay = static fn (?string $day): ?\Carbon\CarbonImmutable => is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 ? \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $day) : null;
    $popoverStart = $popoverDay($isRange ? $startValue : (string) ($value ?? ''));
    $popoverEnd = $isRange ? $popoverDay($endValue) : null;
    $popoverShown = $popoverStart === null ? '' : \Pushery\WireKit\Support\LocalizedDate::bySkeleton($popoverStart, 'yMMMd', 'M j, Y')
        .($popoverEnd !== null ? ' – '.\Pushery\WireKit\Support\LocalizedDate::bySkeleton($popoverEnd, 'yMMMd', 'M j, Y') : '');
    // A placeholder is text, as the field's own attribute is and as the date and time picker's
    // trigger shows it.
    $popoverPlaceholder = filled($placeholder) ? \Pushery\WireKit\Support\SlotContent::textOf($placeholder) : ($isRange ? __('wirekit::Select a date range') : __('wirekit::Select a date'));

    // The trigger is named by the field's label and the value it shows, so a reader hears both, as
    // on the datetime picker. Without a label, a caller's name stands first, else the field's
    // around it, else one made from the name.
    $popoverCallerLabelledBy = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-labelledby');
    $popoverCallerLabelledBy = is_string($popoverCallerLabelledBy) && filled($popoverCallerLabelledBy) ? $popoverCallerLabelledBy : null;
    $popoverCallerLabel = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label');
    $popoverCallerLabel = is_string($popoverCallerLabel) && filled($popoverCallerLabel) ? $popoverCallerLabel : null;
    $popoverNameIds = filled($label) ? $popoverLabelId : ($popoverCallerLabelledBy ?? ($fieldLabelId ?? $popoverLabelId));
    $popoverHiddenName = ! filled($label) && $popoverCallerLabelledBy === null && $fieldLabelId === null ? ($popoverCallerLabel ?? $fallbackLabel) : null;
    $popoverClearName = filled($label) ? \Pushery\WireKit\Support\SlotContent::textOf($label) : ($popoverCallerLabel ?? ($fieldLabelId !== null ? $wkField->labelText() : $fallbackLabel));
    // Where the name is the one made from `name`, a label of the page's own pointed at the trigger
    // takes its place in the browser, and the clear button's wording says where the name goes
    // (resources/js/utils/page-label.js).
    $popoverFallbackClear = $popoverHiddenName !== null && $popoverCallerLabel === null ? __('wirekit::Clear :label') : null;
    // A required picker, or one a required `field` holds, says so in the trigger's name.
    $popoverRequiredWord = $required || $fieldRequired;
    $popoverTriggerNameIds = $popoverNameIds.' '.$popoverValueId.($popoverRequiredWord ? ' '.$dateId.'-required-word' : '');

    // A caller's `form` goes to the fields this component submits (Support\FormOwner).
    $popoverFormOwner = \Pushery\WireKit\Support\FormOwner::of($attributes);

    // `wire:model` binds the hidden fields: the field, or each end of a range by its path, as the
    // native range does.
    // The hidden fields stand inside this root, where `.parent` would still find our names: an
    // `x-model` reaches each as `x-wk-model`, read above the root, which marks the boundary
    // (CallerModel::onField()).
    $popoverModels = \Pushery\WireKit\Support\CallerModel::onField($attributes->whereStartsWith(['wire:model', 'x-model']))->getAttributes();
    $modelScope = \Pushery\WireKit\Support\CallerModel::binds($attributes);
    $popoverEndModels = static fn (string $end): \Illuminate\View\ComponentAttributeBag => new \Illuminate\View\ComponentAttributeBag(array_map(
        static fn ($property) => is_string($property) && $property !== '' ? $property.'.'.$end : $property,
        $popoverModels,
    ));

    // `readonly` shows the value and opens nothing, as on the datetime picker: there is no native
    // field to take it, so the trigger says it is disabled, the dialog does not open on it, and no
    // button clears the value. The hidden fields still submit, as a read-only field does.
    $popoverReadonly = $attributes->has('readonly') && ! $disabled;
    $popoverClearable = \Pushery\WireKit\Support\BooleanProp::from($clearable, false) && ! $disabled && ! $popoverReadonly;

    $popoverTriggerClasses = \Pushery\WireKit\WireKit::resolveClasses('date-picker', 'trigger', implode(' ', [
        'flex w-full items-center gap-[var(--gap-wk-sm)]',
        'ps-[var(--padding-wk-x-md)]',
        // Room at the end for the chevron, a square as wide as a medium field is tall, as a select
        // keeps it, and for the clear button before the chevron.
        $popoverClearable
            ? 'pe-[calc(var(--size-wk-md)+var(--size-wk-target-min))]'
            : 'pe-[var(--size-wk-md)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-start text-[color:var(--color-wk-text)]',
        'border-[length:var(--border-wk-width)]',
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]',
        'shadow-[var(--shadow-wk-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'cursor-pointer',
        'focus:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        'aria-disabled:cursor-default',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $heightClasses,
    ]), $scope);

    $popoverPanelClasses = \Pushery\WireKit\WireKit::resolveClasses('date-picker', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-dropdown,50)]',
        'overflow-y-auto overscroll-contain',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'p-[var(--padding-wk-x-md)]',
        'shadow-[var(--shadow-wk-lg)]',
        'font-[family-name:var(--font-wk-sans)] text-[color:var(--color-wk-text)]',
    ]), $scope);

    $popoverConfig = \Pushery\WireKit\Support\AlpinePayload::from([
        'locale' => \Pushery\WireKit\Support\IcuLocale::tag(),
        'panelId' => $popoverPanelId,
        'range' => $isRange,
    ]);
@endphp

<div
    {{ $outerAttributes }}
    x-data="wirekitDatePicker({{ $popoverConfig }})"
    data-wk-server-value="{{ $popoverValue }}"
    x-bind:data-open="isOpen ? '' : null"
    {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model'])->except(['aria-label', 'aria-labelledby', 'aria-describedby', 'form', 'readonly'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['relative w-full space-y-1.5'])) }}
    @if($callerRef !== '' || $modelScope) data-wk-ref-scope @endif
    data-wk-sr-host
>
    @if($label)
        <x-wirekit::label :id="$popoverLabelId" for="{{ $dateId }}" :required="$required" :help="$help" :help-id="$helpId" :help-field="(string) ($name ?? $dateId)">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @elseif($popoverHiddenName !== null)
        <span id="{{ $popoverLabelId }}" class="sr-only" @if($popoverCallerLabel === null) data-wk-fallback-name="{{ $dateId }}" @endif>{{ $popoverHiddenName }}</span>
    @endif

    <div class="relative">
        <button
            type="button"
            id="{{ $dateId }}"
            x-ref="trigger"
            x-on:click="togglePanel()"
            x-on:keydown="triggerKeydown($event)"
            aria-haspopup="dialog"
            aria-expanded="false"
            x-bind:aria-expanded="isOpen ? 'true' : 'false'"
            aria-controls="{{ $popoverPanelId }}"
            aria-labelledby="{{ $popoverTriggerNameIds }}"
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if($hasError) aria-invalid="true" @endif
            @if($disabled) disabled @endif
            @if($popoverReadonly) aria-disabled="true" @endif
            @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
            class="wk-field wk-touch-row {{ $popoverTriggerClasses }}"
        >
            <svg class="h-4 w-4 shrink-0 text-[color:var(--color-wk-text-muted)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
            {{-- A button cannot say it is required, so its name does: the label's asterisk is
                 hidden from assistive technology, as a native field says it with `required`. --}}
            @if($popoverRequiredWord)
                <span id="{{ $dateId }}-required-word" class="sr-only">{{ __('wirekit::(required)') }}</span>
            @endif
            <span id="{{ $popoverValueId }}" class="min-w-0 flex-1 truncate">
                <span x-show="! showsPlaceholder" x-text="shownValue" @if($popoverShown === '') style="display: none" @endif>{{ $popoverShown }}</span>
                <span x-show="showsPlaceholder" @if($popoverShown !== '') style="display: none" @endif class="text-[color:var(--color-wk-text-placeholder)]">{{ $popoverPlaceholder }}</span>
            </span>
        </button>

        {{-- The chevron and the clear button stand over the end of the trigger rather than in it,
             as a button holds no other button. The chevron lets a press through to the trigger. --}}
        <svg class="pointer-events-none absolute top-1/2 end-[var(--padding-wk-x-md)] h-4 w-4 -translate-y-1/2 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>

        @if($popoverClearable)
            <button
                type="button"
                x-show="! showsPlaceholder"
                @if($popoverShown === '') style="display: none" @endif
                x-on:click="clear()"
                aria-label="{{ __('wirekit::Clear :label', ['label' => $popoverClearName]) }}" @if($popoverFallbackClear !== null) data-wk-fallback-clear="{{ $popoverFallbackClear }}" @endif
                class="wk-touch-target absolute top-1/2 end-[calc(var(--padding-wk-x-md)+--spacing(4)+var(--gap-wk-xs))] flex h-[var(--size-wk-target-min)] w-[var(--size-wk-target-min)] -translate-y-1/2 cursor-pointer items-center justify-center rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-hover)] hover:text-[color:var(--color-wk-text)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        @endif
    </div>

    {{-- The fields a form submits and `wire:model` binds, written by the script with the events a
         native field fires for a pick. A disabled picker leaves them out of the form. --}}
    @if($isRange)
        <input type="hidden" x-ref="inputStart" @if($name) name="{{ $name }}[start]" @endif value="{{ $startValue }}" @if($disabled) disabled @endif {{ $popoverEndModels('start') }} @if($popoverFormOwner) form="{{ $popoverFormOwner }}" @endif />
        <input type="hidden" x-ref="inputEnd" @if($name) name="{{ $name }}[end]" @endif value="{{ $endValue }}" @if($disabled) disabled @endif {{ $popoverEndModels('end') }} @if($popoverFormOwner) form="{{ $popoverFormOwner }}" @endif />
        @if($popoverPresets)
            <input type="hidden" x-ref="inputPreset" @if($name) name="{{ $name }}[preset]" @endif value="{{ $popoverPreset }}" @if($disabled) disabled @endif {{ $popoverEndModels('preset') }} @if($popoverFormOwner) form="{{ $popoverFormOwner }}" @endif />
        @endif
    @else
        <input type="hidden" x-ref="input" @if($name) name="{{ $name }}" @endif value="{{ $value }}" @if($disabled) disabled @endif {{ \Pushery\WireKit\Support\CallerModel::onField($attributes->whereStartsWith(['wire:model', 'x-model'])) }} @if($popoverFormOwner) form="{{ $popoverFormOwner }}" @endif />
    @endif

    {{-- The browser validates no hidden field, so a required picker stops an empty submit through a
         stand-in of its own. --}}
    @if($required)
        @include('wirekit::components.partials.required-check', ['requiredFormOwner' => $popoverFormOwner, 'requiredDisabled' => $disabled || $popoverReadonly])
    @endif

    {{-- On a phone the dialog is a modal over the page (utils/picker-dialog.js, `modalMedia`): this
         is its backdrop, which a tap closes as Escape does. --}}
    <template x-teleport="#wk-overlay-root">
        <div
            x-show="isOpen && dialogModal"
            x-cloak
            wire:key="wk-date-picker-backdrop"
            x-transition:enter="transition-opacity ease-out duration-[var(--transition-wk-duration)]"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-[var(--transition-wk-duration)]"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="closePanel()"
            aria-hidden="true"
            class="wk-overlay-fixed wk-overlay-layer-modal fixed inset-0 z-[var(--z-wk-modal)] bg-[var(--color-wk-overlay)]"
        ></div>
    </template>

    {{-- The dialog, teleported out of the document flow and placed below the trigger, or on a
         phone centered over the backdrop (utils/picker-dialog.js). `click.outside` sits here, on the dialog, because the teleport
         takes it out of the root. The calendar shows the value, or the first day of a range that
         waits for its second, and the picker hears the day it picks. --}}
    <template x-teleport="#wk-overlay-root">
        <div
            x-show="isOpen"
            x-cloak
            wire:key="wk-date-picker-panel"
            {{-- Theme marker — see docs/theming.md "Theme markers". --}}
            data-wk-picker-dialog
            x-bind:data-wk-picker-modal="dialogModal ? '' : null"
            x-ref="panel"
            id="{{ $popoverPanelId }}"
            x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            x-on:click.outside="closePanelOutside($event)"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $popoverNameIds }} {{ $popoverPanelId }}-title"
            class="{{ $popoverPanelClasses }}"
        >
            {{-- The dialog is named by the field and by what it is for, "Due date, Select a date". --}}
            <span id="{{ $popoverPanelId }}-title" class="sr-only">{{ $isRange ? __('wirekit::Select a date range') : __('wirekit::Select a date') }}</span>
            <div x-on:input="onCalendarInput($event)">
                <x-wirekit::calendar
                    flush
                    name="{{ $dateId }}-days"
                    :range="$isRange"
                    :value="$popoverCalendarValue"
                    :presets="$presets"
                    :min="$min"
                    :max="$max"
                    :unavailable="$unavailable"
                    :min-range="$minRange"
                    :max-range="$maxRange"
                    :months="$months"
                    :open-to="$openTo"
                    :with-today="$withToday"
                    :selectable-header="$selectableHeader"
                    :week-numbers="$weekNumbers"
                    :week-starts-on="$weekStartsOn"
                    :fixed-weeks="$fixedWeeks"
                    x-bind:data-wk-server-value="calendarValue()"
                    x-bind:data-wk-opened="dialogOpenings"
                    x-bind:data-wk-months="dialogModal ? 1 : {{ (int) $months }}"
                />
            </div>
        </div>
    </template>

    @if($hint && ! $hasError)
        <p data-wk-prose-skip id="{{ $hintId }}" class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    @if($showsError)
        <p data-wk-prose-skip id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif
</div>
