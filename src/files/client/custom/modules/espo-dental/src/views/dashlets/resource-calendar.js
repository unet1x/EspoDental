define('espo-dental:views/dashlets/resource-calendar', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/resource-grid',
    'espo-dental:utils/dialogs'
], function (Dep, ResourceGrid, Dialogs) {
    return Dep.extend({
        name: 'ResourceCalendar',
        templateContent:
            '<div class="espo-dental-resource-calendar">' +
                '<div class="resource-calendar-toolbar" ' +
                    'style="display:flex;gap:6px;padding:6px;align-items:center;flex-wrap:wrap"></div>' +
                '<div class="resource-calendar-host" ' +
                    'style="overflow:auto;max-height:600px;border-top:1px solid #eee"></div>' +
            '</div>',

        setup: function () {
            Dep.prototype.setup.call(this);
            var d = new Date();
            this.currentDate = d.toISOString().slice(0, 10);
            this.view = this.getOption('defaultView') || 'day';
            this.clinicId = this.getOption('clinicId') || null;
            this.cabinetId = this.getOption('cabinetId') || null;
            this.rowMinutes = parseInt(this.getOption('rowMinutes')) || 30;
            this.startHour = parseInt(this.getOption('startHour'));
            if (isNaN(this.startHour)) this.startHour = 8;
            this.endHour = parseInt(this.getOption('endHour'));
            if (isNaN(this.endHour)) this.endHour = 21;
        },

        afterRender: function () {
            this.renderToolbar();
            this.fetchAndRender();
        },

        renderToolbar: function () {
            var self = this;
            var $tb = this.$el.find('.resource-calendar-toolbar');
            $tb.empty();
            var step = (this.view === 'week') ? 7 : 1;
            var $prev = $('<button type="button" class="btn btn-default btn-xs">&#9664;</button>');
            var $next = $('<button type="button" class="btn btn-default btn-xs">&#9654;</button>');
            var $today = $('<button type="button" class="btn btn-default btn-xs">Today</button>');
            var $date = $('<input type="date" class="form-control input-sm" style="width:140px">').val(this.currentDate);
            var $viewSelect = $('<select class="form-control input-sm" style="width:110px"></select>')
                .append('<option value="day">Day</option><option value="week">Week</option>')
                .val(this.view);
            var $reload = $('<button type="button" class="btn btn-default btn-xs">&#x21bb;</button>');
            var $find = $('<button type="button" class="btn btn-success btn-xs">Find slot</button>');
            $prev.on('click', function () { self.shiftDate(-step); });
            $next.on('click', function () { self.shiftDate(+step); });
            $today.on('click', function () {
                self.currentDate = new Date().toISOString().slice(0, 10);
                self.afterRender();
            });
            $date.on('change', function () { self.currentDate = $(this).val(); self.afterRender(); });
            $viewSelect.on('change', function () { self.view = $(this).val(); self.afterRender(); });
            $reload.on('click', function () { self.fetchAndRender(); });
            $find.on('click', function () { self.openFindSlot(); });
            $tb.append($prev, $today, $next, $date, $viewSelect, $reload, $find);
        },

        shiftDate: function (delta) {
            var d = new Date(this.currentDate + 'T00:00:00');
            d.setDate(d.getDate() + delta);
            this.currentDate = d.toISOString().slice(0, 10);
            this.afterRender();
        },

        fetchAndRender: function () {
            var self = this;
            var $host = this.$el.find('.resource-calendar-host');
            $host.html('<div class="text-muted small" style="padding:10px">Loading...</div>');
            var data = {date: this.currentDate, view: this.view};
            if (this.clinicId) data.clinicId = this.clinicId;
            if (this.cabinetId) data.cabinetId = this.cabinetId;
            Espo.Ajax.getRequest('EspoDental/Calendar/appointments', data)
                .then(function (resp) {
                    if (self.grid && self.grid.dispose) self.grid.dispose();
                    self.grid = new ResourceGrid($host[0], resp, {
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
                    $host.html('<div class="text-danger small" style="padding:10px">Failed to load.</div>');
                });
        },

        handleMove: function (id, change, dateEnd, cabinetId) {
            if (typeof change === 'object') {
                change.id = id;
                this.persistAppointmentChange(change);
                return;
            }

            this.persistAppointmentChange({
                id: id, dateStart: change, dateEnd: dateEnd, cabinetId: cabinetId
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
                .then(function () { self.fetchAndRender(); })
                .catch(function (xhr) {
                    Espo.Ui.error((xhr && xhr.responseText) || 'Update failed');
                    self.fetchAndRender();
                });
        },

        handleCellClick: function () {
            Espo.Ui.warning('Open a patient card and use Book Appointment.');
        },

        handleAppointmentClick: function (id) {
            this.getRouter().navigate('#Appointment/view/' + id, {trigger: true});
        },

        openFindSlot: function () {
            var self = this;
            Dialogs.prompt(this, {
                title: 'Slot duration (minutes)',
                value: '30',
                inputType: 'number'
            }).then(function (value) {
                if (value === null) {
                    return;
                }

                var duration = parseInt(value, 10) || 30;
                var dateFrom = self.currentDate;
                var data = {
                    dateFrom: dateFrom,
                    dateTo: dateFrom,
                    durationMinutes: duration,
                    workStartHour: self.startHour,
                    workEndHour: self.endHour,
                    stepMinutes: self.rowMinutes,
                    limit: 20
                };
                if (self.clinicId) data.clinicId = self.clinicId;
                if (self.cabinetId) data.cabinetId = self.cabinetId;
                Espo.Ajax.getRequest('EspoDental/Calendar/freeSlots', data).then(function (resp) {
                    var slots = (resp && resp.slots) || [];
                    if (!slots.length) {
                        Espo.Ui.warning('No free slots');
                        return;
                    }
                    var msg = slots.slice(0, 10).map(function (s) {
                        return (s.localStart || s.start) + ' / ' + s.cabinetName;
                    }).join('\n');
                    Espo.Ui.info('Free slots:\n' + msg);
                }).catch(function () { Espo.Ui.error('Find slot failed'); });
            });
        }
    });
});
