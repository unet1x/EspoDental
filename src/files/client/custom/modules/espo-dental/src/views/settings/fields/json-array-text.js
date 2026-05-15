define('espo-dental:views/settings/fields/json-array-text', ['views/fields/text'], function (Dep) {

    return Dep.extend({

        rowsMin: 8,
        autoHeightDisabled: true,
        validations: ['jsonArray'],

        getValueForDisplay: function () {
            var value = this.model.get(this.name);

            if (value === null || value === undefined || value === '') {
                value = this.params.default || [];
            }

            if (typeof value === 'string') {
                return value;
            }

            try {
                return JSON.stringify(value, null, 2);
            } catch (e) {
                return '';
            }
        },

        fetch: function () {
            var data = {};
            var value = this.$element.val() || '[]';
            var parsed;

            try {
                parsed = JSON.parse(value);
            } catch (e) {
                data[this.name] = this.model.get(this.name);
                return data;
            }

            data[this.name] = Array.isArray(parsed) ? parsed : this.model.get(this.name);

            return data;
        },

        validateJsonArray: function () {
            var value = this.$element.val() || '[]';
            var parsed;

            try {
                parsed = JSON.parse(value);
            } catch (e) {
                this.showValidationMessage('JSON: ' + e.message);
                return true;
            }

            if (!Array.isArray(parsed)) {
                this.showValidationMessage('JSON value must be an array.');
                return true;
            }

            return false;
        }
    });
});
