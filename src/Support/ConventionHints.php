<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Seven house conventions a template can be checked against without rendering it.
 *
 *   - A button is filled. The rank of an action comes from its `intent`, so a button with a
 *     surface of its own beside a filled one is a second style rather than a lower rank. Three
 *     kinds of control may wear another surface, since theirs says something other than a rank:
 *     one half of a toggle (`aria-pressed`), whose surface tells whether it is chosen; a button
 *     with an icon and no words (`icon-only`); and a control that shows or opens something in
 *     place, a disclosure (`aria-expanded`) or the trigger in the `trigger` slot of a dropdown or a
 *     popover, which reads like a select rather than as an action.
 *   - The fields of a settings page stand in cards, each card one part of the settings. A modal,
 *     an alert dialog, a drawer and an alert carry their own content as a card does, so a field in
 *     one of them stands in place too. A settings page is a view whose path has `settings` as a
 *     part, or one of the parts the application names in `wirekit.doctor.settings_paths`.
 *   - A date or a time a reader sees does not break, so a narrow column moves the whole value to
 *     the next line instead of leaving the hour under the day.
 *   - The actions of a table's row stand at its end: the cell that holds them is `align="end"` or
 *     `actions`. A button in a cell after text the cell writes stands beside that text, as a copy
 *     button beside an address does, and is no action of the row.
 *   - The buttons of a form wrap. A row that holds a submit button and another button carries
 *     `wrap`: two labeled buttons side by side run out of a narrow card at 320 pixels, in a long
 *     language, or under the text spacing of WCAG 1.4.12.
 *   - A destructive action asks first. A button that deletes, removes, cancels or revokes is
 *     `intent="danger"` and opens an alert dialog, whose confirm runs the action; a `wire:click`
 *     that runs it from the button itself, or asks with `wire:confirm` in the browser's own
 *     dialog, leaves the convention. A call that sets state, `$set()` or `$toggle()`, or a method
 *     named for asking, `confirm…`, `ask…`, `open…`, `show…` or `prompt…`, opens the question rather
 *     than running the action, and a button inside an alert dialog is its answer.
 *   - A group of actions has one main action. A button without `intent` stands in the accent, so
 *     two of them in one row, footer or set of actions hold the same rank, and the one beside the
 *     main action takes `intent="neutral"`, or `intent="danger"` where it destroys. A pair of a
 *     danger and an unranked button is left alone: a main action and a destructive one can stand
 *     side by side, and only the words tell such a pair from a keep beside a delete. Two buttons in
 *     different branches of one `@if`, `@switch` or `@forelse` never render together and do not count
 *     as a pair, and the controls whose surface says something other than a rank are left out, as
 *     they are for the filled surface.
 *
 * They are hints, not errors: every pattern here is valid markup, and the page renders either
 * way. `wirekit:doctor:props` reports them, and fails on them only when asked to.
 *
 * Which element stands inside which is read from the tags in order: an opening tag that is
 * neither void nor self-closing opens a scope, the closing tag of the same name ends it, and
 * whatever stands between them has the open tags as its ancestors. Blade comments are blanked
 * first, so markup written inside one is not read.
 */
final class ConventionHints
{
    /**
     * `wrap` on a row, written as it is, as `wrap="…"` with any value but `false`, or bound to any
     * expression but the literal false. A bound expression decides only once the page renders, so it
     * counts as wrapping, as an unknown surface counts as filled.
     */
    private const WRAPS = '/(?<![\w:-])(?::?wrap)(?![\w-])(?!\s*=\s*(["\'])\s*false\s*\1)/';

    /** A button of a form's submission: `type="submit"`. */
    private const SUBMITS = '/(?<![\w:-])type\s*=\s*(["\'])submit\1/';

    /** Elements that hold no content, so they never open a scope. */
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

    /** The form fields whose place on a settings page is a card. */
    private const FIELDS = [
        'x-wirekit::input', 'x-wirekit::select', 'x-wirekit::textarea', 'x-wirekit::toggle',
        'x-wirekit::checkbox', 'x-wirekit::radio-group', 'x-wirekit::password-input',
        'x-wirekit::combobox', 'x-wirekit::number-input', 'x-wirekit::date-picker',
        'x-wirekit::time-picker', 'x-wirekit::datetime-picker', 'x-wirekit::field',
    ];

