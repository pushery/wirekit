<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Htmlable;
use Stringable;

/**
 * What a `field` tells the control inside it.
 *
 * A field labels its control by `for`, and the id it points at is its `name`, the id a plain input
 * takes. A composite control gives its own field an id of its own (`wk-date-…`, `…-input`), and
 * without a label of its own it names itself: with a placeholder sentence, a hidden label made
 * from its name, or not at all. Inside a labeled field that leaves the visible label naming
 * nothing, a click on it focusing nothing, and the control announced by its fallback.
 *
 * A slot renders before the view of the component that holds it, so the field cannot read the
 * control's id. The class behind `field` builds this object in its constructor, and `@aware`
 * hands it to the control. The control takes the label here, records the element a label can
 * name, and leaves its fallback name out; the field's view, which renders after the slot, points
 * its label at what was recorded.
 *
 * Plumbing for the shipped views, not a documented API.
 */
final class FieldControl
{
    private bool $taken = false;

    private ?string $controlId = null;

    private ?string $labelId = null;

    /** The id a control that keeps its own name reported, see follow(). */
    private ?string $followed = null;

    private function __construct(
        private readonly ?string $target,
        private readonly bool $offers,
        private readonly string $labelText,
    ) {}

    public static function open(string|Stringable|null $for, string|Stringable|null $name, Htmlable|string|int|float|null $label): self
    {
        $for = filled($for) ? (string) $for : null;
        $target = $for ?? (filled($name) ? (string) $name : null);
        $text = $label instanceof Htmlable ? trim(strip_tags($label->toHtml())) : trim((string) $label);

        // A field offers its label when it has one, and when it chose its target itself: a caller
        // who points `for` somewhere has wired the label by hand, and that wiring stays theirs.
        return new self($target, $text !== '' && $for === null, $text);
    }

    /**
     * The control takes the field's label as its name, and gets the label's id back.
     *
     * The id is for a part that names itself by reference: a listbox, a slider thumb, an editing
     * surface the browser builds. Null when the field has no label to give, its caller pointed
     * `for` elsewhere, or a control in the same field took the label already; the control then
     * names itself as it would anywhere else.
     *
     * @param  string|null  $controlId  the element a label can name, or null when the control has none
     */
    public function takeLabel(?string $controlId): ?string
    {
        if (! $this->offers || $this->taken) {
            return null;
        }

        $this->taken = true;
        $this->controlId = filled($controlId) ? $controlId : null;

        return $this->labelId = DomId::unique(($this->target ?? 'field').'-label', 'field-label-');
    }

    /**
     * A control that does not take the label reports the id it got, and the label points there.
     *
     * A plain field takes its id from its `name`, which is the field's target, but a second one
     * with the same name on the page gets `-2` from DomId: pointed at the name, the second field's
     * label named the first one's control. The first control to report wins, and a caller who
     * pointed `for` somewhere keeps that wiring, as with takeLabel().
     */
    public function follow(string $controlId): void
    {
        if ($this->offers && ! $this->taken && $this->followed === null && $controlId !== '') {
            $this->followed = $controlId;
        }
    }

    /** The visible label's text, for a control that names its parts with it. */
    public function labelText(): string
    {
        return $this->labelText;
    }

    /**
     * What the field's label points its `for` at.
     *
     * The control that took the label, or nothing when that control has no element a label can
     * name and is named by reference instead. Before any control took it, the id a plain control
     * reported through follow(), else the target the field was given: its `for`, or its `name`,
     * which is the id a plain input carries.
     */
    public function forId(): ?string
    {
        return $this->taken ? $this->controlId : ($this->followed ?? $this->target);
    }

    /** The label's id, once a control took the label; the label carries no id before that. */
    public function labelId(): ?string
    {
        return $this->labelId;
    }
}
