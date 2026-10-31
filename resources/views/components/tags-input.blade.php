{{-- optimistic-ui: supported
     The set shape matches the one `multi-select` uses. What differs is where a
     tag comes from: it is TYPED, not picked from a list, so rolling back a failed
     ADD deletes text the reader wrote. Removing an existing tag carries no such
     cost.

     Both paths take the FOURTH exit anyway, and that is the decision rather than
     an oversight. Splitting them is not available: the set is ONE value, so a
     rollback restores the whole set — there is no way to undo a removal without
     also undoing an addition that happened alongside it. `keep` guarantees
     nothing typed is destroyed; the price is that a refused REMOVAL stays
     removed and says so, which the reader can act on because the tag is one
     keystroke away. --}}
@props([
    // `required` — DECLARED rather than left to the attribute bag. Undeclared, Blade folded it
    // into the bag and it landed on a wrapper div, where it is invalid HTML that nothing
    // reads: no native constraint, no aria-required, no asterisk. StrictnessGate did not
    // complain either, because `required` is in its HTML passthrough list — so it looked like
    // a legitimate attribute all the way down. The result was a required field that submits
    // empty, in the same form as a plain input that behaves correctly.
    'required' => false,
    // The Livewire method to call when the set changes. A refusal KEEPS the
    // change and says it was not saved — see the note above.
    // Needs `wirekit-optimistic.js` on the page beside the bundle, as
    // docs.wirekit.app/extending/optimistic-ui shows; without it no `wirekitOptimistic` exists.
    'optimistic' => null,
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer carries that: it spreads `args` into the call.
    'optimisticArgs' => [],
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'label' => null,
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
    'error' => null,
    'value' => [],
    // Both resolve through config, because the docs page has told developers to override
    // them in `config/wirekit.php` since it shipped and nothing here ever read it — an
    // instruction that fails silently, with no error to search for. The catalog default
    // stays behind a `??` rather than moving into the config stub: a literal in the stub
    // would freeze one language for every locale.
    'maxTags' => config('wirekit.components.tags-input.max-tags'),
    'placeholder' => config('wirekit.components.tags-input.placeholder') ?? __('wirekit::Add a tag…'),
    // Documented as part of this control's API since it shipped, and until now it was
    // documentation only: the attribute bag never reached the text input, so a call site
    // asking for a disabled field got a fully working one.
    'disabled' => false,
    // Read-only as a native field is: the tags stay on the page and are sent with the form, and
    // nothing adds or removes one. Undeclared, the attribute landed on the frame, where it is no
    // valid attribute and stops nothing.
    'readonly' => false,
    'scope' => null,
])