    /** The surfaces that carry their own content, so a field inside one stands as it does in a card. */
    private const CARD_SURFACES = ['card', 'modal', 'alert-dialog', 'drawer', 'alert'];

    /** The calls only a date has, each of which turns one into text for a reader. */
    private const DATE_ONLY_CALL = '/->\s*(?:isoFormat|translatedFormat|diffForHumans|toDateString|toTimeString|toDateTimeString|toFormattedDateString|toDayDateTimeString)\s*\(/';

    /**
     * `format()` with the first argument of a date: a quoted pattern, or one of the date constants.
     * Other types name a method `format()` too, an amount of money among them, so a call with
     * another first argument, or none, is read as no date.
     */
    private const FORMAT_CALL = '/->\s*format\s*\(\s*(?:(["\'])(?<pattern>(?:\\\\.|(?!\1).)*)\1|(?<constant>\\\\?(?:DATE_[A-Z0-9_]+|(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*(?:DateTime|DateTimeImmutable|DateTimeInterface|Carbon|CarbonImmutable|CarbonInterface)::[A-Z][A-Z0-9_]*)))/';

    /** A letter the date format of PHP reads as a part of a date or a time. */
    private const DATE_PATTERN_LETTER = '/[dDjlNSwzWFmMntLoXxYyaABgGhHisuveIOPpTZcrU]/';

    /** A locale such as `de` or `en_US`: the argument a money's or a number's `format()` takes. */
    private const LOCALE = '/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/';

    /** `aria-pressed` on a tag, written as it is or bound. */
    private const PRESSED = '/(?<![\w-])(?::|x-bind:)?aria-pressed\s*=/';

    /** `aria-expanded` on a tag, written as it is or bound: a disclosure, which shows or hides a part of the page. */
    private const EXPANDED = '/(?<![\w-])(?::|x-bind:)?aria-expanded\s*=/';

    /** `icon-only` as an attribute of a tag, bare, with a value or bound, in either spelling; a literal false is no icon button. */
    private const ICON_ONLY = '/(?:^|(?<=\s)):?(?:icon-only|iconOnly)(?=[\s=\/>]|$)(?!\s*=\s*(["\'])\s*(?:false|0)\s*\1)/';

    /** The tags that draw a button: the button, and the triggers that hand their surface to the button they draw. */
    private const BUTTONS = ['x-wirekit::button', 'x-wirekit::modal.trigger', 'x-wirekit::drawer.trigger', 'x-wirekit::shortcuts.trigger'];

    /** The components that set actions side by side: a row, and the footer or the actions of an overlay or a card. */
    private const ACTION_GROUPS = ['x-wirekit::row', 'x-wirekit::modal.footer', 'x-wirekit::drawer.footer', 'x-wirekit::card.footer', 'x-wirekit::alert-dialog.actions'];

    /** The slots that set actions side by side, `footer` and `actions`, in either spelling of a slot. */
    private const ACTION_SLOTS = ['footer', 'actions'];

    /** `intent` on a tag, written as it is or bound: a button that names its rank, whatever the value. */
    private const INTENT = '/(?<![\w:-])(?::|x-bind:)?intent\s*=/';

    /** An attribute bag passed on to a tag, which may carry an `intent` the template does not show. */
    private const FORWARDS_ATTRIBUTES = '/\{\{\s*\$attributes(?![\w])/';

    /** A Blade block that renders one of its branches, and `@empty` written as a block, with its condition. */
    private const BRANCH_OPEN = '/(?<!@)@(?:if|unless|isset|auth|guest|can|cannot|canany|env|production|switch|forelse|session|hasSection|sectionMissing)(?![\w])|(?<!@)@empty(?=\s*\()/';

    /** Where the next branch of such a block begins; a bare `@empty` begins the branch of a `@forelse` without items. */
    private const BRANCH_NEXT = '/(?<!@)@(?:elseif|else|elsecan|elsecannot|elsecanany|elseauth|elseguest|case|default)(?![\w])|(?<!@)@empty(?![\w(])(?!\s*\()/';

