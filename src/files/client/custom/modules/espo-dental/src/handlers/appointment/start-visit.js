define('espo-dental:handlers/appointment/start-visit', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionStartVisit: function (data) {
            var view = this.view;
            var model = view.model;

            if (model.get('parentType') !== 'Patient') {
                Espo.Ui.warning(view.translate('Convert lead first', 'messages', 'Appointment'));
                return;
            }

            var allowed = ['planned', 'rescheduled', 'arrived'];
            if (allowed.indexOf(model.get('status')) === -1) {
                Espo.Ui.warning(view.translate('Status not allowed for start', 'messages', 'Appointment'));
                return;
            }

            view.confirm({
                message: view.translate('Confirm start visit', 'messages', 'Appointment'),
                confirmText: view.translate('Start Visit', 'labels', 'Appointment')
            }, function () {
                Espo.Ajax.postRequest('Appointment/action/startVisit', {id: model.id})
                    .then(function (response) {
                        Espo.Ui.success(view.translate('Visit started', 'messages', 'Appointment'));
                        if (response && response.visitId) {
                            view.getRouter().navigate('#Visit/view/' + response.visitId, {trigger: true});
                        } else {
                            model.fetch();
                        }
                    });
            });
        }
    });
});
