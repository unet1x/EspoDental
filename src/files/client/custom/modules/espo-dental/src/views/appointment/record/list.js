define('espo-dental:views/appointment/record/list', [
    'views/record/list',
    'espo-dental:lib/resource-grid',
    'espo-dental:lib/simple-stom-ui',
    'espo-dental:views/appointment/modals/slot-booking'
], function (Dep, ResourceGrid, SimpleStomUi) {
    return Dep.extend({
        calendarView: 'day',
        currentDate: null,
        clinicId: null,
        cabinetId: null,
        doctorId: null,
        serviceId: null,
        filterOptions: null,
        sidePanelVisible: true,
        sidePanelMode: 'waitlist',
        rowMinutes: 30,
        startHour: 8,
        endHour: 21,
        feedbackLimit: 12,

        setup: function () {
            Dep.prototype.setup.call(this);

            var d = new Date();
            this.currentDate = d.toISOString().slice(0, 10);
            this.filterOptions = {doctors: [], cabinets: [], services: []};
        },

        afterRender: function () {
            if (Dep.prototype.afterRender) {
                Dep.prototype.afterRender.call(this);
            }

            SimpleStomUi.ensureStyles();
            this.renderCalendarWorkspace();
            this.fetchCalendar();
        },

        remove: function () {
            this.disposeCalendarGrid();

            return Dep.prototype.remove.call(this);
        },

        renderCalendarWorkspace: function () {
            this.disposeCalendarGrid();
            this.$el.children('[data-name="appointment-calendar-workspace"]').remove();

            var columns = this.sidePanelVisible
                ? 'minmax(0,1fr) minmax(260px,320px)'
                : 'minmax(0,1fr)';
            var sidePanel = this.sidePanelVisible
                ? '<div data-name="appointment-calendar-side-panel"></div>'
                : '<div data-name="appointment-calendar-side-panel" style="display:none"></div>';
            var body = '<div class="espo-dental-stom-toolbar" data-name="appointment-calendar-toolbar"></div>' +
                '<div class="espo-dental-stom-layout espo-dental-stom-layout--two" ' +
                'style="grid-template-columns:' + columns + '">' +
                SimpleStomUi.panel({
                    title: 'Расписание',
                    body: '<div class="resource-calendar-host" style="overflow:auto;max-height:640px"></div>'
                }) +
                sidePanel +
                '</div>';

            this.$el.prepend(
                '<div data-name="appointment-calendar-workspace" style="margin-bottom:12px">' +
                SimpleStomUi.workspace(body, {classes: ['espo-dental-appointment-calendar-workspace']}) +
                '</div>'
            );

            this.renderCalendarToolbar();
        },

        renderCalendarToolbar: function () {
            var self = this;
            var $toolbar = this.$el.find('[data-name="appointment-calendar-toolbar"]');
            var step = this.calendarView === 'week' ? 7 : 1;
            var activeDay = this.calendarView === 'day' ? ' espo-dental-stom-button--primary' : '';
            var activeWeek = this.calendarView === 'week' ? ' espo-dental-stom-button--primary' : '';
            var $prev = $('<button type="button" class="espo-dental-stom-button" title="Предыдущий период">' +
                '<span class="fas fa-chevron-left" aria-hidden="true"></span>' +
                '</button>');
            var $next = $('<button type="button" class="espo-dental-stom-button" title="Следующий период">' +
                '<span class="fas fa-chevron-right" aria-hidden="true"></span>' +
                '</button>');
            var $today = $('<button type="button" class="espo-dental-stom-button">' +
                '<span class="fas fa-calendar-day" aria-hidden="true"></span><span>Сегодня</span>' +
                '</button>');
            var $reload = $('<button type="button" class="espo-dental-stom-button" title="Обновить">' +
                '<span class="fas fa-sync-alt" aria-hidden="true"></span>' +
                '</button>');
            var $sideToggle = $('<button type="button" class="espo-dental-stom-button' +
                (this.sidePanelVisible ? ' espo-dental-stom-button--primary' : '') +
                '" data-action="toggle-side-panel" title="Показать или скрыть контроль дня">' +
                '<span class="fas fa-columns" aria-hidden="true"></span><span>Контроль дня</span>' +
                '</button>');
            var $date = $('<input type="date" class="form-control input-sm" data-name="mini-calendar" ' +
                'style="width:140px">').val(this.currentDate);
            var $day = $('<button type="button" class="espo-dental-stom-button' + activeDay + '">День</button>');
            var $week = $('<button type="button" class="espo-dental-stom-button' + activeWeek + '">Неделя</button>');
            var $doctor = this.buildFilterSelect(
                'doctor-filter',
                'Врач',
                this.doctorId,
                this.filterOptions.doctors,
                'Все врачи'
            );
            var $cabinet = this.buildFilterSelect(
                'cabinet-filter',
                'Кабинет',
                this.cabinetId,
                this.filterOptions.cabinets,
                'Все кабинеты'
            );
            var $service = this.buildFilterSelect(
                'service-filter',
                'Услуга',
                this.serviceId,
                this.filterOptions.services,
                'Все услуги'
            );

            $prev.on('click', function () {
                self.shiftCalendarDate(-step);
            });
            $next.on('click', function () {
                self.shiftCalendarDate(step);
            });
            $today.on('click', function () {
                self.currentDate = new Date().toISOString().slice(0, 10);
                self.renderCalendarToolbar();
                self.fetchCalendar();
            });
            $reload.on('click', function () {
                self.fetchCalendar();
            });
            $sideToggle.on('click', function () {
                self.sidePanelVisible = !self.sidePanelVisible;
                self.renderCalendarWorkspace();
                self.fetchCalendar();
            });
            $date.on('change', function () {
                self.currentDate = $(this).val();
                self.fetchCalendar();
            });
            $day.on('click', function () {
                self.calendarView = 'day';
                self.renderCalendarToolbar();
                self.fetchCalendar();
            });
            $week.on('click', function () {
                self.calendarView = 'week';
                self.renderCalendarToolbar();
                self.fetchCalendar();
            });
            $doctor.find('select').on('change', function () {
                self.doctorId = $(this).val() || null;
                self.fetchCalendar();
            });
            $cabinet.find('select').on('change', function () {
                self.cabinetId = $(this).val() || null;
                self.fetchCalendar();
            });
            $service.find('select').on('change', function () {
                self.serviceId = $(this).val() || null;
                self.cabinetId = null;
                self.fetchCalendar();
            });

            $toolbar.empty().append(
                $prev,
                $today,
                $next,
                this.renderMiniCalendar($date),
                $('<span class="btn-group" style="display:inline-flex;gap:4px"></span>').append($day, $week),
                $doctor,
                $cabinet,
                $service,
                $sideToggle,
                $reload,
                '<span class="espo-dental-stom-toolbar__spacer"></span>'
            );
        },

        renderMiniCalendar: function ($input) {
            return $('<label class="espo-dental-stom-filter" style="display:inline-flex;align-items:center;' +
                'gap:6px;margin:0;font-size:12px;font-weight:600"></label>')
                .append('<span>Дата</span>')
                .append($input);
        },

        buildFilterSelect: function (name, label, value, rows, allLabel) {
            var found = !value;
            var $select = $('<select class="form-control input-sm" data-name="' + name + '" style="width:160px"></select>');

            $select.append($('<option>').attr('value', '').text(allLabel));

            (rows || []).forEach(function (row) {
                var id = row && row.id ? String(row.id) : '';

                if (!id) {
                    return;
                }

                if (id === value) {
                    found = true;
                }

                $select.append($('<option>').attr('value', id).text(row.name || id));
            });

            if (value && !found) {
                $select.append($('<option>').attr('value', value).text(value));
            }

            $select.val(value || '');

            return $('<label class="espo-dental-stom-filter" style="display:inline-flex;align-items:center;' +
                'gap:6px;margin:0;font-size:12px;font-weight:600"></label>')
                .append('<span>' + SimpleStomUi.escapeHtml(label) + '</span>')
                .append($select);
        },

        shiftCalendarDate: function (delta) {
            var d = new Date(this.currentDate + 'T00:00:00');
            d.setDate(d.getDate() + delta);
            this.currentDate = d.toISOString().slice(0, 10);

            this.renderCalendarToolbar();
            this.fetchCalendar();
        },

        fetchCalendar: function () {
            this.fetchAppointments();
            this.fetchFeedbackPanel();
        },

        fetchAppointments: function () {
            var self = this;
            var $host = this.$el.find('.resource-calendar-host');
            var data = this.buildCalendarRequestData();

            if (!$host.length) {
                return;
            }

            $host.html(SimpleStomUi.emptyState('Загрузка календаря...'));

            Espo.Ajax.getRequest('EspoDental/Calendar/appointments', data)
                .then(function (response) {
                    var filters = (response && response.filters) || {};

                    self.filterOptions = {
                        doctors: filters.doctors || (response && response.doctors) || [],
                        cabinets: filters.cabinets || (response && response.cabinets) || [],
                        services: filters.services || []
                    };
                    self.renderCalendarToolbar();
                    self.disposeCalendarGrid();

                    self.grid = new ResourceGrid($host[0], response || {}, {
                        startHour: self.startHour,
                        endHour: self.endHour,
                        rowMinutes: self.rowMinutes,
                        doctorId: self.doctorId,
                        onMove: self.handleMove.bind(self),
                        onResize: self.handleResize.bind(self),
                        onCellClick: self.handleCellClick.bind(self),
                        onAppointmentClick: self.handleAppointmentClick.bind(self)
                    });
                    self.grid.render();
                })
                .catch(function () {
                    $host.html(SimpleStomUi.emptyState('Не удалось загрузить календарь.'));
                });
        },

        buildCalendarRequestData: function () {
            var data = {
                date: this.currentDate,
                view: this.calendarView
            };

            if (this.clinicId) {
                data.clinicId = this.clinicId;
            }

            if (this.cabinetId) {
                data.cabinetId = this.cabinetId;
            }

            if (this.doctorId) {
                data.doctorId = this.doctorId;
            }

            if (this.serviceId) {
                data.serviceId = this.serviceId;
            }

            return data;
        },

        fetchFeedbackPanel: function () {
            var self = this;
            var $panel = this.$el.find('[data-name="appointment-calendar-side-panel"]');

            if (!$panel.length) {
                return;
            }

            if (!this.sidePanelVisible) {
                $panel.empty();
                return;
            }

            $panel.html(SimpleStomUi.panel({
                title: 'Контроль дня',
                body: SimpleStomUi.emptyState('Загрузка...')
            }));

            var data = {
                date: this.currentDate,
                limit: this.feedbackLimit
            };

            if (this.clinicId) {
                data.clinicId = this.clinicId;
            }
            if (this.cabinetId) {
                data.cabinetId = this.cabinetId;
            }
            if (this.doctorId) {
                data.doctorId = this.doctorId;
            }

            Espo.Ajax.getRequest('EspoDental/Calendar/feedbackPanel', data)
                .then(function (response) {
                    self.renderFeedbackPanel(response || {});
                })
                .catch(function () {
                    $panel.html(SimpleStomUi.panel({
                        title: 'Контроль дня',
                        body: SimpleStomUi.emptyState('Не удалось загрузить контроль дня.')
                    }));
                });
        },

        renderFeedbackPanel: function (data) {
            var self = this;
            var rows = this.getFeedbackRows(data || {});
            var body = this.renderFeedbackModeButtons();

            if (this.sidePanelMode === 'cancelled') {
                body += this.renderCancelled(rows);
            } else if (this.sidePanelMode === 'reschedule') {
                body += this.renderRescheduleRequests(rows);
            } else {
                body += this.renderWaitlist(rows);
            }

            this.$el.find('[data-name="appointment-calendar-side-panel"]').html(SimpleStomUi.panel({
                title: 'Контроль дня',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            }));

            this.$el.find('[data-action="calendar-panel-mode"]').on('click', function () {
                self.sidePanelMode = $(this).attr('data-mode') || 'waitlist';
                self.renderFeedbackPanel(data || {});
            });
        },

        getFeedbackRows: function (data) {
            if (this.sidePanelMode === 'cancelled') {
                return data.cancelled || [];
            }

            if (this.sidePanelMode === 'reschedule') {
                return data.rescheduleRequests || [];
            }

            return data.waitlist || [];
        },

        renderFeedbackModeButtons: function () {
            var waitlistClass = this.sidePanelMode === 'waitlist' ? ' espo-dental-stom-button--primary' : '';
            var cancelledClass = this.sidePanelMode === 'cancelled' ? ' espo-dental-stom-button--primary' : '';
            var rescheduleClass = this.sidePanelMode === 'reschedule' ? ' espo-dental-stom-button--primary' : '';

            return '<div class="espo-dental-stom-toolbar" style="margin-bottom:8px">' +
                '<button type="button" class="espo-dental-stom-button' + waitlistClass + '" ' +
                'data-action="calendar-panel-mode" data-mode="waitlist">Лист ожидания</button>' +
                '<button type="button" class="espo-dental-stom-button' + cancelledClass + '" ' +
                'data-action="calendar-panel-mode" data-mode="cancelled">Отмены и неявки</button>' +
                '<button type="button" class="espo-dental-stom-button' + rescheduleClass + '" ' +
                'data-action="calendar-panel-mode" data-mode="reschedule">Переносы</button>' +
                '</div>';
        },

        renderWaitlist: function (rows) {
            return this.renderListPanel('Лист ожидания', rows, function (row) {
                var href = row.id ? '#AppointmentWaitlistEntry/view/' + encodeURIComponent(row.id) : '#';

                return '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    '<a href="' + href + '">' + SimpleStomUi.escapeHtml(row.parentName || row.name || 'Пациент') + '</a>' +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(row.requestedDate || 'любая дата') +
                    '</span>' +
                    '</span>' +
                    SimpleStomUi.badge(row.priority || 'normal', row.priority || 'normal') +
                    '</li>';
            }, 'Лист ожидания пуст.');
        },

        renderCancelled: function (rows) {
            return this.renderListPanel('Отмененные и неявки', rows, function (row) {
                var href = row.id ? '#Appointment/view/' + encodeURIComponent(row.id) : '#';
                var time = String(row.dateStart || '').slice(11, 16);

                return '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    '<a href="' + href + '">' + SimpleStomUi.escapeHtml(row.parentName || row.name || 'Запись') + '</a>' +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(time || 'без времени') +
                    '</span>' +
                    '</span>' +
                    SimpleStomUi.badge(row.status || 'cancelled', row.status || 'cancelled') +
                    '</li>';
            }, 'Нет отмен и неявок.');
        },

        renderRescheduleRequests: function (rows) {
            return this.renderListPanel('Заявки на перенос', rows, function (row) {
                var href = row.id ? '#AppointmentRescheduleRequest/view/' + encodeURIComponent(row.id) : '#';
                var requested = String(row.requestedStartAt || '').slice(11, 16);
                var doctor = row.requestedDoctorName ? ' · ' + SimpleStomUi.escapeHtml(row.requestedDoctorName) : '';

                return '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    '<a href="' + href + '">' + SimpleStomUi.escapeHtml(row.patientName || row.name || 'Пациент') + '</a>' +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(requested || 'без времени') +
                    doctor +
                    '</span>' +
                    '</span>' +
                    SimpleStomUi.badge(row.status || 'pending_clinic_confirmation', row.status || 'pending_clinic_confirmation') +
                    '</li>';
            }, 'Нет заявок на перенос.');
        },

        renderListPanel: function (title, rows, renderRow, emptyMessage) {
            var body = SimpleStomUi.emptyState(emptyMessage);

            if (rows.length) {
                body = '<ul class="espo-dental-stom-list">';
                rows.forEach(function (row) {
                    body += renderRow(row);
                });
                body += '</ul>';
            }

            return '<div class="espo-dental-stom-muted" style="font-weight:700;margin:0 0 8px">' +
                SimpleStomUi.escapeHtml(title) +
                '</div>' +
                body;
        },

        handleMove: function (id, change, dateEnd, cabinetId) {
            if (typeof change === 'object') {
                change.id = id;
                this.persistAppointmentChange(change);

                return;
            }

            this.persistAppointmentChange({
                id: id,
                dateStart: change,
                dateEnd: dateEnd,
                cabinetId: cabinetId
            });
        },

        handleResize: function (id, change, dateEnd) {
            if (typeof change === 'object') {
                change.id = id;
                this.persistAppointmentChange(change);

                return;
            }

            this.persistAppointmentChange({id: id, dateStart: change, dateEnd: dateEnd});
        },

        persistAppointmentChange: function (payload) {
            var self = this;

            Espo.Ajax.postRequest('EspoDental/Calendar/move', payload)
                .then(function () {
                    self.fetchCalendar();
                    self.refreshRecordList();
                })
                .catch(function (xhr) {
                    Espo.Ui.error((xhr && xhr.responseText) || 'Не удалось обновить запись.');
                    self.fetchCalendar();
                });
        },

        handleCellClick: function (cabinetId, localStart, timezone, freeWindowMinutes) {
            this.openSlotBooking(cabinetId, localStart, timezone, freeWindowMinutes);
        },

        handleAppointmentClick: function (id) {
            this.getRouter().navigate('#Appointment/view/' + id, {trigger: true});
        },

        openSlotBooking: function (cabinetId, localStart, timezone, freeWindowMinutes) {
            this.createView('slotBooking', 'espo-dental:views/appointment/modals/slot-booking', {
                slot: {
                    localStart: localStart || '',
                    timezone: timezone || '',
                    cabinetId: cabinetId || '',
                    clinicId: this.clinicId || '',
                    serviceId: this.serviceId || '',
                    freeWindowMinutes: freeWindowMinutes
                },
                clinicId: this.clinicId || '',
                doctorId: this.doctorId || '',
                serviceId: this.serviceId || '',
                serviceOptions: this.filterOptions.services || []
            }, (function (view) {
                view.render();
                view.on('done', (function (response) {
                    this.fetchCalendar();
                    this.refreshRecordList();

                    if (response && response.appointmentId) {
                        this.getRouter().navigate('#Appointment/view/' + response.appointmentId, {trigger: true});
                    }
                }).bind(this));
            }).bind(this));
        },

        refreshRecordList: function () {
            if (this.collection && this.collection.fetch) {
                this.collection.fetch();
            }
        },

        disposeCalendarGrid: function () {
            if (this.grid && this.grid.dispose) {
                this.grid.dispose();
            }

            this.grid = null;
        }
    });
});
