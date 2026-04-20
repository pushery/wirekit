@props([
    'avatar' => null,
    'name' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Profile — avatar + name display for header areas.
    $classes = WireKit::resolveClasses('profile', 'base', implode(' ', [
        'flex items-center',
        'gap-[var(--gap-wk-sm)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    @if($avatar)
        <img src="{{ $avatar }}" alt="" class="h-8 w-8 rounded-full object-cover" />
    @endif
    @if($name)
        <span class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text)] font-[number:var(--font-wk-body-weight)]">{{ $name }}</span>
    @endif
    {{ $slot }}
</div>
