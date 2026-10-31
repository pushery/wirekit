{{-- Recipe: Validating a Phone Number — the field, and the server decision behind it.
     Full reference: https://docs.wirekit.app/blueprints/recipes/phone-number-validation

     The field binds E.164 (`+4915123456789`) while showing a number the way its reader would
     write it down. Those are two different strings on purpose, and `wire:model` receives the
     first one.

     Whether that number is real is not decided here. The field never decides it: the data behind
     that answer is several megabytes, and a control that quietly rejects a valid number is worse
     than one that never claims to know. The reference above carries the validation rule, one
     `composer require`, and the same library formatting a stored value back for display.

     Your Livewire class needs two members for this view: a public `$phone` property, which
     receives the E.164 string, and a `save()` method, which the form submits to and which runs
     the validation. --}}
<div>
    <x-wirekit::form wire:submit="save">
        <x-wirekit::stack gap="md">
            <x-wirekit::phone
                name="phone"
                label="Phone number"
                :value="$phone ?? null"
                wire:model="phone"
                hint="We only use this to confirm a delivery."
                required
            />
            <x-wirekit::button type="submit">Save</x-wirekit::button>
        </x-wirekit::stack>
    </x-wirekit::form>
</div>
