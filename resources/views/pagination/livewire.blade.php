{{-- The pager a Livewire component renders for links() when its paginationView() returns this
     view. Livewire passes the paginator in, and a scroll target when links() was given one. --}}
<x-wirekit::pagination :paginator="$paginator" livewire :scroll-to="$scrollTo ?? 'body'" />
