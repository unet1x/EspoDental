define('espo-dental:views/dashlets/report-export-center', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, SimpleStomUi) {
    return Dep.extend({
        name: 'ReportExportCenter',
        templateContent: '<div class="espo-dental-report-export-center"></div>',
        events: {
            'click [data-action="reportExportPreview"]': 'previewExport',
            'click [data-action="reportExportCsv"]': 'downloadCsv',
            'click [data-action="reportExportJson"]': 'downloadJson'
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.sources = [
                ['finance', 'Финансовый срез'],
                ['payments', 'Платежи'],
                ['service_profitability', 'Маржинальность услуг'],
                ['material_finance', 'Материалы и склад'],
                ['doctor_utilization', 'Врачи'],
                ['cabinet_utilization', 'Кабинеты'],
                ['appointments', 'Неявки и отмены'],
                ['inventory', 'Состояние склада'],
                ['payroll', 'Зарплата'],
                ['patient_funnel', 'Воронка пациентов']
            ];
        },

        afterRender: function () {
            SimpleStomUi.ensureStyles();
            this.renderShell();
            this.fetchExport('json', false);
        },

        renderShell: function () {
            var html = '<div class="espo-dental-stom-toolbar">' +
                this.renderSourceSelect() +
                '<input type="date" class="form-control input-sm" name="dateFrom" style="width:136px">' +
                '<input type="date" class="form-control input-sm" name="dateTo" style="width:136px">' +
                '<input type="number" class="form-control input-sm" name="limit" min="1" max="500" value="' +
                    this.getDefaultLimit() + '" style="width:86px">' +
                SimpleStomUi.button('Просмотр', {
                    tone: 'quiet',
                    attrs: {'data-action': 'reportExportPreview'}
                }) +
                '<span class="espo-dental-stom-toolbar__spacer"></span>' +
                SimpleStomUi.button('CSV', {
                    tone: 'primary',
                    attrs: {'data-action': 'reportExportCsv'}
                }) +
                SimpleStomUi.button('JSON', {
                    tone: 'quiet',
                    attrs: {'data-action': 'reportExportJson'}
                }) +
                '</div>' +
                '<div class="espo-dental-report-export-center__result">' +
                    SimpleStomUi.emptyState('Загрузка отчета...') +
                '</div>';

            this.$el.find('.espo-dental-report-export-center').html(SimpleStomUi.workspace(html));
        },

        renderSourceSelect: function () {
            var html = '<select class="form-control input-sm" name="source" style="min-width:210px">';

            this.sources.forEach(function (source) {
                html += '<option value="' + SimpleStomUi.escapeHtml(source[0]) + '">' +
                    SimpleStomUi.escapeHtml(source[1]) +
                    '</option>';
            });

            return html + '</select>';
        },

        previewExport: function () {
            this.fetchExport('json', false);
        },

        downloadCsv: function () {
            this.fetchExport('csv', true);
        },

        downloadJson: function () {
            this.fetchExport('json', true);
        },

        fetchExport: function (format, shouldDownload) {
            var params = this.getParams(format);
            var $result = this.$el.find('.espo-dental-report-export-center__result');

            this.setButtonsDisabled(true);
            $result.html(SimpleStomUi.emptyState('Подготовка отчета...'));

            Espo.Ajax.getRequest('EspoDental/Report/export', params)
                .then((function (data) {
                    this.renderResult(data || {});
                    if (shouldDownload) {
                        this.downloadExport(data || {});
                    }
                    this.setButtonsDisabled(false);
                }).bind(this))
                .catch((function () {
                    $result.html(SimpleStomUi.emptyState('Не удалось подготовить отчет.'));
                    this.notify('Не удалось подготовить отчет.', 'error');
                    this.setButtonsDisabled(false);
                }).bind(this));
        },

        getParams: function (format) {
            var $root = this.$el.find('.espo-dental-report-export-center');
            var params = {
                source: $root.find('[name="source"]').val() || 'finance',
                format: format,
                limit: parseInt($root.find('[name="limit"]').val(), 10) || this.getDefaultLimit()
            };
            var dateFrom = $root.find('[name="dateFrom"]').val();
            var dateTo = $root.find('[name="dateTo"]').val();

            if (dateFrom) {
                params.dateFrom = dateFrom;
            }
            if (dateTo) {
                params.dateTo = dateTo;
            }

            return params;
        },

        renderResult: function (data) {
            var rows = data.rows || [];
            var columns = data.columns || [];
            var html = '<div class="espo-dental-stom-muted" style="margin-bottom:8px">' +
                SimpleStomUi.escapeHtml(data.filename || '') +
                ' · ' + rows.length + ' строк' +
                '</div>';

            if (!rows.length || !columns.length) {
                html += SimpleStomUi.emptyState('Нет строк для выбранных параметров.');
                this.$el.find('.espo-dental-report-export-center__result').html(html);
                return;
            }

            html += '<div class="table-responsive"><table class="espo-dental-stom-table">' +
                '<thead><tr>';

            columns.forEach(function (column) {
                html += '<th>' + SimpleStomUi.escapeHtml(column.label || column.key || '') + '</th>';
            });
            html += '</tr></thead><tbody>';

            rows.slice(0, 6).forEach(function (row) {
                html += '<tr>';
                columns.forEach(function (column) {
                    html += '<td>' + SimpleStomUi.escapeHtml(this.formatValue(row[column.key])) + '</td>';
                }, this);
                html += '</tr>';
            }, this);

            html += '</tbody></table></div>';
            if (rows.length > 6) {
                html += '<div class="espo-dental-stom-muted" style="margin-top:6px">Показаны первые 6 строк.</div>';
            }

            this.$el.find('.espo-dental-report-export-center__result').html(html);
        },

        downloadExport: function (data) {
            if (!data.content) {
                this.notify('Экспорт вернулся без данных.', 'warning');
                return;
            }

            var blob = new Blob([data.content], {type: data.mimeType || 'text/plain;charset=utf-8'});
            var url = window.URL.createObjectURL(blob);
            var link = document.createElement('a');

            link.href = url;
            link.download = data.filename || 'espo-dental-report-export.txt';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            this.notify('Экспорт подготовлен.', 'success');
        },

        formatValue: function (value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }

            if (typeof value === 'object') {
                return JSON.stringify(value);
            }

            return value;
        },

        setButtonsDisabled: function (disabled) {
            this.$el.find('[data-action="reportExportPreview"], [data-action="reportExportCsv"], ' +
                '[data-action="reportExportJson"]').prop('disabled', disabled);
        },

        getDefaultLimit: function () {
            return parseInt(this.getOption('displayRecords'), 10) || 50;
        }
    });
});
