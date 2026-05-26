define('espo-dental:views/appointment/modals/slot-booking', [
    'views/modal',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, SimpleStomUi) {
    return Dep.extend({
        templateContent:
            '<div class="espo-dental-slot-booking"></div>',

        events: {
            'input [data-name="patientSearch"]': 'scheduleCandidateSearch',
            'click [data-action="selectCandidate"]': 'selectCandidate',
            'change [data-name="bookingMode"]': 'toggleMode',
            'change [data-name="serviceId"]': 'applySelectedServiceDuration'
        },

        setup: function () {
            this.headerText = this.options.title || 'Запись пациента';
            this.slot = this.options.slot || {};
            this.serviceOptions = this.options.serviceOptions || [];
            this.serviceId = this.options.serviceId || this.slot.serviceId || '';
            this.selectedCandidate = null;
            this.buttonList = [
                {name: 'book', label: 'Записать', style: 'primary'},
                {name: 'cancel', label: 'Отмена'}
            ];
        },

        afterRender: function () {
            SimpleStomUi.ensureStyles();
            this.ensureContainer();
            this.renderForm();
            this.applySelectedServiceDuration();
        },

        ensureContainer: function () {
            if (this.$el.find('.espo-dental-slot-booking').length) {
                return;
            }

            this.$el.find('.modal-body, .body').html('<div class="espo-dental-slot-booking"></div>');
        },

        renderForm: function () {
            var durations = this.buildDurationOptions(this.slot.freeWindowMinutes);
            var html = '<div class="espo-dental-stom">' +
                '<div class="espo-dental-stom-layout espo-dental-stom-layout--two">' +
                '<div>' +
                '<div class="form-group">' +
                '<label>Пациент</label>' +
                '<input type="text" class="form-control" data-name="patientSearch" ' +
                'placeholder="ФИО, телефон или email">' +
                '<div data-name="candidateResults" style="margin-top:8px"></div>' +
                '</div>' +
                '<div class="form-group">' +
                '<label><input type="radio" name="bookingMode" data-name="bookingMode" value="existing" checked> Существующий пациент</label> ' +
                '<label style="margin-left:12px"><input type="radio" name="bookingMode" data-name="bookingMode" value="new"> Новый предварительный пациент</label>' +
                '</div>' +
                '<div data-name="selectedCandidate">' + SimpleStomUi.emptyState('Выберите пациента или создайте предварительного.') + '</div>' +
                '</div>' +
                '<div>' +
                '<div data-name="newPatientFields" style="display:none">' +
                this.renderNewPatientFields() +
                '</div>' +
                this.renderSlotFields(durations) +
                '</div>' +
                '</div>' +
                '</div>';

            this.$el.find('.espo-dental-slot-booking').html(html);
        },

        renderNewPatientFields: function () {
            return '<div class="form-group"><label>Фамилия</label>' +
                '<input type="text" class="form-control" data-name="lastName"></div>' +
                '<div class="form-group"><label>Имя</label>' +
                '<input type="text" class="form-control" data-name="firstName"></div>' +
                '<div class="form-group"><label>Отчество</label>' +
                '<input type="text" class="form-control" data-name="middleName"></div>' +
                '<div class="form-group"><label>Телефон</label>' +
                '<input type="text" class="form-control" data-name="phone"></div>' +
                '<div class="form-group"><label>Email</label>' +
                '<input type="email" class="form-control" data-name="emailAddress"></div>';
        },

        renderSlotFields: function (durations) {
            var html = '<div class="form-group"><label>Дата и время</label>' +
                '<input type="text" class="form-control" data-name="localStart" value="' +
                SimpleStomUi.escapeHtml(this.slot.localStart || '') + '"></div>';

            html += '<div class="form-group"><label>Клиника</label>' +
                '<input type="text" class="form-control" data-name="clinicId" value="' +
                SimpleStomUi.escapeHtml(this.slot.clinicId || this.options.clinicId || '') + '"></div>' +
                '<div class="form-group"><label>Врач</label>' +
                '<input type="text" class="form-control" data-name="doctorId" value="' +
                SimpleStomUi.escapeHtml(this.options.doctorId || '') + '"></div>' +
                '<div class="form-group"><label>Кабинет</label>' +
                '<input type="text" class="form-control" data-name="cabinetId" value="' +
                SimpleStomUi.escapeHtml(this.slot.cabinetId || '') + '"></div>' +
                this.renderServiceField();

            html += '<div class="form-group"><label>Длительность</label>' +
                '<select class="form-control" data-name="durationMinutes">';

            durations.forEach(function (minutes) {
                html += '<option value="' + minutes + '">' + minutes + ' мин.</option>';
            });

            html += '</select></div>' +
                '<div class="text-muted small" data-name="durationHint" style="margin-top:-8px;margin-bottom:12px"></div>' +
                '<div class="form-group"><label>С чем обратился</label>' +
                '<input type="text" class="form-control" data-name="reason"></div>' +
                '<div class="form-group"><label>Заметки</label>' +
                '<textarea class="form-control" data-name="notes" rows="3"></textarea></div>';

            return html;
        },

        renderServiceField: function () {
            var html = '<div class="form-group"><label>Услуга</label>';

            if (!this.serviceOptions.length) {
                html += '<input type="text" class="form-control" data-name="serviceId" value="' +
                    SimpleStomUi.escapeHtml(this.serviceId || '') + '">';
                return html + '</div>';
            }

            html += '<select class="form-control" data-name="serviceId">' +
                '<option value="">Не выбрана</option>';

            this.serviceOptions.forEach((function (service) {
                var selected = String(service.id || '') === String(this.serviceId || '') ? ' selected' : '';
                html += '<option value="' + SimpleStomUi.escapeHtml(service.id || '') + '"' + selected + '>' +
                    SimpleStomUi.escapeHtml(service.name || service.id || '') +
                    '</option>';
            }).bind(this));

            return html + '</select></div>';
        },

        buildDurationOptions: function (freeWindowMinutes) {
            var max = this.getDurationLimitMinutes(freeWindowMinutes);
            var options = [];

            if (max < 15) {
                return options;
            }

            for (var minutes = 15; minutes <= max; minutes += 15) {
                options.push(minutes);
            }

            return options;
        },

        getDurationLimitMinutes: function (freeWindowMinutes) {
            var parsed = parseInt(freeWindowMinutes, 10);

            if (isNaN(parsed)) {
                parsed = 180;
            }

            return Math.max(0, Math.min(parsed, 180));
        },

        applySelectedServiceDuration: function () {
            var serviceId = this.$el.find('[data-name="serviceId"]').val() || '';
            var service = null;
            var freeWindowMinutes = this.getDurationLimitMinutes(this.slot.freeWindowMinutes);
            var $duration = this.$el.find('[data-name="durationMinutes"]');

            this.serviceOptions.forEach(function (row) {
                if (String(row.id || '') === String(serviceId)) {
                    service = row;
                }
            });

            this.updateDurationHint(service, freeWindowMinutes);

            if (!service || !service.duration) {
                return;
            }

            var duration = parseInt(service.duration, 10);

            if (duration > 0 && duration <= freeWindowMinutes && $duration.find('option[value="' + duration + '"]').length) {
                $duration.val(String(duration));
            }
        },

        updateDurationHint: function (service, freeWindowMinutes) {
            var $hint = this.$el.find('[data-name="durationHint"]');
            var serviceDuration = service && service.duration ? parseInt(service.duration, 10) : 0;
            var hasWindow = freeWindowMinutes >= 15;
            var message = hasWindow
                ? 'В выбранном окне доступно до ' + freeWindowMinutes + ' мин.'
                : 'В выбранном окне нет свободного времени для записи.';
            var isError = !hasWindow;

            if (serviceDuration > freeWindowMinutes) {
                message = 'Для услуги нужно ' + serviceDuration +
                    ' мин., а в выбранном окне доступно ' + freeWindowMinutes +
                    ' мин. Выберите другое время или услугу.';
                isError = true;
            }

            $hint
                .toggleClass('text-danger', isError)
                .toggleClass('text-muted', !isError)
                .text(message);

            if (isError) {
                this.disableButton('book');
                return;
            }

            this.enableButton('book');
        },

        scheduleCandidateSearch: function () {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(this.searchCandidates.bind(this), 250);
        },

        searchCandidates: function () {
            var query = this.$el.find('[data-name="patientSearch"]').val() || '';
            var $results = this.$el.find('[data-name="candidateResults"]');

            if (query.length < 2) {
                $results.html('');
                return;
            }

            $results.html(SimpleStomUi.emptyState('Поиск...'));

            Espo.Ajax.getRequest('EspoDental/Appointment/bookingCandidates', {
                q: query,
                limit: 8
            }).then((function (rows) {
                this.renderCandidates(rows || []);
            }).bind(this)).catch(function () {
                $results.html(SimpleStomUi.emptyState('Поиск не выполнен.'));
            });
        },

        renderCandidates: function (rows) {
            var $results = this.$el.find('[data-name="candidateResults"]');
            var html;

            if (!rows.length) {
                $results.html(SimpleStomUi.emptyState('Совпадений нет.'));
                return;
            }

            html = '<ul class="espo-dental-stom-list">';
            rows.forEach(function (row) {
                html += '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    SimpleStomUi.escapeHtml(row.name || row.id) +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(SimpleStomUi.label(row.entityType || '', 'entity')) +
                    (row.phone ? ' · ' + SimpleStomUi.escapeHtml(row.phone) : '') +
                    '</span>' +
                    '</span>' +
                    '<button type="button" class="espo-dental-stom-button" data-action="selectCandidate" ' +
                    'data-id="' + SimpleStomUi.escapeHtml(row.id) + '" ' +
                    'data-type="' + SimpleStomUi.escapeHtml(row.entityType) + '" ' +
                    'data-label="' + SimpleStomUi.escapeHtml(row.name || row.id) + '">Выбрать</button>' +
                    '</li>';
            });
            html += '</ul>';

            $results.html(html);
        },

        selectCandidate: function (e) {
            var $button = $(e.currentTarget);

            this.selectedCandidate = {
                id: $button.attr('data-id'),
                entityType: $button.attr('data-type'),
                label: $button.attr('data-label')
            };

            this.$el.find('[data-name="selectedCandidate"]').html(SimpleStomUi.panel({
                title: 'Выбран пациент',
                body: SimpleStomUi.escapeHtml(this.selectedCandidate.label),
                classes: ['espo-dental-stom-panel--compact']
            }));
            this.$el.find('[data-name="bookingMode"][value="existing"]').prop('checked', true);
            this.toggleMode();
        },

        toggleMode: function () {
            var mode = this.getMode();
            this.$el.find('[data-name="newPatientFields"]').toggle(mode === 'new');
        },

        getMode: function () {
            return this.$el.find('[data-name="bookingMode"]:checked').val() || 'existing';
        },

        actionBook: function () {
            var payload = this.buildPayload();

            if (!payload) {
                return;
            }

            this.disableButton('book');

            Espo.Ajax.postRequest('EspoDental/Appointment/bookFromSlot', payload)
                .then((function (response) {
                    this.trigger('done', response);
                    this.close();
                }).bind(this))
                .catch((function (xhr) {
                    this.enableButton('book');
                    this.applySelectedServiceDuration();
                    Espo.Ui.error(this.getBookingErrorMessage(xhr));
                }).bind(this));
        },

        buildPayload: function () {
            var mode = this.getMode();
            var windowMessage = this.getDurationWindowErrorMessage();

            if (windowMessage) {
                Espo.Ui.warning(windowMessage);
                return null;
            }

            var payload = {
                clinicId: this.$el.find('[data-name="clinicId"]').val() || this.slot.clinicId || '',
                cabinetId: this.$el.find('[data-name="cabinetId"]').val() || this.slot.cabinetId || '',
                doctorId: this.$el.find('[data-name="doctorId"]').val() || this.options.doctorId || '',
                serviceId: this.$el.find('[data-name="serviceId"]').val() || this.serviceId || '',
                localStart: this.$el.find('[data-name="localStart"]').val() || this.slot.localStart || '',
                timezone: this.slot.timezone || '',
                durationMinutes: parseInt(this.$el.find('[data-name="durationMinutes"]').val(), 10) || 30,
                reason: this.$el.find('[data-name="reason"]').val() || '',
                notes: this.$el.find('[data-name="notes"]').val() || ''
            };

            if (mode === 'existing') {
                if (!this.selectedCandidate) {
                    Espo.Ui.warning('Сначала выберите пациента.');
                    return null;
                }

                payload.parentType = this.selectedCandidate.entityType;
                payload.parentId = this.selectedCandidate.id;

                return payload;
            }

            payload.lastName = this.$el.find('[data-name="lastName"]').val() || '';
            payload.firstName = this.$el.find('[data-name="firstName"]').val() || '';
            payload.middleName = this.$el.find('[data-name="middleName"]').val() || '';
            payload.phone = this.$el.find('[data-name="phone"]').val() || '';
            payload.emailAddress = this.$el.find('[data-name="emailAddress"]').val() || '';

            return payload;
        },

        getDurationWindowErrorMessage: function () {
            var serviceId = this.$el.find('[data-name="serviceId"]').val() || '';
            var freeWindowMinutes = this.getDurationLimitMinutes(this.slot.freeWindowMinutes);
            var service = null;

            this.serviceOptions.forEach(function (row) {
                if (String(row.id || '') === String(serviceId)) {
                    service = row;
                }
            });

            if (freeWindowMinutes < 15) {
                return 'В выбранном окне нет свободного времени для записи.';
            }

            if (service && service.duration && parseInt(service.duration, 10) > freeWindowMinutes) {
                return 'Выбранная услуга не помещается в свободное окно. Выберите другое время или услугу.';
            }

            return '';
        },

        getBookingErrorMessage: function (xhr) {
            var message = this.extractErrorMessage(xhr);
            var knownMessages = {
                'Selected cabinet does not match service requirements':
                    'Этот кабинет не подходит для выбранной услуги. Выберите другой кабинет или другую услугу.',
                'Selected service is inactive':
                    'Эта услуга больше не активна. Выберите актуальную услугу.',
                'durationMinutes must be between 15 and 180':
                    'Длительность приема должна быть от 15 до 180 минут.',
                'clinicId, cabinetId and doctorId are required':
                    'Укажите клинику, кабинет и врача.',
                'dateStart is required':
                    'Укажите дату и время приема.',
                'Doctor is already booked at this time.':
                    'У врача уже есть запись на это время. Выберите другой слот.',
                'Cabinet is already booked at this time.':
                    'Кабинет уже занят на это время. Выберите другой слот или кабинет.',
                'Patient is already booked at this time.':
                    'У пациента уже есть запись на это время.',
                'Cabinet is closed for this time.':
                    'Кабинет закрыт на выбранное время.',
                'Doctor is not available at this time.':
                    'Врач не работает в выбранное время.',
                'Time slot conflict.':
                    'Выбранное время уже занято. Обновите календарь и выберите другой слот.',
                'Booking parent not found':
                    'Выбранный пациент не найден. Найдите пациента заново.',
                'Valid parentType and parentId are required':
                    'Сначала выберите пациента из поиска.',
                'lastName and firstName are required':
                    'Для нового предварительного пациента укажите фамилию и имя.',
                'phone is required':
                    'Для нового предварительного пациента укажите телефон.'
            };

            return knownMessages[message] || 'Не удалось создать запись. Проверьте слот и попробуйте еще раз.';
        },

        extractErrorMessage: function (xhr) {
            var text = (xhr && xhr.responseText) || '';
            var json;

            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }

            if (text) {
                try {
                    json = JSON.parse(text);

                    if (json && json.message) {
                        return json.message;
                    }
                } catch (e) {}
            }

            return text;
        }
    });
});
