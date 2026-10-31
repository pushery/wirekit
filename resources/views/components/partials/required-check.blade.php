{{-- optimistic-ui: n/a — sub-component
     A partial of the form components that send through a hidden field. It changes no value. --}}
{{-- The stand-in that lets such a component stop an empty submit when it is required: the browser
     validates no hidden field. Included with:

       $requiredFormOwner   the form the component joins through `form`, or null
       $requiredDisabled    whether the component is disabled or read-only; neither is validated

     No name, so it is never sent. It carries `required` and the component's `requiredValue`, which
     is empty exactly while the component has no value. It is out of the tab order and hidden from
     assistive technology, which hears the requirement from the control's own `aria-required`. Its
     `invalid` event is handled in the component (resources/js/utils/required-check.js): the
     browser's message goes into the component, and the focus onto its control.

     It waits for the script. Only the script gives it a value, so until Alpine starts it is
     `disabled`, which takes it out of the browser's check: a page whose script never runs
     would otherwise stop every submit of its form, the component's value or not, at a field
     nobody can see. The server checks `required` either way. A disabled or read-only component
     keeps it disabled with the script as well: the browser bars a disabled field and a read-only
     one from the check, and an empty read-only component, which nobody can fill, would otherwise
     hold the whole form. --}}
<input
    type="text"
    tabindex="-1"
    aria-hidden="true"
    autocomplete="off"
    required
    data-wk-required-check
    class="sr-only"
    x-bind:value="requiredValue"
    x-on:invalid="onRequiredInvalid($event)"
    @if(filled($requiredFormOwner ?? null)) form="{{ $requiredFormOwner }}" @endif
    disabled
    x-bind:disabled="{{ ($requiredDisabled ?? false) ? 'true' : 'false' }}"
/>
