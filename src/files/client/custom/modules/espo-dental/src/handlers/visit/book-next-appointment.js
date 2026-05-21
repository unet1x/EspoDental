define('espo-dental:handlers/visit/book-next-appointment', [
    'action-handler',
    'helpers/record-modal'
], function (Dep, RecordModal) {

    return Dep.extend({

        actionBookNextAppointment: function () {
            var view = this.view;
            var visit = view.model;
            var attributes = this.buildAttributesFromVisit(visit);

            if (!attributes.parentId) {
                Espo.Ui.warning(view.translate('Patient is required', 'messages', 'Appointment'));
                return;
            }

            this.openAppointment(attributes);
        },

        buildAttributesFromVisit: function (visit) {
            var attributes = {
                parentType: 'Patient',
                parentId: visit.get('patientId'),
                parentName: visit.get('patientName') || visit.get('patientId') || ''
            };

            this.copyLink(attributes, visit, 'clinic');
            this.copyLink(attributes, visit, 'doctor');
            this.copyLink(attributes, visit, 'cabinet');

            var note = visit.get('recommendations') || visit.get('complaints') || '';
            if (note) {
                attributes.complaints = note;
            }

            return attributes;
        },

        copyLink: function (attributes, model, name) {
            var id = model.get(name + 'Id');
            if (!id) {
                return;
            }

            attributes[name + 'Id'] = id;
            attributes[name + 'Name'] = model.get(name + 'Name') || '';
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
