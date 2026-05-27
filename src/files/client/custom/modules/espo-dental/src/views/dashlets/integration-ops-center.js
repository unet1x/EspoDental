define('espo-dental:views/dashlets/integration-ops-center', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/simple-stom-ui',
    'espo-dental:utils/dialogs'
], function (Dep, SimpleStomUi, Dialogs) {
    return Dep.extend({
        name: 'IntegrationOpsCenter',
        templateContent: '<div class="espo-dental-integration-ops-center"></div>',

        events: {
            'click [data-action="requeueNotification"]': 'requeueNotification',
            'click [data-action="processNotificationQueue"]': 'processNotificationQueue',
            'click [data-action="acceptProviderCredentials"]': 'acceptProviderCredentials'
        },

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
            var stageJAcceptance = data.stageJAcceptance || {};
            var notificationSummary = notifications.summary || {};
            var proposalSummary = proposals.summary || {};
            var integrationSummary = integrations.summary || {};
            var toolAudit = (mcp && mcp.toolAudit) || {};
            var html = this.renderKpis(data.status || 'ok', notificationSummary, proposalSummary, integrationSummary, toolAudit);

            html += this.renderStageJAcceptance(stageJAcceptance);

            html += '<div class="espo-dental-stom-layout espo-dental-stom-layout--two">' +
                '<div>' +
                    this.renderIntegrationRows(integrations.rows || []) +
                    this.renderProviderReadiness(integrations.rows || []) +
                    this.renderToolRows((mcp && mcp.tools) || []) +
                '</div>' +
                '<div>' +
                    this.renderQueueControls(notificationSummary) +
                    this.renderQueuedNotifications(notifications.queuedRows || []) +
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
                ['В очереди', notificationSummary.queuedCount || 0],
                ['Queue ready', notificationSummary.queuedReadyCount || 0],
                ['Queue blocked', notificationSummary.queuedBlockedCount || 0],
                ['Ошибки уведомлений', notificationSummary.failedCount || 0],
                ['Кандидаты retry', notificationSummary.retryCandidateCount || 0],
                ['На ревью', proposalSummary.pendingReviewCount || 0],
                ['Нужны секреты', integrationSummary.needsSecretCount || 0],
                ['Runtime gaps', integrationSummary.runtimeMissingCount || 0],
                ['Dry-run ready', integrationSummary.dryRunReadyCount || 0],
                ['Acceptance', integrationSummary.acceptancePendingCount || 0],
                ['Accepted', integrationSummary.acceptedCount || 0]
            ];
            var html = '<div class="espo-dental-stom-layout" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));margin-bottom:10px">';

            rows.forEach(function (row) {
                html += SimpleStomUi.kpi(row[1], row[0]);
            });

            return html + '</div>';
        },

        renderStageJAcceptance: function (acceptance) {
            var checks = acceptance.checks || [];
            if (!checks.length) {
                return '';
            }

            var body = this.renderTable(checks, ['check', 'status', 'detail'], function (row) {
                return [
                    row.label || row.key || '',
                    SimpleStomUi.badge(row.status || 'attention', row.status || 'attention'),
                    row.detail || ''
                ];
            }, {rawColumns: [1]});

            return SimpleStomUi.panel({
                title: 'Stage J acceptance',
                body: '<div class="espo-dental-stom-toolbar" style="margin:0 0 8px">' +
                    SimpleStomUi.badge(acceptance.status || 'attention', acceptance.status || 'attention') +
                    SimpleStomUi.badge('Готово ' + (acceptance.readyCount || 0), 'primary') +
                    SimpleStomUi.badge('Внимание ' + (acceptance.attentionCount || 0), 'warning') +
                    '</div>' + body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderIntegrationRows: function (rows) {
            var body = this.renderTable(rows, ['type', 'status', 'enabled', 'runtime', 'live'], function (row) {
                var liveDelivery = row.liveDelivery || {};
                var liveStatus = liveDelivery.status || 'blocked';
                var runtimeStatus = row.enabled ? (row.runtimeConfigured ? 'ok' : 'missing') : 'not_checked';

                return [
                    row.type || '',
                    SimpleStomUi.label(row.status || 'disabled'),
                    row.enabled ? 'on' : 'off',
                    SimpleStomUi.badge(runtimeStatus, runtimeStatus),
                    SimpleStomUi.badge(liveStatus, liveStatus)
                ];
            }, {rawColumns: [3, 4]});

            return SimpleStomUi.panel({
                title: 'Каналы',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderProviderReadiness: function (rows) {
            if (!rows.length) {
                return '';
            }

            var html = '';

            rows.forEach((function (row) {
                var checklist = row.checklist || [];
                var liveDelivery = row.liveDelivery || {};
                var liveStatus = liveDelivery.status || 'blocked';

                html += '<div style="margin-bottom:12px">' +
                    '<div class="espo-dental-stom-toolbar" style="margin:0 0 6px">' +
                        '<strong>' + SimpleStomUi.escapeHtml(row.type || '') + '</strong>' +
                        SimpleStomUi.badge(liveStatus, liveStatus) +
                        this.renderProviderAcceptanceAction(row, liveStatus) +
                    '</div>' +
                    this.renderTable(checklist, ['check', 'status', 'required'], function (item) {
                        var itemStatus = item.status || 'missing';

                        return [
                            item.label || item.key || '',
                            SimpleStomUi.badge(itemStatus, itemStatus),
                            SimpleStomUi.formatValue(!!item.required)
                        ];
                    }, {rawColumns: [1]}) +
                    '<div class="espo-dental-stom-muted" style="margin-top:6px;font-size:12px">' +
                        SimpleStomUi.escapeHtml(liveDelivery.nextStep || '') +
                    '</div>' +
                    '</div>';
            }).bind(this));

            return SimpleStomUi.panel({
                title: 'Provider readiness',
                body: html,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderProviderAcceptanceAction: function (row, liveStatus) {
            if (liveStatus !== 'pending_acceptance' || !row.id) {
                return '';
            }

            return SimpleStomUi.button('Принять', {
                tone: 'primary',
                attrs: {
                    'data-action': 'acceptProviderCredentials',
                    'data-id': row.id || '',
                    'data-type': row.type || ''
                }
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

        renderQueueControls: function (summary) {
            if (!summary || !summary.queuedCount) {
                return '';
            }

            return SimpleStomUi.panel({
                title: 'Очередь уведомлений',
                body: '<div class="espo-dental-stom-toolbar" style="margin:0">' +
                    SimpleStomUi.badge('В очереди ' + summary.queuedCount, 'primary') +
                    SimpleStomUi.button('Обработать', {
                        tone: 'primary',
                        attrs: {'data-action': 'processNotificationQueue'}
                    }) +
                    '</div>',
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderQueuedNotifications: function (rows) {
            var body = this.renderTable(rows, ['channel', 'provider', 'gate', 'scheduled', 'open'], (function (row) {
                var gate = row.deliveryGate || {};
                var gateStatus = gate.ok ? 'ready' : (gate.error || 'blocked');

                return [
                    row.channel || '',
                    row.provider || '',
                    SimpleStomUi.badge(gateStatus, gateStatus),
                    row.scheduledFor || '',
                    this.renderRecordLink('NotificationLog', row.id, 'Открыть')
                ];
            }).bind(this), {rawColumns: [2, 4]});

            return SimpleStomUi.panel({
                title: 'Preflight очереди',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderFailedNotifications: function (rows) {
            var body = this.renderTable(rows, ['channel', 'error', 'retry', 'action'], (function (row) {
                return [
                    row.channel || '',
                    row.errorMessage || row.provider || '',
                    row.retryCandidate ? 'retry' : 'hold',
                    this.renderNotificationActions(row)
                ];
            }).bind(this), {rawColumns: [3]});

            return SimpleStomUi.panel({
                title: 'Ошибки уведомлений',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderProposalRows: function (rows) {
            var body = this.renderTable(rows, ['action', 'risk', 'summary', 'open'], (function (row) {
                return [
                    row.actionType || '',
                    SimpleStomUi.label(row.riskLevel || 'medium'),
                    row.summary || row.name || '',
                    this.renderRecordLink('AssistantActionProposal', row.id, 'Ревью')
                ];
            }).bind(this), {rawColumns: [3]});

            return SimpleStomUi.panel({
                title: 'Предложения ассистента',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderTable: function (rows, headings, mapper, options) {
            if (!rows.length) {
                return SimpleStomUi.emptyState('Нет данных.');
            }

            options = options || {};
            var rawColumns = options.rawColumns || [];
            var html = '<table class="espo-dental-stom-table"><thead><tr>';
            headings.forEach(function (heading) {
                html += '<th>' + SimpleStomUi.escapeHtml(heading) + '</th>';
            });
            html += '</tr></thead><tbody>';

            rows.forEach(function (row) {
                var cells = mapper(row);
                html += '<tr>';
                cells.forEach(function (cell, index) {
                    html += '<td>' +
                        (rawColumns.indexOf(index) !== -1 ? (cell || '') : SimpleStomUi.escapeHtml(cell)) +
                        '</td>';
                });
                html += '</tr>';
            });

            return html + '</tbody></table>';
        },

        renderRecordLink: function (entityType, id, label) {
            if (!id) {
                return '';
            }

            return '<a href="#' + encodeURIComponent(entityType) + '/view/' + encodeURIComponent(id) + '">' +
                SimpleStomUi.escapeHtml(label) +
                '</a>';
        },

        renderNotificationActions: function (row) {
            var html = '<span class="espo-dental-stom-toolbar" style="margin:0;gap:6px">' +
                this.renderRecordLink('NotificationLog', row.id, 'Открыть');

            if (row.retryCandidate) {
                html += SimpleStomUi.button('В очередь', {
                    tone: 'primary',
                    attrs: {
                        'data-action': 'requeueNotification',
                        'data-id': row.id || ''
                    }
                });
            }

            return html + '</span>';
        },

        requeueNotification: function (e) {
            e.preventDefault();

            var id = $(e.currentTarget).attr('data-id') || '';
            if (!id) {
                return;
            }

            Dialogs.prompt(this, {
                title: 'Вернуть уведомление в очередь',
                message: 'Заметка необязательна. Отправка провайдеру этим действием не выполняется.',
                value: '',
                submitLabel: 'В очередь'
            }).then((function (note) {
                if (note === null) {
                    return;
                }

                Espo.Ajax.postRequest('EspoDental/NotificationLog/requeue', {
                    id: id,
                    note: note || ''
                })
                    .then((function () {
                        Espo.Ui.success('Уведомление возвращено в очередь.');
                        this.fetchData();
                    }).bind(this))
                    .catch(function (xhr) {
                        Espo.Ui.error((xhr && xhr.responseText) || 'Не удалось вернуть уведомление в очередь.');
                    });
            }).bind(this));
        },

        processNotificationQueue: function (e) {
            e.preventDefault();

            Dialogs.confirm(this, {
                message: 'Будут обработаны queued уведомления через настроенный delivery gateway.',
                confirmText: 'Обработать'
            }).then((function (confirmed) {
                if (!confirmed) {
                    return;
                }

                Espo.Ajax.postRequest('EspoDental/NotificationLog/processQueue', {
                    limit: parseInt(this.getOption('displayRecords'), 10) || 8
                })
                    .then((function (result) {
                        var processed = result && result.processed ? result.processed : 0;
                        var skipped = result && result.skipped ? result.skipped : 0;
                        var message = 'Обработано уведомлений: ' + processed + '.';
                        if (skipped) {
                            message += ' Пропущено preflight: ' + skipped + '.';
                        }
                        Espo.Ui.success(message);
                        this.fetchData();
                    }).bind(this))
                    .catch(function (xhr) {
                        Espo.Ui.error((xhr && xhr.responseText) || 'Не удалось обработать очередь уведомлений.');
                    });
            }).bind(this));
        },

        acceptProviderCredentials: function (e) {
            e.preventDefault();

            var id = $(e.currentTarget).attr('data-id') || '';
            var type = $(e.currentTarget).attr('data-type') || '';
            if (!id) {
                return;
            }

            Dialogs.prompt(this, {
                title: 'Принять credentials провайдера',
                message: 'Фиксируется только staff acceptance для ' + type + '. Живая отправка этим действием не выполняется.',
                value: '',
                submitLabel: 'Принять'
            }).then((function (note) {
                if (note === null) {
                    return;
                }

                Espo.Ajax.postRequest('EspoDental/Integration/acceptProviderCredentials', {
                    id: id,
                    note: note || ''
                })
                    .then((function () {
                        Espo.Ui.success('Credentials провайдера приняты.');
                        this.fetchData();
                    }).bind(this))
                    .catch(function (xhr) {
                        Espo.Ui.error((xhr && xhr.responseText) || 'Не удалось принять credentials провайдера.');
                    });
            }).bind(this));
        }
    });
});
