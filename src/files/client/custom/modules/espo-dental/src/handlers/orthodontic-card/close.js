define('espo-dental:handlers/orthodontic-card/close', [
    'action-handler',
    'espo-dental:utils/dialogs'
], function (Dep, Dialogs) {
    return Dep.extend({
        actionClose: function () {
            var view = this.view;
            var model = view.model;

            Dialogs.prompt(view, {
                title: 'Final status (completed/cancelled)',
                value: 'completed'
            }).then(function (value) {
                if (value === null) {
                    return;
                }

                Espo.Ajax.postRequest('EspoDental/OrthodonticCard/close', {
                    id: model.id, finalStatus: value || 'completed'
                }).then(function () {
                    Espo.Ui.success(view.getLanguage().translate('Closed', 'labels', 'OrthodonticCard'));
                    model.fetch();
                }).catch(function (xhr) {
                    Espo.Ui.error((xhr && xhr.responseText) || 'Close failed');
                });
            });
        }
    });
});
