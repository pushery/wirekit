{{-- The pager a Livewire component renders for links() when its paginationView() returns this
     view. Livewire passes the paginator in, and a scroll target when links() was given one.

     Blade escapes the bound paginator below by rendering it when it can be cast to a string. A
     cursor paginator handed to this view with links('wirekit::pagination.livewire') can, and it
     renders through Paginator::$defaultSimpleView, which a Livewire component may have set to the
     simple pager. For the moment of that escape the default is a view that renders nothing, as in
     livewire-simple.blade.php. --}}
@php
    $__wkSimpleView = \Illuminate\Pagination\Paginator::$defaultSimpleView;
    \Illuminate\Pagination\Paginator::defaultSimpleView('wirekit::pagination.discarded');
    try {
@endphp
<x-wirekit::pagination :paginator="$paginator" livewire :scroll-to="$scrollTo ?? 'body'" />
@php
    } finally {
        \Illuminate\Pagination\Paginator::defaultSimpleView($__wkSimpleView);
    }
@endphp
