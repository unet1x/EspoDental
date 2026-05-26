define('espo-dental:views/dashlets/resource-calendar-feedback', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/resource-grid',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, ResourceGrid, SimpleStomUi) {
    return Dep.extend({
        name: 'ResourceCalendar',
        templateContent: '<div class="espo-dental-resource-calendar-feedback"></div>',

        setup: function () {
            Dep.prototype.setup.call(this);

            var d = new Date();
            this.currentDate = d.toISOString().slice(0, 10);
            this.view = this.getOption('defaultView') || 'day';
            this.clinicId = this.getOption('clinicId') || null;
            this.cabinetId = this.getOption('cabinetId') || null;
            this.rowMinutes = parseInt(this.getOption('rowMinutes'), 10) || 30;
            this.startHour = parseInt(this.getOption('startHour'), 10);
            this.endHour = parseInt(this.getOption('endHour'), 10);

            if (isNaN(this.startHour)) {
                this.startHour = 8;
            }

            if (isNaN(this.endHour)) {
                this.endHour = 21;
            }
        },

        afterRender: function () {
            SimpleStomUi.ensureStyles();
            this.renderShell();
            this.fetchAndRender();
        },

        renderShell: function () {
            var html = '<div class="espo-dental-stom-toolbar" data-name="calendar-toolbar"></div>' +
                '<div class="espo-dental-stom-layout espo-dental-stom-layout--two" ' +
                'style="grid-template-columns:minmax(0,1fr) minmax(260px,320px)">' +
                SimpleStomUi.panel({
                    title: 'Calendar',
                    body: '<div class="resource-calendar-host" style="overflow:auto;max-height:620px"></div>'
                }) +
                '<div data-name="calendar-side-panel"></div>' +
                '</div>';

            this.$el.find('.espo-dental-resource-calendar-feedback').html(SimpleStomUi.workspace(html));
            this.renderToolbar();
        },

        renderToolbar: function () {
            var self = this;
            var $toolbar = this.$el.find('[data-name="calendar-toolbar"]');
            var step = this.view === 'week' ? 7 : 1;
            var $prev = $('<button type="button" class="espo-dental-stom-button" title="Previous">&lt;</button>');
            var $next = $('<button type="button" class="espo-dental-stom-button" title="Next">&gt;</button>');
            var $today = $('<button type="button" class="espo-dental-stom-button">Today</button>');
            var $reload = $('<button type="button" class="espo-dental-stom-button" title="Reload">Refresh</button>');
            var $date = $('<input type="date" class="form-control input-sm" style="width:140px">').val(this.currentDate);
            var $view = $('<select class="form-control input-sm" style="width:110px"></select>')
                .append('<option value="day">Day</option><option value="week">Week</option>')
                .val(this.view);
            var $create = $('<button type="button" class="espo-dental-stom-button espo-dental-stom-button--primary">New slot</button>');

            $prev.on('click', function () {
                self.shiftDate(-step);
            });
            $next.on('click', function () {
                self.shiftDate(step);
            });
            $today.on('click', function () {
                self.currentDate = new Date().toISOString().slice(0, 10);
                self.fetchAndRender();
                self.renderToolbar();
            });
            $reload.on('click', function () {
                self.fetchAndRender();
            });
            $date.on('change', function () {
                self.currentDate = $(this).val();
                self.fetchAndRender();
            });
            $view.on('change', function () {
                self.view = $(this).val();
                self.fetchAndRender();
            });
            $create.on('click', function () {
                self.openSlotBooking(null, self.currentDate + ' 09:00:00', '');
            });

            $toolbar.empty().append($prev, $today, $next, $date, $view, $reload, '<span class="espo-dental-stom-toolbar__spacer"></span>', $create);
        },

        shiftDate: function (delta) {
            var d = new Date(this.currentDate + 'T00:00:00');
            d.setDate(d.getDate() + delta);
            this.currentDate = d.toISOString().slice(0, 10);
            this.renderToolbar();
            this.fetchAndRender();
        },

        fetchAndRender: function () {
            this.fetchAppointments();
            this.fetchFeedbackPanel();
        },

        fetchAppointments: function () {
            var self = this;
            var $host = this.$el.find('.resource-calendar-host');
            var data = {date: this.currentDate, view: this.view};

            if (this.clinicId) {
                data.clinicId = this.clinicId;
            }

            if (this.cabinetId) {
                data.cabinetId = this.cabinetId;
            }

            $host.html(SimpleStomUi.emptyState('Loading calendar...'));

            Espo.Ajax.getRequest('EspoDental/Calendar/appointments', data)
                .then(function (response) {
                    if (self.grid && self.grid.dispose) {
                        self.grid.dispose();
                    }

                    self.grid = new ResourceGrid($host[0], response, {
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
                    $host.html(SimpleStomUi.emptyState('Calendar failed to load.'));
                });
        },

        fetchFeedbackPanel: function () {
            var self = this;
            var data = {
                date: this.currentDate,
                limit: parseInt(this.getOption('displayRecords'), 10) || 12
            };
            var $panel = this.$el.find('[data-name="calendar-side-panel"]');

            if (this.clinicId) {
                data.clinicId = this.clinicId;
            }

            $panel.html(SimpleStomUi.panel({
                title: 'Side panel',
                body: SimpleStomUi.emptyState('Loading...')
            }));

            Espo.Ajax.getRequest('EspoDental/Calendar/feedbackPanel', data)
                .then(function (response) {
                    self.renderFeedbackPanel(response || {});
                })
                .catch(function () {
                    $panel.html(SimpleStomUi.panel({
                        title: 'Side panel',
                        body: SimpleStomUi.emptyState('Side panel failed to load.')
                    }));
                });
        },

        renderFeedbackPanel: function (data) {
            var html = '';

            html += this.renderWaitlist(data.waitlist || []);
            html += this.renderCancelled(data.cancelled || []);

            this.$el.find('[data-name="calendar-side-panel"]').html(html);
        },

        renderWaitlist: function (rows) {
            return this.renderListPanel('Waiting list', rows, function (row) {
                var href = row.id ? '#AppointmentWaitlistEntry/view/' + encodeURIComponent(row.id) : '#';

                return '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    '<a href="' + href + '">' + SimpleStomUi.escapeHtml(row.parentName || row.name || 'Waitlist') + '</a>' +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(row.requestedDate || 'Any date') +
                    '</span>' +
                    '</span>' +
                    SimpleStomUi.badge(row.priority || 'normal', row.priority || 'normal') +
                    '</li>';
            }, 'No patients waiting.');
        },

        renderCancelled: function (rows) {
            return this.renderListPanel('Cancelled today', rows, function (row) {
                var href = row.id ? '#Appointment/view/' + encodeURIComponent(row.id) : '#';

                return '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    '<a href="' + href + '">' + SimpleStomUi.escapeHtml(row.parentName || row.name || 'Appointment') + '</a>' +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(String(row.dateStart || '').slice(11, 16)) +
                    '</span>' +
                    '</span>' +
                    SimpleStomUi.badge(row.status || 'cancelled', row.status || 'cancelled') +
                    '</li>';
            }, 'No cancellations today.');
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
                    self.fetchAndRender();
                })
                .catch(function (xhr) {
                    Espo.Ui.error((xhr && xhr.responseText) || 'Update failed');
                    self.fetchAndRender();
                });
        },

        handleCellClick: function (cabinetId, localStart, timezone) {
            this.openSlotBooking(cabinetId, localStart, timezone);
        },

        handleAppointmentClick: function (id) {
            this.getRouter().navigate('#Appointment/view/' + id, {trigger: true});
        },

        openSlotBooking: function (cabinetId, localStart, timezone) {
            var query = '?dateStart=' + encodeURIComponent(localStart || '');

            if (cabinetId) {
                query += '&cabinetId=' + encodeURIComponent(cabinetId);
            }

            if (timezone) {
                query += '&timezone=' + encodeURIComponent(timezone);
            }

            this.getRouter().navigate('#Appointment/create' + query, {trigger: true});
        }
    });
});
