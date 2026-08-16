<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The one place a tab bar's appearance is decided.
 *
 * There are two tab bars in this library and there is no version of that where two class
 * ladders are acceptable. `tabs` owns its selection in Alpine and shows a panel;
 * `tabs.list` + `tabs.tab` are the same bar with the selection on the SERVER and no
 * panels at all — different behavior, identical surface. A reader has to be unable to
 * tell them apart, and a second copy of six variant branches guarantees that they
 * eventually can.
 *
 * The failure would be quiet in the way this codebase keeps rediscovering: nothing
 * breaks, no test goes red, the two bars simply drift a border-radius apart over a few
 * releases until someone screenshots them side by side.
 *
 * The strings are the ones `tabs` already shipped, moved rather than rewritten, and the
 * move is proven by rendering every variant/orientation pair before and after and
 * diffing the markup byte for byte.
 *
 * `resolveClasses` is deliberately NOT called here. It applies the developer's per-
 * component `scope` override, which is a question about a call site rather than about a
 * variant — the caller holds the scope and stays the one to apply it.
 */
final class TablistStyles
{
    /**
     * Classes for the `role="tablist"` container.
     *
     * The horizontal bars cap at the container width and scroll, which is what keeps a
     * narrow viewport from squishing labels. That is safe against WCAG 2.1.1 without a
     * `tabindex` on the scroll region, because the container carries `role="tablist"`
     * and owns a keyboard model — the composite-widget shape of the scroll-region rule.
     */
    public static function list(string $variant, bool $vertical): string
    {
        return match (true) {
            // Vertical — stack the tabs; the active indicator becomes the inline-end
            // border for underline, and a full-width pill or segment otherwise.
            $vertical && $variant === 'pills' => 'flex flex-col gap-1 p-1 rounded-[var(--radius-wk-lg)] bg-[var(--color-wk-bg-muted)]',
            $vertical && $variant === 'bordered' => 'flex flex-col border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] overflow-hidden',
            $vertical => 'flex flex-col items-stretch border-r-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
            // Horizontal (default).
            $variant === 'pills' => 'inline-flex items-center gap-1 p-1 rounded-[var(--radius-wk-lg)] bg-[var(--color-wk-bg-muted)] max-w-full overflow-x-auto overflow-y-hidden',
            $variant === 'bordered' => 'inline-flex items-center border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] overflow-hidden max-w-full overflow-x-auto',
            default => 'inline-flex items-center gap-2 border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)] max-w-full overflow-x-auto overflow-y-hidden',
        };
    }

    /**
     * Classes every tab carries regardless of state.
     *
     * `shrink-0 whitespace-nowrap` keep each tab at its natural label width inside the
     * scrollable bar — without them a narrow viewport squishes the tabs and wraps or
     * clips the labels instead of letting the bar scroll.
     */
    public static function tab(string $variant, bool $vertical): string
    {
        $base = 'inline-flex items-center gap-2 shrink-0 whitespace-nowrap font-[number:var(--font-wk-body-weight)] text-[length:var(--text-wk-sm)] transition-colors duration-[var(--transition-wk-duration)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed cursor-pointer';

        $variantClasses = match (true) {
            // Vertical — left-align the content; the underline indicator moves to the
            // inline-end edge.
            $vertical && $variant === 'pills' => 'justify-start p-[var(--padding-wk-x-sm)] rounded-[var(--radius-wk-md)]',
            $vertical && $variant === 'bordered' => 'justify-start p-[var(--padding-wk-x-sm)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)] last:border-b-0',
            $vertical => 'justify-start p-[var(--padding-wk-x-sm)] -mr-[length:var(--border-wk-width)] border-r-[3px] border-transparent',
            // Horizontal (default).
            $variant === 'pills' => 'p-[var(--padding-wk-x-sm)] rounded-[var(--radius-wk-md)]',
            $variant === 'bordered' => 'p-[var(--padding-wk-x-sm)] border-r-[length:var(--border-wk-width)] border-[var(--color-wk-border)] last:border-r-0',
            default => 'p-[var(--padding-wk-x-sm)] -mb-[length:var(--border-wk-width)] border-b-[3px] border-transparent',
        };

        return $base.' '.$variantClasses;
    }

    /** Classes for the selected tab. */
    public static function tabActive(string $variant): string
    {
        return match ($variant) {
            'pills' => 'bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-sm)]',
            'bordered' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
            default => 'border-[var(--color-wk-accent)] text-[color:var(--color-wk-text)]',
        };
    }

    /** Classes for every tab that is not selected. */
    public static function tabInactive(): string
    {
        return 'text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)]';
    }
}
