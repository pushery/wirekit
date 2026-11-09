{{-- Recipe: Stat with Sparkline — a KPI with its change, an inline trend line and a citation.
     Full reference: https://docs.wirekit.app/blueprints/recipes/stat-with-sparkline

     The trend line is the sparkline component, which colors itself by the direction of its
     series; give it `trend="up"` where falling is good. Build `$points` in your Livewire class
     from the last 7 or 30 values. The citation says where the number comes from. --}}
<div>
    <x-wirekit::stat
        label="Total Events"
        value="2.4M"
        change="+12.3%"
        trend="up"
        citation="Source: events table (snapshot {{ now()->format('Y-m-d') }})"
    >
        <x-slot:sparkline>
            <x-wirekit::sparkline :data="$points ?? [6, 8, 10, 14, 12, 16, 18, 20, 22]" aria-hidden="true" />
        </x-slot:sparkline>
    </x-wirekit::stat>
</div>