@aware(['announceErrors' => null, 'wkField' => null, 'wkFieldSet' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('tags-input', $attributes->getAttributes());

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'wkField', 'wk-field', 'wkFieldSet', 'wk-field-set']);
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `disabled="false"` would disable the field the call site asked to leave alone.
    $disabled = BooleanProp::from($disabled, false, 'tags-input', 'disabled');
    $readonly = BooleanProp::from($readonly, false, 'tags-input', 'readonly');
    $required = BooleanProp::from($required, false, 'tags-input', 'required');


    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'tags-'); // page-unique DOM id; see Support\DomId
    // The bag holds a bound name escaped once; read as the text it stands for, so the field is
    // sent under the name the caller bound (Support\AttributeText).
    // Without a `name` the field is sent under the caller's `id`, and without either it is not
    // sent at all: an id DomId counted out names nothing a form should carry.
    $name = \Pushery\WireKit\Support\AttributeText::get($attributes, 'name', filled($attributes->get('id')) ? $id : '');
    // Without a `name`, the bag files this field's error under the property its `wire:model`
    // binds (FieldError::nameOf).
    $errorName = \Pushery\WireKit\Support\FieldError::nameOf(\Pushery\WireKit\Support\AttributeText::get($attributes, 'name'), $attributes) ?? $name;

    // A caller's `id` goes on the text field, the element a `<label for>` elsewhere on the
    // page and a link to `#id` reach. Without one the field takes the component's id with
    // `-input` after it.
    $callerId = filled($attributes->get('id'));
    $fieldId = $callerId ? $id : $id.'-input';

    // Inside a labeled field the field's label names the text field by `for` and the group by
    // reference, where the placeholder would name the field (Support\FieldControl). Not when
    // this component is named by its own label or an aria-label, which win as anywhere else.
    $fieldLabelId = ! filled($label) && \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label') === null && $wkField instanceof \Pushery\WireKit\Support\FieldControl
        ? $wkField->takeLabel($fieldId)
        : null;

    // Consumed above and re-emitted where they belong -- the text field and the
    // hidden inputs. A `name` left on the wrapper <div> names no form control.
    $attributes = $attributes->except(['id', 'name']);

    // A field around this control with its error off turns it off here too (Support\FieldControl::errorOf()).
    $error = \Pushery\WireKit\Support\FieldControl::errorOf($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $error);

    $errorMessage = \Pushery\WireKit\Support\FieldGroup::entryMessage($wkFieldSet, $errors ?? null, $errorName, $error);
    $hasError = $errorMessage !== null;

    // Normalize the initial value into an array of strings. Accepts a real
    // array (e.g. `:value="['Laravel', 'Livewire']"`) or a comma-separated
    // string (e.g. `value="Laravel,Livewire"`); both shapes are common in
    // developer code. A Collection is read as the list it holds; the branch
    // below would otherwise take it for no value and start the field empty.
    $value = \Pushery\WireKit\Support\ListProp::from($value);
    // An entry that is a list itself, as `old()` gives one after a request that sent `name[][]`,
    // is reported and left out (Support\FieldValue).
    $value = is_array($value) ? \Pushery\WireKit\Support\FieldValue::items($value, 'tags-input') : $value;
    if (is_string($value)) {
        $initialTags = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($t) => $t !== ''));
    } elseif (is_array($value)) {
        $initialTags = array_values(array_map(fn ($t) => (string) $t, $value));
    } else {
        $initialTags = [];
    }

    // Container classes — styled like an input field but wraps tag chips + text input.
    // Tight container padding (`p-1` = 4 px on every side) keeps the chips
    // hugging the input-box border instead of floating inside a large
    // inset frame. The chips already carry their own internal padding;
    // the container only needs enough room for the focus ring and a
    // single-pixel border without doubling the visual whitespace.
    $containerClasses = WireKit::resolveClasses('tags-input', 'base', implode(' ', [
        'flex flex-wrap items-center gap-1',
        'min-h-[var(--size-wk-md)]',
        'p-1',
        'font-[family-name:var(--font-wk-sans)]',
        'bg-[var(--color-wk-bg-input)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus-within:ring-[length:var(--ring-wk-width)] focus-within:ring-[var(--color-wk-ring)]',
    ]), $scope);

    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)]'
        : 'border-[var(--color-wk-border-strong)]';

    // Dimming sits on the CONTAINER rather than on the text input, because the chips and
    // their remove buttons are the larger half of the control and they are disabled too —
    // a dimmed field beside fully-lit chips reads as one part being off, not the control.
    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] cursor-not-allowed'
        : '';

    /*
     * Tag chip classes — `px-2 py-1` (8 px horizontal, 4 px vertical).
     * Symmetric on each axis (left == right, top == bottom) but more
     * generous horizontally than vertically so the label has breathing
     * room around its trailing X-button without inflating the chip
     * height. The earlier `p-1` (uniform 4 px) made chips read as
     * cramped against multi-word labels; the previous-previous
     * `pl-x-sm pr-1 py-1` was asymmetric left-vs-right which looked
     * lopsided. `px-2 py-1` keeps both axes symmetric AND gives the
     * label enough horizontal slack to read comfortably.
     */
    $tagClasses = implode(' ', [
        'inline-flex items-center gap-1',
        'px-2 py-1',
        'text-[length:var(--text-wk-sm)]',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
    ]);

    $describedBy = trim(($hint && !$hasError ? $id . '-hint' : '') . ' ' . ($hasError ? $id . '-error' : ''));
    // A caller's aria-describedby joins this list, because the control is what it describes
    // and an attribute is written once: the parser keeps the first copy of a duplicate. Own
    // ids first, then the caller's.
    $describedBy = trim($describedBy.' '.((string) $attributes->get('aria-describedby', '')));
    // The field's help, after its own message: what the field is for. Only beside a label,
    // which is where its hidden copy is rendered.
    $helpId = filled($help) && filled($label) ? $id.'-help' : null;
    // A `field` around this control renders its hint below it, and the control is described by it
    // as by a hint of its own (Support\FieldControl).
    $describedBy = \Pushery\WireKit\Support\FieldControl::describe($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $describedBy, $id, $hint ? $id.'-hint' : null, $helpId ?? null);
    // A required `field` around this control hides its asterisk from assistive technology, so
    // the control says it, with `aria-required` and never the browser's own check
    // (Support\FieldControl::requiredBy()).
    $fieldRequired = \Pushery\WireKit\Support\FieldControl::requiredBy($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $required);
    $describedBy = trim($describedBy.' '.($helpId ?? ''));
    // A required tags input that a submit found empty says so under the field
    // (partials/required-message), unless the server's own message already stands there.
    $requiredMessageId = $required && ! $hasError ? $id.'-required' : null;
    $describedBy = trim($describedBy.' '.($requiredMessageId ?? ''));

    /*
     * What the live region says, as TEMPLATES rather than sentences built in JavaScript.
     *
     * Every one of these covers a change a screen reader is otherwise given nothing to
     * notice: the field empties and a chip appears elsewhere, Backspace takes a tag back
     * with no focus move behind it, and two exits leave the typed text standing with no
     * reason — a duplicate and a full set. The last two are the ones worth the most: the
     * reader has evidence that nothing happened and none of why.
     *
     * Assembled from placeholders here because a sentence concatenated in JavaScript
     * cannot be translated and word order is not the same in every language — the shape
     * `wizard` uses for its step announcement, for the same reason.
     *
     * The limit is known here, so it chooses the form of the full-set sentence ("Maximum of
     * 1 tag reached"), and `:count` stays in it for the factory to fill.
     */
    $tagLimit = \Pushery\WireKit\Support\NumericProp::intOrNull($maxTags, 'tags-input', 'maxTags');
    $tagAnnouncements = \Pushery\WireKit\Support\AlpinePayload::from([
        'added' => __('wirekit::Added :name'),
        'removed' => __('wirekit::Removed :name'),
        'duplicate' => __('wirekit::Already in the list: :name'),
        'limit' => \Pushery\WireKit\Support\Plural::choice('wirekit::Maximum of :count tags reached', $tagLimit ?? 2, ['count' => ':count']),
    ]);

    // `bind` rather than `value`: `tags` already exists on the component this
    // layer nests inside. A tuple is not needed — the set IS the value, and the
    // factory snapshots an array by COPY, so a rollback cannot restore the same
    // reference it was meant to replace.
    $optimisticConfig = ($optimistic === null || $disabled) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'tags',
        'action' => $optimistic,
        'args' => (array) \Pushery\WireKit\Support\ListProp::renumbered($optimisticArgs),
        'failure' => 'keep',
        'debug' => (bool) config('app.debug'),
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'kept' => __('wirekit::Could not save. Your entry is still here.'),
        ],
        'errorRegion' => \Pushery\WireKit\Support\CssIdentifier::idSelector($id.'-error'),
    ]);

    // A caller's `x-ref` belongs to the caller's component. The caller's attributes land on our
    // root, and a root registers a ref on itself, where the caller's `$refs` never reads it.
    // The name moves to `x-wk-ref`, which registers the root on the root above
    // `data-wk-ref-scope` (resources/js/utils/caller-ref.js).
    $callerRef = trim((string) $attributes->get('x-ref', ''));
    $attributes = $attributes->except('x-ref');
    // A caller's `aria-labelledby` names the control and wins over the component's own name, as
    // their `aria-label` does; on the wrapper it would name nothing a reader lands on.
    $callerLabelledBy = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-labelledby');
    $callerLabelledBy = is_string($callerLabelledBy) && filled($callerLabelledBy) ? $callerLabelledBy : null;
    // A caller's `form` goes to the field this component submits, and only there: on the
    // wrapper it is not a valid attribute and joins nothing to the form (Support\FormOwner).
    $formOwner = \Pushery\WireKit\Support\FormOwner::of($attributes);
    $attributes = $attributes->except('form');
    // What acts only on the field the reader types into goes to the text field: on the wrapper
    // `autofocus` focuses nothing, `tabindex` leaves the field's tab stop as it is, the on-screen
    // keyboard reads none of the rest, and `maxlength` limits nothing (Support\FieldAttributes). On
    // the text field `maxlength` is the length of a tag as it is typed.
    [$fieldAttributes, $attributes] = \Pushery\WireKit\Support\FieldAttributes::split($attributes, [...\Pushery\WireKit\Support\FieldAttributes::KEYBOARD, 'autofocus', 'maxlength', 'tabindex']);
