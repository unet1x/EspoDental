define('espo-dental:handlers/salary-entry/pay', ['action-handler'], function (Dep) {
    return Dep.extend({
        actionPay: function () {
            var view = this.view;
            var model = view.model;
            var method = window.prompt('Method (cash/card/transfer/other)', 'cash') || 'cash';
            Espo.Ajax.postRequest('EspoDental/SalaryEntry/pay', {id: model.id, method: method})
                .then(function () {
                    Espo.Ui.success(view.getLanguage().translate('Paid', 'labels', 'SalaryEntry'));
                    model.fetch();
                })
                .catch(function (xhr) {
                    Espo.Ui.error((xhr && xhr.responseText) || 'Pay failed');
                });
        }
    });
});
