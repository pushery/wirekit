{{-- optimistic-ui: n/a — sub-component
     A partial of the form components that send through a hidden field. It changes no value. --}}
{{-- The stand-in that lets such a component stop an empty submit when it is required: the browser
     validates no hidden field. Included with:

       $requiredFormOwner   the form the component joins through `form`, or null
       $requiredDisabled    whether the component is disabled; a disabled field is not validated

     No name, so it is never sent. It carries `required` and the component's `requiredValue`, which
     is empty exactly while the component has no value. It is out of the tab order and hidden from
     assistive technology, which hears the requirement from the control's own `aria-required`. Its
     `invalid` event is handled in the component (resources/js/utils/required-check.js): the
     browser's message goes into the component, and the focus onto its control. --}}
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
    @if($requiredDisabled ?? false) disabled @endif
/>