@endphp

<div {{ $outerAttributes }} class="relative space-y-1.5 min-w-0" @if($callerRef !== '') data-wk-ref-scope @endif>
    @if($label)
        <x-wirekit::label :help="$help" :help-id="$helpId" :help-field="$name !== '' ? $name : $id" :for="$fieldId" :required="$required">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @endif

    <div
        {{-- The tags travel on `data-wk-server-value`, not in `x-data`, so the expression is the
             same for every set: a Livewire morph that changed it would start the component again,
             and through `x-modelable` the answer to a `wire:model.live` change would send the set
             of the request back, without a tag the reader added since. --}}
        data-wk-server-value="{{ \Pushery\WireKit\Support\AlpinePayload::json($initialTags) }}"
        x-data="wirekitTagsInput({ name: {{ \Pushery\WireKit\Support\AlpinePayload::string($name) }}, maxTags: {{ $tagLimit ?? 'null' }}, announcements: {{ $tagAnnouncements }}, readonly: {{ $readonly ? 'true' : 'false' }} })"
        {{-- The tag set lives only in Alpine and leaves through hidden inputs, which
             emit nothing when a binding writes their value. `x-modelable` is the bridge
             Alpine provides for exactly that, and `wire:model` compiles to `x-model`, so
             one attribute serves a Livewire property and a plain Alpine page alike.
             Without it the control failed in the direction that reads as success: the
             chips confirmed the choice while the server never heard about it. --}}
        x-modelable="tags"
        {{-- The whole bag, not just `class`, so everything else the caller wrote --
             `wire:model`, `data-*` -- arrives here. `aria-describedby` and `aria-label` are
             the exceptions: both belong to the text input, which takes them itself, and
             `aria-label` on this roleless element would be a prohibited attribute. --}}
        {{ \Pushery\WireKit\Support\CallerModel::aroundRoot($attributes->except(['aria-describedby', 'aria-label', 'aria-labelledby', 'autocomplete'])) }}
        {{-- `focus` fires here as the reader enters the control as a whole; the model events fire its
             `blur` on this root when they leave (utils/focus-unit.js). --}}
        x-wk-focus-unit.focus-only
        @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
    >
        {{-- The set's own live region, OUTSIDE the optimistic wrapper below.
             Unconditional and starting empty, for the reason the optimistic announcer
             states next to itself: a live region that arrives together with its text is
             a new node and announces nothing.

             `tagAnnouncement`, not `announcement` — the optimistic layer declares a
             property of that name, so inside its element the shorter name would resolve
             to the layer's save-state message instead of to this one. The two say
             different things and both are wanted: what the set did, and whether it
             saved. --}}
        <div class="sr-only" aria-live="polite" aria-atomic="true" x-text="tagAnnouncement"></div>

