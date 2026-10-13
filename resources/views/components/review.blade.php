{{-- optimistic-ui: n/a — presentational
     Renders no interactive element of its own: what a reader can do with a review stands in the
     actions slot, and what it does is the caller's. --}}
@props([
    // The review's score, as `rating` shows one.
    'rating' => 0,
    // The top of the scale, as on `rating`.
    'max' => 5,
    // The title, the review's heading.
    'title' => null,
    // The reviewer's display name.
    'author' => null,
    // When it was written: a date, a string Carbon reads, or a Unix timestamp.
    'date' => null,
    // A purchase the shop has verified: a badge says so in words.
    'verified' => false,
    // The heading level of the title, the one the page's outline needs.
    'level' => 3,
    // Who replied for the shop, and when. The reply itself is the `reply` slot.
    'replyAuthor' => null,
    'replyDate' => null,
    // The zone the dates are written in. Null keeps each date's own.
    'timezone' => null,
    'scope' => null,
])

@php
    use Carbon\CarbonImmutable;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\LocalizedDate;
    use Pushery\WireKit\Support\Moment;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('review', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    $verified = BooleanProp::from($verified, false);

    $max = max(1, min(10, (int) $max));
    $score = max(0.0, min((float) $rating, (float) $max));
    $level = (int) WireKit::validateProp('review', 'level', (string) $level, ['2', '3', '4', '5', '6']);

    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id'), 'review-'); // page-unique DOM id; see Support\DomId

    // A date of any class is read as the instant it holds (Support\Moment), in the zone asked for,
    // and written as the locale writes a date: "Sep 28, 2026", "28. Sept. 2026".
    $moment = static fn (mixed $value): ?CarbonImmutable => ($read = Moment::of($value)) === null ? null : Moment::in($read, $timezone, 'review');
    $written = $moment($date);
    $replied = $moment($replyDate);
    $dateText = static fn (CarbonImmutable $value): string => LocalizedDate::bySkeleton($value, 'yMMMd', 'M j, Y');

    $hasReply = isset($reply) && $reply->hasActualContent();
    $replyName = filled($replyAuthor) ? __('wirekit::Reply from :name', ['name' => $replyAuthor]) : __('wirekit::Reply from the shop');

    // The article is named by its heading, or by its author where it has no title.
    $labelledBy = filled($title) ? $id.'-title' : (filled($author) ? $id.'-author' : null);

    $classes = WireKit::resolveClasses('review', 'base', implode(' ', [
        'flex flex-col gap-[var(--gap-wk-sm)]',
        'font-[family-name:var(--font-wk-sans)] text-[color:var(--color-wk-text)]',
    ]), $scope);

    $metaClasses = 'flex flex-wrap items-center gap-x-[var(--gap-wk-sm)] gap-y-[var(--gap-wk-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]';
@endphp

<article data-wk-review {{ $attributes->except('id')->class([$classes]) }} id="{{ $id }}" @if($labelledBy && ! $attributes->has('aria-label') && ! $attributes->has('aria-labelledby')) aria-labelledby="{{ $labelledBy }}" @endif>
    <x-wirekit::rating :value="$score" :max="$max" readonly size="sm" />

    @if(filled($title))
        <x-wirekit::heading :level="$level" size="sm" break="anywhere" id="{{ $id }}-title">{{ $title }}</x-wirekit::heading>
    @endif

    @if(filled($author) || $written || $verified)
        <p data-wk-prose-skip data-wk-review-meta class="m-0 {{ $metaClasses }}">
            @if(filled($author))
                <span id="{{ $id }}-author" class="font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] [overflow-wrap:anywhere]">{{ $author }}</span>
            @endif
            @if($written)
                <time datetime="{{ $written->toIso8601String() }}">{{ $dateText($written) }}</time>
            @endif
            @if($verified)
                <x-wirekit::badge intent="success" size="sm">{{ __('wirekit::Verified purchase') }}</x-wirekit::badge>
            @endif
        </p>
    @endif

    @if($slot->hasActualContent())
        <div data-wk-review-body class="text-[length:var(--text-wk-sm)] [overflow-wrap:anywhere]">{{ $slot }}</div>
    @endif

    @if($hasReply)
        {{-- The shop's reply is an article of its own, named by who wrote it, set off by a line at
             its start edge so its indent reads in either direction. --}}
        <article data-wk-review-reply aria-labelledby="{{ $id }}-reply" class="ms-[var(--space-wk-md)] flex flex-col gap-[var(--gap-wk-xs)] border-s-[length:var(--border-wk-width)] border-[var(--color-wk-border)] ps-[var(--padding-wk-x-md)]">
            <p data-wk-prose-skip class="m-0 {{ $metaClasses }}">
                <span id="{{ $id }}-reply" class="font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] [overflow-wrap:anywhere]">{{ $replyName }}</span>
                @if($replied)
                    <time datetime="{{ $replied->toIso8601String() }}">{{ $dateText($replied) }}</time>
                @endif
            </p>
            <div class="text-[length:var(--text-wk-sm)] [overflow-wrap:anywhere]">{{ $reply }}</div>
        </article>
    @endif

    @isset($actions)
        <div data-wk-review-actions class="flex flex-wrap items-center gap-[var(--gap-wk-sm)]">{{ $actions }}</div>
    @endisset
</article>
