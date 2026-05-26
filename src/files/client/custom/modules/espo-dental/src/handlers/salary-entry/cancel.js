define('espo-dental:handlers/salary-entry/cancel', [
    'action-handler',
    'espo-dental:utils/dialogs'
], function (Dep, Dialogs) {
    return Dep.extend({
        actionCancel: function () {
            var view = this.view;
            var model = view.model;

            Dialogs.confirm(view, {
                message: view.getLanguage().translate('confirmation', 'labels')
            }).then(function (confirmed) {
                if (!confirmed) {
                    return;
                }

                Espo.Ajax.postRequest('EspoDental/SalaryEntry/cancel', {id: model.id})
                    .then(function () {
                        Espo.Ui.success(view.getLanguage().translate('Cancelled', 'labels', 'SalaryEntry'));
                        model.fetch();
                    })
                    .catch(function (xhr) {
                        Espo.Ui.error((xhr && xhr.responseText) || 'Cancel failed');
                    });
            });
        }
    });
});
