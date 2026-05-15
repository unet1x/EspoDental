define('espo-dental:views/tooth-chart-snapshot/record/detail', [
    'views/record/detail',
    'espo-dental:tooth-chart/renderer'
], function (Dep, Renderer) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'sync', this.rerenderChart);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.renderChart();
        },

        rerenderChart: function () {
            this.renderChart();
        },

        renderChart: function () {
            var container = this.$el.find('.tooth-chart-container').get(0);
            if (!container) {
                container = this.injectContainer();
            }
            if (!container) {
                return;
            }
            var dentition = this.model.get('dentitionType') || 'adult';
            var teeth = this.model.get('teeth') || {};
            var readOnly = this.type !== 'edit' && this.mode !== 'edit';
            Renderer.render(container, {
                dentition: dentition,
                teeth: teeth,
                readOnly: readOnly,
                conditions: this.getDentalSetting('espoDentalToothChartConditions'),
                surfaces: this.getDentalSetting('espoDentalToothChartSurfaces'),
                language: this.getDentalSetting('language'),
                onChange: function (next) {
                    this.model.set('teeth', next);
                }.bind(this),
                translate: function (key, cat, scope) {
                    return this.translate(key, cat || 'options', scope || 'ToothChartSnapshot');
                }.bind(this)
            });
        },

        getDentalSetting: function (name) {
            var config = typeof this.getConfig === 'function' ? this.getConfig() : null;
            if (!config || typeof config.get !== 'function') {
                return null;
            }

            return config.get(name);
        },

        injectContainer: function () {
            var $panels = this.$el.find('.panel-body').first();
            if (!$panels.length) {
                return null;
            }
            var div = document.createElement('div');
            div.className = 'tooth-chart-container';
            div.style.marginTop = '12px';
            $panels.append(div);
            return div;
        }
    });
});
