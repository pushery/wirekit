{{-- Recipe: Feature with Numbered Marker — incremental-counter affordance on a feature card.
     Full reference: https://docs.wirekit.app/blueprints/recipes/feature-numbered-marker --}}
<div>
    <x-wirekit::feature-grid cols="3">
        @foreach([
            ['n' => '01', 'title' => 'Install', 'lede' => 'composer require pushery/wirekit'],
            ['n' => '02', 'title' => 'Configure', 'lede' => 'php artisan wirekit:install'],
            ['n' => '03', 'title' => 'Build', 'lede' => 'Drop components into your Blade views'],
        ] as $step)
            <x-wirekit::feature>
                {{-- `iconSlot`, not `eyebrow`. `feature` has no slot by that name, and Blade
                     DISCARDS an unknown named slot silently — the step numbers this recipe is
                     named after rendered nowhere at all. `iconSlot` is the marker position
                     above the title, and its own comment calls it the escape hatch for exactly
                     this: a caller-supplied badge instead of an icon. --}}
                <x-slot:iconSlot>
                    <span class="font-mono text-[length:var(--text-wk-2xl)] text-[color:var(--color-wk-accent)]">{{ $step['n'] }}</span>
                </x-slot:iconSlot>
                <x-slot:title>{{ $step['title'] }}</x-slot:title>
                {{ $step['lede'] }}
            </x-wirekit::feature>
        @endforeach
    </x-wirekit::feature-grid>
</div>
