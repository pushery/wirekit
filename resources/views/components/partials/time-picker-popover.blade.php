{{-- optimistic-ui: n/a — sub-component
     The time picker's `popover` form, whose state lives in the time picker's factory; its
     optimistic layer belongs to the native field, and `optimistic` is left out here. --}}
{{-- The time picker with `popover`, included from time-picker.blade.php with its variables.

     The hidden field a form sends and `wire:model` binds, with the native field's name and value,
     and one of two ways to pick (resources/js/components/time-picker.js): a trigger shaped like the
     field that opens a dialog with the list of times, or with `time="input"` a field that takes
     the time typed in segments, with the list as suggestions. The list's times are every `step`
     from `min` to `max`, written on the locale's clock unless `format` names one. --}}
@php
    $popoverPanelId = $id.'-dialog';
    $popoverLabelId = $id.'-label';
    $popoverValueId = $id.'-value';
    $popoverDisabled = (bool) $attributes->get('disabled', false);
    $popoverRequired = (bool) $attributes->get('required', false);
    $popoverTyped = $timeEntry === 'input';
    // `readonly` shows the value and opens nothing, as on the datetime picker: there is no native
    // field to take it, so the trigger, or the button of a typed time's suggestions, says it is
    // disabled, a typed time's segments are read-only, and no button clears the value. The hidden
    // field still submits, as a read-only field does.
    $popoverReadonly = $attributes->has('readonly') && ! $popoverDisabled;

    // What the trigger shows before the script starts, in the application's language.
    $popoverClock = ['12-hour' => ['hmm', 'g:i A'], '24-hour' => ['Hmm', 'H:i']][$popoverTimeFormat] ?? ['jmm', 'g:i A'];
    $popoverShown = is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1
        ? \Pushery\WireKit\Support\LocalizedDate::bySkeleton(\Carbon\CarbonImmutable::createFromFormat('!H:i', $value), $popoverClock[0], $popoverClock[1])
        : '';
    // The placeholder is spoken text: read as the text the developer wrote, never as HTML escaped again.
    $popoverPlaceholder = \Pushery\WireKit\Support\AttributeText::get($attributes, 'placeholder');
    $popoverPlaceholder = is_string($popoverPlaceholder) && filled($popoverPlaceholder) ? $popoverPlaceholder : __('wirekit::Select a time');

    // Named by the field's label and the time it shows; without a label, by a caller's name, by the
    // label of a field around it, or by one made from the field's name.
    $popoverCallerLabelledBy = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-labelledby');
    $popoverCallerLabelledBy = is_string($popoverCallerLabelledBy) && filled($popoverCallerLabelledBy) ? $popoverCallerLabelledBy : null;
    $popoverCallerLabel = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label');
    $popoverCallerLabel = is_string($popoverCallerLabel) && filled($popoverCallerLabel) ? $popoverCallerLabel : null;
    // Inside a labeled field the field's label names the time, where a hidden name made from the
    // field's name would (Support\FieldControl). Its `for` points at the trigger, or at the first
    // segment of a typed time, which takes the picker's id.
    $popoverFieldLabelId = ! filled($label) && $popoverCallerLabelledBy === null && $popoverCallerLabel === null && $wkField instanceof \Pushery\WireKit\Support\FieldControl
        ? $wkField->takeLabel($id)
        : null;
    $popoverFallbackLabel = $name !== '' ? \Illuminate\Support\Str::headline($name) : __('wirekit::Time');
    $popoverNameIds = filled($label) ? $popoverLabelId : ($popoverCallerLabelledBy ?? ($popoverFieldLabelId ?? $popoverLabelId));
    $popoverHiddenName = ! filled($label) && $popoverCallerLabelledBy === null && $popoverFieldLabelId === null ? ($popoverCallerLabel ?? $popoverFallbackLabel) : null;
    $popoverClearName = filled($label) ? \Pushery\WireKit\Support\SlotContent::textOf($label) : ($popoverCallerLabel ?? ($popoverFieldLabelId !== null ? $wkField->labelText() : $popoverFallbackLabel));
    // Where the name is the one made from `name`, a label of the page's own pointed at the picker
    // takes its place in the browser, and the clear button's wording says where the name goes
    // (resources/js/utils/page-label.js).
    $popoverFallbackClear = $popoverHiddenName !== null && $popoverCallerLabel === null ? __('wirekit::Clear :label') : null;
    $popoverRequiredWord = $popoverRequired || $fieldRequired;
    $popoverTriggerNameIds = $popoverNameIds.' '.$popoverValueId.($popoverRequiredWord ? ' '.$id.'-required-word' : '');

    // A caller's `form` goes to the field this component submits (Support\FormOwner).
    $popoverFormOwner = \Pushery\WireKit\Support\FormOwner::of($attributes);
    $popoverClearable = \Pushery\WireKit\Support\BooleanProp::from($clearable, false) && ! $popoverDisabled && ! $popoverTyped && ! $popoverReadonly;

    $popoverHeight = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
    };

    $popoverTriggerClasses = \Pushery\WireKit\WireKit::resolveClasses('time-picker', 'trigger', implode(' ', [
        'flex w-full items-center gap-[var(--gap-wk-sm)]',
        'ps-[var(--padding-wk-x-md)]',
        // Room at the end for the chevron, a square as wide as a medium field is tall, as a select
        // keeps it, and for the clear button before the chevron.
        $popoverClearable
            ? 'pe-[calc(var(--size-wk-md)+var(--size-wk-target-min))]'
            : 'pe-[var(--size-wk-md)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-start text-[color:var(--color-wk-text)] tabular-nums',
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
        $popoverHeight,
    ]), $scope);

    $popoverPanelClasses = \Pushery\WireKit\WireKit::resolveClasses('time-picker', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-dropdown,50)]',
        'flex flex-col h-[calc(var(--size-wk-sm)*7)] w-[var(--size-wk-time-list)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'p-[var(--padding-wk-x-xs)]',
        'shadow-[var(--shadow-wk-lg)]',
        'font-[family-name:var(--font-wk-sans)] text-[color:var(--color-wk-text)]',
    ]), $scope);

    $popoverConfig = \Pushery\WireKit\Support\AlpinePayload::from([
        'locale' => \Pushery\WireKit\Support\IcuLocale::tag(),
        'panelId' => $popoverPanelId,
        'timeFormat' => $popoverTimeFormat,
        'time' => \Pushery\WireKit\Support\TimeLimits::from($popoverInterval, $min, $max, null, null, null),
        'unavailableLabel' => __('wirekit::unavailable'),
        // The words of a typed time; `:from` and `:to` are filled in by the script with the times
        // as the field writes them.
        'timeInput' => $popoverTyped ? [
            'hourLabel' => __('wirekit::Hour'),
            'minuteLabel' => __('wirekit::Minute'),
            'dayPeriodLabel' => __('wirekit::AM/PM'),
            'unavailableMessage' => __('wirekit:::time is not available.'),
            'outsideMessage' => __('wirekit::Pick a time from :from to :to.'),
            'readonly' => $popoverReadonly,
        ] : null,
    ]);
