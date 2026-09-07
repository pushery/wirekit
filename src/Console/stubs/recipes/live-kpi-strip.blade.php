{{-- Recipe: Live KPI Strip — wire:poll refreshes the dashboard's headline metrics.
     Full reference: https://docs.wirekit.app/blueprints/recipes/live-kpi-strip
     Pair this view with the matching Livewire class — refreshKpis() updates $kpis. --}}
<div wire:poll.30s>
    <x-wirekit::row>
        @foreach($kpis ?? [] as $kpi)
            <x-wirekit::ticker
                :label="$kpi['label']"
                :value="$kpi['value']"
                :delta="$kpi['delta'] ?? null"
                size="sm"
            />
        @endforeach
    </x-wirekit::row>
</div>
