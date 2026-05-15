define('espo-dental:views/visit/record/detail', [
    'views/record/detail',
    'espo-dental:tooth-chart/renderer'
], function (Dep, Renderer) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'sync', this.renderToothChartPreview);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.renderToothChartPreview();
        },

        renderToothChartPreview: function () {
            if (!this.model.id || !this.$el) {
                return;
            }

            var $host = this.ensureToothChartHost();
            if (!$host.length) {
                return;
            }

            var self = this;
            var $chart = $host.find('[data-name="tooth-chart-preview"]');
            $chart.html('<span class="text-muted">' +
                this.translate('Loading...', 'messages', 'Global') +
                '</span>');

            Espo.Ajax.getRequest('Visit/action/toothChart', {id: this.model.id})
                .then(function (data) {
                    if (!data || !data.id) {
                        $chart.html('<span class="text-muted">' +
                            self.translate('No Data', 'labels', 'Global') +
                            '</span>');
                        return;
                    }

                    $host.find('[data-action="editToothChart"]')
                        .toggle(self.model.get('status') === 'in_progress')
                        .attr('href', '#ToothChartSnapshot/edit/' + data.id);

                    Renderer.render($chart.get(0), {
                        dentition: data.dentitionType || 'adult',
                        teeth: data.teeth || {},
                        readOnly: true,
                        conditions: self.getDentalSetting('espoDentalToothChartConditions'),
                        surfaces: self.getDentalSetting('espoDentalToothChartSurfaces'),
                        language: self.getDentalSetting('language'),
                        translate: function (key, cat) {
                            return self.translate(key, cat || 'options', 'ToothChartSnapshot');
                        }
                    });
                });
        },

        getDentalSetting: function (name) {
            var config = typeof this.getConfig === 'function' ? this.getConfig() : null;
            if (!config || typeof config.get !== 'function') {
                return null;
            }

            return config.get(name);
        },

        ensureToothChartHost: function () {
            var $existing = this.$el.find('[data-name="visit-tooth-chart-panel"]');
            if ($existing.length) {
                return $existing;
            }

            var $panel = $('<div class="panel panel-default" data-name="visit-tooth-chart-panel">' +
                '<div class="panel-heading clearfix">' +
                    '<span class="panel-title">' +
                        this.translate('Tooth Chart', 'labels', 'Visit') +
                    '</span>' +
                    '<a class="btn btn-default btn-xs pull-right" ' +
                        'data-action="editToothChart" style="display:none">' +
                        this.translate('Edit', 'labels', 'Global') +
                    '</a>' +
                '</div>' +
                '<div class="panel-body">' +
                    '<div data-name="tooth-chart-preview"></div>' +
                '</div>' +
            '</div>');

            var $firstPanel = this.$el.find('.panel').first();
            if ($firstPanel.length) {
                $panel.insertAfter($firstPanel);
            } else {
                this.$el.append($panel);
            }

            return $panel;
        }
    });
});
