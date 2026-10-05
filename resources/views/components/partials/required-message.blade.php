{{-- optimistic-ui: n/a — sub-component
     A partial of the form components that send through a hidden field. It shows a message. --}}
{{-- The message of a required component that a submit or a validity check found empty: the
     browser's own message for the stand-in (partials/required-check), in the reader's language.
     Included with:

       $requiredMessageId     the id the control names in its aria-describedby
       $requiredMessageClass  classes for where it stands, or null; inside a flex row,
                              `basis-full` puts it on a line of its own
       $requiredMessageBound  false where it stands outside the component's scope, as under the
                              editor's frame: then it carries no expression, and the component
                              writes its text and shows it itself

     Shown while the component is still empty. The control is described by it, so the message is
     read when the focus arrives there, which is where the check puts it. It starts hidden, so
     nothing shows before the component's script has run. --}}
<p
    data-wk-prose-skip
    data-wk-required-message
    id="{{ $requiredMessageId }}"
    @if($requiredMessageBound ?? true)
        x-show="requiredMessage !== '' && requiredValue === ''"
        x-text="requiredMessage"
        style="display: none"
    @else
        hidden
    @endif
    class="{{ trim('text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)] '.($requiredMessageClass ?? '')) }}"
></p>
