define('espo-dental:handlers/invoice/accept-payment', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionAcceptPayment: function () {
            var view = this.view;
            var model = view.model;
            if (['storno', 'cancelled', 'draft', 'paid'].indexOf(model.get('status')) !== -1) {
                Espo.Ui.warning(view.translate('Invoice not payable', 'messages', 'Invoice'));
                return;
            }

            var balance = parseFloat(model.get('balance') || 0);
            this.openDialog(Math.max(0, balance), function (amount, method) {
                var payload = {
                    patientId: model.get('patientId'),
                    clinicId: model.get('clinicId'),
                    invoiceId: model.id,
                    amount: amount,
                    method: method
                };

                Espo.Ajax.postRequest('Payment/action/accept', payload)
                    .then(function (response) {
                        Espo.Ui.success(
                            view.translate('Payment accepted', 'messages', 'Invoice') +
                            ' ' + (response && response.number ? response.number : '')
                        );
                        model.fetch();
                    });
            }.bind(this));
        },

        openDialog: function (defaultAmount, callback) {
            var view = this.view;
            var methods = this.getPaymentMethods();
            var html =
                '<div class="espo-dental-payment-dialog-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,0.32);z-index:1050"></div>' +
                '<div class="espo-dental-payment-dialog" style="position:fixed;top:72px;left:50%;transform:translateX(-50%);width:min(420px,calc(100vw - 24px));background:#fff;border:1px solid #cfd6df;border-radius:6px;box-shadow:0 14px 40px rgba(0,0,0,0.28);z-index:1060;padding:16px">' +
                    '<h4 style="margin:0 0 14px">' + this.escapeHtml(view.translate('Accept Payment', 'labels', 'Invoice')) + '</h4>' +
                    '<div class="form-group">' +
                        '<label>' + this.escapeHtml(view.translate('Payment amount', 'messages', 'Invoice')) + '</label>' +
                        '<input class="form-control" name="amount" type="number" min="0.01" step="0.01" value="' + this.escapeAttribute(defaultAmount.toFixed(2)) + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + this.escapeHtml(view.translate('method', 'fields', 'Payment')) + '</label>' +
                        '<select class="form-control" name="method">' +
                            methods.map(function (method) {
                                return '<option value="' + this.escapeAttribute(method.id) + '">' +
                                    this.escapeHtml(method.label || this.translatePaymentMethod(method.id)) +
                                    '</option>';
                            }.bind(this)).join('') +
                        '</select>' +
                    '</div>' +
                    '<div style="text-align:right;margin-top:16px">' +
                        '<button class="btn btn-default" data-action="cancel">' + this.escapeHtml(view.translate('Cancel', 'labels', 'Global')) + '</button> ' +
                        '<button class="btn btn-primary" data-action="save">' + this.escapeHtml(view.translate('Accept Payment', 'labels', 'Invoice')) + '</button>' +
                    '</div>' +
                '</div>';

            var $dialog = window.jQuery(html);
            window.jQuery(document.body).append($dialog);

            var close = function () {
                $dialog.remove();
            };

            $dialog.find('[data-action="cancel"]').on('click', close);
            $dialog.find('[data-action="save"]').on('click', function () {
                var amount = parseFloat($dialog.find('[name="amount"]').val());
                var method = $dialog.find('[name="method"]').val() || 'cash';

                if (!(amount > 0)) {
                    Espo.Ui.warning(view.translate('Invalid amount', 'messages', 'Invoice'));
                    return;
                }

                close();
                callback(amount, method);
            });
        },

        getPaymentMethods: function () {
            var configured = null;
            var config = this.view && typeof this.view.getConfig === 'function' ? this.view.getConfig() : null;
            if (config && typeof config.get === 'function') {
                configured = config.get('espoDentalPaymentMethods');
            }

            var methods = this.normalizeOptionList(configured);
            if (!methods.length) {
                methods = this.normalizeOptionList([
                    {id: 'cash'},
                    {id: 'card'},
                    {id: 'bank_transfer'},
                    {id: 'online'},
                    {id: 'terminal'},
                    {id: 'crypto'},
                    {id: 'other'}
                ]);
            }

            return methods;
        },

        normalizeOptionList: function (value) {
            if (typeof value === 'string' && value !== '') {
                try {
                    value = JSON.parse(value);
                } catch (e) {
                    value = [];
                }
            }
            if (!Array.isArray(value)) {
                return [];
            }

            var seen = {};
            return value.map(function (item) {
                if (typeof item === 'string') {
                    return {id: item, label: this.translatePaymentMethod(item)};
                }
                item = item || {};
                var id = String(item.id || item.value || item.name || '').trim();
                if (!id || seen[id]) {
                    return null;
                }
                seen[id] = true;
                return {
                    id: id,
                    label: this.pickConfiguredLabel(item) || this.translatePaymentMethod(id)
                };
            }.bind(this)).filter(function (item) {
                return !!item;
            });
        },

        pickConfiguredLabel: function (item) {
            var language = this.getConfiguredLanguage();
            if (
                language &&
                item.labels &&
                typeof item.labels === 'object' &&
                typeof item.labels[language] === 'string' &&
                item.labels[language] !== ''
            ) {
                return item.labels[language];
            }
            if (typeof item.label === 'string' && item.label !== '') {
                return item.label;
            }
            if (typeof item.name === 'string' && item.name !== '') {
                return item.name;
            }
            return '';
        },

        getConfiguredLanguage: function () {
            var config = this.view && typeof this.view.getConfig === 'function' ? this.view.getConfig() : null;
            if (!config || typeof config.get !== 'function') {
                return '';
            }

            return config.get('language') || '';
        },

        translatePaymentMethod: function (method) {
            var key = 'Payment method ' + method;
            var label = this.view.translate(key, 'messages', 'Invoice');
            return label === key ? method.replace(/_/g, ' ') : label;
        },

        escapeHtml: function (value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        escapeAttribute: function (value) {
            return this.escapeHtml(value);
        }
    });
});
