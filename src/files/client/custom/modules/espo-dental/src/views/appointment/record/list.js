define('espo-dental:views/appointment/record/list', [
    'views/record/list',
    'espo-dental:lib/resource-grid',
    'espo-dental:lib/simple-stom-ui',
    'espo-dental:views/appointment/modals/slot-booking'
], function (Dep, ResourceGrid, SimpleStomUi) {
    return Dep.extend({
        calendarView: 'day',
        currentDate: null,
        rowMinutes: 30,
        startHour: 8,
        endHour: 21,
        feedbackLimit: 12,

        setup: function () {
            Dep.prototype.setup.call(this);

            var d = new Date();
            this.currentDate = d.toISOString().slice(0, 10);
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

            var body = '<div class="espo-dental-stom-toolbar" data-name="appointment-calendar-toolbar"></div>' +
                '<div class="espo-dental-stom-layout espo-dental-stom-layout--two" ' +
                'style="grid-template-columns:minmax(0,1fr) minmax(260px,320px)">' +
                SimpleStomUi.panel({
                    title: 'Расписание',
                    body: '<div class="resource-calendar-host" style="overflow:auto;max-height:640px"></div>'
                }) +
                '<div data-name="appointment-calendar-side-panel"></div>' +
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
            var $date = $('<input type="date" class="form-control input-sm" style="width:140px">').val(this.currentDate);
            var $day = $('<button type="button" class="espo-dental-stom-button' + activeDay + '">День</button>');
            var $week = $('<button type="button" class="espo-dental-stom-button' + activeWeek + '">Неделя</button>');

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

            $toolbar.empty().append(
                $prev,
                $today,
                $next,
                $date,
                $('<span class="btn-group" style="display:inline-flex;gap:4px"></span>').append($day, $week),
                $reload,
                '<span class="espo-dental-stom-toolbar__spacer"></span>'
            );
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
            var data = {
                date: this.currentDate,
                view: this.calendarView
            };

            if (!$host.length) {
                return;
            }

            $host.html(SimpleStomUi.emptyState('Загрузка календаря...'));

            Espo.Ajax.getRequest('EspoDental/Calendar/appointments', data)
                .then(function (response) {
                    self.disposeCalendarGrid();

                    self.grid = new ResourceGrid($host[0], response || {}, {
                        startHour: self.startHour,
                        endHour: self.endHour,
                        rowMinutes: self.rowMinutes,
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

        fetchFeedbackPanel: function () {
            var self = this;
            var $panel = this.$el.find('[data-name="appointment-calendar-side-panel"]');

            if (!$panel.length) {
                return;
            }

            $panel.html(SimpleStomUi.panel({
                title: 'Контроль дня',
                body: SimpleStomUi.emptyState('Загрузка...')
            }));

            Espo.Ajax.getRequest('EspoDental/Calendar/feedbackPanel', {
                date: this.currentDate,
                limit: this.feedbackLimit
            })
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
            var html = '';

            html += this.renderWaitlist(data.waitlist || []);
            html += this.renderCancelled(data.cancelled || []);

            this.$el.find('[data-name="appointment-calendar-side-panel"]').html(html);
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

        renderListPanel: function (title, rows, renderRow, emptyMessage) {
            var body = SimpleStomUi.emptyState(emptyMessage);

            if (rows.length) {
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

        handleCellClick: function (cabinetId, localStart, timezone) {
            this.openSlotBooking(cabinetId, localStart, timezone);
        },

        handleAppointmentClick: function (id) {
            this.getRouter().navigate('#Appointment/view/' + id, {trigger: true});
        },

        openSlotBooking: function (cabinetId, localStart, timezone) {
            this.createView('slotBooking', 'espo-dental:views/appointment/modals/slot-booking', {
                slot: {
                    localStart: localStart || '',
                    timezone: timezone || '',
                    cabinetId: cabinetId || '',
                    clinicId: '',
                    freeWindowMinutes: 180
                },
                clinicId: ''
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
