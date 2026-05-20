{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
@props([
    // `tier` — which `--padding-wk-x-*` token to read. Default `lg`
    // (the canonical page-edge spine). Pass `sm` / `md` / `xl` for a
    // different inline-padding tier. The spine-participant convention
    // applies to the `lg` tier — other tiers are valid but produce a
    // visually-distinct left edge from brand-bar / main / footer.
    'tier' => 'lg',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Resolve the spine-padding utility via the canonical helper so
    // developer-authored opt-in components stay in lockstep with the
    // first-party spine participants (brand-bar / main / container /
    // header / footer / cta / hero).
    $spineClass = WireKit::spinePadding($tier);

    $rootClass = WireKit::resolveClasses('spine-aware', 'base', implode(' ', [
        'wk-spine-aware',
        'w-full',
        $spineClass,
    ]), $scope);
@endphp

<div {{ $attributes->class([$rootClass]) }}>
    {{ $slot }}
</div>
