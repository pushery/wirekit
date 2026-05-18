@props([
    'emoji' => null,
    'count' => 0,
    'active' => false,
    'users' => [],
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $userList = is_array($users) ? $users : [];
    $ariaLabel = $emoji . ', ' . trans_choice('{0} no reactions|{1} :count person reacted|[2,*] :count people reacted', $count, ['count' => $count]);

    $baseClasses = WireKit::resolveClasses('reaction', 'base', implode(' ', [
        'inline-flex items-center gap-x-1',
        'h-7 px-2',
        'rounded-[var(--radius-wk-full)]',
        'border-[length:var(--border-wk-width)]',
        'text-[length:var(--text-wk-sm)]',
        'font-[family-name:var(--font-wk-sans)]',
        'transition-[background-color,border-color,box-shadow]',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'cursor-pointer select-none',
    ]), $scope);

    $stateClasses = $active
        ? implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-accent)_12%,var(--color-wk-bg))]',
            'border-[color-mix(in_srgb,var(--color-wk-accent)_35%,transparent)]',
            'text-[color:var(--color-wk-accent-content)]',
        ])
        : implode(' ', [
            'bg-[var(--color-wk-bg-muted)]',
            'border-transparent',
            'text-[color:var(--color-wk-text-muted)]',
            'hover:border-[var(--color-wk-border)]',
            'hover:bg-[var(--color-wk-bg-elevated)]',
        ]);
@endphp

<button
    type="button"
    aria-pressed="{{ $active ? 'true' : 'false' }}"
    aria-label="{{ $ariaLabel }}"
    @if(count($userList) > 0)
        aria-describedby="reaction-users-{{ md5($emoji . implode(',', $userList)) }}"
    @endif
    {{ $attributes->class([$baseClasses, $stateClasses]) }}
>
    <span class="font-[font-variant-emoji:emoji]" aria-hidden="true">{{ $emoji }}</span>
    @if($count > 0)
        <span class="font-[number:var(--font-wk-heading-weight)] tabular-nums">{{ $count }}</span>
    @endif
    @if(count($userList) > 0)
        <span id="reaction-users-{{ md5($emoji . implode(',', $userList)) }}" class="sr-only">
            {{ implode(', ', $userList) }}
        </span>
    @endif
</button>