@endphp

<div
    {{ $outerAttributes }}
    x-data="wirekitTimePicker({{ $popoverConfig }})"
    {{-- The hidden field below stands inside this root, where `.parent` would still find our names:
         an `x-model` reaches it as `x-wk-model`, read above this element (CallerModel::onField()). --}}
    @if(\Pushery\WireKit\Support\CallerModel::binds($attributes)) data-wk-ref-scope @endif
    data-wk-server-value="{{ $value ?? '' }}"
    x-bind:data-open="isOpen ? '' : null"
    {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model'])->except(['aria-label', 'aria-labelledby', 'aria-describedby', 'form', 'disabled', 'required', 'placeholder', 'readonly'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['relative space-y-1.5 min-w-0'])) }}
    data-wk-sr-host
>
    @if($label && $popoverTyped)
        {{-- The label points at the first segment, which the script writes, so it points there
             once the segment is in the page: a press on it starts the typing there. --}}
        <x-wirekit::label :id="$popoverLabelId" x-bind:for="{{ \Pushery\WireKit\Support\AlpinePayload::string($id) }}" :help="$help" :help-id="$helpId" :help-field="$name !== '' ? $name : $id" :required="$popoverRequired">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @elseif($label)
        <x-wirekit::label :id="$popoverLabelId" :for="$id" :help="$help" :help-id="$helpId" :help-field="$name !== '' ? $name : $id" :required="$popoverRequired">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @elseif($popoverHiddenName !== null)
        <span id="{{ $popoverLabelId }}" class="sr-only" @if($popoverCallerLabel === null) data-wk-fallback-name="{{ $id }}" @endif>{{ $popoverHiddenName }}</span>
    @endif

    @if($popoverTyped)
        @if($popoverRequiredWord)
            <span id="{{ $id }}-required-word" class="sr-only">{{ __('wirekit::(required)') }}</span>
        @endif
        {{-- Its own ids under `-time`, apart from the picker's error and hint. --}}
        @include('wirekit::components.partials.time-input', [
            'timeInputId' => $id.'-time',
            'timeInputRow' => false,
            'timeInputLabelledBy' => $popoverNameIds.($popoverRequiredWord ? ' '.$id.'-required-word' : ''),
            'timeInputDescribedBy' => $describedBy,
            'timeInputSize' => $size,
            'timeInputServerError' => $hasError,
            'timeInputDisabled' => $popoverDisabled,
            'timeInputFirstId' => $id,
            'timeInputReadonly' => $popoverReadonly,
        ])
    @else
        <div class="relative">
            <button
                type="button"
                id="{{ $id }}"
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
                @if($popoverDisabled) disabled @endif
                @if($popoverReadonly) aria-disabled="true" @endif
                class="wk-field wk-touch-row {{ $popoverTriggerClasses }}"
            >
                <svg class="h-4 w-4 shrink-0 text-[color:var(--color-wk-text-muted)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                {{-- A button cannot say it is required, so its name does: the label's asterisk is
                     hidden from assistive technology, as a native field says it with `required`. --}}
                @if($popoverRequiredWord)
                    <span id="{{ $id }}-required-word" class="sr-only">{{ __('wirekit::(required)') }}</span>
                @endif
                <span id="{{ $popoverValueId }}" class="min-w-0 flex-1 truncate">
                    <span x-show="! showsPlaceholder" x-text="shownValue" @if($popoverShown === '') style="display: none" @endif>{{ $popoverShown }}</span>
                    <span x-show="showsPlaceholder" @if($popoverShown !== '') style="display: none" @endif class="text-[color:var(--color-wk-text-placeholder)]">{{ $popoverPlaceholder }}</span>
                </span>
            </button>

            {{-- The chevron and the clear button stand over the end of the trigger rather than in
                 it, as a button holds no other button. The chevron lets a press through. --}}
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

        {{-- On a phone the dialog is a modal over the page (utils/picker-dialog.js, `modalMedia`): this
             is its backdrop, which a tap closes as Escape does. --}}
        <template x-teleport="#wk-overlay-root">
            <div
                x-show="isOpen && dialogModal"
                x-cloak
                wire:key="wk-time-picker-backdrop"
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

        {{-- The dialog with the list of times, teleported and placed below the trigger, or on a
             phone centered over the backdrop (utils/picker-dialog.js), named by the field's label. --}}
        <template x-teleport="#wk-overlay-root">
            <div
                x-show="isOpen"
                x-cloak
                wire:key="wk-time-picker-panel"
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
                {{-- The dialog is named by the field and by what it is for, "Alarm, Select a time". --}}
                <span id="{{ $popoverPanelId }}-title" class="sr-only">{{ __('wirekit::Select a time') }}</span>
                @include('wirekit::components.partials.time-list', ['timeListId' => $popoverPanelId.'-times', 'timeListHeading' => false, 'timeListLabelledBy' => $popoverNameIds])
            </div>
        </template>
    @endif

    {{-- The field a form submits and `wire:model` binds, written by the script with the events a
         native field fires for a pick. A disabled picker leaves it out of the form. --}}
    <input type="hidden" x-ref="input" @if($name !== '') name="{{ $name }}" @endif value="{{ $value ?? '' }}" @if($popoverDisabled) disabled @endif {{ \Pushery\WireKit\Support\CallerModel::onField($attributes->whereStartsWith(['wire:model', 'x-model'])) }} @if($popoverFormOwner) form="{{ $popoverFormOwner }}" @endif />

    {{-- The browser validates no hidden field, so a required picker stops an empty submit through a
         stand-in of its own. --}}
    @if($popoverRequired)
        @include('wirekit::components.partials.required-check', ['requiredFormOwner' => $popoverFormOwner, 'requiredDisabled' => $popoverDisabled || $popoverReadonly])
    @endif

    @if($hasError && $errorMessage)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
