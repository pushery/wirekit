/**
 * WireKit Tags Input Alpine Component.
 *
 * Free-form tag entry: type + Enter/comma to create tags.
 * Backspace on empty input removes the last tag.
 *
 * @param {Object} config
 * @param {string} config.name - Input name for form submission
 * @param {number|null} config.maxTags - Maximum number of tags allowed
 */
export default function wirekitTagsInput(config = {}) {
    return {
        tags: [],
        _maxTags: config.maxTags || null,

        /**
         * Add the current input value as a new tag.
         */
        addTag() {
            const input = this.$refs.input;
            const value = input.value.trim();

            if (!value) return;
            if (this.tags.includes(value)) return; // no duplicates
            if (this._maxTags && this.tags.length >= this._maxTags) return;

            this.tags.push(value);
            input.value = '';
        },

        /**
         * Remove a tag by index.
         */
        removeTag(index) {
            this.tags.splice(index, 1);
        },

        /**
         * Backspace on empty input removes the last tag.
         */
        onBackspace(event) {
            if (event.target.value === '' && this.tags.length > 0) {
                this.tags.pop();
            }
        },
    };
}
