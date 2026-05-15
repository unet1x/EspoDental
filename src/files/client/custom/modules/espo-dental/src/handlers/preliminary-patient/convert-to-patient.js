define('espo-dental:handlers/preliminary-patient/convert-to-patient', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionConvertToPatient: function (data) {
            var view = this.view;
            var model = view.model;

            if (model.get('convertedToPatientId')) {
                Espo.Ui.warning(view.translate('Already converted', 'messages', 'PreliminaryPatient'));
                return;
            }

            view.createView('convertDialog', 'espo-dental:views/preliminary-patient/modals/convert', {
                model: model
            }, function (modalView) {
                modalView.render();
                view.listenToOnce(modalView, 'done', function (result) {
                    Espo.Ui.success(view.translate('Converted', 'messages', 'PreliminaryPatient'));

                    if (result && result.patientId) {
                        view.getRouter().navigate('#Patient/view/' + result.patientId, {trigger: true});
                    } else {
                        model.fetch();
                    }
                });
            });
        }
    });
});
