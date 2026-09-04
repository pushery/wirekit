{{-- optimistic-ui: n/a — client-only
     Its buttons command the editor in the page. --}}
@props([
    // Command vocabulary to render. '|' is a visual separator. Commands the active
    // extension set doesn't support simply no-op (Tiptap's chain ignores them).
    'commands' => ['bold', 'italic', 'strike', 'link', '|', 'bullet-list', 'ordered-list'],
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('editor.toolbar', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // command => [accessible label, glyph, glyph-style, isActive() expr | null (action, not toggle)].
    // Glyphs are TEXT where a letterform IS the convention (B / I / H1 / ¶) and
    // flat inline SVGs (stroke currentColor — never emoji codepoints, which
    // render as colored "3D" emoji and clash with the flat set; the link 🔗 was
    // exactly that bug) where the convention is pictographic (link, undo/redo,
    // task-list). Static blade-authored markup, never developer-controlled.
    $svgAttrs = 'class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    $meta = [
        'bold' => ['Bold', 'B', 'font-bold', "isActive('bold')"],
        'italic' => ['Italic', 'I', 'italic', "isActive('italic')"],
        'underline' => ['Underline', 'U', 'underline', "isActive('underline')"],
        'strike' => ['Strikethrough', 'S', 'line-through', "isActive('strike')"],
        'code' => ['Inline code', '</>', 'font-mono text-[0.7em]', "isActive('code')"],
        'link' => ['Link', '<svg '.$svgAttrs.'><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>', '', "isActive('link')"],
        'heading-1' => ['Heading 1', 'H1', 'font-bold text-[0.8em]', "isActive('heading', { level: 1 })"],
        'heading-2' => ['Heading 2', 'H2', 'font-bold text-[0.8em]', "isActive('heading', { level: 2 })"],
        'heading-3' => ['Heading 3', 'H3', 'font-bold text-[0.8em]', "isActive('heading', { level: 3 })"],
        'paragraph' => ['Paragraph', '¶', '', "isActive('paragraph')"],
        'quote' => ['Quote', '”', 'font-serif', "isActive('blockquote')"],
        'bullet-list' => ['Bullet list', '•', '', "isActive('bulletList')"],
        'ordered-list' => ['Numbered list', '1.', 'text-[0.8em]', "isActive('orderedList')"],
        'task-list' => ['Task list', '<svg '.$svgAttrs.'><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/><path d="m9 11 3 3L22 4"/></svg>', '', "isActive('taskList')"],
        'code-block' => ['Code block', '{}', 'font-mono', "isActive('codeBlock')"],
        'horizontal-rule' => ['Divider', '―', '', null],
        'align-left' => ['Align left', '⇤', '', "isActive({ textAlign: 'left' })"],
        'align-center' => ['Align center', '↔', '', "isActive({ textAlign: 'center' })"],
        'align-right' => ['Align right', '⇥', '', "isActive({ textAlign: 'right' })"],
        'align-justify' => ['Justify', '☰', '', "isActive({ textAlign: 'justify' })"],
        'clear-formatting' => ['Clear formatting', '⨉', '', null],
        'undo' => ['Undo', '<svg '.$svgAttrs.'><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/></svg>', '', null],
        'redo' => ['Redo', '<svg '.$svgAttrs.'><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5A5.5 5.5 0 0 0 4 14.5v0A5.5 5.5 0 0 0 9.5 20H13"/></svg>', '', null],
    ];

    $buttonClasses = implode(' ', [
        'inline-flex items-center justify-center shrink-0',
        'h-8 min-w-8 px-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-sm)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text-muted)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'cursor-pointer',
        // Disabled state — only the history buttons (undo/redo) ever set [disabled]
        // (when there's nothing to undo/redo). The disabled: variants are inert on
        // every other button. pointer-events-none also kills the hover bg/text.
        'disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed disabled:pointer-events-none',
    ]);

    // History buttons whose availability gates :disabled. The reactive guards
    // (canUndo()/canRedo()) live in the wirekitEditor Alpine component.
    $historyGuard = ['undo' => 'canUndo()', 'redo' => 'canRedo()'];

    // Which button is the toolbar's single tab stop. `role="toolbar"` promises a
    // screen reader that Tab reaches the group ONCE and the arrow keys move
    // inside it, so exactly one button may carry tabindex="0" — the `full` preset
    // is otherwise seventeen consecutive tab stops between whatever precedes the
    // editor and the editable surface itself. From there the tabindex roves with
    // the focus (focusToolbarCommand() in the wirekitEditor component).
    //
    // The history buttons are skipped when choosing it: undo/redo bind :disabled
    // to an empty history stack, and a disabled element is not focusable — a
    // toolbar whose only tab stop starts out disabled cannot be entered at all.
    $tabStopKeys = array_keys(array_filter(
        $commands,
        fn ($command) => isset($meta[$command]) && ! isset($historyGuard[$command]),
    ));
    // No such button (a toolbar of undo/redo alone): fall back to the first one
    // that renders, which is focusable the moment there is anything to undo.
    if ($tabStopKeys === []) {
        $tabStopKeys = array_keys(array_filter($commands, fn ($command) => isset($meta[$command])));
    }
    $tabStopKey = $tabStopKeys[0] ?? null;

    $toolbarClasses = WireKit::resolveClasses('editor.toolbar', 'base', implode(' ', [
        'flex flex-wrap items-center gap-0.5',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]',
        'border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-subtle)]',
    ]), $scope);
@endphp

{{-- The arrow keys are bound on the container rather than on each button: keydown
     bubbles out of the button (and out of the tooltip wrapper around it), so one
     pair of handlers covers a vocabulary of any length and there is nothing to
     keep in step when a command is added. focusToolbarCommand() lives in the
     wirekitEditor Alpine component, the same scope the buttons already call
     cmd() and isActive() in. --}}
<div
    role="toolbar"
    aria-label="{{ __('wirekit::Editor commands') }}"
    x-on:keydown.arrow-right.prevent="focusToolbarCommand($event, 'next')"
    x-on:keydown.arrow-left.prevent="focusToolbarCommand($event, 'prev')"
    x-on:keydown.home.prevent="focusToolbarCommand($event, 'first')"
    x-on:keydown.end.prevent="focusToolbarCommand($event, 'last')"
    {{ $attributes->class([$toolbarClasses]) }}
>
    @foreach($commands as $commandKey => $command)
        @if($command === '|')
            <span class="mx-1 h-5 w-px shrink-0 bg-[var(--color-wk-border)]" aria-hidden="true"></span>
        @elseif(isset($meta[$command]))
            @php([$label, $glyph, $glyphClass, $active] = $meta[$command])
            {{-- WireKit's own tooltip (not the native browser `title`): a styled,
                 themed hover/focus hint placed below the toolbar. The button keeps
                 `aria-label` as its accessible name, so the tooltip is purely the
                 visual affordance — no double-announce.

                 `focusable-trigger="false"` because the slot is already a button.
                 The tooltip's trigger wrapper is focusable by default, which is right
                 for a tooltip on an icon or a span and wrong here: it puts a
                 `tabindex="0"` div in front of every command, so the toolbar would
                 still be one tab stop per command however the buttons themselves are
                 numbered. The trigger opens on `focusin`, which bubbles, so the
                 button's own focus still shows the hint. --}}
            <x-wirekit::tooltip :text="$label" placement="bottom" focusable-trigger="false">
                <button
                    type="button"
                    {{-- Keep editor focus + selection while running the command (Pitfall #7). --}}
                    x-on:mousedown.prevent=""
                    x-on:click="cmd({{ \Pushery\WireKit\Support\AlpinePayload::string($command) }})"
                    @if(isset($historyGuard[$command]))
                        {{-- Undo/redo disable themselves when the history stack is empty. --}}
                        :disabled="!{{ $historyGuard[$command] }}"
                    @endif
                    @if($active)
                        :aria-pressed="{{ $active }} ? 'true' : 'false'"
                        :class="{{ $active }} ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]' : ''"
                    @endif
                    aria-label="{{ $label }}"
                    tabindex="{{ $commandKey === $tabStopKey ? '0' : '-1' }}"
                    class="{{ $buttonClasses }}"
                >
                    {{-- SVG glyphs are static blade-authored markup from the $meta map
                         above (same safe class as alert's $defaultIcon — never
                         developer-controlled), so raw output is safe here. --}}
                    @if(str_starts_with($glyph, '<svg'))
                        <span class="{{ $glyphClass }}" aria-hidden="true">{!! $glyph !!}</span>
                    @else
                        <span class="{{ $glyphClass }}" aria-hidden="true">{{ $glyph }}</span>
                    @endif
                </button>
            </x-wirekit::tooltip>
        @endif
    @endforeach
</div>
