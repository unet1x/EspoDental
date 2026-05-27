define('espo-dental:views/dashlets/management-snapshot', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, SimpleStomUi) {
    return Dep.extend({
        name: 'ManagementSnapshot',
        templateContent: '<div class="espo-dental-management-snapshot"></div>',

        afterRender: function () {
            SimpleStomUi.ensureStyles();
            this.fetchData();
        },

        fetchData: function () {
            var limit = parseInt(this.getOption('displayRecords'), 10) || 5;

            this.$el.find('.espo-dental-management-snapshot')
                .html(SimpleStomUi.workspace(SimpleStomUi.emptyState('Загрузка управленческого среза...')));

            Espo.Ajax.getRequest('EspoDental/Report/managementSnapshot', {limit: limit})
                .then((function (data) {
                    this.renderSnapshot(data || {});
                }).bind(this))
                .catch((function () {
                    this.$el.find('.espo-dental-management-snapshot')
                        .html(SimpleStomUi.workspace(SimpleStomUi.emptyState('Не удалось загрузить управленческий срез.')));
                }).bind(this));
        },

        renderSnapshot: function (data) {
            var finance = data.finance || {};
            var stock = data.stock || {};
            var quality = data.appointmentQuality || {};
            var payroll = data.payroll || {};
            var html = this.renderKpis(finance, stock, quality, payroll);

            html += '<div class="espo-dental-stom-layout espo-dental-stom-layout--two">' +
                '<div>' +
                    this.renderFinancePanel(finance) +
                    this.renderDoctorRows(data.doctorRows || []) +
                '</div>' +
                '<div>' +
                    this.renderRiskPanel(stock, quality, payroll) +
                    this.renderCabinetRows(data.cabinetRows || []) +
                    this.renderPayrollRows((payroll && payroll.rows) || []) +
                '</div>' +
                '</div>';

            this.$el.find('.espo-dental-management-snapshot').html(SimpleStomUi.workspace(html));
        },

        renderKpis: function (finance, stock, quality, payroll) {
            var rows = [
                ['Выручка', this.formatMoney(finance.revenue || 0)],
                ['Долг по счетам', this.formatMoney(finance.openInvoiceBalance || 0)],
                ['Известные расходы', this.formatMoney((finance.materialCost || 0) + (finance.payrollAccrued || 0))],
                ['Операционный остаток', this.formatMoney(finance.grossAfterKnownCosts || 0)],
                ['Риски склада', (stock.lowStockCount || 0) + (stock.criticalStockCount || 0) + (stock.outStockCount || 0)],
                ['Проблемы записей', (quality.issueRate || 0) + '%'],
                ['Начисления ЗП', this.formatMoney(payroll.totalAmount || 0)]
            ];
            var html = '<div class="espo-dental-stom-layout" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));margin-bottom:10px">';

            rows.forEach(function (row) {
                html += SimpleStomUi.kpi(row[1], row[0]);
            });

            return html + '</div>';
        },

        renderFinancePanel: function (finance) {
            var body = '<table class="espo-dental-stom-table"><tbody>' +
                this.renderKeyValue('Выручка периода', this.formatMoney(finance.revenue || 0)) +
                this.renderKeyValue('Открытые счета', (finance.openInvoiceCount || 0) + ' · ' +
                    this.formatMoney(finance.openInvoiceBalance || 0)) +
                this.renderKeyValue('Просроченные счета', finance.overdueInvoiceCount || 0) +
                this.renderKeyValue('Материалы списаны', this.formatMoney(finance.materialCost || 0)) +
                this.renderKeyValue('ЗП начислена', this.formatMoney(finance.payrollAccrued || 0)) +
                this.renderKeyValue('Остаток после известных расходов', this.formatMoney(finance.grossAfterKnownCosts || 0)) +
                '</tbody></table>';

            return SimpleStomUi.panel({
                title: 'Финансы периода',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderRiskPanel: function (stock, quality, payroll) {
            var body = '<div class="espo-dental-stom-toolbar">' +
                SimpleStomUi.badge('Склад: ' + ((stock.lowStockCount || 0) + (stock.criticalStockCount || 0) +
                    (stock.outStockCount || 0)), (stock.criticalStockCount || stock.outStockCount) ? 'danger' : 'success') +
                SimpleStomUi.badge('Неявки: ' + (quality.noShowRate || 0) + '%', (quality.issueRate || 0) > 10 ? 'warning' : 'success') +
                SimpleStomUi.badge('ЗП: ' + (payroll.entryCount || 0), (payroll.draftAmount || 0) > 0 ? 'warning' : 'success') +
                '</div>';

            body += '<table class="espo-dental-stom-table"><tbody>' +
                this.renderKeyValue('Материалов', stock.materialCount || 0) +
                this.renderKeyValue('Критично / нет остатка', (stock.criticalStockCount || 0) + ' / ' + (stock.outStockCount || 0)) +
                this.renderKeyValue('Отмены / неявки', (quality.cancellationCount || 0) + ' / ' + (quality.noShowCount || 0)) +
                this.renderKeyValue('ЗП черновики / утверждено', this.formatMoney(payroll.draftAmount || 0) + ' / ' +
                    this.formatMoney(payroll.approvedAmount || 0)) +
                '</tbody></table>';

            return SimpleStomUi.panel({
                title: 'Риски',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderDoctorRows: function (rows) {
            return this.renderRowsPanel(
                'Врачи',
                rows,
                'Нет данных по врачам.',
                function (row) {
                    return [
                        row.doctorName || row.doctorId || '',
                        (row.visitCount || 0) + ' приемов',
                        this.formatMoney(row.grossAmount || 0)
                    ];
                }.bind(this)
            );
        },

        renderCabinetRows: function (rows) {
            return this.renderRowsPanel(
                'Кабинеты',
                rows,
                'Нет данных по кабинетам.',
                function (row) {
                    return [
                        row.cabinetName || row.cabinetId || '',
                        (row.utilizationPercent || 0) + '%',
                        (row.occupiedMinutes || 0) + ' мин'
                    ];
                }
            );
        },

        renderPayrollRows: function (rows) {
            return this.renderRowsPanel(
                'Зарплата',
                rows,
                'Начислений за период нет.',
                function (row) {
                    return [
                        row.userName || row.userId || '',
                        SimpleStomUi.label(row.status || 'draft'),
                        this.formatMoney(row.totalAmount || 0)
                    ];
                }.bind(this)
            );
        },

        renderRowsPanel: function (title, rows, emptyMessage, mapper) {
            var body = SimpleStomUi.emptyState(emptyMessage);

            if (rows.length) {
                body = '<table class="espo-dental-stom-table"><tbody>';
                rows.forEach(function (row) {
                    var cells = mapper(row);
                    body += '<tr>' +
                        '<td>' + SimpleStomUi.escapeHtml(cells[0]) + '</td>' +
                        '<td>' + SimpleStomUi.escapeHtml(cells[1]) + '</td>' +
                        '<td style="text-align:right">' + SimpleStomUi.escapeHtml(cells[2]) + '</td>' +
                        '</tr>';
                });
                body += '</tbody></table>';
            }

            return SimpleStomUi.panel({
                title: title,
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderKeyValue: function (label, value) {
            return '<tr><th>' + SimpleStomUi.escapeHtml(label) + '</th><td style="text-align:right">' +
                SimpleStomUi.escapeHtml(value) +
                '</td></tr>';
        },

        formatMoney: function (value) {
            var number = parseFloat(value || 0);

            return Math.round(number).toLocaleString();
        }
    });
});
