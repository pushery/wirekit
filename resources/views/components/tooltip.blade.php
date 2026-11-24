{{-- optimistic-ui: n/a — client-only
     Its state is visibility and placement. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'text' => null,
    'placement' => config('wirekit.components.tooltip.placement', 'top'),
    'offset' => config('wirekit.components.tooltip.offset', 6),
    'delayShow' => config('wirekit.components.tooltip.delay-show', 300),
    'delayHide' => config('wirekit.components.tooltip.delay-hide', 100),
    // Whether the trigger is a tab stop of its own, so a tooltip on something that is not a
    // control (an icon, a word) is reachable by keyboard and not by hover alone (WCAG 2.1.1).
    // `null`, the default, reads the slot: a slot that already holds a control is the stop a
    // reader lands on, and its focus reaches the trigger and shows the tooltip, so the trigger
    // stays out of the tab order rather than becoming a second stop with no name. `true` and
    // `false` from the caller are kept as they are.
    'focusableTrigger' => null,
    // `false` when the tooltip text is also the trigger's accessible name, as on an icon-only
    // control named by its tooltip. Describing a control with its own name makes a screen
    // reader say it twice ("Fast mode, button, Fast mode"), so the pairing is left out. Where
    // that name is in the slot, as its words or its `aria-label`, the component leaves the
    // pairing out by itself; this is for a name it cannot read there, such as one given through
    // `aria-labelledby` or bound by Alpine.
    'describes' => true,
    // Switch the tooltip off without removing it. A tooltip on a control that
    // has become inert — a collapsed sidebar item, a disabled action — has to be
    // able to go quiet, and `pointer-events-none` on the root is not a way to do
    // that however much it looks like one: `mouseenter` is delivered to every
    // ancestor of the element actually hit, whatever their own pointer-events
    // value, so the handler fires and the panel appears over something that is
    // supposed to be dead.
    //
    // Rendered as an attribute rather than passed into the factory, so a call
    // site can bind it — `x-bind:data-wk-tooltip-disabled="collapsed"` — and get
    // a tooltip that follows live state. The component reads it at trigger time.
    'disabled' => false,
    // The panel's surface. `inverted` is the classic tooltip, the text color as the background and
    // the page color as the text, so it stands out on either theme. `elevated` takes the surface
    // menus and popovers use: the elevated background, the body text and the theme's border.
    'surface' => 'inverted',
    // A keyboard shortcut drawn after the text, as the `kbd` component draws its `shortcut`: ⌘ S
    // on a Mac and Ctrl S elsewhere, named in the reader's language. Unset, the tooltip draws the
    // shortcut the control in its slot carries in `x-wk-shortcut`; `false` draws none. A
    // single-key shortcut is drawn only while single keys work.
    'shortcut' => null,
    // Draw into one panel the page's tooltips share instead of a panel of its own, which each
    // tooltip otherwise carries in its markup. Only a tooltip of words alone shares: one with a
    // content slot, a shortcut or the words of a cut text keeps its own panel, which draws them.
    // The words then stand once, in an element the trigger is described by, and the shared panel
    // shows them while the tooltip is open. Off by default; `components.tooltip.shared` in
    // `config/wirekit.php` turns it on for every tooltip.
    'shared' => config('wirekit.components.tooltip.shared', false),
    // The id of an element on the page that already holds the tooltip's words, such as the hidden
    // help text a field is described by. A shared tooltip is described by it and shows its words,
    // so they are not written a second time. Without it, a shared tooltip writes its own.
    'textFrom' => null,
    // Whether a panel of the tooltip's own waits out of the page until the tooltip first shows.
    // The trigger is described from the start all the same, by the tooltip's words in a hidden
    // element beside it. A shared tooltip draws into the page's one panel and has none of its own
    // to hold back. The default comes from the configuration (`components.tooltip.lazy`).
    'lazy' => config('wirekit.components.tooltip.lazy', false),
    'scope' => null,
    // What the host and the trigger wrapper root in. `div` is the default; `as="span"` is
    // what makes this component usable inside a
    // text-level one.
    //
    // It has to move both elements, and that is the whole reason it is one prop rather
    // than two. A `<div>` inside a `<p>` is invalid HTML, so the parser closes the paragraph
    // and hoists the div out — the trigger stops being a child of its text node and becomes
    // its sibling. In a grid that sibling gets a cell of its own, which is how a warning
    // glyph ends up next to the wrong row. Nothing about that is red: the Blade reads
    // correctly, both markers are in the output so every assertion passes, and there is no
    // overflow and no console error for a screen sweep to catch.
    //
    // The panel is unaffected either way — it lives in a `<template x-teleport>`, and a
    // `<template>` is phrasing content, so it is valid inside a `<p>` and its children are an
    // inert fragment the paragraph's content model never sees.
    //
    // The default stays `div` deliberately rather than becoming `span`: a span wrapper is
    // invalid the moment a slot carries block content, and only the caller knows what is in
    // its slot.
    'as' => 'div',
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // `describes="false"` on an unbound tag is the string "false", which is truthy.
    $describes = BooleanProp::from($describes, true, 'tooltip', 'describes');

    // Blade compiles an unbound attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false, 'tooltip', 'disabled');
    // Switched off, the tooltip has nothing to show on focus, so it makes no stop of its own.
    $focusableTrigger = $focusableTrigger === null
        ? ! $disabled && ! \Pushery\WireKit\Support\SlotControl::holdsFocus((string) $slot)
        : BooleanProp::from($focusableTrigger, true, 'tooltip', 'focusableTrigger');
    $surface = WireKit::validateProp('tooltip', 'surface', (string) $surface, ['inverted', 'elevated']);

    // `$as` is interpolated into two opening tags below, and Blade's escaping does not make
    // that safe: `e()` escapes neither a space nor an `=`, so `as="div onmouseover=alert(1)"`
    // would arrive as a working event handler. `tagName()` checks the shape rather than an
    // allowlist, the same way `card` does — an enum would have to guess which elements a
    // developer legitimately wants.
    $as = WireKit::tagName('tooltip', (string) $as);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('tooltip', $attributes->getAttributes());

    // One of the twelve placements Floating UI takes, or a side of the line, `start` or `end`, which
    // the script turns into one of them by the page's direction (Support\FloatingPlacement). A word
    // neither knows would put the panel on the top-left corner of its trigger: debug refuses it,
    // production logs it and uses the default.
    // No placement at all, `null` or empty, is the default, as the script reads it.
    $placement = \Pushery\WireKit\Support\StrictnessGate::enforce('tooltip', 'placement', (string) ($placement ?: 'top'), [...\Pushery\WireKit\Support\FloatingPlacement::ALL, ...\Pushery\WireKit\Support\FloatingPlacement::LOGICAL], 'top');

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:mouseenter', 'x-on:mouseleave', 'x-on:focusin', 'x-on:focusout', 'x-on:pointerdown', 'x-on:pointerup', 'x-on:pointerleave', 'x-on:keydown.escape.window.capture']);

    // Generate unique ID for ARIA association between trigger and tooltip
    // Stable across re-renders. The trigger's aria-describedby and the bubble's id are the
    // two halves of one pairing; a fresh id per render would leave them naming different
    // things, and both stay well-formed so
    // nothing in the markup looks wrong. A screen reader simply stops announcing the tip.
    // A tooltip that takes its words from an element on the page, as the help of a field does,
    // takes its id from that element's as well: it then depends on no count of the tooltips
    // before it, so a field keeps it when its form is rendered alone, and HTML written from a
    // cache cannot hold it twice.
    $tooltipId = is_string($textFrom) && trim($textFrom) !== ''
        ? \Pushery\WireKit\Support\DomId::distinct(trim($textFrom).'-tooltip')
        : \Pushery\WireKit\Support\DomId::unique(null, 'wk-tooltip-');

    // The stop this component makes of its trigger is a button: activating it shows the tooltip, as a
    // toggletip does. A button takes its name from its words; around an icon alone it has none, so
    // the tooltip's own text names it, and the description is left off so it is not read twice. A
    // name of the caller's comes first (Support\CallerName). Around a disabled control, which a
    // tooltip often explains, the button says it is unavailable, as the control is. A slot that takes
    // the focus itself, by the Tab key or by the arrow keys of its group, as a toolbar's buttons do,
    // gets no stop of its own (Support\SlotControl::holdsFocus()), and a stop the caller forces
    // around it gets no role: it would nest one control in another.
    $triggerName = new \Illuminate\Support\HtmlString('');
    // A tooltip switched off describes nothing: a reader would hear a tip nobody sees. A switch
    // bound at the call site is followed by the script instead.
    $triggerDescribes = $describes && ! $disabled;
    $triggerWords = null;
    $slotHtml = (string) $slot;
    // A content slot that holds nothing but Livewire's markers is none, and the text stands.
    $hasContent = \Pushery\WireKit\Support\SlotContent::given($content ?? null);
    $triggerIsButton = $focusableTrigger && ! \Pushery\WireKit\Support\SlotControl::holdsFocus($slotHtml);
    $triggerExplainsDisabled = $triggerIsButton && preg_match('/<(?:button|input|select|textarea)\b|<a\b[^>]*\shref=/i', $slotHtml) === 1;
    if ($triggerIsButton) {
        [$callerLabelledBy, $callerLabel, $attributes] = \Pushery\WireKit\Support\CallerName::split($attributes);
        $slotText = \Pushery\WireKit\Support\SlotContent::spoken($slotHtml);
        $slotNamesItself = \Pushery\WireKit\Support\SlotContent::names($slotHtml);
        $tipText = $hasContent ? \Pushery\WireKit\Support\SlotContent::plain($content) : \Pushery\WireKit\Support\SlotContent::textOf($text);
        $ownName = ! $slotNamesItself && $tipText !== '' ? $tipText : null;
        $triggerName = \Pushery\WireKit\Support\CallerName::attribute($callerLabelledBy, $callerLabel, $ownName);
        if ($callerLabelledBy === null && $callerLabel === null && $ownName !== null) {
            $triggerDescribes = false;
        }
        // The words the stop is named by, where they are in the markup here.
        if ($callerLabelledBy === null) {
            $triggerWords = $callerLabel ?? ($slotText !== '' ? $slotText : null);
        }
    }

    // The shortcut drawn after the text: the caller's, or the one the control in the slot binds.
    $tipShortcut = null;

    if ($shortcut !== false && $shortcut !== 'false') {
        $tipShortcut = is_string($shortcut) && trim($shortcut) !== ''
            ? trim($shortcut)
            : (preg_match('/\sx-wk-shortcut(?:\.[\w-]+)*="([^"]*)"/', $slotHtml, $bound) === 1 ? html_entity_decode($bound[1], ENT_QUOTES | ENT_HTML5) : null);
    }

    $tipShortcutKeys = \Pushery\WireKit\Support\KeyShortcuts::display($tipShortcut);

    // A shortcut the caller names that the grammar does not read is reported, and the panel draws no
    // keys. One read from the control in the slot is reported by the script that binds it.
    if (is_string($shortcut) && trim($shortcut) !== '' && $shortcut !== 'false' && $tipShortcutKeys === null) {
        \Pushery\WireKit\Support\StrictnessGate::reject('tooltip', 'shortcut', trim($shortcut), \Pushery\WireKit\Support\KeyShortcuts::EXPECTED, '');
    }

    // A text the trigger cuts and marks, as a badge with `truncate` marks its label, leads the panel
    // with its words while it is cut. The panel holds a place for them only when the slot has one.
    $tipCutLead = preg_match('/\sdata-wk-tooltip-cut(?=[\s=>\/])/', $slotHtml) === 1;

    // A trigger already named by the tooltip's words would hear them again as its description
    // ("Bold, button, Bold"): the control in the slot, which the script hands the description to,
    // or the stop this component makes of the trigger. The pairing is left out then, as `describes`
    // leaves it out. A shortcut drawn after the words says more than the name, and keeps it.
    if ($triggerDescribes && $tipShortcutKeys === null) {
        $triggerWords ??= $focusableTrigger ? null : \Pushery\WireKit\Support\SlotControl::name($slotHtml);
        $tipWords = $hasContent ? \Pushery\WireKit\Support\SlotContent::plain($content) : \Pushery\WireKit\Support\SlotContent::textOf($text);

        if ($triggerWords !== null && $tipWords !== '' && mb_strtolower(\Illuminate\Support\Str::squish($triggerWords)) === mb_strtolower(\Illuminate\Support\Str::squish($tipWords))) {
            $triggerDescribes = false;
        }
    }

    // Tooltip panel classes — inverted colors, small rounded box
    // w-max ensures the tooltip sizes to its content (not the trigger width)
    // Uses `fixed` positioning so the tooltip escapes ancestor `overflow: hidden` containers.
    // Floating UI uses strategy: 'fixed' to position relative to the viewport.
    //
    // The page's shared panel draws this list without classes where it is the default: the
    // stylesheet gives `#wk-tooltip-shared[data-wk-tooltip-default]` the declarations of these
    // utilities, so a trigger without a component of its own does not carry the list.
    $defaultPanelClasses = implode(' ', [
        'fixed',
        'w-max',
        'z-[var(--z-wk-tooltip)]',
        'max-w-[var(--size-wk-tooltip-max)]',
        // A code, a key or an address has no space to wrap at, so it breaks inside the panel's
        // width instead of running out of it.
        '[overflow-wrap:anywhere]',
        'px-[var(--padding-wk-x-sm)]',
        'py-[var(--padding-wk-y-xs)]',
        'bg-[var(--color-wk-tooltip-bg)]',
        'text-[color:var(--color-wk-tooltip-text)]',
        'text-[length:var(--text-wk-sm)]',
        'font-[family-name:var(--font-wk-sans)]',
        'rounded-[var(--radius-wk-sm)]',
        'shadow-[var(--shadow-wk-md)]',
    ]);
    $tooltipClasses = WireKit::resolveClasses('tooltip', 'panel', implode(' ', array_filter([
        $defaultPanelClasses,
        // The elevated surface keeps the popover's border, so it has an edge on a theme with no
        // shadow. The colors come from the wrapper (`data-wk-tooltip-surface` in the stylesheet).
        $surface === 'elevated' ? 'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]' : '',
        // There is deliberately no `pointer-events-none` here, and the absence is the
        // rule rather than an omission. WCAG 1.4.13 requires content shown on hover to
        // be hoverable: the pointer must be able to travel onto it without it
        // disappearing, which is what lets somebody reading a long tip at high zoom, or
        // with an unsteady hand, finish reading it at all. A panel that cannot receive
        // `mouseenter` also cannot cancel the hide the trigger's `mouseleave` just
        // armed, so it vanished the moment the pointer set off towards it.
        // The panel binds `mouseenter`/`mouseleave` below; the `offset` gap between
        // trigger and panel is crossed well inside `delayHide`.
    ])), $scope);
    // The page's shared panel, for a tooltip of words alone. Content, a shortcut and the words of a
    // cut text are drawn by a panel of the tooltip's own, which keeps them.
    $shared = BooleanProp::from($shared, false, 'tooltip', 'shared');
    $sharedPanel = $shared && ! $hasContent && $tipShortcutKeys === null && ! $tipCutLead;
    // What a shared tooltip is described by and shows: an element the caller names, which holds the
    // words already, or one this tooltip writes, hidden, in place of its panel. A tooltip that
    // describes nothing writes no such element, which a search of the page's text would find beside
    // the trigger's own name: its words ride on the trigger as `data-wk-tip-words`, where the panel
    // reads them, and a trigger this tooltip names with them, as a stop around an icon, carries
    // them in that name already.
    $textFromId = is_string($textFrom) && trim($textFrom) !== '' ? trim($textFrom) : null;
    $wordsOnTrigger = $sharedPanel && $textFromId === null && ! $triggerDescribes;
    $triggerNamedByWords = $triggerIsButton && ($callerLabelledBy ?? null) === null && ($callerLabel ?? null) === null && ($ownName ?? null) !== null;
    $tipWordsAttribute = $wordsOnTrigger && ! $triggerNamedByWords ? \Pushery\WireKit\Support\SlotContent::textOf($text) : null;
    $describedById = $sharedPanel && $textFromId !== null ? $textFromId : ($wordsOnTrigger ? '' : $tooltipId);
    $ownWords = $sharedPanel && $textFromId === null && ! $wordsOnTrigger
        ? new \Illuminate\Support\HtmlString('<span id="'.e($tooltipId).'" hidden data-wk-tooltip-words>'.e($text).'</span>')
        : '';

    // A lazy tooltip holds the teleport of its panel in a template of its own, which the browser
    // keeps out of the page until the factory builds it (utils/lazy-panel.js). The trigger cannot
    // name a panel that is not there yet, and a build on the first focus would race the screen
    // reader that reads the description as the focus lands. So the trigger names the words of the
    // panel instead, hidden beside it under an id of their own (partials/tooltip-words), before the
    // build and after it, and no two elements ever carry one id. The wrapper is an echo, since a
    // conditional around a tag's two ends would leave Livewire's markers inside the template.
    $lazy = BooleanProp::from($lazy, BooleanProp::from(config('wirekit.components.tooltip.lazy', false)), 'tooltip', 'lazy');
    $lazyPanel = $lazy && ! $sharedPanel;
    $lazyPanelOpen = $lazyPanel ? new \Illuminate\Support\HtmlString('<template data-wk-lazy-panel>') : '';
    $lazyPanelClose = $lazyPanel ? new \Illuminate\Support\HtmlString('</template>') : '';

    $tipDescribedWords = '';

    if ($lazyPanel) {
        $describedById = $tooltipId.'-words';
        // What the open panel says: its content as a screen reader reads it, with the parts hidden
        // from one left out, or its text. A string is text already, and markup is read for its words.
        $tipDescribedWords = $hasContent || $text instanceof \Illuminate\Contracts\Support\Htmlable
            ? \Illuminate\Support\Str::squish(\Pushery\WireKit\Support\SlotContent::spoken($hasContent ? $content : $text))
            : trim((string) $text);
    }

    $tipOffset = \Pushery\WireKit\Support\NumericProp::int($offset, 0, 'tooltip', 'offset');
    $tipDelayShow = \Pushery\WireKit\Support\NumericProp::int($delayShow, 0, 'tooltip', 'delayShow');
    $tipDelayHide = \Pushery\WireKit\Support\NumericProp::int($delayHide, 0, 'tooltip', 'delayHide');

    // A shared tooltip whose trigger is its own tab stop around a slot that holds no control is one
    // element, with no component of its own: the page's host drives every such trigger from the
    // document (resources/js/utils/shared-tooltips.js) through the same factory. `data-wk-tip`
    // names the element its words stand in, and an option it differs in from the factory's
    // default. A control of the caller's in the slot keeps the component, which describes that
    // control once the page has started, since the template never sees it.
    $lean = $sharedPanel && $triggerIsButton;
    $leanTrigger = '';

    if ($lean) {
        // What a caller may set as well goes through the bag, where the caller's value wins and
        // the tag holds each name once; the markers stand in the string below.
        $leanBag = $attributes->merge(array_filter([
            'data-wk-tip-placement' => $placement !== 'top' ? $placement : null,
            'data-wk-tip-offset' => $tipOffset !== 6 ? (string) $tipOffset : null,
            'data-wk-tip-delay-show' => $tipDelayShow !== 300 ? (string) $tipDelayShow : null,
            'data-wk-tip-delay-hide' => $tipDelayHide !== 100 ? (string) $tipDelayHide : null,
            'data-wk-tooltip-disabled' => $disabled ? 'true' : null,
            'data-wk-tooltip-surface' => $surface === 'elevated' ? 'elevated' : null,
            // The stylesheet draws the default panel; a panel that differs carries its classes.
            'data-wk-tooltip-panel-class' => $tooltipClasses !== $defaultPanelClasses ? \Pushery\WireKit\Support\CompactClasses::map('wk-tooltip-panel '.$tooltipClasses) : null,
            'aria-describedby' => $triggerDescribes ? $describedById : null,
            'data-wk-tip-words' => $tipWordsAttribute,
            'tabindex' => '0',
            'role' => 'button',
            'aria-disabled' => $triggerExplainsDisabled ? 'true' : null,
        ], static fn ($value): bool => $value !== null));

        // The wrapper's place in the line and the trigger's focus ring, on the one element. Built as
        // a string, so the classes take their short names here, which the view's class attributes
        // take as it compiles; a class of the caller's keeps its name.
        $leanClasses = \Pushery\WireKit\Support\CompactClasses::map(
            \Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, 'relative inline-block rounded-[var(--radius-wk-sm)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]'),
            false,
            preg_split('/\s+/', trim(\Illuminate\Support\Arr::toCssClasses($attributes->get('class', ''))), -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );
        $leanTrigger = new \Illuminate\Support\HtmlString(
            '<'.$as.' data-wk-prose-skip data-wk-tip="'.e($describedById).'" '.$leanBag->class($leanClasses)->toHtml().($triggerName->toHtml() !== '' ? ' '.$triggerName->toHtml() : '').'>'.$slotHtml.'</'.$as.'>'.(string) $ownWords
        );
    }

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above. One element without a
    // component of its own stands in the caller's scope, where the ref belongs.
    if (! $lean) {
        $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
    }
@endphp

{{-- One element the page's host drives, or the root with a component of its own. An echo and an
     include rather than a conditional, which Livewire would mark at both ends. --}}
{{ $leanTrigger }}@includeWhen(! $lean, 'wirekit::components.partials.tooltip-root')