define('espo-dental:handlers/salary-entry/build', ['action-handler'], function (Dep) {
    return Dep.extend({
        actionBuild: function () {
            var view = this.view;
            var model = view.model;
            Espo.Ajax.postRequest('EspoDental/SalaryEntry/build', {
                userId: model.get('userId'),
                periodFrom: model.get('periodFrom'),
                periodTo: model.get('periodTo'),
                profileId: model.get('profileId'),
                hoursWorked: model.get('hoursWorked') || 0
            }).then(function () {
                Espo.Ui.success(view.getLanguage().translate('Built', 'labels', 'SalaryEntry'));
                model.fetch();
            }).catch(function (xhr) {
                Espo.Ui.error((xhr && xhr.responseText) || 'Build failed');
            });
        }
    });
});
