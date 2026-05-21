define('espo-dental:handlers/invoice/book-next-appointment', [
    'action-handler',
    'helpers/record-modal'
], function (Dep, RecordModal) {

    return Dep.extend({

        actionBookNextAppointment: function () {
            var view = this.view;
            var invoice = view.model;
            var attributes = this.buildAttributesFromInvoice(invoice);
            var visitId = invoice.get('visitId');

            if (!attributes.parentId) {
                Espo.Ui.warning(view.translate('Patient is required', 'messages', 'Appointment'));
                return;
            }

            if (!visitId) {
                this.openAppointment(attributes);
                return;
            }

            Espo.Ajax.getRequest('Visit/' + visitId)
                .then(function (visit) {
                    this.applyVisitContext(attributes, visit || {});
                    this.openAppointment(attributes);
                }.bind(this))
                .catch(function () {
                    this.openAppointment(attributes);
                }.bind(this));
        },

        buildAttributesFromInvoice: function (invoice) {
            var attributes = {
                parentType: 'Patient',
                parentId: invoice.get('patientId'),
                parentName: invoice.get('patientName') || invoice.get('patientId') || ''
            };

            this.copyLink(attributes, invoice, 'clinic');

            return attributes;
        },

        applyVisitContext: function (attributes, visit) {
            this.copyLinkFromData(attributes, visit, 'clinic', false);
            this.copyLinkFromData(attributes, visit, 'doctor', true);
            this.copyLinkFromData(attributes, visit, 'cabinet', true);

            var note = visit.recommendations || visit.complaints || '';
            if (note) {
                attributes.complaints = note;
            }
        },

        copyLink: function (attributes, model, name) {
            var id = model.get(name + 'Id');
            if (!id) {
                return;
            }

            attributes[name + 'Id'] = id;
            attributes[name + 'Name'] = model.get(name + 'Name') || '';
        },

        copyLinkFromData: function (attributes, data, name, overwrite) {
            var id = data[name + 'Id'];
            if (!id || (!overwrite && attributes[name + 'Id'])) {
                return;
            }

            attributes[name + 'Id'] = id;
            attributes[name + 'Name'] = data[name + 'Name'] || '';
        },

        openAppointment: function (attributes) {
            var view = this.view;
            var modalHelper = new RecordModal();

            modalHelper.showCreate(view, {
                entityType: 'Appointment',
                attributes: attributes,
                fullFormDisabled: true,
                layoutName: 'detailSmall',
                afterSave: function (appointment) {
                    Espo.Ui.success(this.composeBookedMessage(view, appointment));
                    view.model.fetch();
                }.bind(this)
            });
        },

        composeBookedMessage: function (view, appointment) {
            var clinicTime = appointment ? appointment.espoDentalSelectedSlotClinicTime || '' : '';

            if (!clinicTime) {
                return view.translate('Appointment booked', 'messages', 'Patient');
            }

            return view
                .translate('Appointment booked for', 'messages', 'Patient')
                .replace('{time}', clinicTime);
        },

        isBookNextAppointmentAvailable: function () {
            var recordView = this.view.getRecordView ? this.view.getRecordView() : null;

            return !!this.view.model.get('patientId') &&
                !(recordView && recordView.isEditMode && recordView.isEditMode());
        }
    });
});
