/**
 * The triggers of shared tooltips that carry no component of their own.
 *
 * A tooltip of words alone that draws into the page's shared panel, around a slot that holds no
 * control, is one element: `data-wk-tip` names the element its words stand in, the trigger is its
 * own tab stop and carries its description, and the panel is the page's. A table can hold a hundred
 * of them in one block, and with a component each, every one wrote an Alpine root with eight
 * listeners, its panel's class list and a trigger span inside it.
 *
 * One listener per event on the document drives them all. The first event on a trigger makes its
 * tooltip from the same factory every tooltip runs, held in a WeakMap, so showing, hiding, the
 * timers, the placement and the repair after an update are the code of a tooltip with a component
 * of its own. The factory reads four Alpine magics, and this host gives each tooltip its element
 * for `$el`, `$root` and `$refs.trigger`, and a microtask for `$nextTick`.
 *
 * `mouseenter` and `mouseleave` do not bubble, so the host reads `mouseover` and `mouseout` and
 * compares `relatedTarget`, for every trigger from the target up: a trigger inside another one
 * hears its own enter and leave, as a wrapper with listeners of its own does. Escape is heard once,
 * on the window in the capture phase, and reaches every tooltip this host made, as the listener
 * each tooltip with a component adds there.
 */

const TIP = '[data-wk-tip]';
const INSTALLED = Symbol.for('wirekit.sharedTooltips');

/** The factory's defaults, which a trigger writes only where it differs from them. */
const DEFAULTS = { placement: 'top', offset: 6, delayShow: 300, delayHide: 100 };

/**
 * A number an attribute holds, or the default where it holds none. `0` is a value: `delay-show="0"`
 * shows at once.
 */
function numberOf(element, name, fallback) {
    const value = element.getAttribute(name);

    if (value === null || value.trim() === '') {
        return fallback;
    }

    const number = Number(value);

    return Number.isFinite(number) ? number : fallback;
}

/** Every trigger from the target up, the nearest first. */
export function triggersAround(target) {
    const found = [];
    let trigger = typeof target?.closest === 'function' ? target.closest(TIP) : null;

    while (trigger) {
        found.push(trigger);
        const parent = trigger.parentElement;
        trigger = parent && typeof parent.closest === 'function' ? parent.closest(TIP) : null;
    }

    return found;
}

/** Whether an event between two elements crossed the edge of the trigger. */
function crosses(trigger, other) {
    return ! (other && typeof trigger.contains === 'function' && trigger.contains(other));
}

/**
 * Drive every trigger of a shared tooltip on the page from the document.
 *
 * `factory` returns the tooltip factory, or a promise of it where the bundle loads the tooltip's
 * module on demand. Installed once per window; a second call is a no-op.
 *
 * @param {Window} win
 * @param {() => Function|Promise<Function>} factory
 * @returns {{ tooltipOf: (trigger: Element) => Promise<object|null>, made: Set<object> }|null}
 */
