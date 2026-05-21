define('espo-dental:views/patient/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'sync', function () {
                this.renderClinicalFilesPanel();
                this.renderPatientHistoryPanel();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.renderQuestionnaireAlert();
            this.renderClinicalFilesPanel();
            this.renderPatientHistoryPanel();
        },

        renderQuestionnaireAlert: function () {
            if (!this.$el) {
                return;
            }

            this.$el.find('[data-name="patient-questionnaire-alert"]').remove();

            var expired = this.model.get('questionnaireExpired') === true;
            var hasAlerts = this.model.get('questionnaireHasAlerts') === true;

            if (!expired && !hasAlerts) {
                return;
            }

            var messages = [];
            if (expired) {
                messages.push(this.translate('Questionnaire expired warning', 'messages', 'Patient'));
            }
            if (hasAlerts) {
                messages.push(this.translate('Questionnaire has alerts warning', 'messages', 'Patient'));
            }

            var $alert = $('<div class="alert ' + (expired ? 'alert-danger' : 'alert-warning') +
                '" data-name="patient-questionnaire-alert" style="margin-bottom:12px"></div>');
            $alert.text(messages.join(' '));

            var $firstPanel = this.$el.find('.panel').first();
            if ($firstPanel.length) {
                $alert.insertBefore($firstPanel);
            } else {
                this.$el.prepend($alert);
            }
        },

        renderClinicalFilesPanel: function () {
            if (!this.model.id || !this.$el) {
                return;
            }

            var $panel = this.ensureClinicalFilesPanel();
            var $body = $panel.find('[data-name="patient-clinical-files-body"]');
            var self = this;

            $body.html('<span class="text-muted">' +
                this.translate('Loading...', 'messages', 'Global') +
                '</span>');

            Espo.Ajax.getRequest('Patient/action/files', {
                id: this.model.id,
                limit: 12
            }).then(function (data) {
                self.renderClinicalFilesContent($body, data || {});
            }).catch(function () {
                $body.html('<span class="text-danger">' +
                    self.translate('Error') +
                '</span>');
            });
        },

        renderPatientHistoryPanel: function () {
            if (!this.model.id || !this.$el) {
                return;
            }

            var $panel = this.ensurePatientHistoryPanel();
            var $body = $panel.find('[data-name="patient-history-body"]');
            var self = this;

            $body.html('<span class="text-muted">' +
                this.translate('Loading...', 'messages', 'Global') +
                '</span>');

            Espo.Ajax.getRequest('Patient/action/history', {
                id: this.model.id,
                limit: 8
            }).then(function (data) {
                self.renderPatientHistoryContent($body, data || {});
            }).catch(function () {
                $body.html('<span class="text-danger">' +
                    self.translate('Error') +
                    '</span>');
            });
        },

        ensurePatientHistoryPanel: function () {
            var $existing = this.$el.find('[data-name="patient-history-panel"]');
            if ($existing.length) {
                return $existing;
            }

            var $panel = $('<div class="panel panel-default" data-name="patient-history-panel">' +
                '<div class="panel-heading">' +
                    '<span class="panel-title">' +
                        this.translate('Patient History', 'labels', 'Patient') +
                    '</span>' +
                '</div>' +
                '<div class="panel-body" data-name="patient-history-body"></div>' +
            '</div>');

            var $questionnaireField = this.$el.find('[data-name="lastQuestionnaireAt"]').first();
            var $anchor = $questionnaireField.closest('.panel').first();

            if (!$anchor.length) {
                $anchor = this.$el.find('.panel').first();
            }

            if ($anchor.length) {
                $panel.insertAfter($anchor);
            } else {
                this.$el.append($panel);
            }

            return $panel;
        },

        ensureClinicalFilesPanel: function () {
            var $existing = this.$el.find('[data-name="patient-clinical-files-panel"]');
            if ($existing.length) {
                return $existing;
            }

            var $panel = $('<div class="panel panel-default" data-name="patient-clinical-files-panel">' +
                '<div class="panel-heading">' +
                    '<span class="panel-title">' +
                        this.translate('Clinical Files', 'labels', 'Patient') +
                    '</span>' +
                '</div>' +
                '<div class="panel-body" data-name="patient-clinical-files-body"></div>' +
            '</div>');

            var $questionnaireField = this.$el.find('[data-name="lastQuestionnaireAt"]').first();
            var $anchor = $questionnaireField.closest('.panel').first();

            var $historyPanel = this.$el.find('[data-name="patient-history-panel"]').first();
            if ($historyPanel.length) {
                $anchor = $historyPanel;
            }

            if (!$anchor.length) {
                $anchor = this.$el.find('.panel').first();
            }

            if ($anchor.length) {
                $panel.insertAfter($anchor);
            } else {
                this.$el.append($panel);
            }

            return $panel;
        },

        renderPatientHistoryContent: function ($body, data) {
            var futureAppointments = Array.isArray(data.futureAppointments) ? data.futureAppointments : [];
            var pastVisits = Array.isArray(data.pastVisits) ? data.pastVisits : [];

            $body.html(
                '<div class="row">' +
                    '<div class="col-sm-6">' +
                        '<h5 style="margin-top:0">' +
                            this.translate('Future Appointments', 'labels', 'Patient') +
                        '</h5>' +
                        this.renderFutureAppointments(futureAppointments) +
                    '</div>' +
                    '<div class="col-sm-6">' +
                        '<h5 style="margin-top:0">' +
                            this.translate('Past Visits', 'labels', 'Patient') +
                        '</h5>' +
                        this.renderPastVisits(pastVisits) +
                    '</div>' +
                '</div>'
            );
        },

        renderFutureAppointments: function (appointments) {
            if (!appointments.length) {
                return this.emptyState();
            }

            var html = '<div class="table-responsive">' +
                '<table class="table table-condensed table-bordered" style="margin-bottom:0">' +
                '<tbody>';

            appointments.forEach(function (appointment) {
                var title = appointment.name || appointment.localStart || this.translate('Appointment', 'scopeNames', 'Global');
                var time = appointment.localStart || appointment.dateStart || '';
                var meta = this.renderHistoryMeta([
                    appointment.doctorName,
                    appointment.cabinetName,
                    appointment.clinicName
                ]);

                html += '<tr><td>' +
                    '<a href="#Appointment/view/' + this.escapeAttribute(appointment.id) + '">' +
                        this.escapeHtml(title) +
                    '</a>' +
                    '<div class="text-muted small">' + this.escapeHtml(time) + '</div>' +
                    meta +
                    this.renderStatusLabel(appointment.status, 'Appointment') +
                '</td></tr>';
            }.bind(this));

            html += '</tbody></table></div>';

            return html;
        },

        renderPastVisits: function (visits) {
            if (!visits.length) {
                return this.emptyState();
            }

            var html = '<div class="table-responsive">' +
                '<table class="table table-condensed table-bordered" style="margin-bottom:0">' +
                '<tbody>';

            visits.forEach(function (visit) {
                var title = visit.name || visit.localStartedAt || this.translate('Visit', 'scopeNames', 'Global');
                var time = visit.localStartedAt || visit.startedAt || '';
                var total = visit.totalAmount == null || visit.totalAmount === '' ?
                    '' :
                    '<span class="text-muted small">' + this.escapeHtml(visit.totalAmount) + '</span>';
                var meta = this.renderHistoryMeta([
                    visit.doctorName,
                    visit.cabinetName,
                    visit.clinicName
                ]);

                html += '<tr><td>' +
                    '<a href="#Visit/view/' + this.escapeAttribute(visit.id) + '">' +
                        this.escapeHtml(title) +
                    '</a>' +
                    '<div class="text-muted small">' + this.escapeHtml(time) + '</div>' +
                    meta +
                    this.renderStatusLabel(visit.status, 'Visit') +
                    (total ? '<div>' + total + '</div>' : '') +
                '</td></tr>';
            }.bind(this));

            html += '</tbody></table></div>';

            return html;
        },

        renderHistoryMeta: function (values) {
            var parts = values.filter(function (value) {
                return value != null && value !== '';
            }).map(function (value) {
                return this.escapeHtml(value);
            }.bind(this));

            return parts.length ?
                '<div class="text-muted small">' + parts.join(' &middot; ') + '</div>' :
                '';
        },

        renderStatusLabel: function (status, scope) {
            if (!status) {
                return '';
            }

            return '<div style="margin-top:4px">' +
                '<span class="label label-default">' +
                    this.escapeHtml(this.translateOptionValue(status, 'status', scope)) +
                '</span>' +
            '</div>';
        },

        renderClinicalFilesContent: function ($body, data) {
            var photos = Array.isArray(data.photos) ? data.photos : [];
            var questionnaireFiles = Array.isArray(data.questionnaireFiles) ? data.questionnaireFiles : [];

            $body.html(
                '<div class="row">' +
                    '<div class="col-sm-6">' +
                        '<h5 style="margin-top:0">' +
                            this.translate('Recent Visit Photos', 'labels', 'Patient') +
                        '</h5>' +
                        this.renderPhotos(photos) +
                    '</div>' +
                    '<div class="col-sm-6">' +
                        '<h5 style="margin-top:0">' +
                            this.translate('Questionnaire Files', 'labels', 'Patient') +
                        '</h5>' +
                        this.renderQuestionnaireFiles(questionnaireFiles) +
                    '</div>' +
                '</div>'
            );
        },

        renderPhotos: function (photos) {
            if (!photos.length) {
                return this.emptyState();
            }

            var html = '<div class="table-responsive">' +
                '<table class="table table-condensed table-bordered" style="margin-bottom:0">' +
                '<tbody>';

            photos.forEach(function (photo) {
                var image = photo.imageId ?
                    '<a href="?entryPoint=download&id=' + this.escapeAttribute(photo.imageId) + '" target="_blank">' +
                        '<img class="img-thumbnail" alt="" src="?entryPoint=download&id=' +
                            this.escapeAttribute(photo.imageId) +
                            '" style="width:72px;height:54px;object-fit:cover">' +
                    '</a>' :
                    '<span class="text-muted">&mdash;</span>';
                var title = photo.name || photo.imageName || this.translate('VisitPhoto', 'scopeNames', 'Global');
                var meta = this.renderPhotoMeta(photo);

                html += '<tr>' +
                    '<td style="width:84px;vertical-align:top">' + image + '</td>' +
                    '<td style="vertical-align:top">' +
                        '<a href="#VisitPhoto/view/' + this.escapeAttribute(photo.id) + '">' +
                            this.escapeHtml(title) +
                        '</a>' +
                        meta +
                    '</td>' +
                '</tr>';
            }.bind(this));

            html += '</tbody></table></div>';

            return html;
        },

        renderPhotoMeta: function (photo) {
            var parts = [];

            if (photo.visitId) {
                parts.push('<a href="#Visit/view/' + this.escapeAttribute(photo.visitId) + '">' +
                    this.escapeHtml(photo.visitName || this.translate('Visit', 'scopeNames', 'Global')) +
                    '</a>');
            }

            if (photo.recordedAt) {
                parts.push(this.escapeHtml(photo.recordedAt));
            }

            var badges = [];
            if (photo.stage) {
                badges.push(this.escapeHtml(this.translateVisitPhotoOption(photo.stage, 'stage')));
            }
            if (photo.category) {
                badges.push(this.escapeHtml(this.translateVisitPhotoOption(photo.category, 'category')));
            }
            if (photo.tooth) {
                badges.push(this.escapeHtml(this.translate('tooth', 'fields', 'VisitPhoto') + ': ' + photo.tooth));
            }

            var html = parts.length ?
                '<div class="text-muted small">' + parts.join(' &middot; ') + '</div>' :
                '';

            if (badges.length) {
                html += '<div class="small">' + badges.join(' &middot; ') + '</div>';
            }

            if (photo.orthancUrl) {
                html += '<div class="small"><a href="' + this.escapeAttribute(photo.orthancUrl) +
                    '" target="_blank">Orthanc</a></div>';
            }

            return html;
        },

        renderQuestionnaireFiles: function (rows) {
            if (!rows.length) {
                return this.emptyState();
            }

            var html = '<div class="table-responsive">' +
                '<table class="table table-condensed table-bordered" style="margin-bottom:0">' +
                '<tbody>';

            rows.forEach(function (row) {
                var title = row.name || row.filledAt || this.translate('Health Questionnaire', 'labels', 'Patient');
                var links = [];

                if (row.pdfFileId) {
                    links.push('<a href="?entryPoint=download&id=' + this.escapeAttribute(row.pdfFileId) +
                        '" target="_blank">' +
                        this.escapeHtml(row.pdfFileName || 'PDF') +
                        '</a>');
                }

                if (row.signatureAttachmentId) {
                    links.push('<a href="?entryPoint=download&id=' + this.escapeAttribute(row.signatureAttachmentId) +
                        '" target="_blank">' +
                        this.escapeHtml(row.signatureAttachmentName || this.translate('Signature', 'fields', 'HealthQuestionnaire')) +
                        '</a>');
                }

                var flags = [];
                if (row.hasAlerts) {
                    flags.push('<span class="label label-warning">' +
                        this.translate('Alerts', 'labels', 'Patient') +
                        '</span>');
                }
                if (row.isExpired) {
                    flags.push('<span class="label label-danger">' +
                        this.translate('Expired', 'labels', 'Patient') +
                        '</span>');
                }

                html += '<tr><td>' +
                    '<a href="#HealthQuestionnaire/view/' + this.escapeAttribute(row.id) + '">' +
                        this.escapeHtml(title) +
                    '</a>' +
                    '<div class="text-muted small">' + this.escapeHtml(row.filledAt || '') + '</div>' +
                    '<div class="small">' + (links.length ? links.join(' &middot; ') : '<span class="text-muted">&mdash;</span>') + '</div>' +
                    (flags.length ? '<div style="margin-top:4px">' + flags.join(' ') + '</div>' : '') +
                '</td></tr>';
            }.bind(this));

            html += '</tbody></table></div>';

            return html;
        },

        translateVisitPhotoOption: function (value, field) {
            var language = this.getLanguage ? this.getLanguage() : null;
            if (language && typeof language.translateOption === 'function') {
                return language.translateOption(value, field, 'VisitPhoto');
            }

            return value;
        },

        translateOptionValue: function (value, field, scope) {
            var language = this.getLanguage ? this.getLanguage() : null;
            if (language && typeof language.translateOption === 'function') {
                return language.translateOption(value, field, scope);
            }

            return value;
        },

        emptyState: function () {
            return '<span class="text-muted">' +
                this.translate('No Data', 'labels', 'Global') +
                '</span>';
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
