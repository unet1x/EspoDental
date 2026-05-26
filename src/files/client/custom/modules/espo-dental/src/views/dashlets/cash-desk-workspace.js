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
            this.$el.find('[data-name="cashBody"]').html(SimpleStomUi.emptyState('Loading cash desk...'));

            Espo.Ajax.getRequest('EspoDental/CashDesk/workspace', {
                unpaidOnly: unpaidOnly ? 'true' : 'false',
                limit: parseInt(this.getOption('displayRecords'), 10) || 30
            }).then((function (data) {
                this.renderWorkspace(data || {});
            }).bind(this)).catch((function () {
                this.$el.find('[data-name="cashBody"]').html(SimpleStomUi.emptyState('Cash desk failed to load.'));
            }).bind(this));
        },

        renderWorkspace: function (data) {
            this.renderFilters(data.filters || {});
            this.renderInvoices(data.invoices || [], data.closingPreview || null);
        },

        renderFilters: function (filters) {
            var checked = filters.unpaidOnly === false ? '' : ' checked';
            var html = '<label style="display:flex;align-items:center;gap:6px;margin:0">' +
                '<input type="checkbox" data-name="unpaidOnly"' + checked + '> Only unpaid' +
                '</label>';

            this.$el.find('[data-name="cashFilters"]').html(SimpleStomUi.panel({
                title: 'Filters',
                body: html,
                classes: ['espo-dental-stom-panel--compact']
            }));
        },

        renderInvoices: function (invoices, closingPreview) {
            var html = '<div class="espo-dental-stom-toolbar" style="margin-bottom:8px">' +
                SimpleStomUi.button('New payment', {tone: 'primary', attrs: {'data-action': 'newPayment'}}) +
                SimpleStomUi.button('Close shift', {tone: 'quiet', attrs: {'data-action': 'closeShift'}}) +
                '</div>';

            html += this.renderClosingPreview(closingPreview);
            html += '<table class="espo-dental-stom-table"><thead><tr>' +
                '<th>Invoice</th><th>Patient</th><th>Status</th><th>Total</th><th>Paid</th><th>Balance</th>' +
                '</tr></thead><tbody>';
            invoices.forEach(function (invoice) {
                html += '<tr data-invoice-id="' + SimpleStomUi.escapeHtml(invoice.id || '') + '">' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.number || invoice.id || '') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.patientName || invoice.patientId || '') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.status || '') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.totalAmount || 0) + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.paidAmount || 0) + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(invoice.balance || 0) + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';

            this.$el.find('[data-name="cashBody"]').html(SimpleStomUi.panel({
                title: 'Invoices',
                body: invoices.length ? html : SimpleStomUi.emptyState('No invoices.')
            }));
        },

        renderClosingPreview: function (preview) {
            if (!preview) {
                return '';
            }

            return '<div class="espo-dental-stom-toolbar" style="margin-bottom:8px">' +
                SimpleStomUi.badge('Cash ' + (preview.cashTotal || 0), 'primary') +
                SimpleStomUi.badge('Card ' + (preview.cardTotal || 0)) +
                SimpleStomUi.badge('Crypto ' + (preview.cryptoTotal || 0)) +
                SimpleStomUi.badge('Advance ' + (preview.advanceTotal || 0)) +
                '</div>';
        },

        closeShift: function () {
            this.notify('Select a clinic before closing shift.', 'warning');
        }
    });
});
