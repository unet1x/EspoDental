define('espo-dental:views/dashlets/dashboard-action-center', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, SimpleStomUi) {
    return Dep.extend({
        name: 'DashboardActionCenter',
        templateContent: '<div class="espo-dental-dashboard-action-center"></div>',

        afterRender: function () {
            SimpleStomUi.ensureStyles();
            this.fetchData();
        },

        fetchData: function () {
            var data = {
                limit: parseInt(this.getOption('displayRecords'), 10) || 8
            };

            if (this.getOption('clinicId')) {
                data.clinicId = this.getOption('clinicId');
            }

            this.$el.find('.espo-dental-dashboard-action-center')
                .html(SimpleStomUi.workspace(SimpleStomUi.emptyState('Загрузка центра действий...')));

            Espo.Ajax.getRequest('EspoDental/Dashboard/actionCenter', data)
                .then((function (response) {
                    this.renderActionCenter(response || {});
                }).bind(this))
                .catch((function () {
                    this.$el.find('.espo-dental-dashboard-action-center')
                        .html(SimpleStomUi.workspace(SimpleStomUi.emptyState('Не удалось загрузить центр действий.')));
                }).bind(this));
        },

        renderActionCenter: function (data) {
            var summary = data.summary || {};
            var html = '';

            html += this.renderSummary(summary);
            html += '<div class="espo-dental-stom-layout espo-dental-stom-layout--three">';
            html += '<div>';
            html += this.renderPendingActions(data.pendingActions || []);
            html += '</div>';
            html += '<div>';
            html += this.renderWaitingPatients(data.waitingPatients || []);
            html += this.renderAssignedTasks(data.assignedTasks || []);
            html += '</div>';
            html += '<div>';
            html += this.renderAlerts(data.alerts || []);
            html += this.renderWeeklyWorkload(data.weeklyWorkload || []);
            html += '</div>';
            html += '</div>';

            this.$el.find('.espo-dental-dashboard-action-center').html(SimpleStomUi.workspace(html));
        },

        renderSummary: function (summary) {
            var items = [
                ['Пациентов в клинике', summary.waitingPatients || 0],
                ['Нужно действие', summary.pendingActions || 0],
                ['Мои задачи', summary.assignedTasks || 0],
                ['Алерты', summary.openAlerts || 0],
                ['Неделя', summary.weekAppointments || 0]
            ];

            var html = '<div class="espo-dental-stom-layout" ' +
                'style="grid-template-columns:repeat(auto-fit,minmax(96px,1fr));margin-bottom:12px">';

            items.forEach(function (item) {
                html += '<div class="espo-dental-stom-kpi">' +
                    '<div class="espo-dental-stom-kpi__value">' + SimpleStomUi.escapeHtml(item[1]) + '</div>' +
                    '<div class="espo-dental-stom-kpi__label">' + SimpleStomUi.escapeHtml(item[0]) + '</div>' +
                    '</div>';
            });

            return html + '</div>';
        },

        renderWaitingPatients: function (rows) {
            return this.renderPanelList('Пациенты ожидают', rows, (function (row) {
                var href = row.id ? '#Appointment/view/' + encodeURIComponent(row.id) : '#';

                return '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    '<a href="' + href + '">' + SimpleStomUi.escapeHtml(row.parentName || row.name || 'Запись') + '</a>' +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(this.formatTime(row.dateStart)) +
                    (row.cabinetName ? ' · ' + SimpleStomUi.escapeHtml(row.cabinetName) : '') +
                    '</span>' +
                    '</span>' +
                    SimpleStomUi.badge(row.status || 'waiting', row.status || 'waiting') +
                    '</li>';
            }).bind(this), 'Нет ожидающих пациентов.');
        },

        renderPendingActions: function (rows) {
            return this.renderPanelList('Нужно действие', rows, function (row) {
                var href = row.id ? '#AssistantActionProposal/view/' + encodeURIComponent(row.id) : '#';
                var label = row.summary || row.name || row.actionType || 'Действие';

                return '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    '<a href="' + href + '">' + SimpleStomUi.escapeHtml(label) + '</a>' +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(row.actionType || '') +
                    '</span>' +
                    '</span>' +
                    SimpleStomUi.badge(row.riskLevel || 'medium', row.riskLevel || 'medium') +
                    '</li>';
            }, 'Нет действий, требующих реакции.');
        },

        renderAssignedTasks: function (rows) {
            return this.renderPanelList('Мои задачи', rows, function (row) {
                var href = row.id ? '#Task/view/' + encodeURIComponent(row.id) : '#';

                return '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    '<a href="' + href + '">' + SimpleStomUi.escapeHtml(row.name || 'Задача') + '</a>' +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(row.dateEnd || 'без срока') +
                    '</span>' +
                    '</span>' +
                    SimpleStomUi.badge(row.priority || row.status || 'task', row.priority || 'muted') +
                    '</li>';
            }, 'Нет назначенных задач.');
        },

        renderAlerts: function (rows) {
            return this.renderPanelList('Алерты', rows, function (row) {
                var href = row.id ? '#LowStockAlert/view/' + encodeURIComponent(row.id) : '#';

                return '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    '<a href="' + href + '">' + SimpleStomUi.escapeHtml(row.materialName || row.name || 'Материал') + '</a>' +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(row.currentStock) + ' / ' +
                    SimpleStomUi.escapeHtml(row.threshold) +
                    '</span>' +
                    '</span>' +
                    SimpleStomUi.badge(row.level || 'low', row.level || 'low') +
                    '</li>';
            }, 'Нет открытых складских алертов.');
        },

        renderWeeklyWorkload: function (rows) {
            var body;

            if (!rows.length) {
                body = SimpleStomUi.emptyState('Нет записей на этой неделе.');
            } else {
                body = '<table class="espo-dental-stom-table"><thead><tr>' +
                    '<th>Дата</th><th class="text-right">Записи</th><th class="text-right">В клинике</th>' +
                    '</tr></thead><tbody>';
                rows.forEach(function (row) {
                    body += '<tr>' +
                        '<td>' + SimpleStomUi.escapeHtml(row.date || '') + '</td>' +
                        '<td class="text-right">' + SimpleStomUi.escapeHtml(row.appointmentCount || 0) + '</td>' +
                        '<td class="text-right">' +
                        SimpleStomUi.escapeHtml((row.arrivedCount || 0) + (row.inProgressCount || 0)) +
                        '</td>' +
                        '</tr>';
                });
                body += '</tbody></table>';
            }

            return SimpleStomUi.panel({
                title: 'Неделя',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderPanelList: function (title, rows, renderRow, emptyMessage) {
            var body;

            if (!rows.length) {
                body = SimpleStomUi.emptyState(emptyMessage);
            } else {
                body = '<ul class="espo-dental-stom-list">';
                rows.forEach(function (row) {
                    body += renderRow(row);
                });
                body += '</ul>';
            }

            return SimpleStomUi.panel({
                title: title,
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        formatTime: function (value) {
            if (!value) {
                return '';
            }

            return String(value).slice(11, 16);
        }
    });
});
