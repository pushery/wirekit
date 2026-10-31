{{-- optimistic-ui: n/a — presentational
     Not a component but an @include of `chart`, which renders its data with it; it holds no
     control and no state. --}}
{{-- A chart's data as a table (Charts\ChartDataTable): one row per label, named by a row header,
     one column per dataset, named by a column header, and the chart's name as the caption, so a
     screen reader that reaches the table on its own knows which chart it belongs to. The corner
     cell is a plain cell: a header cell with no words is an empty name. --}}
<table data-wk-prose-skip class="w-full border-collapse text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">
    {{-- Held at the start edge, as the chart's links are: hidden text there cannot reach past the
         far edge of the frame that scrolls a wide table, the only edge at which it would widen the
         page. --}}
    <caption data-wk-prose-skip class="sr-only start-0">{{ $wkCaption }}</caption>
    <thead data-wk-prose-skip>
        <tr data-wk-prose-skip>
            <td data-wk-prose-skip class="border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]"></td>
            @foreach ($wkTable['columns'] as $wkColumn)
                <th data-wk-prose-skip scope="col" class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-end font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">{{ $wkColumn }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody data-wk-prose-skip>
        @foreach ($wkTable['rows'] as $wkRow)
            <tr data-wk-prose-skip>
                <th data-wk-prose-skip scope="row" class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-start font-[number:var(--font-wk-body-weight)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border-subtle)]">{{ $wkRow['label'] }}</th>
                @foreach ($wkRow['cells'] as $wkCell)
                    <td data-wk-prose-skip class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-end tabular-nums border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border-subtle)]">{{ $wkCell }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
