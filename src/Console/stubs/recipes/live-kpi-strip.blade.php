{{-- Recipe: Live KPI Strip — wire:poll refreshes the dashboard's headline metrics.
     Full reference: https://docs.wirekit.app/recipes/live-kpi-strip
     Pair this view with the matching Livewire class — refreshKpis() updates $kpis. --}}
<div wire:poll.30s>
    <x-wirekit::row>
        @foreach($kpis ?? [] as $kpi)
            <x-wirekit::stat
                :label="$kpi['label']"
                :value="$kpi['value']"
                :delta="$kpi['delta'] ?? null"
                animate
            />
        @endforeach
    </x-wirekit::row>
</div>
