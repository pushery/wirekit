/**
 * The initials an avatar shows for a person without a picture.
 *
 * The first letter or digit of the first word and of the last: "Ada Byron Lovelace" is "AL",
 * "(Ada) Lovelace" is "AL" and "Ada" is "A", and a word with neither, an emoji after a name, gives
 * nothing. Read by code point, so a letter outside the Basic Multilingual Plane stays whole where
 * `charAt(0)` would keep half of it, and the marks that combine with a letter stay with it, so a
 * decomposed "Émile" keeps its accent. Mirrors `Support\Initials::from()` on the server and is held
 * to the same table (tests/fixtures/initials-cases.json), so a person drawn by the browser gets the
 * letters the server gives them everywhere else.
 *
 * @param {string|null|undefined} name
 * @returns {string} the initials, or '' where the name holds no letter and no digit
 */
export function initialsOf(name) {
    const letters = String(name ?? '').trim().split(/\s+/u)
        .map((word) => word.match(/[\p{L}\p{N}]\p{M}*/u)?.[0])
        .filter((letter) => letter !== undefined);

    if (letters.length === 0) {
        return '';
    }

    return (letters.length > 1 ? letters[0] + letters[letters.length - 1] : letters[0]).toUpperCase();
}
