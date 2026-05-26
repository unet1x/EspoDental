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
            'click [data-action="openPatient"]': 'openPatient',
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
                    var selected = patient.id === this.selectedId ? ' espo-dental-stom-badge--primary' : '';
                    body += '<li class="espo-dental-stom-list__item">' +
                        '<span>' +
                        '<button type="button" class="btn btn-link btn-sm" data-action="selectPatient" ' +
                        'data-id="' + SimpleStomUi.escapeHtml(patient.id) + '" style="padding:0;text-align:left">' +
                        SimpleStomUi.escapeHtml(patient.name || patient.id) +
                        '</button>' +
                        '<span class="espo-dental-stom-muted"> · ' +
                        SimpleStomUi.escapeHtml(patient.phone || patient.cardNumber || '') +
                        '</span>' +
                        '</span>' +
                        '<span class="espo-dental-stom-badge' + selected + '">' +
                        SimpleStomUi.escapeHtml(patient.status || 'patient') +
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
                '</div>' +
                '</div>';
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

        renderTabBody: function (tab, data) {
            if (tab === 'toothChart') {
                return this.renderToothChartTab(data);
            }

            if (tab === 'clinicalHistory') {
                return this.renderKeyValues('Только клиническая история, без оплат и финансовых сумм', data);
            }

            if (tab === 'finance') {
                return this.renderKeyValues('Только расчеты и оплаты, без клинических записей', data);
            }

            return this.renderKeyValues('', data);
        },

        renderToothChartTab: function (data) {
            data = data || {};

            if (!data.snapshotCount) {
                return SimpleStomUi.emptyState('Снимков зубной формулы пока нет.');
            }

            var current = data.currentSnapshot || {};
            var html = '<div class="espo-dental-stom-toolbar" style="margin-bottom:8px">' +
                '<span class="espo-dental-stom-badge espo-dental-stom-badge--primary">' +
                SimpleStomUi.escapeHtml(current.dentitionType || 'adult') +
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
                    '<td>' + SimpleStomUi.escapeHtml(row.surface || 'whole') + '</td>' +
                    '<td>' + SimpleStomUi.escapeHtml(row.condition || '') + '</td>' +
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
                    '<td>' + SimpleStomUi.escapeHtml(snapshot.dentitionType || '') + '</td>' +
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
                html += '<tr><th>' + SimpleStomUi.escapeHtml(key) + '</th><td>' +
                    SimpleStomUi.escapeHtml(data[key] === null ? '' : data[key]) +
                    '</td></tr>';
            });
            html += '</tbody></table>';

            return html;
        },

        selectPatient: function (e) {
            this.selectedId = $(e.currentTarget).attr('data-id') || null;
            this.fetchWorkspace();
        },

        selectTab: function (e) {
            this.activeTab = $(e.currentTarget).attr('data-tab') || 'basicData';
            this.fetchWorkspace();
        },

        openPatient: function () {
            if (this.selectedId) {
                this.getRouter().navigate('#Patient/view/' + this.selectedId, {trigger: true});
            }
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
