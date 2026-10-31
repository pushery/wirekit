/**
 * Whether a press landed on the control that opens a panel.
 *
 * A panel teleported out of its component closes on Alpine's `click.outside`, which counts
 * everything outside the panel as outside, the trigger included. The click that opens the panel
 * can reach that handler once the panel shows, and then closes it in the same click: the focus
 * falls to the body and the panel never appears. The trigger's own click toggles the panel, so
 * the outside handler leaves a press on the trigger to it.
 *
 * The trigger is the component's `x-ref`, or, where the refs registered in a nested scope, the
 * element of that name inside the component's root, which a teleport never moves.
 *
 * @param {Event|null} event - The click the outside handler received
 * @param {object} scope - The component's Alpine scope
 * @param {string} [ref='trigger'] - The name of the trigger's `x-ref`
 * @returns {boolean}
 */
export function pressedTrigger(event, scope, ref = 'trigger') {
    const target = event?.target ?? null;

    if (target === null) {
        return false;
    }

    const trigger = scope?.$refs?.[ref] ?? scope?.$root?.querySelector?.(`[x-ref="${ref}"]`) ?? null;

    return trigger?.contains?.(target) === true;
}
