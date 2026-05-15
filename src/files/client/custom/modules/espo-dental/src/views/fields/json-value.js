define('espo-dental:views/fields/json-value', ['views/fields/base'], function (Dep) {

    return Dep.extend({

        readOnly: true,

        getValueForDisplay: function () {
            var value = this.model.get(this.name);

            if (value === null || value === undefined || value === '') {
                return '';
            }

            if (Array.isArray(value)) {
                return value.length ? value.join(', ') : this.translate('No');
            }

            if (typeof value === 'object') {
                var keys = Object.keys(value);

                if (!keys.length) {
                    return this.translate('No');
                }

                var yes = this.translate('Yes');
                var no = this.translate('No');

                return keys.map(function (key) {
                    var item = value[key];

                    if (item === true) {
                        item = yes;
                    } else if (item === false) {
                        item = no;
                    }

                    return key + ': ' + item;
                }).join('\n');
            }

            return String(value);
        }
    });
});