    /** The end of such a block. */
    private const BRANCH_CLOSE = '/(?<!@)@(?:endif|endunless|endisset|endempty|endauth|endguest|endcan|endcannot|endcanany|endenv|endproduction|endswitch|endforelse|endsession)(?![\w])/';

    /** The components whose `trigger` slot holds the control that opens them. */
    private const TRIGGER_OWNERS = ['x-wirekit::dropdown', 'x-wirekit::popover'];

    /** The child components that hold the control which opens their parent, the slot's other spelling. */
    private const TRIGGER_TAGS = ['x-wirekit::dropdown.trigger'];

    /** The tags a row's action is drawn with: a button, the triggers that draw one, and a menu of actions. */
    private const ROW_ACTIONS = ['x-wirekit::button', 'x-wirekit::modal.trigger', 'x-wirekit::drawer.trigger', 'x-wirekit::dropdown'];

    /** `align` written as `end` or `right`, or bound to one of the two as a single literal. */
    private const ALIGNED_TO_THE_END = '/(?<![:\w-])align\s*=\s*(["\'])(?:end|right)\1|(?<![:\w-]):align\s*=\s*(?:"\s*\'(?:end|right)\'\s*"|\'\s*"(?:end|right)"\s*\')/';

    /** `actions` as an attribute of a cell, bare, with a value or bound; a literal false makes it no cell of actions. */
    private const ACTIONS_CELL = '/(?:^|(?<=\s)):?actions(?=[\s=\/>]|$)(?!\s*=\s*(["\'])\s*(?:false|0)\s*\1)/';

    /** `align` bound to an expression that is more than a literal: its value is known once the page renders. */
    private const ALIGN_BOUND = '/(?<![:\w-]):align\s*=/';

    /** `wire:click`, with or without modifiers, and the call it makes. A bound `:wire:click` is a PHP value and is left alone. */
    private const WIRE_CLICK = '/(?<![\w:-])wire:click(?:\.[\w.-]+)?\s*=\s*(["\'])(?<call>.*?)\1/s';

    /** The controls an action runs from: a button and the entries of a menu. */
    private const ACTION_CONTROLS = ['x-wirekit::button', 'x-wirekit::dropdown.item', 'x-wirekit::context-menu.item', 'x-wirekit::menubar.item'];

    /**
     * A control marked as dangerous: `intent` written as `danger` or bound to it as a single literal,
     * or the `danger` switch of a menu's entry, bare, with a value or bound, unless a literal false.
     */
    private const DANGER = '/(?<![:\w-])intent\s*=\s*(["\'])danger\1|(?<![:\w-]):intent\s*=\s*(?:"\s*\'danger\'\s*"|\'\s*"danger"\s*\')|(?:^|(?<=\s)):?danger(?=[\s=\/>]|$)(?!\s*=\s*(["\'])\s*(?:false|0)\s*\2)/';

    /** The verbs a method that destroys something begins with, `deleteUser` and `remove` among them, and not `deleted`. */
    private const DESTRUCTIVE_VERB = '/^(?:delete|destroy|remove|revoke|archive|detach|purge|terminate|forget|wipe|unsubscribe|deactivate|disconnect)(?![a-z])/';

    /** The verbs a method that asks before an action begins with, so the button opens the question. */
    private const ASKING_VERB = '/^(?:confirm|ask|open|show|prompt)(?![a-z])/';

    /**
     * A surface other than the filled one, written as it is or bound to a single string literal:
     * `:surface="'ghost'"` is as fixed before the page renders as `surface="ghost"`. A bound
     * expression of anything else, a variable or a condition, says nothing until it renders and is
     * left alone, and so is `::surface`, which passes the attribute on rather than binding the prop.
     */
    private const NOT_FILLED = '/(?<![:\w-])surface\s*=\s*(["\'])(?:outline|soft|ghost|link)\1|(?<![:\w-]):surface\s*=\s*(?:"\s*\'(?:outline|soft|ghost|link)\'\s*"|\'\s*"(?:outline|soft|ghost|link)"\s*\')/';

