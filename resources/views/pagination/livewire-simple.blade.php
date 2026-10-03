{{-- The pager a Livewire component renders for links() on a simple or cursor paginator when its
     paginationSimpleView() returns this view. Previous and next are all such a paginator can drive.

     Blade escapes the bound paginator below by rendering it when it can be cast to a string, which
     a cursor paginator can, and it renders through Paginator::$defaultSimpleView: this very view,
     while the Livewire component lives. For the moment of that escape the default is a view that
     renders nothing, so the binding neither renders this view again without end nor renders a
     pager only to throw it away. --}}
@php
    $__wkSimpleView = \Illuminate\Pagination\Paginator::$defaultSimpleView;
    \Illuminate\Pagination\Paginator::defaultSimpleView('wirekit::pagination.discarded');
    try {
@endphp
<x-wirekit::pagination :paginator="$paginator" variant="mini" livewire :scroll-to="$scrollTo ?? 'body'" />
@php
    } finally {
        \Illuminate\Pagination\Paginator::defaultSimpleView($__wkSimpleView);
    }
@endphp
