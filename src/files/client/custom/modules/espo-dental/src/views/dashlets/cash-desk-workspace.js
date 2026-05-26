define('espo-dental:views/dashlets/cash-desk-workspace', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, SimpleStomUi) {
    return Dep.extend({
        name: 'CashDeskWorkspace',
        templateContent: '<div class="espo-dental-cash-desk-workspace"></div>',

        events: {
            'change [data-name="unpaidOnly"]': 'fetchWorkspace',
            'change [data-name="doctorId"]': 'changeDoctorFilter',
            'click [data-action="selectInvoice"]': 'selectInvoice',
            'click [data-action="openPaymentWizard"]': 'openPaymentWizard',
            'click [data-action="openInvoice"]': 'openSelectedInvoice',
            'click [data-action="openPatient"]': 'openSelectedPatient',
            'click [data-action="openVisit"]': 'openSelectedVisit',
            'click [data-action="closeShift"]': 'closeShift'
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.doctorId = '';
            this.unpaidOnly = true;
            this.selectedInvoice = null;
            this.selectedInvoiceId = null;
        },

        afterRender: function () {
            SimpleStomUi.ensureStyles();
            this.renderShell();
            this.fetchWorkspace();
        },

        renderShell: function () {
            var html = '<div class="espo-dental-stom-layout espo-dental-stom-layout--two">' +
                '<div data-name="cashFilters"></div>' +
                '<div data-name="cashBody"></div>' +
                '</div>';

            this.$el.find('.espo-dental-cash-desk-workspace').html(SimpleStomUi.workspace(html));
        },

        fetchWorkspace: function () {
            var $unpaidOnly = this.$el.find('[data-name="unpaidOnly"]');
            var unpaidOnly = $unpaidOnly.length ? $unpaidOnly.is(':checked') : this.unpaidOnly;
            var doctorId = this.doctorId || this.$el.find('[data-name="doctorId"]').val() || '';

            this.unpaidOnly = unpaidOnly;
            this.doctorId = doctorId;
            this.$el.find('[data-name="cashBody"]').html(SimpleStomUi.emptyState('Загрузка кассы...'));

            Espo.Ajax.getRequest('EspoDental/CashDesk/workspace', {
                unpaidOnly: unpaidOnly ? 'true' : 'false',
                doctorId: doctorId,
                selectedInvoiceId: this.selectedInvoiceId || '',
                limit: parseInt(this.getOption('displayRecords'), 10) || 30
            }).then((function (data) {
                this.renderWorkspace(data || {});
            }).bind(this)).catch((function () {
                this.$el.find('[data-name="cashBody"]').html(SimpleStomUi.emptyState('Не удалось загрузить кассу.'));
            }).bind(this));
        },

        renderWorkspace: function (data) {
            this.selectedInvoice = data.selectedInvoice || null;
            this.selectedInvoiceId = this.selectedInvoice ? this.selectedInvoice.id : null;
            this.renderFilters(data.filters || {}, data.doctorOptions || []);
            this.renderInvoices(data.invoices || [], data.selectedInvoice || null, data.closingPreview || null);
        },

        renderFilters: function (filters, doctorOptions) {
            this.doctorId = filters.doctorId || this.doctorId || '';

            var checked = filters.unpaidOnly === false ? '' : ' checked';
            var doctorHtml = '<label style="display:block;margin:0 0 10px">' +
                '<span class="espo-dental-stom-muted" style="display:block;margin-bottom:4px">Врач</span>' +
                '<select class="form-control input-sm" data-name="doctorId">' +
                '<option value="">Все врачи</option>';

            (doctorOptions || []).forEach((function (doctor) {
                var selected = doctor.id === this.doctorId ? ' selected' : '';
                doctorHtml += '<option value="' + SimpleStomUi.escapeHtml(doctor.id) + '"' + selected + '>' +
                    SimpleStomUi.escapeHtml(doctor.name || doctor.id) +
                    '</option>';
            }).bind(this));

            doctorHtml += '</select></label>';

            var html = doctorHtml +
                '<label style="display:flex;align-items:center;gap:6px;margin:0">' +
                '<input type="checkbox" data-name="unpaidOnly"' + checked + '> Только неоплаченные' +
                '</label>';

            this.$el.find('[data-name="cashFilters"]').html(SimpleStomUi.panel({
                title: 'Фильтры',
                body: html,
                classes: ['espo-dental-stom-panel--compact']
            }));
        },

        renderInvoices: function (invoices, selectedInvoice, closingPreview) {
            var html = '<div class="espo-dental-stom-toolbar" style="margin-bottom:8px">' +
                SimpleStomUi.button('Закрыть смену', {tone: 'primary', attrs: {'data-action': 'closeShift'}}) +
                '</div>';

            html += this.renderClosingPreview(closingPreview);
            html += '<div class="espo-dental-stom-layout espo-dental-stom-layout--two" ' +
                'style="grid-template-columns:minmax(0,1fr) minmax(260px,340px)">';
            html += SimpleStomUi.panel({
                title: 'Счета к оплате',
                body: invoices.length ? this.renderInvoiceTable(invoices) : SimpleStomUi.emptyState('Счетов для оплаты нет.')
            });
            html += this.renderSelectedInvoicePanel(selectedInvoice);
            html += '</div>';

            this.$el.find('[data-name="cashBody"]').html(html);
        },

        renderInvoiceTable: function (invoices) {
            var html = '<table class="espo-dental-stom-table"><thead><tr>' +
                '<th>Счет</th><th>Пациент</th><th>Врач</th><th>Статус</th><th>Остаток</th>' +
                '</tr></thead><tbody>';

            invoices.forEach((function (invoice) {
                var selected = invoice.id === this.selectedInvoiceId;
                var rowStyle = selected ? ' style="background:#f7faf8"' : '';
                html += '<tr data-invoice-id="' + SimpleStomUi.escapeHtml(invoice.id || '') + '"' +
                    (selected ? ' data-selected-invoice="true"' : '') + rowStyle + '>' +
                    '<td><button type="button" class="btn btn-link btn-sm" data-action="selectInvoice" ' +
                    'data-id="' + SimpleStomUi.escapeHtml(invoice.id || '') + '" style="padding:0;text-align:left">' +
                    SimpleStomUi.escapeHtml(invoice.number || invoice.id || '') +
                    '</button></td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.patientName || invoice.patientId || '') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.doctorName || invoice.doctorId || '') + '</td>' +
                    '<td>' + SimpleStomUi.badge(invoice.status || 'issued', invoice.status || 'issued') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(this.formatMoney(invoice.balance || 0)) + '</td>' +
                    '</tr>';
            }).bind(this));

            html += '</tbody></table>';

            return html;
        },

        renderSelectedInvoicePanel: function (invoice) {
            if (!invoice) {
                return SimpleStomUi.panel({
                    title: 'Выбранный счет',
                    body: SimpleStomUi.emptyState('Выберите счет для действий.'),
                    classes: ['espo-dental-stom-panel--compact']
                });
            }

            var html = '<div class="espo-dental-stom-toolbar">' +
                SimpleStomUi.badge(invoice.status || 'issued', invoice.status || 'issued') +
                (invoice.payable ? SimpleStomUi.badge('можно оплатить', 'success') : SimpleStomUi.badge('нет действия оплаты', 'muted')) +
                '</div>';

            html += '<table class="espo-dental-stom-table" style="margin-bottom:10px"><tbody>' +
                this.renderKeyValue('Счет', invoice.number || invoice.id || '') +
                this.renderKeyValue('Пациент', invoice.patientName || invoice.patientId || '') +
                this.renderKeyValue('Врач', invoice.doctorName || invoice.doctorId || '') +
                this.renderKeyValue('Прием', invoice.visitName || invoice.visitId || '') +
                this.renderKeyValue('Дата', String(invoice.issuedAt || '').slice(0, 16)) +
                this.renderKeyValue('Итого', this.formatMoney(invoice.totalAmount || 0)) +
                this.renderKeyValue('Оплачено', this.formatMoney(invoice.paidAmount || 0)) +
                this.renderKeyValue('Остаток', this.formatMoney(invoice.balance || 0)) +
                '</tbody></table>';

            html += '<div class="espo-dental-stom-toolbar" style="margin:0">' +
                SimpleStomUi.button('Принять оплату', {
                    tone: invoice.payable ? 'primary' : 'quiet',
                    attrs: {
                        'data-action': 'openPaymentWizard',
                        disabled: invoice.payable ? null : 'disabled'
                    }
                }) +
                SimpleStomUi.button('Открыть счет', {tone: 'quiet', attrs: {'data-action': 'openInvoice'}}) +
                SimpleStomUi.button('Пациент', {tone: 'quiet', attrs: {'data-action': 'openPatient'}}) +
                (invoice.visitId ? SimpleStomUi.button('Прием', {tone: 'quiet', attrs: {'data-action': 'openVisit'}}) : '') +
                '</div>';

            return SimpleStomUi.panel({
                title: 'Выбранный счет',
                body: html,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderKeyValue: function (label, value) {
            return '<tr><th>' + SimpleStomUi.escapeHtml(label) + '</th><td>' +
                SimpleStomUi.escapeHtml(value || '') +
                '</td></tr>';
        },

        renderClosingPreview: function (preview) {
            if (!preview) {
                return '';
            }

            return '<div class="espo-dental-stom-toolbar" style="margin-bottom:8px">' +
                SimpleStomUi.badge('Наличные ' + this.formatMoney(preview.cashTotal || 0), 'primary') +
                SimpleStomUi.badge('Карта ' + this.formatMoney(preview.cardTotal || 0)) +
                SimpleStomUi.badge('Криптовалюта ' + this.formatMoney(preview.cryptoTotal || 0)) +
                SimpleStomUi.badge('Аванс ' + this.formatMoney(preview.advanceTotal || 0)) +
                '</div>';
        },

        changeDoctorFilter: function (e) {
            this.doctorId = $(e.currentTarget).val() || '';
            this.selectedInvoiceId = null;
            this.fetchWorkspace();
        },

        selectInvoice: function (e) {
            this.selectedInvoiceId = $(e.currentTarget).attr('data-id') || null;
            this.fetchWorkspace();
        },

        openPaymentWizard: function () {
            if (!this.selectedInvoice || !this.selectedInvoice.payable) {
                this.notify('Этот счет нельзя оплатить.', 'warning');
                return;
            }

            this.openPaymentDialog(this.selectedInvoice);
        },

        openSelectedInvoice: function () {
            if (this.selectedInvoice && this.selectedInvoice.id) {
                this.getRouter().navigate('#Invoice/view/' + this.selectedInvoice.id, {trigger: true});
            }
        },

        openSelectedPatient: function () {
            if (this.selectedInvoice && this.selectedInvoice.patientId) {
                this.getRouter().navigate('#Patient/view/' + this.selectedInvoice.patientId, {trigger: true});
            }
        },

        openSelectedVisit: function () {
            if (this.selectedInvoice && this.selectedInvoice.visitId) {
                this.getRouter().navigate('#Visit/view/' + this.selectedInvoice.visitId, {trigger: true});
            }
        },

        openPaymentDialog: function (invoice) {
            var methods = this.getPaymentMethods();
            var defaultAmount = Math.max(0, parseFloat(invoice.balance || 0));
            var html =
                '<div class="espo-dental-payment-dialog-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,0.32);z-index:1050"></div>' +
                '<div class="espo-dental-payment-dialog" style="position:fixed;top:72px;left:50%;transform:translateX(-50%);width:min(420px,calc(100vw - 24px));background:#fff;border:1px solid #cfd6df;border-radius:6px;box-shadow:0 14px 40px rgba(0,0,0,0.28);z-index:1060;padding:16px">' +
                    '<h4 style="margin:0 0 14px">Принять оплату</h4>' +
                    '<div class="espo-dental-stom-muted" style="margin-bottom:12px">' +
                        SimpleStomUi.escapeHtml((invoice.number || invoice.id || '') + ' · ' + (invoice.patientName || '')) +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Сумма платежа</label>' +
                        '<input class="form-control" name="amount" type="number" min="0.01" step="0.01" value="' + SimpleStomUi.escapeHtml(defaultAmount.toFixed(2)) + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Метод оплаты</label>' +
                        '<select class="form-control" name="method">' +
                            methods.map(function (method) {
                                return '<option value="' + SimpleStomUi.escapeHtml(method.id) + '">' +
                                    SimpleStomUi.escapeHtml(method.label || this.translatePaymentMethod(method.id)) +
                                    '</option>';
                            }.bind(this)).join('') +
                        '</select>' +
                    '</div>' +
                    '<div style="text-align:right;margin-top:16px">' +
                        '<button class="btn btn-default" data-action="cancel">Отмена</button> ' +
                        '<button class="btn btn-primary" data-action="save">Принять оплату</button>' +
                    '</div>' +
                '</div>';

            var $dialog = window.jQuery(html);
            window.jQuery(document.body).append($dialog);

            var close = function () {
                $dialog.remove();
            };

            $dialog.find('[data-action="cancel"]').on('click', close);
            $dialog.find('[data-action="save"]').on('click', (function () {
                var amount = parseFloat($dialog.find('[name="amount"]').val());
                var method = $dialog.find('[name="method"]').val() || 'cash';

                if (!(amount > 0)) {
                    this.notify('Введите положительную сумму платежа.', 'warning');
                    return;
                }

                close();
                this.acceptPayment(invoice, amount, method);
            }).bind(this));
        },

        acceptPayment: function (invoice, amount, method) {
            Espo.Ajax.postRequest('Payment/action/accept', {
                patientId: invoice.patientId,
                clinicId: invoice.clinicId,
                invoiceId: invoice.id,
                amount: amount,
                method: method
            }).then((function () {
                this.notify('Платеж принят.', 'success');
                this.fetchWorkspace();
            }).bind(this)).catch((function () {
                this.notify('Не удалось принять платеж.', 'error');
            }).bind(this));
        },

        getPaymentMethods: function () {
            var configured = null;
            var config = typeof this.getConfig === 'function' ? this.getConfig() : null;
            if (config && typeof config.get === 'function') {
                configured = config.get('espoDentalPaymentMethods');
            }

            var methods = this.normalizeOptionList(configured);
            if (!methods.length) {
                methods = this.normalizeOptionList([
                    {id: 'cash'},
                    {id: 'card'},
                    {id: 'bank_transfer'},
                    {id: 'crypto'},
                    {id: 'advance'}
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
            return value.map((function (item) {
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
            }).bind(this)).filter(function (item) {
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
            var config = typeof this.getConfig === 'function' ? this.getConfig() : null;
            if (!config || typeof config.get !== 'function') {
                return '';
            }

            return config.get('language') || '';
        },

        translatePaymentMethod: function (method) {
            return SimpleStomUi.label(method, 'paymentMethod') || method.replace(/_/g, ' ');
        },

        formatMoney: function (value) {
            var amount = parseFloat(value || 0);

            if (!isFinite(amount)) {
                amount = 0;
            }

            return amount.toFixed(2);
        },

        closeShift: function () {
            this.notify('Выберите клинику перед закрытием смены.', 'warning');
        }
    });
});
