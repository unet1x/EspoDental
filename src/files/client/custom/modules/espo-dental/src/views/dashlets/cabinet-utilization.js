define('espo-dental:views/dashlets/cabinet-utilization', ['views/dashlets/abstract/base'], function (Dep) {
    return Dep.extend({
        name: 'CabinetUtilization',
        templateContent: '<div class="espo-dental-cabinet-utilization" style="padding:8px"></div>',

        afterRender: function () {
            this.fetchData();
        },

        fetchData: function () {
            var limit = parseInt(this.getOption('displayRecords')) || 8;
            var workStartHour = parseInt(this.getOption('workStartHour'));
            var workEndHour = parseInt(this.getOption('workEndHour'));

            if (isNaN(workStartHour)) {
                workStartHour = 8;
            }

            if (isNaN(workEndHour)) {
                workEndHour = 21;
            }

            this.$el.find('.espo-dental-cabinet-utilization')
                .html('<div class="text-muted small">Загрузка отчета...</div>');

            Espo.Ajax.getRequest('EspoDental/Report/cabinetUtilization', {
                limit: limit,
                workStartHour: workStartHour,
                workEndHour: workEndHour
            })
                .then((function (data) {
                    this.renderRows((data && data.rows) || []);
                }).bind(this))
                .catch((function () {
                    this.$el.find('.espo-dental-cabinet-utilization')
                        .html('<div class="text-danger small">Не удалось загрузить отчет.</div>');
                }).bind(this));
        },

        renderRows: function (rows) {
            var $host = this.$el.find('.espo-dental-cabinet-utilization');

            if (!rows.length) {
                $host.html('<div class="text-muted small">Данных пока нет.</div>');
                return;
            }

            var html = '<div class="table-responsive"><table class="table table-condensed table-striped">' +
                '<thead><tr>' +
                '<th>Кабинет</th><th class="text-right">Записи</th>' +
                '<th class="text-right">Занято</th><th class="text-right">Доступно</th>' +
                '<th class="text-right">Загрузка</th>' +
                '</tr></thead><tbody>';

            rows.forEach((function (row) {
                html += '<tr>' +
                    '<td>' + this.escapeHtml(row.cabinetName || row.cabinetId || '') + '</td>' +
                    '<td class="text-right">' + (row.appointmentCount || 0) + '</td>' +
                    '<td class="text-right">' + this.formatHours(row.occupiedMinutes) + '</td>' +
                    '<td class="text-right">' + this.formatHours(row.availableMinutes) + '</td>' +
                    '<td class="text-right">' + this.formatPercent(row.utilizationPercent) + '</td>' +
                    '</tr>';
            }).bind(this));

            html += '</tbody></table></div>';
            $host.html(html);
        },

        formatHours: function (minutes) {
            var value = parseFloat(minutes) || 0;

            return (value / 60).toFixed(1);
        },

        formatPercent: function (value) {
            var number = parseFloat(value) || 0;

            return number.toFixed(1) + '%';
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
