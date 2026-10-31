{{-- Recipe: Live KPI Strip — wire:poll refreshes the dashboard's headline metrics.
     Full reference: https://docs.wirekit.app/blueprints/recipes/live-kpi-strip
     Pair this view with the matching Livewire class. It needs a public $kpis array, one entry per
     ticker with a label, a value and optionally a delta and an intent, and a refreshKpis() method,
     which the poll calls every 30 seconds to update $kpis. --}}
<div wire:poll.30s="refreshKpis">
    {{-- align="start" keeps the tickers top-aligned when some carry a delta and others do not. --}}
    <x-wirekit::row gap="md" :wrap="true" align="start">
        @foreach ($kpis ?? [] as $kpi)
            <x-wirekit::ticker
                :label="$kpi['label']"
                :value="$kpi['value']"
                :delta="$kpi['delta'] ?? null"
                :intent="$kpi['intent'] ?? null"
                size="sm"
            />
        @endforeach
    </x-wirekit::row>
</div>
