define('espo-dental:handlers/patient/book-appointment', [
    'action-handler',
    'helpers/record-modal'
], function (Dep, RecordModal) {

    return Dep.extend({

        actionBookAppointment: function () {
            var view = this.view;
            var patient = view.model;

            var attributes = {
                parentType: 'Patient',
                parentId: patient.id,
                parentName: patient.get('name') || patient.id
            };

            if (patient.get('clinicId')) {
                attributes.clinicId = patient.get('clinicId');
                attributes.clinicName = patient.get('clinicName') || '';
            }

            var modalHelper = new RecordModal();

            modalHelper.showCreate(view, {
                entityType: 'Appointment',
                relate: {
                    model: patient,
                    link: 'parent'
                },
                attributes: attributes,
                fullFormDisabled: true,
                layoutName: 'detailSmall',
                afterSave: function (appointment) {
                    Espo.Ui.success(this.composeBookedMessage(view, appointment));
                    patient.trigger('after:relate');
                    patient.fetch();
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

        isBookAppointmentAvailable: function () {
            var recordView = this.view.getRecordView ? this.view.getRecordView() : null;

            return !(recordView && recordView.isEditMode && recordView.isEditMode());
        }
    });
});
