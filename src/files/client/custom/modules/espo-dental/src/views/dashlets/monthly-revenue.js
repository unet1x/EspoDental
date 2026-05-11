define('espo-dental:views/dashlets/monthly-revenue', ['views/dashlets/abstract/base'], function (Dep) {
    return Dep.extend({
        name: 'MonthlyRevenue',
        templateContent: '<div class="espo-dental-monthly-revenue" style="padding:8px"></div>',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.rows = [];
        },

        afterRender: function () {
            this.fetchData();
        },

        fetchData: function () {
            var months = parseInt(this.getOption('monthsBack')) || 12;
            this.$el.find('.espo-dental-monthly-revenue')
                .html('<div class="text-muted small">Loading...</div>');
            Espo.Ajax.getRequest('EspoDental/Report/monthlyRevenue', {monthsBack: months})
                .then((function (data) {
                    this.rows = data || [];
                    this.renderChart();
                }).bind(this))
                .catch((function () {
                    this.$el.find('.espo-dental-monthly-revenue')
                        .html('<div class="text-danger small">Failed to load.</div>');
                }).bind(this));
        },

        renderChart: function () {
            var rows = this.rows;
            var $host = this.$el.find('.espo-dental-monthly-revenue');
            if (!rows.length) {
                $host.html('<div class="text-muted small">No data.</div>');
                return;
            }
            var max = 0;
            rows.forEach(function (r) { if (r.value > max) max = r.value; });
            if (max === 0) max = 1;

            var width = $host.width() || 320;
            var height = 180;
            var paddingLeft = 28;
            var paddingBottom = 22;
            var paddingTop = 10;
            var chartW = width - paddingLeft - 8;
            var chartH = height - paddingBottom - paddingTop;
            var barW = chartW / rows.length * 0.7;
            var gap = chartW / rows.length * 0.3;

            var svg = '<svg width="' + width + '" height="' + height + '" style="overflow:visible">';
            rows.forEach(function (r, i) {
                var h = Math.round((r.value / max) * chartH);
                var x = paddingLeft + i * (barW + gap);
                var y = paddingTop + (chartH - h);
                svg += '<rect x="' + x + '" y="' + y + '" width="' + barW + '" height="' + h
                    + '" fill="#1F77B4" rx="2"></rect>';
                svg += '<text x="' + (x + barW / 2) + '" y="' + (paddingTop + chartH + 12)
                    + '" text-anchor="middle" font-size="9" fill="#666">' + r.label + '</text>';
                if (r.value > 0) {
                    svg += '<text x="' + (x + barW / 2) + '" y="' + (y - 2)
                        + '" text-anchor="middle" font-size="9" fill="#444">'
                        + Math.round(r.value) + '</text>';
                }
            });
            svg += '</svg>';
            $host.html(svg);
        }
    });
});
