define('espo-dental:views/patient/fields/balance', ['views/fields/base'], function (Dep) {

    return Dep.extend({

        readOnly: true,

        getValueForDisplay: function () {
            var value = parseFloat(this.model.get(this.name) || 0);
            var currency = this.model.get(this.name + 'Currency') || 'RUB';

            return this.formatMoney(value, currency);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            var value = parseFloat(this.model.get(this.name) || 0);
            var color = '';

            if (value < 0) {
                color = '#c0392b';
            } else if (value > 0) {
                color = '#218838';
            }

            this.$el.css({
                color: color,
                'font-weight': value === 0 ? '' : '600'
            });
        },

        formatMoney: function (value, currency) {
            try {
                return new Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency: currency
                }).format(value);
            } catch (e) {
                return value.toFixed(2) + ' ' + currency;
            }
        }
    });
});
