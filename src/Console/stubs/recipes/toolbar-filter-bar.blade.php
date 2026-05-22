{{-- Recipe: Toolbar Filter Bar — search input + select filters + reset button above a data table.
     Full reference: https://docs.wirekit.app/recipes/toolbar-filter-bar --}}
<div>
    <x-wirekit::toolbar>
        <x-wirekit::input
            wire:model.live.debounce.300ms="search"
            placeholder="Search products"
            aria-label="Search products"
        />
        <x-wirekit::select
            wire:model.live="status"
            aria-label="Filter by status"
        >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
        </x-wirekit::select>
        <x-wirekit::select
            wire:model.live="category"
            aria-label="Filter by category"
        >
            <option value="">All categories</option>
            <option value="hardware">Hardware</option>
            <option value="software">Software</option>
        </x-wirekit::select>
        <x-wirekit::button
            wire:click="resetFilters"
            intent="neutral"
            surface="filled"
        >Reset</x-wirekit::button>
    </x-wirekit::toolbar>

    {{-- Drop your filtered <x-wirekit::table> below. --}}
</div>