@if($optimisticConfig)
        {{-- Inside the component that owns the set, not around it: a nested
             Alpine component reads and writes its parent's properties through
             `this` and never the reverse, so `bind: 'tags'` only resolves this
             way round. Wrapped the other way it would bind to nothing, and fail
             as `tags` being undefined rather than as anything that names the
             cause.

             `display: contents` so the chip row keeps its own layout. --}}
        <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
@endif
        {{-- The tags the page was loaded with, for a page without a script. The fields below come
             from a template, which renders nothing until Alpine starts, so a form sent without a
             script carried no tags at all, and a server stored the list empty. A browser running
             scripts never reads `noscript`, so there only the template's fields go into the form.
             `hidden` keeps the element out of the layout without a script as well; a field is
             sent whatever it shows. --}}
        @if($initialTags !== [])
            <noscript hidden>@foreach($initialTags as $initialTag)<input type="hidden" @if($name !== '') name="{{ \Illuminate\Support\Str::finish((string) $name, '[]') }}" @endif value="{{ $initialTag }}" @if($disabled) disabled @endif @if($formOwner) form="{{ $formOwner }}" @endif />@endforeach</noscript>
        @endif

        {{-- Hidden inputs for form submission — one per tag --}}
        <template x-for="(tag, i) in tags" :key="i">
            {{-- A disabled field is left out of the form data, as a native one is. --}}
            {{-- The name ends in `[]` once, whether the caller wrote `skills` or `skills[]`, the way a
                 native multiple select is written. Twice, PHP reads a list of one-item lists. --}}
            <input type="hidden" @if($name !== '') :name="{{ \Pushery\WireKit\Support\AlpinePayload::string(\Illuminate\Support\Str::finish((string) $name, '[]')) }}" @endif :value="tag" @if($disabled) disabled @endif @if($formOwner) form="{{ $formOwner }}" @endif />
        </template>
        {{-- The browser validates no hidden field, and an empty list has none at all, so a required
             tags input stops an empty submit through a stand-in of its own. --}}
        @if($required)
            @include('wirekit::components.partials.required-check', ['requiredFormOwner' => $formOwner, 'requiredDisabled' => $disabled || $readonly])
        @endif

        {{-- `wk-field-frame`: on a coarse pointer the frame takes the 44px touch floor and the text
             input inside gives its own up. Outside `resolveClasses()`, so restyling keeps it. --}}
        <div class="{{ $containerClasses }} {{ $stateClasses }} {{ $disabledClasses }} wk-field-frame" @click="$refs.input.focus()"
            {{-- A named group around the chips AND the field, so a screen reader that
                 lands on the remove button of the fourth chip is told which control it
                 is inside. Only when a label exists: `role="group"` with no accessible
                 name adds a level to walk through and says nothing at the top of it. --}}
            @if($callerLabelledBy) role="group" aria-labelledby="{{ $callerLabelledBy }}" @elseif($label) role="group" aria-label="{{ \Pushery\WireKit\Support\SlotContent::textOf($label) }}" @elseif($fieldLabelId) role="group" aria-labelledby="{{ $fieldLabelId }}" @endif
            {{-- Inside the layer's scope, which the component's own wrapper is
                 not: the layer nests within it, so `isPending` does not resolve
                 out there. --}}
            @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
        >
            {{-- Tag chips.
                 `display: contents` so the chips stay flex items of the container they
                 look like they are in — the list wrapper exists for the accessibility
                 tree, which reads the DOM and keeps a role whose box was removed from
                 layout. The same technique the optimistic wrapper below uses, and for
                 the same reason: a structural element that must not become a layout one. --}}
            <span role="list" style="display: contents">
            <template x-for="(tag, i) in tags" :key="'tag-'+i">
                <span role="listitem" class="{{ $tagClasses }}">
                    {{-- `anywhere`, so a tag with no break point, an email address, counts as breakable
                         in its chip's width: the chip shrinks to the row and wraps the tag inside. --}}
                    <span x-text="tag" class="[overflow-wrap:anywhere]"></span>
                    {{-- No remove button on a read-only set: removing is no action there. --}}
                    @unless($readonly)
                    <button
                        type="button"
                        {{-- How the component finds the remaining chips after one is
                             removed. Removing a chip takes the focused button out of
                             the document, so focus has to be placed on a sibling
                             rather than left to fall back to the page; a marker
                             attribute is what makes that sibling addressable without
                             the factory knowing anything about these classes.

                             24px square, the WCAG 2.2 target size, as on the pills of
                             multi-select; the negative block margin keeps the chip's height. --}}
                        data-wk-tag-remove
                        @if($disabled) disabled @endif
                        @click="removeTag(i)"
                        :aria-label="{{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Remove :name')) }}.replace(':name', tag)"
                        class="-my-[3px] inline-flex size-[var(--size-wk-target-min)] shrink-0 items-center justify-center rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-hover)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:cursor-not-allowed transition-colors cursor-pointer"
                    >
                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 12 12" fill="currentColor"><path d="M3.05 3.05a.5.5 0 01.7 0L6 5.29l2.25-2.24a.5.5 0 01.7.7L6.71 6l2.24 2.25a.5.5 0 01-.7.7L6 6.71 3.75 8.95a.5.5 0 01-.7-.7L5.29 6 3.05 3.75a.5.5 0 010-.7z"/></svg>
                    </button>
                    @endunless
                </span>
            </template>
            </span>

            {{--
                Text input for new tags — carries its own `px-2` padding so the
                placeholder text reads as comfortably indented from the input-
                box border (matching a regular `<x-wirekit::input>`), without
                inflating the OUTER container's gutter and pushing the tag
                chips away from the border. The container stays tight (`p-1`)
                so chips hug the edge; this `px-2` only affects the text-input
                slot, giving the empty-state placeholder its expected
                breathing room.
            --}}
            <input
                {{-- Key presses of an input method composing text stop here, before the keys below (utils/ime.js). --}}
                x-wk-ime
                type="text"
                id="{{ $fieldId }}"
                x-ref="input"
                {{-- The browser's own suggestions would open over the field where a tag is typed. --}}
                autocomplete="off"
                placeholder="{{ \Pushery\WireKit\Support\SlotContent::textOf($placeholder) }}"
                {{ $fieldAttributes }}
                {{-- When no visible <label> is rendered (no `label` prop), the
                     type-a-tag input would have no accessible name — a
                     placeholder is not a name (WCAG 2.1 AA / axe `label`).
                     Fall back to the placeholder as the aria-label so the
                     control is always named, to the default placeholder
                     where a blank one was given; when a label IS present the
                     <label for> above owns the name and we must NOT override
                     it with aria-label. The same holds for a caller's `id`: it
                     is there so a label of the caller's can reach the field, and
                     an aria-label would win over that label, so the fallback
                     steps aside and the placeholder names the field only when
                     nothing else does. --}}
                @if($callerLabelledBy) aria-labelledby="{{ $callerLabelledBy }}" @elseif(filled(\Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label'))) aria-label="{{ \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label') }}" @elseif(! filled($label) && ! $callerId && ! $fieldLabelId) aria-label="{{ \Pushery\WireKit\Support\BlankName::first($placeholder, config('wirekit.components.tags-input.placeholder'), __('wirekit::Add a tag…')) }}" @endif
                @if($hasError)
                    aria-invalid="true"
                @elseif($requiredMessageId)
                    x-bind:aria-invalid="requiredMessage !== '' && requiredValue === '' ? 'true' : null"
                @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                {{-- On the field a tag is typed into, the element a reader lands on: ARIA does not
                     allow `aria-required` on the group around the chips, nor on a plain `div`. --}}
                @if($required || $fieldRequired) aria-required="true" @endif
                @if($disabled) disabled @endif
                {{-- A full set is announced as unavailable rather than silently ignoring
                     keys. `aria-disabled` and not `disabled`: a disabled field leaves the
                     tab order, so the reader arrives at a control that has vanished and is
                     never told why — while `readonly` is what actually stops the typing.
                     `null` rather than `false`, because an aria attribute set to the string
                     "false" is a statement, and the statement here is that there is none. --}}
                x-bind:aria-disabled="atMaxTags ? 'true' : null"
                {{-- A read-only set is read-only for good. Bound, `readonly` would come off again
                     whenever the set is not full. --}}
                @if($readonly) readonly @else x-bind:readonly="atMaxTags" @endif
                @keydown.enter.prevent="addTag()"
                @keydown.comma.prevent="addTag()"
                {{-- A comma that arrives without its key (a paste, an on-screen keyboard) ends a tag too. --}}
                @input="onInput($event)"
                @compositionend="onInput($event)"
                {{-- A list pasted one entry to a line, which the one-line field would run together. --}}
                @paste="onPaste($event)"
                @keydown.backspace="onBackspace($event)"
                @keydown.escape="onEscape($event)"
                {{-- `w-0`: the input grows into the row through `flex-1`, and asks for no more than its
                     minimum. Without it WebKit takes a text input's default width as its minimum, and
                     a framed field 258px wide stood 13px past its container. --}}
                class="wk-field flex-1 w-0 min-w-[80px] px-2 bg-transparent text-[color:var(--color-wk-text)] text-[length:var(--text-wk-md)] placeholder:text-[color:var(--color-wk-text-placeholder)] focus-visible:outline-hidden disabled:cursor-not-allowed"
            />
        </div>
@if($optimisticConfig)
        {{-- Inside the layer, or `announcement` does not resolve — the region
             would render empty forever and the component would look wired.
             Unconditional and starting empty: a live region that arrives
             together with its text is a new node and announces nothing. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
        </div>
@endif
        {{-- Inside the component's root, where its state is: the error and the hint below stand
             outside it. --}}
        @if($requiredMessageId)
            @include('wirekit::components.partials.required-message', ['requiredMessageId' => $requiredMessageId])
        @endif
    </div>

    @if($hasError && $errorMessage)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
