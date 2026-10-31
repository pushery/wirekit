{{-- Recipe: Toolbar Filter Bar — the search on the left, the filter selects and the page's action on the right.
     Full reference: https://docs.wirekit.app/blueprints/recipes/toolbar-filter-bar
     Pair this view with the matching Livewire class. It binds $search, $status and $category,
     which the query behind your list reads to filter it, and lists the categories from a
     $categories collection. The action button dispatches open-add-product to whichever component
     opens the form. --}}
<div>
    <x-wirekit::toolbar aria-label="Product list filters">
        <x-wirekit::row gap="sm" align="center" style="flex: 1; max-width: 28rem">
            <x-wirekit::input
                name="search"
                type="search"
                placeholder="Search products..."
                size="sm"
                aria-label="Search products"
                wire:model.live.debounce.300ms="search"
            />
        </x-wirekit::row>
        <x-wirekit::row gap="sm" align="center">
            <x-wirekit::select name="status" size="sm" aria-label="Filter by status" wire:model.live="status">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="archived">Archived</option>
            </x-wirekit::select>
            <x-wirekit::select name="category" size="sm" aria-label="Filter by category" wire:model.live="category">
                <option value="">All categories</option>
                @foreach ($categories ?? [] as $category)
                    <option value="{{ $category->slug }}">{{ $category->name }}</option>
                @endforeach
            </x-wirekit::select>
            <x-wirekit::button size="sm" wire:click="$dispatch('open-add-product')">
                + Add Product
            </x-wirekit::button>
        </x-wirekit::row>
    </x-wirekit::toolbar>
</div>
