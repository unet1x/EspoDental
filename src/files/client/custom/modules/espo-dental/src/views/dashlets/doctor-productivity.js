define('espo-dental:views/dashlets/doctor-productivity', ['views/dashlets/abstract/base'], function (Dep) {
    return Dep.extend({
        name: 'DoctorProductivity',
        templateContent: '<div class="espo-dental-doctor-productivity" style="padding:8px"></div>',

        afterRender: function () {
            this.fetchData();
        },

        fetchData: function () {
            var limit = parseInt(this.getOption('displayRecords')) || 8;

            this.$el.find('.espo-dental-doctor-productivity')
                .html('<div class="text-muted small">Loading...</div>');

            Espo.Ajax.getRequest('EspoDental/Report/doctorProductivity', {limit: limit})
                .then((function (data) {
                    this.renderRows((data && data.rows) || []);
                }).bind(this))
                .catch((function () {
                    this.$el.find('.espo-dental-doctor-productivity')
                        .html('<div class="text-danger small">Failed to load.</div>');
                }).bind(this));
        },

        renderRows: function (rows) {
            var $host = this.$el.find('.espo-dental-doctor-productivity');

            if (!rows.length) {
                $host.html('<div class="text-muted small">No data.</div>');
                return;
            }

            var html = '<div class="table-responsive"><table class="table table-condensed table-striped">' +
                '<thead><tr>' +
                '<th>Doctor</th><th class="text-right">Visits</th>' +
                '<th class="text-right">Services</th><th class="text-right">Amount</th>' +
                '<th class="text-right">Avg</th>' +
                '</tr></thead><tbody>';

            rows.forEach((function (row) {
                html += '<tr>' +
                    '<td>' + this.escapeHtml(row.doctorName || row.doctorId || '') + '</td>' +
                    '<td class="text-right">' + (row.visitCount || 0) + '</td>' +
                    '<td class="text-right">' + (row.serviceLineCount || 0) + '</td>' +
                    '<td class="text-right">' + this.formatMoney(row.grossAmount) + '</td>' +
                    '<td class="text-right">' + this.formatMoney(row.averageVisitAmount) + '</td>' +
                    '</tr>';
            }).bind(this));

            html += '</tbody></table></div>';
            $host.html(html);
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
