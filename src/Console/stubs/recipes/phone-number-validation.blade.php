{{-- Recipe: Validating a Phone Number — the field, and the server decision behind it.
     Full reference: https://docs.wirekit.app/blueprints/recipes/phone-number-validation

     The field binds E.164 (`+4915123456789`) while showing a number the way its reader would
     write it down. Those are two different strings on purpose, and `wire:model` receives the
     first one.

     What is NOT here is the decision whether that number is real. The field never makes it: the
     data behind it is several megabytes, and a control that quietly rejects a valid number is
     worse than one that never claims to know. The reference above carries the validation rule,
     one `composer require`, and the same library formatting a stored value back for display.

     Your Livewire class needs two members for this view: a public `$phone` property, which
     receives the E.164 string, and a `save()` method, which the form submits to. Without them
     the field renders and the button does nothing — the kind of silence that reads like a
     broken component rather than a missing method. --}}
<form wire:submit="save">
    <x-wirekit::phone
        name="phone"
        label="Phone number"
        wire:model="phone"
        :country-order="['DE', 'AT', 'CH']"
        hint="We only use this to confirm a delivery."
        required
    />

    <div class="mt-4">
        <x-wirekit::button type="submit">Save</x-wirekit::button>
    </div>
</form>