    /** A class that keeps an element's text on one line. */
    private const NOWRAP = '/(?<![\w:-])(?:whitespace-nowrap|text-nowrap)(?![\w-])/';

    /**
     * @param  list<string>  $settingsPaths  the parts of a path that mark a settings page, `settings` unless the application names others
     * @param  bool  $inCard  whether every place that includes this template has a card surface open around it, so its fields stand in one
     * @return list<array{line: int, rule: string, message: string}>
     */
    public static function find(string $relativePath, string $contents, array $settingsPaths = ['settings'], bool $inCard = false): array
    {
        $source = BladeParser::blankComments($contents);
        $settingsPage = self::isSettingsPage($relativePath, $settingsPaths);
        $hints = [];
        // The cells of a table by the offset of their tag: those that wrote text before a control,
        // and those already named for a row's actions that stand elsewhere than at its end.
        $cellsWithText = [];
        $cellsNamed = [];
        // The rows by the offset of their tag, with the buttons they hold and whether one submits.
        $rows = [];
        // The groups of actions by the offset of their tag, with the branches each button in the
        // accent stands in, and the branches of the source's Blade blocks.
        $accented = [];
        $segments = self::branches($source);

        foreach (self::walk($source) as [$event, $stack]) {
            if ($event['kind'] === 'echo') {
                // A script or a stylesheet is no text a reader sees either.
                $kept = self::anyAncestor($stack, static fn (array $tag): bool => preg_match(self::NOWRAP, $tag['attributes']) === 1
                    || in_array(strtolower($tag['name']), ['script', 'style'], true));

                if (! $kept && self::writesADate($event['text'])) {
                    $hints[] = self::hint($source, $event['offset'], 'unbroken-time', 'A date or a time is written here into text that may break across lines. Keep it on one line, for example in an element with `whitespace-nowrap`.');
                }

                $cell = self::nearestCell($stack);

                if ($cell !== null) {
                    $cellsWithText[$cell['offset']] = true;
                }

                continue;
            }

            if ($event['kind'] !== 'open') {
                continue;
            }

            $name = $event['name'];

            if (in_array($name, self::BUTTONS, true) && preg_match(self::NOT_FILLED, $event['attributes']) === 1
                && ! self::mayWearAnotherSurface($event['attributes'], $stack)) {
                $hints[] = self::hint($source, $event['offset'], 'filled-button', 'A button with a surface of its own. Buttons are filled, and an action takes its rank from `intent`: the main one in the accent, another `intent="neutral"`, a destructive one `intent="danger"`. Only a toggle (`aria-pressed`), a button with an icon alone (`icon-only`), a disclosure (`aria-expanded`) and the trigger of a dropdown or a popover wear another surface.');
            }

            if ($settingsPage && ! $inCard && in_array($name, self::FIELDS, true) && ! self::anyAncestor($stack, self::isCardSurface(...))) {
                $hints[] = self::hint($source, $event['offset'], 'content-in-card', 'A field of a settings page outside a card. Each part of the settings stands in a card with a head: an icon, the name of the part and one sentence on what it is for.');
            }

            // A cell is named once, at its own tag, however many actions it holds.
            $cell = in_array($name, self::ROW_ACTIONS, true) ? self::nearestCell($stack) : null;

            if ($cell !== null && ! isset($cellsWithText[$cell['offset']]) && ! isset($cellsNamed[$cell['offset']])
                && self::standsAtTheEnd($cell['attributes']) === false) {
                $cellsNamed[$cell['offset']] = true;
                $hints[] = self::hint($source, $cell['offset'], 'row-actions-at-the-end', 'A cell with the actions of its row that does not stand at the row\'s end. Give it `align="end"` or `actions`, so the actions stand side by side at the end of every row.');
            }

            // A button counts for the nearest row around it, through any element between.
            if (in_array($name, self::BUTTONS, true)) {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['name'] === 'x-wirekit::row') {
                        $row = $stack[$i];
                        $rows[$row['offset']] ??= ['attributes' => $row['attributes'], 'buttons' => 0, 'submits' => false];
                        $rows[$row['offset']]['buttons']++;
                        $rows[$row['offset']]['submits'] = $rows[$row['offset']]['submits'] || preg_match(self::SUBMITS, $event['attributes']) === 1;

                        break;
                    }
                }
            }

