/**
 * The full text of an axis label that ApexCharts shortened, in a tip with the look of the kit's
 * tooltip.
 *
 * ApexCharts shortens an axis label that runs past `labels.maxWidth`, ends the text it draws with
 * "..." and puts the full text into a `<title>`, which the browser shows as a tooltip of its own,
 * after a delay and in its own look. A label whose drawn text is a shortening of its title shows
 * the tip instead, under the pointer and on a touch, and gives up its `<title>` so that the two
 * never show at once. A label drawn whole shows no tip.
 *
 * The chart is an image to assistive technology (`role="img"` on its root), and nothing inside an
 * image takes the focus, so a keyboard and a screen reader find every label in full in the chart's
 * data table (`table`), not here.
 */

const LABEL = '.apexcharts-xaxis-label, .apexcharts-yaxis-label';
const ELLIPSIS = /(?:\.\.\.|…)$/;
// The distance between the label and the tip, and the least distance from the viewport's edge.
const GAP = 6;
const EDGE = 4;

const isTitle = (node) => String(node?.nodeName ?? '').toLowerCase() === 'title';

/** The text a label draws: its own text, without the `<title>` that carries it in full. */
function drawnText(label) {
    let text = '';

    for (const node of label?.childNodes ?? []) {
        if (! isTitle(node)) {
            text += node.textContent ?? '';
        }
    }

    return text.trim();
}

/**
 * The full text of a label ApexCharts shortened, or null for one it drew whole.
 *
 * A drawn text that ends in an ellipsis and with which the full text begins is a shortening. The
 * full text drawn as it is, or a label whose own words end in dots, is not.
 *
 * @param {Element} label  the `<text>` of an axis label
 * @returns {string|null}
 */
export function shortenedLabelText(label) {
    const title = [...(label?.childNodes ?? [])].find(isTitle);
    const full = String(label?.dataset?.wkFullLabel ?? title?.textContent ?? '').trim();
    const drawn = drawnText(label);

    if (full === '' || drawn === full || ! ELLIPSIS.test(drawn)) {
        return null;
    }

    const kept = drawn.replace(ELLIPSIS, '').trimEnd();

    return kept !== '' && full.startsWith(kept) ? full : null;
}

/**
 * Show the full text of a shortened axis label in `tip` while the pointer is on the label or on the
 * tip, and after a touch until the next touch elsewhere. Escape hides it, and so does a scroll,
 * which would leave it standing beside a label that moved away (WCAG 1.4.13: hoverable, dismissible).
 *
 * @param {Element} mount  the element ApexCharts draws into
 * @param {HTMLElement} tip  the empty, hidden element the full text is written into
 * @returns {() => void}  stops watching and hides the tip
 */
export function watchLabelTips(mount, tip) {
    if (! mount || ! tip) {
        return () => {};
    }

    const doc = mount.ownerDocument ?? document;
    const view = doc.defaultView ?? window;
    let current = null;

    const hide = () => {
        current = null;
        tip.hidden = true;
        tip.textContent = '';
    };

    // Above the label and centered on it, below it where the viewport has no room above, and never
    // past either side of the viewport. The tip is `fixed`, so the label's box is all it needs.
    const place = (label) => {
        const box = label.getBoundingClientRect();
        const width = tip.offsetWidth;
        const height = tip.offsetHeight;
        const left = Math.min(Math.max(EDGE, box.left + (box.width - width) / 2), Math.max(EDGE, view.innerWidth - width - EDGE));
        const above = box.top - height - GAP;

        tip.style.left = `${Math.round(left)}px`;
        tip.style.top = `${Math.round(above >= EDGE ? above : box.bottom + GAP)}px`;
    };

    const show = (label, full) => {
        const title = [...label.childNodes].find(isTitle);

        if (title) {
            label.dataset.wkFullLabel = full;
            title.remove();
        }

        current = label;
        tip.textContent = full;
        tip.hidden = false;
        place(label);
    };

    const onOver = (event) => {
        const label = event.target?.closest?.(LABEL) ?? null;

        if (label === null || label === current) {
            return;
        }

        const full = shortenedLabelText(label);

        if (full === null) {
            if (current) {
                hide();
            }

            return;
        }

        show(label, full);
    };

    // A touch ends with the pointer leaving, which would take the tip away before it was read, so
    // a touched label keeps its tip until the next touch elsewhere.
    const onOut = (event) => {
        const to = event.relatedTarget;

        if (current === null || event.pointerType === 'touch' || (to && (current.contains(to) || tip.contains(to)))) {
            return;
        }

        hide();
    };

    const onTipOut = (event) => {
        const to = event.relatedTarget;

        if (event.pointerType === 'touch' || (to && current && current.contains(to))) {
            return;
        }

        hide();
    };

    const onDown = (event) => {
        if (current && ! current.contains(event.target) && ! tip.contains(event.target)) {
            hide();
        }
    };

    const onKey = (event) => {
        if (event.key === 'Escape' && current) {
            hide();
        }
    };

    const onScroll = () => {
        if (current) {
            hide();
        }
    };

    mount.addEventListener('pointerover', onOver);
    mount.addEventListener('pointerout', onOut);
    tip.addEventListener('pointerout', onTipOut);
    doc.addEventListener('pointerdown', onDown, true);
    doc.addEventListener('keydown', onKey);
    view.addEventListener('scroll', onScroll, { capture: true, passive: true });

    return () => {
        mount.removeEventListener('pointerover', onOver);
        mount.removeEventListener('pointerout', onOut);
        tip.removeEventListener('pointerout', onTipOut);
        doc.removeEventListener('pointerdown', onDown, true);
        doc.removeEventListener('keydown', onKey);
        view.removeEventListener('scroll', onScroll, { capture: true });
        hide();
    };
}
