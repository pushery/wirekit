/**
 * A component measures itself with the others started in the same task, once that task is done.
 *
 * A read of a size lays the page out first when anything changed since the last read. Alpine starts
 * every component of a page in one task, and between two components the others write to the page,
 * so a component that measures itself at its start lays the whole page out again, once per
 * component, and a page of many of them stalls. Read together once the task is done, they all read
 * one layout, and still before the first frame, so the first paint shows the sizes they read.
 *
 * A measure that writes through Alpine writes after every measure has read, as Alpine writes once
 * the code that changed its data has returned. A measure that writes to the page itself, such as an
 * attribute only the stylesheet reads, makes the next read bring the styles up to date, which needs
 * no new layout as long as the attribute changes no size.
 */

const waiting = new Set();

/** Run each measure, and let one that fails leave the measures after it their turn. */
export function measureEach(measures) {
    for (const measure of measures) {
        try {
            measure?.();
        } catch (error) {
            globalThis.reportError?.(error);
        }
    }
}

/**
 * Measure with the others started in this task, once it is done.
 *
 * @param {() => void} measure
 * @returns {() => void} take the measure back, for a component that is gone before it runs
 */
export function measureOnceStarted(measure) {
    if (waiting.size === 0) {
        queueMicrotask(() => {
            const measures = [...waiting];
            waiting.clear();
            measureEach(measures);
        });
    }

    waiting.add(measure);

    return () => {
        waiting.delete(measure);
    };
}

let waitingForTheFonts = null;

/**
 * Measure again once the page's fonts are in, with the others that asked for it in this task.
 *
 * A text measured in the fallback face can change its width when the web font arrives. Reading
 * `document.fonts.ready` brings the styles and the layout up to date in Blink, even long after the
 * fonts are in, so a component that read it at its start laid the page out once per component.
 * The measures that ask in one task read it once between them, once that task is done, and run
 * together when it settles.
 *
 * @param {() => void} measure
 * @returns {() => void} take the measure back, for a component that is gone before the fonts are in
 */
export function measureOnceTheFontsAreIn(measure) {
    if (typeof document === 'undefined' || !document.fonts) {
        return () => {};
    }

    if (waitingForTheFonts === null) {
        const measures = new Set();
        // The fonts checked above: their promise is read from them once the task is done.
        const fonts = document.fonts;
        waitingForTheFonts = measures;

        queueMicrotask(() => {
            waitingForTheFonts = null;
            fonts.ready?.then?.(() => measureEach([...measures]));
        });
    }

    const measures = waitingForTheFonts;
    measures.add(measure);

    return () => {
        measures.delete(measure);
    };
}
