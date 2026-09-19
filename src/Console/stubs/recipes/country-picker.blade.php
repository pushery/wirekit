{{-- Recipe: Country Picker — a searchable country list with a flag in the panel and in the field.
     Full reference: https://docs.wirekit.app/blueprints/recipes/country-picker

     The options are spelled out here rather than read from a variable, so the scaffolded view
     renders the moment it is written. Swap the array for the list your Livewire class builds —
     the reference above shows how to derive it from `intl`, localized and sorted.

     The flag artwork ships with WireKit. A code it does not carry still renders its row, with a
     neutral placeholder of the same width where the flag would be. --}}
<div>
    <x-wirekit::combobox
        name="country"
        label="Country"
        wire:model="country"
        placeholder="Search countries"
        :options="[
            ['value' => 'DE', 'label' => 'Germany', 'flag' => 'de', 'keywords' => ['DE', 'Deutschland']],
            ['value' => 'FR', 'label' => 'France', 'flag' => 'fr', 'keywords' => ['FR']],
            ['value' => 'CH', 'label' => 'Switzerland', 'flag' => 'ch', 'keywords' => ['CH', 'Schweiz', 'Suisse']],
            ['value' => 'JP', 'label' => 'Japan', 'flag' => 'jp', 'keywords' => ['JP', 'Nippon']],
        ]"
    />
</div>
