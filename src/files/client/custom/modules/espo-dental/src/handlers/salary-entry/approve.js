define('espo-dental:handlers/salary-entry/approve', ['action-handler'], function (Dep) {
    return Dep.extend({
        actionApprove: function () {
            var view = this.view;
            var model = view.model;
            Espo.Ajax.postRequest('EspoDental/SalaryEntry/approve', {id: model.id})
                .then(function () {
                    Espo.Ui.success(view.getLanguage().translate('Approved', 'labels', 'SalaryEntry'));
                    model.fetch();
                })
                .catch(function (xhr) {
                    Espo.Ui.error((xhr && xhr.responseText) || 'Approve failed');
                });
        }
    });
});
