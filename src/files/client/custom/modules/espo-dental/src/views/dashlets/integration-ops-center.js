define('espo-dental:views/dashlets/integration-ops-center', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, SimpleStomUi) {
    return Dep.extend({
        name: 'IntegrationOpsCenter',
        templateContent: '<div class="espo-dental-integration-ops-center"></div>',

        afterRender: function () {
            SimpleStomUi.ensureStyles();
            this.fetchData();
        },

        fetchData: function () {
            var limit = parseInt(this.getOption('displayRecords'), 10) || 8;

            this.$el.find('.espo-dental-integration-ops-center')
                .html(SimpleStomUi.workspace(SimpleStomUi.emptyState('Загрузка интеграций...')));

            Espo.Ajax.getRequest('EspoDental/Integration/healthcheck', {limit: limit})
                .then((function (data) {
                    this.renderHealth(data || {});
                }).bind(this))
                .catch((function () {
                    this.$el.find('.espo-dental-integration-ops-center')
                        .html(SimpleStomUi.workspace(SimpleStomUi.emptyState('Не удалось загрузить интеграции.')));
                }).bind(this));
        },

        renderHealth: function (data) {
            var notifications = data.notifications || {};
            var proposals = data.proposals || {};
            var integrations = data.integrations || {};
            var mcp = data.mcp || {};
            var notificationSummary = notifications.summary || {};
            var proposalSummary = proposals.summary || {};
            var integrationSummary = integrations.summary || {};
            var toolAudit = (mcp && mcp.toolAudit) || {};
            var html = this.renderKpis(data.status || 'ok', notificationSummary, proposalSummary, integrationSummary, toolAudit);

            html += '<div class="espo-dental-stom-layout espo-dental-stom-layout--two">' +
                '<div>' +
                    this.renderIntegrationRows(integrations.rows || []) +
                    this.renderToolRows((mcp && mcp.tools) || []) +
                '</div>' +
                '<div>' +
                    this.renderFailedNotifications(notifications.failedRows || []) +
                    this.renderProposalRows(proposals.rows || []) +
                '</div>' +
                '</div>';

            this.$el.find('.espo-dental-integration-ops-center').html(SimpleStomUi.workspace(html));
        },

        renderKpis: function (status, notificationSummary, proposalSummary, integrationSummary, toolAudit) {
            var rows = [
                ['Статус', SimpleStomUi.label(status)],
                ['MCP tools', (toolAudit.safeToolCount || 0) + ' / ' + (toolAudit.toolCount || 0)],
                ['Ошибки уведомлений', notificationSummary.failedCount || 0],
                ['Кандидаты retry', notificationSummary.retryCandidateCount || 0],
                ['На ревью', proposalSummary.pendingReviewCount || 0],
                ['Нужны секреты', integrationSummary.needsSecretCount || 0]
            ];
            var html = '<div class="espo-dental-stom-layout" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));margin-bottom:10px">';

            rows.forEach(function (row) {
                html += SimpleStomUi.kpi(row[1], row[0]);
            });

            return html + '</div>';
        },

        renderIntegrationRows: function (rows) {
            var body = this.renderTable(rows, ['type', 'status', 'enabled'], function (row) {
                return [
                    row.type || '',
                    SimpleStomUi.label(row.status || 'disabled'),
                    row.enabled ? 'on' : 'off'
                ];
            });

            return SimpleStomUi.panel({
                title: 'Каналы',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderToolRows: function (rows) {
            var body = this.renderTable(rows, ['name', 'route', 'mutation'], function (row) {
                return [
                    row.name || '',
                    row.route || '',
                    row.directMutation ? 'mutation' : 'safe'
                ];
            });

            return SimpleStomUi.panel({
                title: 'MCP tools',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderFailedNotifications: function (rows) {
            var body = this.renderTable(rows, ['channel', 'error', 'retry'], function (row) {
                return [
                    row.channel || '',
                    row.errorMessage || row.provider || '',
                    row.retryCandidate ? 'retry' : 'hold'
                ];
            });

            return SimpleStomUi.panel({
                title: 'Ошибки уведомлений',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderProposalRows: function (rows) {
            var body = this.renderTable(rows, ['action', 'risk', 'summary'], function (row) {
                return [
                    row.actionType || '',
                    SimpleStomUi.label(row.riskLevel || 'medium'),
                    row.summary || row.name || ''
                ];
            });

            return SimpleStomUi.panel({
                title: 'Предложения ассистента',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderTable: function (rows, headings, mapper) {
            if (!rows.length) {
                return SimpleStomUi.emptyState('Нет данных.');
            }

            var html = '<table class="espo-dental-stom-table"><thead><tr>';
            headings.forEach(function (heading) {
                html += '<th>' + SimpleStomUi.escapeHtml(heading) + '</th>';
            });
            html += '</tr></thead><tbody>';

            rows.forEach(function (row) {
                var cells = mapper(row);
                html += '<tr>';
                cells.forEach(function (cell) {
                    html += '<td>' + SimpleStomUi.escapeHtml(cell) + '</td>';
                });
                html += '</tr>';
            });

            return html + '</tbody></table>';
        }
    });
});
