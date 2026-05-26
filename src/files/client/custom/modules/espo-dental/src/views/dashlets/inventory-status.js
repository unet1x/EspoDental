define('espo-dental:views/dashlets/inventory-status', ['views/dashlets/abstract/base'], function (Dep) {
    return Dep.extend({
        name: 'InventoryStatus',
        templateContent: '<div class="espo-dental-inventory-status" style="padding:8px"></div>',

        afterRender: function () {
            this.fetchData();
        },

        fetchData: function () {
            var limit = parseInt(this.getOption('displayRecords')) || 8;

            this.$el.find('.espo-dental-inventory-status')
                .html('<div class="text-muted small">Загрузка...</div>');

            Espo.Ajax.getRequest('EspoDental/Report/inventoryStatus', {limit: limit})
                .then((function (data) {
                    this.renderRows((data && data.summary) || {}, (data && data.rows) || []);
                }).bind(this))
                .catch((function () {
                    this.$el.find('.espo-dental-inventory-status')
                        .html('<div class="text-danger small">Не удалось загрузить.</div>');
                }).bind(this));
        },

        renderRows: function (summary, rows) {
            var $host = this.$el.find('.espo-dental-inventory-status');

            if (!rows.length) {
                $host.html('<div class="text-muted small">Нет данных.</div>');
                return;
            }

            var html = '<div class="small text-muted" style="margin-bottom:6px">' +
                'Материалы: ' + (summary.materialCount || 0) +
                ' | Ниже порога: ' + (summary.lowStockCount || 0) +
                ' | Критично: ' + (summary.criticalStockCount || 0) +
                ' | Нет остатка: ' + (summary.outStockCount || 0) +
                ' | Стоимость: ' + this.formatMoney(summary.inventoryValue) +
                '</div>';

            html += '<div class="table-responsive"><table class="table table-condensed table-striped">' +
                '<thead><tr>' +
                '<th>Материал</th><th>Уровень</th><th class="text-right">Остаток</th>' +
                '<th class="text-right">Списано</th><th class="text-right">Итого</th>' +
                '<th class="text-right">Стоимость</th>' +
                '</tr></thead><tbody>';

            rows.forEach((function (row) {
                html += '<tr>' +
                    '<td>' + this.escapeHtml(row.materialName || row.materialId || '') + '</td>' +
                    '<td>' + this.escapeHtml(row.stockLevel || '') + '</td>' +
                    '<td class="text-right">' + this.formatQuantity(row.currentStock) + ' ' +
                    this.escapeHtml(row.unit || '') + '</td>' +
                    '<td class="text-right">' + this.formatQuantity(row.outboundQuantity) + '</td>' +
                    '<td class="text-right">' + this.formatQuantity(row.netQuantity) + '</td>' +
                    '<td class="text-right">' + this.formatMoney(row.inventoryValue) + '</td>' +
                    '</tr>';
            }).bind(this));

            html += '</tbody></table></div>';
            $host.html(html);
        },

        formatQuantity: function (value) {
            var number = parseFloat(value) || 0;

            return number.toLocaleString(undefined, {maximumFractionDigits: 3});
        },

        formatMoney: function (value) {
            var number = parseFloat(value) || 0;

            return Math.round(number).toLocaleString();
        },

        escapeHtml: function (value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    });
});
