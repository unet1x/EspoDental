define('espo-dental:handlers/salary-entry/cancel', ['action-handler'], function (Dep) {
    return Dep.extend({
        actionCancel: function () {
            var view = this.view;
            var model = view.model;
            if (!window.confirm(view.getLanguage().translate('confirmation', 'labels'))) {
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
        }
    });
});
