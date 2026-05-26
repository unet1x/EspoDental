define('espo-dental:views/dashlets/patient-workspace', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, SimpleStomUi) {
    return Dep.extend({
        name: 'PatientWorkspace',
        templateContent: '<div class="espo-dental-patient-workspace"></div>',

        events: {
            'input [data-name="patientSearch"]': 'scheduleSearch',
            'click [data-action="selectPatient"]': 'selectPatient',
            'click [data-action="bookAppointment"]': 'bookAppointment',
            'click [data-action="uploadFile"]': 'uploadFile',
            'click [data-tab]': 'selectTab'
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.query = '';
            this.selectedId = null;
            this.activeTab = 'basicData';
        },

        afterRender: function () {
            SimpleStomUi.ensureStyles();
            this.renderShell();
            this.fetchWorkspace();
        },

        renderShell: function () {
            var html = '<div class="espo-dental-stom-toolbar">' +
                '<input type="text" class="form-control input-sm" data-name="patientSearch" ' +
                'placeholder="Фамилия, имя, телефон или номер карты" style="max-width:360px">' +
                '<span class="espo-dental-stom-toolbar__spacer"></span>' +
                '</div>' +
                '<div class="espo-dental-stom-layout espo-dental-stom-layout--two">' +
                '<div data-name="patientList"></div>' +
                '<div data-name="patientDetail"></div>' +
                '</div>';

            this.$el.find('.espo-dental-patient-workspace').html(SimpleStomUi.workspace(html));
        },

        scheduleSearch: function () {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout((function () {
                this.query = this.$el.find('[data-name="patientSearch"]').val() || '';
                this.selectedId = null;
                this.fetchWorkspace();
            }).bind(this), 250);
        },

        fetchWorkspace: function () {
            var data = {
                q: this.query,
                selectedId: this.selectedId,
                limit: parseInt(this.getOption('displayRecords'), 10) || 20
            };

            this.$el.find('[data-name="patientList"]').html(SimpleStomUi.emptyState('Загрузка пациентов...'));
            this.$el.find('[data-name="patientDetail"]').html(SimpleStomUi.emptyState('Загрузка карточки...'));

            Espo.Ajax.getRequest('EspoDental/Patient/workspace', data)
                .then((function (response) {
                    this.selectedId = response.selectedPatientId || null;
                    this.renderWorkspace(response || {});
                }).bind(this))
                .catch((function () {
                    this.$el.find('[data-name="patientList"]').html(SimpleStomUi.emptyState('Не удалось загрузить список пациентов.'));
                    this.$el.find('[data-name="patientDetail"]').html(SimpleStomUi.emptyState('Не удалось загрузить карточку пациента.'));
                }).bind(this));
        },

        renderWorkspace: function (data) {
            this.renderPatientList(data.patients || []);
            this.renderPatientDetail(data.selectedPatient || null);
        },

        renderPatientList: function (patients) {
            var body = SimpleStomUi.emptyState('Пациенты не найдены.');

            if (patients.length) {
                body = '<ul class="espo-dental-stom-list">';
                patients.forEach((function (patient) {
                    var status = patient.status || 'patient';
                    var meta = [];
                    var nextAppointment = this.formatAppointment(patient.nextAppointment);

                    if (patient.phone) {
                        meta.push(patient.phone);
                    }
                    if (patient.cardNumber) {
                        meta.push('карта ' + patient.cardNumber);
                    }
                    if (patient.age !== null && typeof patient.age !== 'undefined') {
                        meta.push(patient.age + ' лет');
                    }
                    if (patient.preferredChannel) {
                        meta.push('связь: ' + this.formatChannel(patient.preferredChannel));
                    }
                    if (nextAppointment) {
                        meta.push('следующая: ' + nextAppointment);
                    }

                    body += '<li class="espo-dental-stom-list__item">' +
                        '<span>' +
                        '<button type="button" class="btn btn-link btn-sm" data-action="selectPatient" ' +
                        'data-id="' + SimpleStomUi.escapeHtml(patient.id) + '" style="padding:0;text-align:left">' +
                        SimpleStomUi.escapeHtml(patient.name || patient.id) +
                        '</button>' +
                        (meta.length ? '<span class="espo-dental-stom-muted"> · ' +
                            SimpleStomUi.escapeHtml(meta.join(' · ')) +
                            '</span>' : '') +
                        '</span>' +
                        '<span class="espo-dental-stom-toolbar" style="margin:0;justify-content:flex-end">' +
                        this.renderAlertBadges(patient.alerts || [], 3) +
                        SimpleStomUi.badge(patient.id === this.selectedId ? 'выбран' : status, patient.id === this.selectedId ? 'primary' : status) +
                        '</span>' +
                        '</li>';
                }).bind(this));
                body += '</ul>';
            }

            this.$el.find('[data-name="patientList"]').html(SimpleStomUi.panel({
                title: 'Список пациентов',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            }));
        },

        renderPatientDetail: function (patient) {
            if (!patient) {
                this.$el.find('[data-name="patientDetail"]').html(SimpleStomUi.emptyState('Выберите пациента из списка.'));
                return;
            }

            var body = '<div class="espo-dental-stom-toolbar">' +
                SimpleStomUi.button('Записаться на прием', {tone: 'primary', attrs: {'data-action': 'bookAppointment'}}) +
                SimpleStomUi.button('Загрузить файл', {tone: 'quiet', attrs: {'data-action': 'uploadFile'}}) +
                '</div>';

            body += '<div style="margin-bottom:10px">' +
                '<h3 class="espo-dental-stom-panel__title">' + SimpleStomUi.escapeHtml(patient.name || '') + '</h3>' +
                '<div class="espo-dental-stom-muted">' +
                SimpleStomUi.escapeHtml(patient.phone || '') +
                (patient.emailAddress ? ' · ' + SimpleStomUi.escapeHtml(patient.emailAddress) : '') +
                (patient.cardNumber ? ' · карта ' + SimpleStomUi.escapeHtml(patient.cardNumber) : '') +
                '</div>' +
                '<div class="espo-dental-stom-toolbar" style="margin-top:8px">' +
                SimpleStomUi.badge(patient.status || 'patient', patient.status || 'patient') +
                this.renderAlertBadges(patient.alerts || [], 8) +
                '</div>' +
                '</div>';
            body += this.renderPatientHighlights(patient);
            body += this.renderTabs(patient.tabs || {});

            this.$el.find('[data-name="patientDetail"]').html(SimpleStomUi.panel({
                title: 'Карточка пациента',
                body: body
            }));
        },

        renderTabs: function (tabs) {
            var labels = {
                basicData: 'Основные данные',
                toothChart: 'Зубная формула',
                clinicalHistory: 'История обращений',
                files: 'Файлы',
                finance: 'Расчеты / финансы',
                family: 'Семья'
            };
            var order = ['basicData', 'toothChart', 'clinicalHistory', 'files', 'finance', 'family'];
            var html = '<div class="espo-dental-stom-toolbar" style="margin-bottom:8px">';

            order.forEach((function (name) {
                var tone = this.activeTab === name ? 'primary' : 'quiet';
                html += SimpleStomUi.button(labels[name], {
                    tone: tone,
                    attrs: {'data-tab': name}
                });
            }).bind(this));

            html += '</div>';
            html += '<div>' + this.renderTabBody(this.activeTab, tabs[this.activeTab] || {}) + '</div>';

            return html;
        },

        renderPatientHighlights: function (patient) {
            var rows = [
                ['Возраст', patient.age !== null && typeof patient.age !== 'undefined' ? patient.age + ' лет' : ''],
                ['Канал связи', this.formatChannel(patient.preferredChannel || '')],
                ['Ближайшая запись', this.formatAppointment(patient.nextAppointment) || 'нет'],
                ['Баланс', this.formatBalance(patient.balance)]
            ];
            var html = '<table class="espo-dental-stom-table" style="margin-bottom:10px"><tbody>';

            rows.forEach(function (row) {
                html += '<tr><th>' + SimpleStomUi.escapeHtml(row[0]) + '</th><td>' +
                    SimpleStomUi.escapeHtml(row[1] || '') +
                    '</td></tr>';
            });

            return html + '</tbody></table>';
        },

        renderTabBody: function (tab, data) {
            if (tab === 'toothChart') {
                return this.renderToothChartTab(data);
            }

            if (tab === 'clinicalHistory') {
                return this.renderClinicalHistoryTab(data);
            }

            if (tab === 'files') {
                return this.renderFilesTab(data);
            }

            if (tab === 'finance') {
                return this.renderFinanceTab(data);
            }

            return this.renderKeyValues('', data);
        },

        renderClinicalHistoryTab: function (data) {
            var html = this.renderKeyValues(
                'Только клиническая история, без оплат и финансовых сумм',
                this.pickSummary(data, ['nextAppointment', 'futureAppointmentCount', 'visitCount', 'latestVisitId'])
            );

            html += this.renderLinkedRows('Записи', data.recentAppointments || [], 'Записей нет.');
            html += this.renderLinkedRows('Приемы', data.recentVisits || [], 'Приемов нет.');

            return html;
        },

        renderFilesTab: function (data) {
            var html = this.renderKeyValues('', this.pickSummary(data, ['visitPhotoCount', 'questionnaireCount']));

            html += this.renderLinkedRows('Анкеты', data.recentQuestionnaires || [], 'Анкет нет.');

            return html;
        },

        renderFinanceTab: function (data) {
            var html = this.renderKeyValues(
                'Только расчеты и оплаты, без клинических записей',
                this.pickSummary(data, ['openInvoiceCount', 'openInvoiceBalance', 'paymentCount'])
            );

            html += this.renderLinkedRows('Открытые счета', data.openInvoices || [], 'Открытых счетов нет.');
            html += this.renderLinkedRows('Платежи', data.recentPayments || [], 'Платежей нет.');

            return html;
        },

        renderLinkedRows: function (title, rows, emptyMessage) {
            var html = '<div class="espo-dental-stom-muted" style="font-weight:700;margin:10px 0 6px">' +
                SimpleStomUi.escapeHtml(title) +
                '</div>';

            if (!rows.length) {
                return html + SimpleStomUi.emptyState(emptyMessage);
            }

            html += '<table class="espo-dental-stom-table"><tbody>';
            rows.forEach(function (row) {
                var href = row.entityType && row.id
                    ? '#' + encodeURIComponent(row.entityType) + '/view/' + encodeURIComponent(row.id)
                    : '#';
                var amount = row.amount || row.amount === 0 ? ' · ' + row.amount : '';

                html += '<tr>' +
                    '<td><a href="' + href + '">' + SimpleStomUi.escapeHtml(row.label || row.id || '') + '</a>' +
                    '<div class="espo-dental-stom-muted">' +
                    SimpleStomUi.escapeHtml(String(row.date || '').slice(0, 16)) +
                    (row.doctorName ? ' · ' + SimpleStomUi.escapeHtml(row.doctorName) : '') +
                    amount +
                    '</div></td>' +
                    '<td style="text-align:right">' +
                    (row.status ? SimpleStomUi.badge(row.status, row.status) : '') +
                    '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';

            return html;
        },

        renderToothChartTab: function (data) {
            data = data || {};

            if (!data.snapshotCount) {
                return SimpleStomUi.emptyState('Снимков зубной формулы пока нет.');
            }

            var current = data.currentSnapshot || {};
            var html = '<div class="espo-dental-stom-toolbar" style="margin-bottom:8px">' +
                '<span class="espo-dental-stom-badge espo-dental-stom-badge--primary">' +
                SimpleStomUi.escapeHtml(SimpleStomUi.label(current.dentitionType || 'adult', 'dentition')) +
                '</span>' +
                '<span class="espo-dental-stom-muted">' +
                SimpleStomUi.escapeHtml(current.recordedAt || '') +
                (current.doctorName ? ' · ' + SimpleStomUi.escapeHtml(current.doctorName) : '') +
                '</span>' +
                '</div>';

            html += '<div style="margin-bottom:10px">';
            html += this.renderSnapshotSummary(current);
            html += '</div>';
            html += this.renderRecentSnapshots(data.recentSnapshots || []);

            return html;
        },

        renderSnapshotSummary: function (snapshot) {
            var summary = snapshot.summary || [];

            if (!summary.length) {
                return SimpleStomUi.emptyState('В текущем снимке нет отмеченных состояний.');
            }

            var html = '<table class="espo-dental-stom-table"><thead><tr>' +
                '<th>Зуб</th><th>Поверхность</th><th>Состояние</th><th>Заметка</th>' +
                '</tr></thead><tbody>';

            summary.forEach(function (row) {
                html += '<tr>' +
                    '<td>' + SimpleStomUi.escapeHtml(row.tooth || '') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(SimpleStomUi.label(row.surface || 'whole', 'surface')) + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(SimpleStomUi.label(row.condition || '', 'condition')) + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(row.note || '') + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';

            return html;
        },

        renderRecentSnapshots: function (snapshots) {
            if (!snapshots.length) {
                return '';
            }

            var html = '<div class="espo-dental-stom-muted" style="margin-bottom:6px">История снимков</div>' +
                '<table class="espo-dental-stom-table"><thead><tr>' +
                '<th>Дата</th><th>Прикус</th><th>Прием</th><th>Врач</th><th>Отмечено</th>' +
                '</tr></thead><tbody>';

            snapshots.forEach(function (snapshot) {
                html += '<tr data-tooth-chart-snapshot="' + SimpleStomUi.escapeHtml(snapshot.id || '') + '">' +
                    '<td>' + SimpleStomUi.escapeHtml(snapshot.recordedAt || '') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(SimpleStomUi.label(snapshot.dentitionType || '', 'dentition')) + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(snapshot.visitName || '') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(snapshot.doctorName || '') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(snapshot.annotatedTeeth || 0) + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';

            return html;
        },

        renderKeyValues: function (prefix, data) {
            var html = prefix ? '<div class="espo-dental-stom-muted" style="margin-bottom:6px">' +
                SimpleStomUi.escapeHtml(prefix) + '</div>' : '';

            html += '<table class="espo-dental-stom-table"><tbody>';
            Object.keys(data).forEach(function (key) {
                html += '<tr><th>' + SimpleStomUi.escapeHtml(SimpleStomUi.label(key, 'field')) + '</th><td>' +
                    SimpleStomUi.escapeHtml(this.formatKeyValue(data[key])) +
                    '</td></tr>';
            }, this);
            html += '</tbody></table>';

            return html;
        },

        renderAlertBadges: function (alerts, limit) {
            var html = '';

            (alerts || []).slice(0, limit || 3).forEach(function (alert) {
                html += SimpleStomUi.badge(alert.label || alert.key || '', alert.tone || 'muted');
            });

            return html;
        },

        formatKeyValue: function (value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }

            if (typeof value === 'object') {
                return this.formatAppointment(value);
            }

            return SimpleStomUi.formatValue(value);
        },

        formatAppointment: function (appointment) {
            if (!appointment || !appointment.dateStart) {
                return '';
            }

            var parts = [String(appointment.dateStart).slice(0, 16)];

            if (appointment.serviceName) {
                parts.push(appointment.serviceName);
            }
            if (appointment.doctorName) {
                parts.push(appointment.doctorName);
            }
            if (appointment.cabinetName) {
                parts.push(appointment.cabinetName);
            }

            return parts.join(' · ');
        },

        formatChannel: function (channel) {
            var labels = {
                email: 'email',
                sms: 'SMS',
                telegram: 'Telegram',
                whatsapp: 'WhatsApp',
                phone: 'телефон'
            };

            return labels[channel] || channel || '';
        },

        formatBalance: function (balance) {
            balance = parseFloat(balance || 0);

            if (balance > 0) {
                return 'аванс ' + balance;
            }

            if (balance < 0) {
                return 'долг ' + Math.abs(balance);
            }

            return '0';
        },

        pickSummary: function (data, keys) {
            var out = {};

            keys.forEach(function (key) {
                if (Object.prototype.hasOwnProperty.call(data || {}, key)) {
                    out[key] = data[key];
                }
            });

            return out;
        },

        selectPatient: function (e) {
            this.selectedId = $(e.currentTarget).attr('data-id') || null;
            this.fetchWorkspace();
        },

        selectTab: function (e) {
            this.activeTab = $(e.currentTarget).attr('data-tab') || 'basicData';
            this.fetchWorkspace();
        },

        bookAppointment: function () {
            if (this.selectedId) {
                this.getRouter().navigate('#Appointment/create?parentType=Patient&parentId=' + this.selectedId, {
                    trigger: true
                });
            }
        },

        uploadFile: function () {
            if (this.selectedId) {
                this.getRouter().navigate('#Patient/view/' + this.selectedId, {trigger: true});
            }
        }
    });
});
