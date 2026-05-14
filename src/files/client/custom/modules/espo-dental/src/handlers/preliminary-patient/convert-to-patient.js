define('espo-dental:handlers/preliminary-patient/convert-to-patient', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionConvertToPatient: function (data) {
            var model = this.view.model;

            if (model.get('convertedToPatientId')) {
                Espo.Ui.warning(this.view.translate('Already converted', 'messages', 'PreliminaryPatient'));
                return;
            }

            this.view.createView('convertDialog', 'espo-dental:views/preliminary-patient/modals/convert', {
                model: model
            }, function (view) {
                view.render();
                this.view.listenToOnce(view, 'done', function (result) {
                    Espo.Ui.success(this.view.translate('Converted', 'messages', 'PreliminaryPatient'));

                    if (result && result.patientId) {
                        this.view.getRouter().navigate('#Patient/view/' + result.patientId, {trigger: true});
                    } else {
                        model.fetch();
                    }
                }, this);
            }, this);
        }
    });
});
