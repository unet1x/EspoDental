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
                afterSave: function () {
                    Espo.Ui.success(view.translate('Appointment booked', 'messages', 'Patient'));
                    patient.trigger('after:relate');
                    patient.fetch();
                }
            });
        },

        isBookAppointmentAvailable: function () {
            var recordView = this.view.getRecordView ? this.view.getRecordView() : null;

            return !(recordView && recordView.isEditMode && recordView.isEditMode());
        }
    });
});
