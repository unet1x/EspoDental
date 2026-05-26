define('espo-dental:views/dashlets/cash-desk-workspace', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, SimpleStomUi) {
    return Dep.extend({
        name: 'CashDeskWorkspace',
        templateContent: '<div class="espo-dental-cash-desk-workspace"></div>',

        events: {
            'change [data-name="unpaidOnly"]': 'fetchWorkspace',
            'click [data-action="closeShift"]': 'closeShift'
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
            var unpaidOnly = this.$el.find('[data-name="unpaidOnly"]').is(':checked');
            this.$el.find('[data-name="cashBody"]').html(SimpleStomUi.emptyState('Загрузка кассы...'));

            Espo.Ajax.getRequest('EspoDental/CashDesk/workspace', {
                unpaidOnly: unpaidOnly ? 'true' : 'false',
                limit: parseInt(this.getOption('displayRecords'), 10) || 30
            }).then((function (data) {
                this.renderWorkspace(data || {});
            }).bind(this)).catch((function () {
                this.$el.find('[data-name="cashBody"]').html(SimpleStomUi.emptyState('Не удалось загрузить кассу.'));
            }).bind(this));
        },

        renderWorkspace: function (data) {
            this.renderFilters(data.filters || {});
            this.renderInvoices(data.invoices || [], data.closingPreview || null);
        },

        renderFilters: function (filters) {
            var checked = filters.unpaidOnly === false ? '' : ' checked';
            var html = '<label style="display:flex;align-items:center;gap:6px;margin:0">' +
                '<input type="checkbox" data-name="unpaidOnly"' + checked + '> Только неоплаченные' +
                '</label>';

            this.$el.find('[data-name="cashFilters"]').html(SimpleStomUi.panel({
                title: 'Фильтры',
                body: html,
                classes: ['espo-dental-stom-panel--compact']
            }));
        },

        renderInvoices: function (invoices, closingPreview) {
            var html = '<div class="espo-dental-stom-toolbar" style="margin-bottom:8px">' +
                SimpleStomUi.button('Закрыть смену', {tone: 'primary', attrs: {'data-action': 'closeShift'}}) +
                '</div>';

            html += this.renderClosingPreview(closingPreview);
            html += '<table class="espo-dental-stom-table"><thead><tr>' +
                '<th>Счет</th><th>Пациент</th><th>Статус</th><th>Итого</th><th>Оплачено</th><th>Остаток</th>' +
                '</tr></thead><tbody>';
            invoices.forEach(function (invoice) {
                html += '<tr data-invoice-id="' + SimpleStomUi.escapeHtml(invoice.id || '') + '">' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.number || invoice.id || '') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.patientName || invoice.patientId || '') + '</td>' +
                    '<td>' + SimpleStomUi.badge(invoice.status || 'issued', invoice.status || 'issued') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.totalAmount || 0) + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.paidAmount || 0) + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.balance || 0) + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';

            this.$el.find('[data-name="cashBody"]').html(SimpleStomUi.panel({
                title: 'Счета к оплате',
                body: invoices.length ? html : SimpleStomUi.emptyState('Счетов для оплаты нет.')
            }));
        },

        renderClosingPreview: function (preview) {
            if (!preview) {
                return '';
            }

            return '<div class="espo-dental-stom-toolbar" style="margin-bottom:8px">' +
                SimpleStomUi.badge('Наличные ' + (preview.cashTotal || 0), 'primary') +
                SimpleStomUi.badge('Карта ' + (preview.cardTotal || 0)) +
                SimpleStomUi.badge('Криптовалюта ' + (preview.cryptoTotal || 0)) +
                SimpleStomUi.badge('Аванс ' + (preview.advanceTotal || 0)) +
                '</div>';
        },

        closeShift: function () {
            this.notify('Выберите клинику перед закрытием смены.', 'warning');
        }
    });
});
