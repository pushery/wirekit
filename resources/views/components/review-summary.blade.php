{{-- optimistic-ui: n/a — navigation
     Its only interactive elements are the filter links of the distribution, each leading to the
     address that lists the reviews with that many stars. --}}
@props([
    // The mean score, as `rating` shows one. Null while there are no reviews.
    'average' => null,
    // The number of reviews. Null takes the sum of the distribution. It may be larger, when reviews
    // without stars are counted.
    'count' => null,
    // Stars => number of reviews, `[5 => 82, 4 => 30, 3 => 9, 2 => 4, 1 => 3]`. A step that is missing
    // counts 0.
    'distribution' => [],
    // The top of the scale, as on `rating`.
    'max' => 5,
    // Stars => the address that lists the reviews with that many stars. A row with one is a link.
    'filters' => [],
    // The stars the list is filtered by, marked on its row.
    'current' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\ListProp;
    use Pushery\WireKit\Support\LocalizedNumber;
    use Pushery\WireKit\Support\NumericProp;
    use Pushery\WireKit\Support\Plural;
    use Pushery\WireKit\Support\SafeUrl;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('review-summary', $attributes->getAttributes());

    $max = max(1, min(10, \Pushery\WireKit\Support\NumericProp::int($max, 0, 'review-summary', 'max')));

    // The distribution, one count per step from the top down. A key outside the scale or a count
    // that is not a whole number of zero or more is reported and left out, so a bar never stands
    // for something nobody said.
    $counts = array_fill(1, $max, 0);
    $given = ListProp::from($distribution, 'review-summary', 'distribution');

    foreach (is_array($given) ? $given : [] as $stars => $n) {
        $whole = NumericProp::intOrNull($n);
        $valid = is_int($stars) && $stars >= 1 && $stars <= $max && $whole !== null && $whole == $n && $whole >= 0;

        if (! $valid) {
            StrictnessGate::reject('review-summary', 'distribution', is_scalar($n) ? $stars.' => '.NumericProp::text($n) : $stars.' => '.get_debug_type($n), 'stars from 1 to '.$max.' with a whole number of reviews', '');

            continue;
        }

        $counts[$stars] = $whole;
    }

    krsort($counts);
    $sum = array_sum($counts);
    $countTotal = NumericProp::intOrNull($count, 'review-summary', 'count');
    $total = $countTotal !== null && $countTotal >= 0 ? $countTotal : $sum;

    $links = ListProp::from($filters, 'review-summary', 'filters');
    $links = is_array($links) ? $links : [];
    $current = NumericProp::intOrNull($current, 'review-summary', 'current');

    // The average, or nothing to show while there are no reviews: "0.0 out of 5" would say every
    // reviewer gave nothing.
    $averageNumber = \Pushery\WireKit\Support\NumericProp::orNull($average, 'review-summary', 'average');
    $hasAverage = $averageNumber !== null && $total > 0;
    $score = $hasAverage ? max(0.0, min((float) $averageNumber, (float) $max)) : null;

    $rows = [];

    foreach ($counts as $stars => $n) {
        // An address the browser would run as script, `javascript:` and the like, leaves the row
        // as text rather than a link.
        $href = $links[$stars] ?? null;
        $href = is_string($href) || $href instanceof Stringable ? SafeUrl::href($href) : '';

        $rows[] = [
            'stars' => $stars,
            'count' => $n,
            // The bar's share of the reviews with stars, so the bars add up to the whole.
            'share' => $sum > 0 ? round($n / $sum * 100, 1) : 0.0,
            'href' => $href !== '' ? $href : null,
            'current' => $current === $stars,
            'sentence' => __('wirekit:::stars: :reviews', [
                'stars' => Plural::choice('wirekit:::count star|:count stars', $stars, ['count' => $stars]),
                'reviews' => Plural::choice('wirekit::{1} :count review|[2,*] :count reviews', $n, ['count' => LocalizedNumber::format((float) $n)]),
            ]),
        ];
    }

    $classes = WireKit::resolveClasses('review-summary', 'base', implode(' ', [
        'flex flex-col gap-[var(--gap-wk-md)]',
        'font-[family-name:var(--font-wk-sans)] text-[color:var(--color-wk-text)]',
    ]), $scope);

    $rowClasses = WireKit::resolveClasses('review-summary', 'row', implode(' ', [
        'grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-[var(--gap-wk-sm)]',
        'rounded-[var(--radius-wk-sm)] px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)]',
        'text-[length:var(--text-wk-sm)]',
    ]), $scope);

    // A filter row is the target of its filter: on a touch surface it stands 44px tall
    // (`wk-touch-row`), as the rows of a menu do, so the stacked rows never share a target. The
    // chosen row has an outline as well as its fill, so it does not rest on color.
    $linkClasses = implode(' ', [
        'wk-touch-row wk-focus-ring cursor-pointer hover:bg-[var(--color-wk-bg-hover)]',
        'data-[active]:bg-[var(--color-wk-bg-active)] data-[active]:outline-[length:var(--border-wk-width)] data-[active]:outline-[color:var(--color-wk-border-strong)]',
    ]);
@endphp

{{-- The rows hold visually hidden text, so the root is positioned to keep it inside (data-wk-sr-host). --}}
<div data-wk-review-summary data-wk-sr-host {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    <div data-wk-review-summary-score class="flex flex-wrap items-center gap-x-[var(--gap-wk-md)] gap-y-[var(--gap-wk-xs)]">
        @if($hasAverage)
            {{-- The number repeats the stars' image name, which says it with its scale. --}}
            <span aria-hidden="true" class="text-[length:var(--text-wk-3xl)] font-[number:var(--font-wk-heading-weight)] leading-none tabular-nums">{{ LocalizedNumber::format($score, precision: 1) }}</span>
            <span class="flex flex-col gap-[var(--gap-wk-xs)]">
                <x-wirekit::rating :value="$score" :max="$max" readonly />
                <span data-wk-review-summary-count class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ Plural::choice('wirekit::{1} :count review|[2,*] :count reviews', $total, ['count' => LocalizedNumber::format((float) $total)]) }}</span>
            </span>
        @else
            <span data-wk-review-summary-count class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ __('wirekit::No reviews yet') }}</span>
        @endif
    </div>

    @if($sum > 0)
        <ul data-wk-prose-skip data-wk-review-summary-distribution role="list" aria-label="{{ __('wirekit::Rating distribution') }}" class="m-0 flex list-none flex-col gap-[var(--gap-wk-xs)] p-0" style="list-style: none; margin: 0; padding: 0;">
            @foreach($rows as $row)
                <li data-wk-prose-skip class="m-0 p-0">
                    @if($row['href'] !== null)
                        <a data-wk-prose-skip href="{{ $row['href'] }}" data-wk-review-summary-row @if($row['current']) aria-current="true" data-active @endif class="{{ $rowClasses }} {{ $linkClasses }} text-[color:var(--color-wk-text)] no-underline">
                    @else
                        <span data-wk-review-summary-row class="{{ $rowClasses }}">
                    @endif
                        <span class="sr-only">{{ $row['sentence'] }}</span>
                        <span aria-hidden="true" class="inline-flex items-center gap-[var(--gap-wk-xs)] tabular-nums">{{ LocalizedNumber::format((float) $row['stars']) }}<x-wirekit::icon name="star" size="xs" class="text-[color:var(--color-wk-text-muted)]" /></span>
                        {{-- The share rides in an inline style; where a policy drops it, the bar stays empty
                             rather than full, and the number beside it still says how many. --}}
                        <span aria-hidden="true" class="h-2 overflow-hidden rounded-[var(--radius-wk-full)] bg-[var(--color-wk-bg-muted)]">
                            <span data-wk-review-summary-bar class="block h-full w-0 rounded-[var(--radius-wk-full)] bg-[var(--color-wk-text)]" style="width: {{ $row['share'] }}%"></span>
                        </span>
                        <span aria-hidden="true" class="min-w-[3ch] text-end tabular-nums text-[color:var(--color-wk-text-muted)]">{{ LocalizedNumber::format((float) $row['count']) }}</span>
                    @if($row['href'] !== null)
                        </a>
                    @else
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if(\Pushery\WireKit\Support\SlotContent::given($notice ?? null))
        <div data-wk-review-summary-notice class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)] [overflow-wrap:anywhere]">{{ $notice }}</div>
    @endif

    @if(\Pushery\WireKit\Support\SlotContent::given($actions ?? null))
        <div data-wk-review-summary-actions class="flex flex-wrap gap-[var(--gap-wk-sm)]">{{ $actions }}</div>
    @endif
</div>
