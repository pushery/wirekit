{{-- optimistic-ui: n/a — sub-component
     One language of a translatable-input. Its field carries the caller's model on the language's
     key, and whether the row is open lives on the parent's Alpine state. --}}
{{-- One language of a translatable-input: its code beside the kit's field.

     The code is decoration for the eye. The field is named by the language in full through its
     visually hidden label, and `lang` on it tells a screen reader how to pronounce what is typed.
     A row that the component opens on demand stays in the page while it is closed, so its field
     keeps its value and its `wire:model`. Closed, it is `hidden="until-found"` where the engine
     supports it, so the browser's find in page can open it, and `x-wk-findable` hides it as `x-show`
     would elsewhere; `x-on:beforematch` opens the language in the component's own state, for a
     match as for `wirekit:focus-invalid`. The space above the row sits on the element inside, since
     a closed until-found row keeps its box (utils/findable.js).

     `$bag` carries only what this component writes itself or forwards as the caller wrote it: the
     language code, `lang`, the models, the attributes that belong to a field and the placeholder
     binding. A text a person typed reaches the field through a bound attribute, which Blade
     escapes; an attribute bag is printed as it is. `$rowHint` is the caller's sentence for this
     language, under its field.

     The value as a whole answers through the `field.set` around the rows. Its group, which the
     component reads by `@aware` inside the set's slot and hands on with this include's scope,
     makes a message filed under the value describe every language's field, and each says invalid
     with it, as the set's checkboxes do. With the component's error off (`$errorOff`), the field
     leaves the bag alone as well, which it reads under its own key otherwise. --}}
@php
    $wkGroup = ($wkFieldSet ?? null) instanceof \Pushery\WireKit\Support\FieldGroup ? $wkFieldSet : null;
    $wkGroupInvalid = $wkGroup !== null && $wkGroup->isInvalid($errors ?? null);
    $wkGroupIds = $wkGroup === null ? null : ($wkGroupInvalid ? $wkGroup->errorId : ($wkGroup->hint !== null ? $wkGroup->hintId : null));

    // The set's message or hint first, then a description the caller gave the value, as on every
    // field. `merge()` would keep the caller's list alone, since a value in the bag wins there.
    if ($wkGroupIds !== null) {
        $callerDescribedBy = trim((string) $bag->get('aria-describedby', ''));
        $bag = $bag->except('aria-describedby')->merge(['aria-describedby' => trim($wkGroupIds.' '.$callerDescribedBy)]);
    }

    // A field with an error of its own says so itself.
    if ($wkGroupInvalid && ! $field['invalid']) {
        $bag = $bag->merge(['aria-invalid' => 'true']);
    }
@endphp
<div
    id="{{ $field['row'] }}"
    data-wk-translation-row="{{ $code }}"
    @if($collapsible)
        x-wk-findable="isOpen({{ \Pushery\WireKit\Support\AlpinePayload::string($code) }})"
        x-on:beforematch="reveal({{ \Pushery\WireKit\Support\AlpinePayload::string($code) }})"
        @if($closed) hidden="until-found" @endif
    @endif
>
    <div class="{{ $rowClasses }}">
        {{-- The code stands where a field's label stands, so it carries what a label would say
             about its field: the stylesheet marks the code of an unsaved language with a dot, in
             the rules after "The words beside the label". --}}
        <span aria-hidden="true" data-wk-translation-code class="{{ $codeClasses }}">{{ strtoupper($code) }}</span>

        @if($multiline)
            <x-wirekit::textarea
                :id="$field['id']"
                :name="$field['name']"
                :label="$languageName"
                hide-label
                :hint="$rowHint ?? null"
                :error="$errorOff ? false : null"
                :rows="$rows"
                :size="$size"
                :placeholder="$placeholderText"
                :required="$required"
                :disabled="$disabled"
                :readonly="$readonly"
                :scope="$scope"
                :attributes="$bag"
            >{{ (string) ($field['text'] ?? '') }}</x-wirekit::textarea>
        @else
            <x-wirekit::input
                :id="$field['id']"
                :name="$field['name']"
                :label="$languageName"
                hide-label
                :hint="$rowHint ?? null"
                :error="$errorOff ? false : null"
                :size="$size"
                :value="$field['text']"
                :placeholder="$placeholderText"
                :required="$required"
                :disabled="$disabled"
                :readonly="$readonly"
                :scope="$scope"
                :attributes="$bag"
            />
        @endif
    </div>
</div>