export function installSharedTooltips(win, factory) {
    if (! win || ! win.document || win[INSTALLED]) {
        return win?.[INSTALLED] ?? null;
    }

    const doc = win.document;
    const tooltips = new WeakMap();
    // Every tooltip this host made that has not left the page, for Escape and a page change.
    const made = new Set();
    let resolved = null;

    const factoryNow = () => {
        if (resolved) {
            return resolved;
        }

        const answer = factory();

        if (answer && typeof answer.then === 'function') {
            return answer.then((loaded) => {
                resolved = loaded;

                return loaded;
            });
        }

        resolved = answer;

        return answer;
    };

    /** The trigger's tooltip, with what the trigger says now; null where the factory is missing. */
    const make = (make, trigger) => {
        let tooltip = tooltips.get(trigger);

        if (! tooltip) {
            if (typeof make !== 'function') {
                return null;
            }

            tooltip = make({ shared: true });
            tooltip.$el = trigger;
            tooltip.$root = trigger;
            tooltip.$refs = { trigger };
            tooltip.$nextTick = (callback) => new Promise((resolve) => {
                queueMicrotask(() => {
                    callback?.();
                    resolve();
                });
            });
            tooltips.set(trigger, tooltip);
        }

        // Read on every event: an update writes the trigger anew, and a render names its words
        // with a new id each time.
        tooltip._textFrom = trigger.getAttribute('data-wk-tip') || null;
        tooltip._placement = trigger.getAttribute('data-wk-tip-placement') || DEFAULTS.placement;
        tooltip._offset = numberOf(trigger, 'data-wk-tip-offset', DEFAULTS.offset);
        tooltip._delayShow = numberOf(trigger, 'data-wk-tip-delay-show', DEFAULTS.delayShow);
        tooltip._delayHide = numberOf(trigger, 'data-wk-tip-delay-hide', DEFAULTS.delayHide);
        made.add(tooltip);

        return tooltip;
    };

    /** The trigger's tooltip, once the factory is there. */
    const tooltipOf = (trigger) => {
        const loaded = factoryNow();

        return loaded && typeof loaded.then === 'function'
            ? loaded.then((ready) => make(ready, trigger))
            : Promise.resolve(make(loaded, trigger));
    };

    /**
     * Hand an event to the trigger's tooltip. Synchronously where the factory is there, which
     * every bundle but the per-page one has from the start, so a handler runs inside the event it
     * answers, as a listener of the trigger's own does.
     */
    const handle = (trigger, run) => {
        const loaded = factoryNow();

        if (loaded && typeof loaded.then === 'function') {
            loaded.then((ready) => {
                const tooltip = make(ready, trigger);

                if (tooltip && trigger.isConnected !== false) {
                    run(tooltip);
                }
            });

            return;
        }

        const tooltip = make(loaded, trigger);

        if (tooltip) {
            run(tooltip);
        }
    };

    doc.addEventListener('mouseover', (event) => {
        for (const trigger of triggersAround(event.target)) {
            if (crosses(trigger, event.relatedTarget)) {
                handle(trigger, (tooltip) => tooltip.mouseenter());
            }
        }
    });

    doc.addEventListener('mouseout', (event) => {
        for (const trigger of triggersAround(event.target)) {
            if (crosses(trigger, event.relatedTarget)) {
                handle(trigger, (tooltip) => tooltip.mouseleave());
            }
        }
    });

    doc.addEventListener('focusin', (event) => {
        for (const trigger of triggersAround(event.target)) {
            handle(trigger, (tooltip) => tooltip.focusin(event));
        }
    });

    doc.addEventListener('focusout', (event) => {
        for (const trigger of triggersAround(event.target)) {
            handle(trigger, (tooltip) => tooltip.focusout());
        }
    });

    doc.addEventListener('pointerdown', (event) => {
        for (const trigger of triggersAround(event.target)) {
            handle(trigger, (tooltip) => tooltip.pointerdown(event));
        }
    });

    doc.addEventListener('pointerup', (event) => {
        for (const trigger of triggersAround(event.target)) {
            handle(trigger, (tooltip) => tooltip.pointerup(event));
        }
    });

    // `pointerleave` does not bubble either: `pointerout` across the trigger's edge stands for it.
    doc.addEventListener('pointerout', (event) => {
        for (const trigger of triggersAround(event.target)) {
            if (crosses(trigger, event.relatedTarget)) {
                handle(trigger, (tooltip) => tooltip.pointerleave(event));
            }
        }
    });

    // Enter and Space on a trigger that is a button show the tooltip at once, as activating a
    // toggletip does; the trigger holds no control, so the key is the trigger's own.
    doc.addEventListener('keydown', (event) => {
        const trigger = event.target;

        if (
            (event.key !== 'Enter' && event.key !== ' ')
            || typeof trigger?.matches !== 'function'
            || ! trigger.matches(`${TIP}[role="button"]`)
        ) {
            return;
        }

        handle(trigger, (tooltip) => tooltip.activate(event));
    });

    // Escape, once for every tooltip this host made: heard on the window in the capture phase, so
    // a modal or a drawer around the reader reads a press a tooltip on screen marked as handled.
    win.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' && event.key !== 'Esc') {
            return;
        }

        for (const tooltip of made) {
            if (tooltip.$root?.isConnected === false) {
                tooltip._forceClose();
                made.delete(tooltip);

                continue;
            }

            tooltip.keydownEscape(event);
        }
    }, true);

    // A page change closes every one of them, as it closes a tooltip with a component.
    doc.addEventListener('livewire:navigating', () => {
        for (const tooltip of made) {
            tooltip._forceClose();
        }

        made.clear();
    });

    const installed = { tooltipOf, made };
    win[INSTALLED] = installed;

    return installed;
}
