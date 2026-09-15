{{-- The pager a Livewire component renders for links() on a simple or cursor paginator when its
     paginationSimpleView() returns this view. Previous and next are all such a paginator can drive. --}}
<x-wirekit::pagination :paginator="$paginator" variant="mini" livewire :scroll-to="$scrollTo ?? 'body'" />
