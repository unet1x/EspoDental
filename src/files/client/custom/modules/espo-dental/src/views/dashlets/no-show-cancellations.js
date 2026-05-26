define('espo-dental:views/dashlets/no-show-cancellations', ['views/dashlets/abstract/base'], function (Dep) {
    return Dep.extend({
        name: 'NoShowCancellations',
        templateContent: '<div class="espo-dental-no-show-cancellations" style="padding:8px"></div>',

        afterRender: function () {
            this.fetchData();
        },

        fetchData: function () {
            var limit = parseInt(this.getOption('displayRecords')) || 8;

            this.$el.find('.espo-dental-no-show-cancellations')
                .html('<div class="text-muted small">Загрузка отчета...</div>');

            Espo.Ajax.getRequest('EspoDental/Report/noShowCancellations', {limit: limit})
                .then((function (data) {
                    this.renderRows((data && data.summary) || {}, (data && data.rows) || []);
                }).bind(this))
                .catch((function () {
                    this.$el.find('.espo-dental-no-show-cancellations')
                        .html('<div class="text-danger small">Не удалось загрузить отчет.</div>');
                }).bind(this));
        },

        renderRows: function (summary, rows) {
            var $host = this.$el.find('.espo-dental-no-show-cancellations');

            if (!rows.length) {
                $host.html('<div class="text-muted small">Данных пока нет.</div>');
                return;
            }

            var html = '<div class="small text-muted" style="margin-bottom:6px">' +
                'Всего: ' + (summary.appointmentCount || 0) +
                ' | Неявки: ' + (summary.noShowCount || 0) +
                ' | Отмены: ' + (summary.cancellationCount || 0) +
                ' | Проблемные: ' + this.formatPercent(summary.issueRate) +
                '</div>';

            html += '<div class="table-responsive"><table class="table table-condensed table-striped">' +
                '<thead><tr>' +
                '<th>Врач</th><th class="text-right">Всего</th>' +
                '<th class="text-right">Неявки</th><th class="text-right">Отмены</th>' +
                '<th class="text-right">Проблемные, %</th>' +
                '</tr></thead><tbody>';

            rows.forEach((function (row) {
                html += '<tr>' +
                    '<td>' + this.escapeHtml(row.doctorName || row.doctorId || '') + '</td>' +
                    '<td class="text-right">' + (row.appointmentCount || 0) + '</td>' +
                    '<td class="text-right">' + (row.noShowCount || 0) + '</td>' +
                    '<td class="text-right">' + (row.cancellationCount || 0) + '</td>' +
                    '<td class="text-right">' + this.formatPercent(row.issueRate) + '</td>' +
                    '</tr>';
            }).bind(this));

            html += '</tbody></table></div>';
            $host.html(html);
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
