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
            'change [data-name="bookingMode"]': 'toggleMode'
        },

        setup: function () {
            this.headerText = this.options.title || 'Book slot';
            this.slot = this.options.slot || {};
            this.selectedCandidate = null;
            this.buttonList = [
                {name: 'book', label: 'Book', style: 'primary'},
                {name: 'cancel', label: 'Cancel'}
            ];
        },

        afterRender: function () {
            SimpleStomUi.ensureStyles();
            this.renderForm();
        },

        renderForm: function () {
            var durations = this.buildDurationOptions(this.slot.freeWindowMinutes || 180);
            var html = '<div class="espo-dental-stom">' +
                '<div class="espo-dental-stom-layout espo-dental-stom-layout--two">' +
                '<div>' +
                '<div class="form-group">' +
                '<label>Patient search</label>' +
                '<input type="text" class="form-control" data-name="patientSearch" ' +
                'placeholder="Name, phone or email">' +
                '<div data-name="candidateResults" style="margin-top:8px"></div>' +
                '</div>' +
                '<div class="form-group">' +
                '<label><input type="radio" name="bookingMode" data-name="bookingMode" value="existing" checked> Existing patient</label> ' +
                '<label style="margin-left:12px"><input type="radio" name="bookingMode" data-name="bookingMode" value="new"> New preliminary patient</label>' +
                '</div>' +
                '<div data-name="selectedCandidate">' + SimpleStomUi.emptyState('Select a patient or switch to new.') + '</div>' +
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
            return '<div class="form-group"><label>Last name</label>' +
                '<input type="text" class="form-control" data-name="lastName"></div>' +
                '<div class="form-group"><label>First name</label>' +
                '<input type="text" class="form-control" data-name="firstName"></div>' +
                '<div class="form-group"><label>Middle name</label>' +
                '<input type="text" class="form-control" data-name="middleName"></div>' +
                '<div class="form-group"><label>Phone</label>' +
                '<input type="text" class="form-control" data-name="phone"></div>' +
                '<div class="form-group"><label>Email</label>' +
                '<input type="email" class="form-control" data-name="emailAddress"></div>';
        },

        renderSlotFields: function (durations) {
            var html = '<div class="form-group"><label>Start</label>' +
                '<input type="text" class="form-control" data-name="localStart" value="' +
                SimpleStomUi.escapeHtml(this.slot.localStart || '') + '"></div>';

            html += '<div class="form-group"><label>Clinic ID</label>' +
                '<input type="text" class="form-control" data-name="clinicId" value="' +
                SimpleStomUi.escapeHtml(this.slot.clinicId || this.options.clinicId || '') + '"></div>' +
                '<div class="form-group"><label>Doctor ID</label>' +
                '<input type="text" class="form-control" data-name="doctorId" value="' +
                SimpleStomUi.escapeHtml(this.options.doctorId || '') + '"></div>' +
                '<div class="form-group"><label>Cabinet ID</label>' +
                '<input type="text" class="form-control" data-name="cabinetId" value="' +
                SimpleStomUi.escapeHtml(this.slot.cabinetId || '') + '"></div>';

            html += '<div class="form-group"><label>Duration</label>' +
                '<select class="form-control" data-name="durationMinutes">';

            durations.forEach(function (minutes) {
                html += '<option value="' + minutes + '">' + minutes + ' min</option>';
            });

            html += '</select></div>' +
                '<div class="form-group"><label>Reason</label>' +
                '<input type="text" class="form-control" data-name="reason"></div>' +
                '<div class="form-group"><label>Notes</label>' +
                '<textarea class="form-control" data-name="notes" rows="3"></textarea></div>';

            return html;
        },

        buildDurationOptions: function (freeWindowMinutes) {
            var max = Math.max(15, Math.min(parseInt(freeWindowMinutes, 10) || 180, 180));
            var options = [];

            for (var minutes = 15; minutes <= max; minutes += 15) {
                options.push(minutes);
            }

            return options;
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

            $results.html(SimpleStomUi.emptyState('Searching...'));

            Espo.Ajax.getRequest('EspoDental/Appointment/bookingCandidates', {
                q: query,
                limit: 8
            }).then((function (rows) {
                this.renderCandidates(rows || []);
            }).bind(this)).catch(function () {
                $results.html(SimpleStomUi.emptyState('Search failed.'));
            });
        },

        renderCandidates: function (rows) {
            var $results = this.$el.find('[data-name="candidateResults"]');
            var html;

            if (!rows.length) {
                $results.html(SimpleStomUi.emptyState('No matches.'));
                return;
            }

            html = '<ul class="espo-dental-stom-list">';
            rows.forEach(function (row) {
                html += '<li class="espo-dental-stom-list__item">' +
                    '<span>' +
                    SimpleStomUi.escapeHtml(row.name || row.id) +
                    '<span class="espo-dental-stom-muted"> · ' +
                    SimpleStomUi.escapeHtml(row.entityType || '') +
                    (row.phone ? ' · ' + SimpleStomUi.escapeHtml(row.phone) : '') +
                    '</span>' +
                    '</span>' +
                    '<button type="button" class="espo-dental-stom-button" data-action="selectCandidate" ' +
                    'data-id="' + SimpleStomUi.escapeHtml(row.id) + '" ' +
                    'data-type="' + SimpleStomUi.escapeHtml(row.entityType) + '" ' +
                    'data-label="' + SimpleStomUi.escapeHtml(row.name || row.id) + '">Select</button>' +
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
                title: 'Selected',
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
                    Espo.Ui.error((xhr && xhr.responseText) || 'Booking failed');
                }).bind(this));
        },

        buildPayload: function () {
            var mode = this.getMode();
            var payload = {
                clinicId: this.$el.find('[data-name="clinicId"]').val() || this.slot.clinicId || '',
                cabinetId: this.$el.find('[data-name="cabinetId"]').val() || this.slot.cabinetId || '',
                doctorId: this.$el.find('[data-name="doctorId"]').val() || this.options.doctorId || '',
                localStart: this.$el.find('[data-name="localStart"]').val() || this.slot.localStart || '',
                timezone: this.slot.timezone || '',
                durationMinutes: parseInt(this.$el.find('[data-name="durationMinutes"]').val(), 10) || 30,
                reason: this.$el.find('[data-name="reason"]').val() || '',
                notes: this.$el.find('[data-name="notes"]').val() || ''
            };

            if (mode === 'existing') {
                if (!this.selectedCandidate) {
                    Espo.Ui.warning('Select a patient first.');
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
        }
    });
});