            // A button in the accent counts for the nearest group of actions around it.
            if (in_array($name, self::BUTTONS, true) && self::standsInTheAccent($event['attributes'], $stack)) {
                $group = self::nearestActionGroup($stack);

                if ($group !== null) {
                    $accented[$group][] = self::branchAt($segments, $event['offset']);
                }
            }

            if (in_array($name, self::ACTION_CONTROLS, true) && self::actsWithoutAsking($event['attributes'], $stack)) {
                $hints[] = self::hint($source, $event['offset'], 'destructive-asks-first', 'A destructive action that runs without the kit\'s question. A button that deletes, removes, cancels or revokes is `intent="danger"` and opens an `alert-dialog`, whose `alert-dialog.confirm` runs the action; `wire:confirm` asks in the browser\'s own dialog instead.');
            }
        }

        // After the walk, since a row is judged by every button it holds; its hints come last.
        foreach ($rows as $offset => $row) {
            if ($row['submits'] && $row['buttons'] > 1 && preg_match(self::WRAPS, $row['attributes']) !== 1) {
                $hints[] = self::hint($source, $offset, 'form-buttons-wrap', 'A row with the submit button of a form beside another button, and no `wrap`. At 320 pixels, in a long language or under the text spacing of WCAG 1.4.12, the two run out of a narrow card; give the row `wrap`.');
            }
        }

        // After the walk as well, since a group is judged by every button it holds.
        foreach ($accented as $offset => $branches) {
            if (self::twoRenderTogether($branches)) {
                $hints[] = self::hint($source, $offset, 'one-main-action', 'Two buttons of one group in the accent. Neither names its rank with `intent`, so both read as the main action: keep the accent for the main one and give the other `intent="neutral"`, or `intent="danger"` where it destroys something.');
            }
        }

        return $hints;
    }

    /**
     * The views a template includes by name, each with whether a card surface stands open around
     * the include, so a field the view holds stands in that card. `@include`, `@includeIf`,
     * `@includeWhen` and `@includeUnless` name one view; `@includeFirst` names several and is left
     * out, as is a name that is not written as a string.
     *
     * @return list<array{view: string, line: int, inCard: bool}>
     */
    public static function includes(string $contents): array
    {
        $source = BladeParser::blankComments($contents);
        $includes = [];

        foreach (self::walk($source) as [$event, $stack]) {
            if ($event['kind'] === 'include') {
                $includes[] = [
                    'view' => $event['name'],
                    'line' => substr_count($source, "\n", 0, max(0, $event['offset'])) + 1,
                    'inCard' => self::anyAncestor($stack, self::isCardSurface(...)),
                ];
            }
        }

        return $includes;
    }

    /**
     * The events of a source in order, each with the tags open around it: an opening tag that is
     * neither void nor self-closing opens a scope after its own event, and the closing tag of the
     * same name ends it. A stray closing tag closes nothing, and a closing tag is no event here.
     *
     * @return list<array{0: array{kind: string, offset: int, name: string, attributes: string, selfClosing: bool, text: string}, 1: list<array{name: string, attributes: string, offset: int}>}>
     */
    private static function walk(string $source): array
    {
        $walked = [];
        $stack = [];

        foreach (self::events($source) as $event) {
            if ($event['kind'] === 'close') {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['name'] === $event['name']) {
                        array_splice($stack, $i);

                        break;
                    }
                }

                continue;
            }

            $walked[] = [$event, $stack];

            if ($event['kind'] === 'open' && ! $event['selfClosing'] && ! in_array(strtolower($event['name']), self::VOID, true)) {
                $stack[] = ['name' => $event['name'], 'attributes' => $event['attributes'], 'offset' => $event['offset']];
            }
        }

        return $walked;
    }

    /**
     * Whether an echo turns a date into text: a call only a date has, or `format()` with the first
     * argument of a date. A quoted pattern counts when it holds a letter of a date format and is
     * not a locale, whose letters a date format reads as well.
     */
    private static function writesADate(string $echo): bool
    {
        if (preg_match(self::DATE_ONLY_CALL, $echo) === 1) {
            return true;
        }

        preg_match_all(self::FORMAT_CALL, $echo, $calls, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        foreach ($calls as $call) {
            if ($call['constant'] !== null) {
                return true;
            }

            $pattern = (string) $call['pattern'];

            if (preg_match(self::LOCALE, $pattern) !== 1 && preg_match(self::DATE_PATTERN_LETTER, $pattern) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a view is a settings page: one of the given parts stands in its path as a folder, or
     * as a file name before a dot, an underscore or a hyphen. A part that is not a word of text is
     * left out, and with none left the part is `settings`.
     *
     * @param  array<array-key, mixed>  $settingsPaths
     */
    private static function isSettingsPage(string $relativePath, array $settingsPaths): bool
    {
        $parts = [];

        foreach ($settingsPaths as $part) {
            $part = is_string($part) ? trim($part, " \t/") : '';

            if ($part !== '') {
                $parts[] = preg_quote($part, '#');
            }
        }

        $pattern = '#(?:^|/)(?:'.($parts === [] ? 'settings' : implode('|', $parts)).')(?:/|[._-]|$)#i';

        return preg_match($pattern, $relativePath) === 1;
    }

    /**
     * The nearest table cell around an event, or null outside one.
     *
     * @param  list<array{name: string, attributes: string, offset: int}>  $stack
     * @return array{name: string, attributes: string, offset: int}|null
     */
    private static function nearestCell(array $stack): ?array
    {
        foreach (array_reverse($stack) as $tag) {
            if ($tag['name'] === 'x-wirekit::table.td') {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Whether a cell stands at the end of its row: true for `actions` or for `align` written as
     * `end` or `right`, false for any other cell, and null where a bound `align` is known only once
     * the page renders.
     */
    private static function standsAtTheEnd(string $attributes): ?bool
    {
        if (preg_match(self::ACTIONS_CELL, $attributes) === 1 || preg_match(self::ALIGNED_TO_THE_END, $attributes) === 1) {
            return true;
        }

        return preg_match(self::ALIGN_BOUND, $attributes) === 1 ? null : false;
    }

    /**
     * Whether a button runs a destructive action from its own `wire:click`: a danger button, or a
     * call that begins with a verb of destruction, outside an alert dialog. A call to one of
     * Livewire's state helpers, `$set()` or `$toggle()` among them, opens the question rather than
     * running the action, and so does a method named for asking. `cancel` counts with an argument
     * or a word after it, `cancel(3)` or `cancelSubscription`, since a bare `cancel` usually leaves
     * a form.
     *
     * @param  list<array{name: string, attributes: string, offset: int}>  $stack
     */
    private static function actsWithoutAsking(string $attributes, array $stack): bool
    {
        if (preg_match(self::WIRE_CLICK, $attributes, $click) !== 1) {
            return false;
        }

        if (self::anyAncestor($stack, static fn (array $tag): bool => $tag['name'] === 'x-wirekit::alert-dialog' || str_starts_with($tag['name'], 'x-wirekit::alert-dialog.'))) {
            return false;
        }

        $call = (string) preg_replace('/^\$parent\./', '', trim($click['call']));

        if (str_starts_with($call, '$') || preg_match('/^([A-Za-z_]\w*)\s*(?:\((.*)\))?/s', $call, $parts) !== 1) {
            return false;
        }

        $method = $parts[1];

        if (preg_match(self::ASKING_VERB, $method) === 1) {
            return false;
        }

        $cancels = preg_match('/^cancel(?=[A-Z0-9_])/', $method) === 1 || ($method === 'cancel' && trim($parts[2] ?? '') !== '');

        return preg_match(self::DANGER, $attributes) === 1 || preg_match(self::DESTRUCTIVE_VERB, $method) === 1 || $cancels;
    }

    /**
     * Whether a button is one of the controls whose surface says something other than a rank: one
     * half of a toggle, a button with an icon alone, a disclosure, or the control in the `trigger`
     * slot of a dropdown or a popover, in either spelling of the slot, or in `dropdown.trigger`.
     *
     * @param  list<array{name: string, attributes: string, offset: int}>  $stack
     */
    private static function mayWearAnotherSurface(string $attributes, array $stack): bool
    {
        if (preg_match(self::PRESSED, $attributes) === 1 || preg_match(self::EXPANDED, $attributes) === 1 || preg_match(self::ICON_ONLY, $attributes) === 1) {
            return true;
        }

        $parent = null;

        foreach ($stack as $tag) {
            if (in_array($tag['name'], self::TRIGGER_TAGS, true)) {
                return true;
            }

            $slot = $tag['name'] === 'x-slot:trigger'
                || ($tag['name'] === 'x-slot' && preg_match('/(?<![\w:-])name\s*=\s*(["\'])trigger\1/', $tag['attributes']) === 1);

            if ($slot && $parent !== null && in_array($parent['name'], self::TRIGGER_OWNERS, true)) {
                return true;
            }

            $parent = $tag;
        }

        return false;
    }

    /**
     * Whether a button stands in the accent without naming its rank: no `intent`, written or bound,
     * no attribute bag that may bring one, the filled surface, and none of the controls whose
     * surface says something other than a rank. A button with a surface of its own has the
     * `filled-button` hint already.
     *
     * @param  list<array{name: string, attributes: string, offset: int}>  $stack
     */
    private static function standsInTheAccent(string $attributes, array $stack): bool
    {
        return preg_match(self::INTENT, $attributes) !== 1
            && preg_match(self::FORWARDS_ATTRIBUTES, $attributes) !== 1
            && preg_match(self::NOT_FILLED, $attributes) !== 1
            && ! self::mayWearAnotherSurface($attributes, $stack);
    }

    /**
     * The offset of the nearest group of actions around a place: a row, the footer or the actions of
     * an overlay or a card, or a slot named `footer` or `actions` in either spelling.
     *
     * @param  list<array{name: string, attributes: string, offset: int}>  $stack
     */
    private static function nearestActionGroup(array $stack): ?int
    {
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $tag = $stack[$i];

            if (in_array($tag['name'], self::ACTION_GROUPS, true)) {
                return $tag['offset'];
            }

            foreach (self::ACTION_SLOTS as $slot) {
                if ($tag['name'] === 'x-slot:'.$slot
                    || ($tag['name'] === 'x-slot' && preg_match('/(?<![\w:-])name\s*=\s*(["\'])'.$slot.'\1/', $tag['attributes']) === 1)) {
                    return $tag['offset'];
                }
            }
        }

        return null;
    }

    /**
     * The branches of the source's Blade blocks that render one of them, as segments that begin
     * where a directive stands: from a segment's offset on, `branches` maps the offset of each block
     * open there to the number of the branch the source stands in. A directive that closes or
     * divides no open block is left out.
     *
     * @return list<array{offset: int, branches: array<int, int>}>
     */
    private static function branches(string $source): array
    {
        // The kind of each directive by its offset; no two of the patterns match at one offset.
        $marks = [];

        foreach ([self::BRANCH_OPEN => 'open', self::BRANCH_NEXT => 'next', self::BRANCH_CLOSE => 'close'] as $pattern => $kind) {
            preg_match_all($pattern, $source, $found, PREG_OFFSET_CAPTURE);

            foreach ($found[0] as [, $offset]) {
                $marks[(int) $offset] = $kind;
            }
        }

        ksort($marks);

        $segments = [['offset' => 0, 'branches' => []]];
        $open = [];

        foreach ($marks as $offset => $kind) {
            if ($kind === 'open') {
                $open[$offset] = 0;
            } elseif ($open !== []) {
                $innermost = array_key_last($open);

                if ($kind === 'next') {
                    $open[$innermost]++;
                } else {
                    unset($open[$innermost]);
                }
            }

            $segments[] = ['offset' => $offset, 'branches' => $open];
        }

        return $segments;
    }

    /**
     * The branches a place stands in: those of the last segment that begins at or before it.
     *
     * @param  list<array{offset: int, branches: array<int, int>}>  $segments
     * @return array<int, int>
     */
    private static function branchAt(array $segments, int $offset): array
    {
        $branches = [];

        foreach ($segments as $segment) {
            if ($segment['offset'] > $offset) {
                break;
            }

            $branches = $segment['branches'];
        }

        return $branches;
    }

    /**
     * Whether two of the places can render together: in every block around both of them, they
     * stand in the same branch.
     *
     * @param  list<array<int, int>>  $places
     */
    private static function twoRenderTogether(array $places): bool
    {
        $count = count($places);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $shared = array_intersect_key($places[$i], $places[$j]);

                if (array_intersect_assoc($shared, $places[$j]) === $shared) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A card, or a surface that carries its own content as a card does, or a part of one.
     *
     * @param  array{name: string, attributes: string, offset: int}  $tag
     */
    private static function isCardSurface(array $tag): bool
    {
        foreach (self::CARD_SURFACES as $surface) {
            if ($tag['name'] === 'x-wirekit::'.$surface || str_starts_with($tag['name'], 'x-wirekit::'.$surface.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The opening tags, closing tags, echoes and includes of the source, in the order they stand.
     *
     * @return list<array{kind: string, offset: int, name: string, attributes: string, selfClosing: bool, text: string}>
     */
    private static function events(string $source): array
    {
        $events = [];
        $tags = [];

        foreach (BladeParser::tagsFromSource($source) as $tag) {
            // A tag another element interrupted, or one the file ended inside, is no element.
            if ($tag['terminator'] !== '>') {
                continue;
            }

            $region = substr($source, $tag['attrStart'], $tag['attrEnd'] - $tag['attrStart']);
            $tags[] = [$tag['start'], $tag['attrEnd']];

            $events[] = [
                'kind' => 'open',
                'offset' => $tag['start'],
                'name' => $tag['name'],
                'attributes' => $region,
                'selfClosing' => str_ends_with(rtrim($region), '/'),
                'text' => '',
            ];
        }

        preg_match_all('#</([a-zA-Z][\w:.-]*)\s*>#', $source, $closes, PREG_OFFSET_CAPTURE);

        foreach ($closes[1] as [$name, $offset]) {
            $events[] = ['kind' => 'close', 'offset' => (int) $offset - 2, 'name' => $name, 'attributes' => '', 'selfClosing' => false, 'text' => ''];
        }

        // An escaped echo, `@{{ … }}`, prints its braces and evaluates nothing.
        preg_match_all('/(?<!@)\{\{(.*?)\}\}/s', $source, $echoes, PREG_OFFSET_CAPTURE);

        foreach ($echoes[1] as [$text, $offset]) {
            $start = (int) $offset - 2;

            // An echo inside a tag writes an attribute's value, which is no text a reader sees.
            foreach ($tags as [$tagStart, $tagEnd]) {
                if ($start > $tagStart && $start < $tagEnd) {
                    continue 2;
                }
            }

            $events[] = ['kind' => 'echo', 'offset' => $start, 'name' => '', 'attributes' => '', 'selfClosing' => false, 'text' => $text];
        }

        // An include that names its view as a string: the first argument of `@include` and
        // `@includeIf`, the second of `@includeWhen` and `@includeUnless`.
        preg_match_all('/@include(?:If)?\s*\(\s*(["\'])(?<view>[\w.:\/-]+)\1|@include(?:When|Unless)\s*\([^,]*,\s*(["\'])(?<conditional>[\w.:\/-]+)\3/', $source, $includes, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        foreach ($includes as $include) {
            $view = $include['view'][0] ?? $include['conditional'][0] ?? null;

            if ($view !== null) {
                $events[] = ['kind' => 'include', 'offset' => (int) $include[0][1], 'name' => $view, 'attributes' => '', 'selfClosing' => false, 'text' => ''];
            }
        }

        usort($events, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        return $events;
    }

    /**
     * @param  list<array{name: string, attributes: string, offset: int}>  $stack
     * @param  callable(array{name: string, attributes: string, offset: int}): bool  $matches
     */
    private static function anyAncestor(array $stack, callable $matches): bool
    {
        foreach ($stack as $tag) {
            if ($matches($tag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{line: int, rule: string, message: string}
     */
    private static function hint(string $source, int $offset, string $rule, string $message): array
    {
        return ['line' => substr_count($source, "\n", 0, max(0, $offset)) + 1, 'rule' => $rule, 'message' => $message];
    }
}
