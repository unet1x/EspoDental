define('espo-dental:views/appointment/fields/date-start-slot', [
    'views/fields/datetime',
    'moment'
], function (Dep, moment) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:doctorId change:cabinetId change:clinicId change:serviceId change:duration', function (model, value, options) {
                if (!options || !options.espoDentalApplyingSlot) {
                    this.clearSelectedSlot();
                }

                this.scheduleSlotLoad();
            });

            this.on('remove', function () {
                if (this.slotLoadTimeout) {
                    clearTimeout(this.slotLoadTimeout);
                }
            }, this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== this.MODE_EDIT) {
                return;
            }

            this.hideNativeDateTimeControl();
            this.renderSlotPicker();
            this.bindSlotPicker();
            this.bindDurationFieldWatcher();
            this.scheduleSlotLoad();
        },

        hideNativeDateTimeControl: function () {
            if (this.$date && this.$date.length) {
                this.$date.closest('.input-group').hide();
            }

            if (this.$time && this.$time.length) {
                this.$time.closest('.input-group').hide();
            }
        },

        renderSlotPicker: function () {
            this.$el.find('.espo-dental-slot-picker').remove();

            var html =
                '<div class="espo-dental-slot-picker" style="margin-top:6px">' +
                    '<input type="date" class="form-control input-sm" data-name="slotDate" ' +
                        'style="width:180px" value="' + this.escapeAttribute(this.getSlotDate()) + '">' +
                    '<select class="form-control input-sm" data-name="slotSelect" ' +
                        'style="display:none;margin-top:8px;max-width:360px"></select>' +
                    '<div class="text-muted small" data-name="slotStatus" style="margin-top:4px"></div>' +
                '</div>';

            this.$el.append(html);
        },

        bindSlotPicker: function () {
            var self = this;
            var $picker = this.$el.find('.espo-dental-slot-picker');

            $picker.find('[data-name="slotDate"]').on('change', function () {
                self.clearSelectedSlot();
                self.scheduleSlotLoad();
            });

            $picker.find('[data-name="slotSelect"]').on('change', function () {
                var index = parseInt(window.jQuery(this).val(), 10);
                if (isNaN(index) || !self.slotList || !self.slotList[index]) {
                    return;
                }

                self.applySlot(self.slotList[index]);
            });
        },

        bindDurationFieldWatcher: function () {
            var durationView = this.getDurationFieldView();

            if (!durationView || !durationView.$duration || !durationView.$duration.length) {
                if (!this.durationWatcherRetry) {
                    this.durationWatcherRetry = true;
                    setTimeout(function () {
                        this.durationWatcherRetry = false;
                        this.bindDurationFieldWatcher();
                    }.bind(this), 300);
                }

                return;
            }

            durationView.$duration
                .off('change.espoDentalSlots')
                .on('change.espoDentalSlots', function () {
                    this.clearSelectedSlot();
                    this.scheduleSlotLoad();
                }.bind(this));
        },

        scheduleSlotLoad: function () {
            if (this.mode !== this.MODE_EDIT || !this.$el || !this.$el.length) {
                return;
            }

            if (this.slotLoadTimeout) {
                clearTimeout(this.slotLoadTimeout);
            }

            this.slotLoadTimeout = setTimeout(function () {
                this.loadSlots();
            }.bind(this), 300);
        },

        loadSlots: function () {
            var $picker = this.$el.find('.espo-dental-slot-picker');
            if (!$picker.length) {
                return;
            }

            var doctorId = this.model.get('doctorId');
            var cabinetId = this.model.get('cabinetId');

            if (!doctorId || !cabinetId) {
                this.renderNoSlots(
                    this.translate('Select doctor and cabinet first', 'messages', 'Appointment'),
                    true
                );
                return;
            }

            var date = $picker.find('[data-name="slotDate"]').val() || this.getSlotDate();
            var durationMinutes = this.getDurationMinutes();
            var requestId = String(Date.now()) + '-' + Math.random();
            this.currentSlotRequestId = requestId;

            this.setSlotStatus(this.translate('Loading...', 'messages', 'Global'), true);

            var data = {
                dateFrom: date,
                dateTo: date,
                durationMinutes: durationMinutes,
                doctorId: doctorId,
                cabinetId: cabinetId,
                stepMinutes: 15,
                limit: 80
            };

            if (this.model.get('clinicId')) {
                data.clinicId = this.model.get('clinicId');
            }
            if (this.model.get('serviceId')) {
                data.serviceId = this.model.get('serviceId');
            }

            if (this.model.id) {
                data.excludeAppointmentId = this.model.id;
            }
            if (this.model.get('parentType') && this.model.get('parentId')) {
                data.parentType = this.model.get('parentType');
                data.parentId = this.model.get('parentId');
            }

            Espo.Ajax.getRequest('EspoDental/Calendar/freeSlots', data)
                .then(function (response) {
                    if (this.currentSlotRequestId !== requestId) {
                        return;
                    }

                    this.renderSlots((response && response.slots) || []);
                }.bind(this))
                .catch(function () {
                    if (this.currentSlotRequestId !== requestId) {
                        return;
                    }

                    this.renderNoSlots(
                        this.translate('Free slot search failed', 'messages', 'Appointment'),
                        false
                    );
                }.bind(this));
        },

        renderSlots: function (slots) {
            this.slotList = slots || [];

            if (!this.slotList.length) {
                this.renderNoSlots(
                    this.translate('No free slots found', 'messages', 'Appointment'),
                    false
                );
                return;
            }

            var $select = this.$el.find('[data-name="slotSelect"]');
            var html = '<option value="">' +
                this.escapeHtml(this.translate('Select free slot', 'labels', 'Appointment')) +
                '</option>';

            this.slotList.forEach(function (slot, index) {
                html += '<option value="' + index + '">' +
                    this.escapeHtml(this.formatSlot(slot)) +
                    '</option>';
            }, this);

            $select.html(html).show();
            this.setSlotStatus('', true);
        },

        renderNoSlots: function (message, muted) {
            this.slotList = [];
            this.$el.find('[data-name="slotSelect"]').hide().html('');
            this.setSlotStatus(message, muted);
        },

        setSlotStatus: function (message, muted) {
            var $status = this.$el.find('[data-name="slotStatus"]');
            $status
                .toggleClass('text-danger', !muted)
                .toggleClass('text-muted', !!muted)
                .text(message || '');
        },

        applySlot: function (slot) {
            this.selectedSlotStart = slot.start;
            this.selectedSlotEnd = slot.end;
            this.selectedSlotClinicTime = this.formatSlotDateTime(slot);
            this.model.espoDentalSelectedSlotClinicTime = this.selectedSlotClinicTime;
            this.setDateTimeInputs(slot.start);

            var attrs = {};
            attrs[this.name] = slot.start;
            attrs.dateEnd = slot.end;
            if (slot.cabinetId) {
                attrs.cabinetId = slot.cabinetId;
            }
            if (slot.cabinetName) {
                attrs.cabinetName = slot.cabinetName;
            }

            this.model.set(attrs, {ui: true, espoDentalApplyingSlot: true});
            this.trigger('change');
        },

        clearSelectedSlot: function () {
            this.selectedSlotStart = null;
            this.selectedSlotEnd = null;
            this.selectedSlotClinicTime = null;

            if (this.model) {
                delete this.model.espoDentalSelectedSlotClinicTime;
            }
        },

        setDateTimeInputs: function (value) {
            var display = this.getDateTime().toDisplay(value);
            if (!display) {
                return;
            }

            var pair = this.splitDatetime(display);
            this.$date.val(pair[0]);
            this.$time.val(pair[1]);
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            if (this.selectedSlotStart) {
                data[this.name] = this.selectedSlotStart;
                data.dateEnd = this.selectedSlotEnd || null;
            }

            return data;
        },

        getDurationMinutes: function () {
            var seconds = this.getDurationSecondsFromField();

            if (!seconds) {
                seconds = this.getDurationSecondsFromDates();
            }

            if (!seconds) {
                seconds = parseInt(this.model.get('duration') || 0, 10);
            }

            if (!seconds) {
                seconds = parseInt(this.model.getFieldParam('duration', 'default') || 1800, 10);
            }

            if (isNaN(seconds) || seconds <= 0) {
                seconds = 1800;
            }

            return Math.max(15, Math.round(seconds / 60));
        },

        getDurationSecondsFromField: function () {
            var durationView = this.getDurationFieldView();

            if (durationView && durationView.$duration && durationView.$duration.length) {
                var value = parseInt(durationView.$duration.val(), 10);
                if (!isNaN(value) && value > 0) {
                    return value;
                }
            }

            if (durationView && typeof durationView.seconds === 'number' && durationView.seconds > 0) {
                return durationView.seconds;
            }

            return 0;
        },

        getDurationSecondsFromDates: function () {
            var start = this.model.get(this.name);
            var end = this.model.get('dateEnd');

            if (!start || !end) {
                return 0;
            }

            var seconds = moment.utc(end).unix() - moment.utc(start).unix();

            return seconds > 0 ? seconds : 0;
        },

        getDurationFieldView: function () {
            var parentView = this.getParentView ? this.getParentView() : null;

            if (!parentView || typeof parentView.getView !== 'function') {
                return null;
            }

            return parentView.getView('duration') || null;
        },

        getSlotDate: function () {
            if (this.$date && this.$date.val()) {
                var parsed = moment(this.$date.val(), this.getDateTime().getDateFormat(), true);
                if (parsed.isValid()) {
                    return parsed.format('YYYY-MM-DD');
                }
            }

            var value = this.model.get(this.name);
            if (value) {
                var display = this.getDateTime().toDisplay(value);
                if (display) {
                    var pair = this.splitDatetime(display);
                    var modelDate = moment(pair[0], this.getDateTime().getDateFormat(), true);
                    if (modelDate.isValid()) {
                        return modelDate.format('YYYY-MM-DD');
                    }
                }
            }

            return this.localToday();
        },

        localToday: function () {
            var date = new Date();
            var month = String(date.getMonth() + 1);
            var day = String(date.getDate());

            if (month.length < 2) {
                month = '0' + month;
            }
            if (day.length < 2) {
                day = '0' + day;
            }

            return date.getFullYear() + '-' + month + '-' + day;
        },

        formatSlot: function (slot) {
            var start = this.getSlotDisplayValue(slot, 'localStart', 'start');
            var end = this.getSlotDisplayValue(slot, 'localEnd', 'end');
            var startTime = this.extractDisplayTime(start);
            var endTime = this.extractDisplayTime(end);
            var label = startTime + ' - ' + endTime;

            if (slot.cabinetName) {
                label += ' / ' + slot.cabinetName;
            }

            return label;
        },

        formatSlotDateTime: function (slot) {
            return this.formatDisplayDateTime(
                this.getSlotDisplayValue(slot, 'localStart', 'start')
            );
        },

        getSlotDisplayValue: function (slot, localName, utcName) {
            return slot[localName] || this.getDateTime().toDisplay(slot[utcName]) || slot[utcName];
        },

        formatDisplayDateTime: function (value) {
            var parsed = moment(value, ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD HH:mm'], true);

            if (parsed.isValid()) {
                return parsed.format(
                    this.getDateTime().getDateFormat() + ' ' + this.getDateTime().getTimeFormat()
                );
            }

            return value;
        },

        extractDisplayTime: function (value) {
            var parsed = moment(value, ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD HH:mm'], true);

            if (parsed.isValid()) {
                return parsed.format(this.getDateTime().getTimeFormat());
            }

            var pair = this.splitDatetime(value);
            return pair[1] || value;
        },

        escapeHtml: function (value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        escapeAttribute: function (value) {
            return this.escapeHtml(value);
        }
    });
});
