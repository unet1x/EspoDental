define('espo-dental:handlers/salary-entry/pay', [
    'action-handler',
    'espo-dental:utils/dialogs'
], function (Dep, Dialogs) {
    return Dep.extend({
        actionPay: function () {
            var view = this.view;
            var model = view.model;

            Dialogs.prompt(view, {
                title: 'Method (cash/card/transfer/other)',
                value: 'cash'
            }).then(function (method) {
                if (method === null) {
                    return;
                }

                Espo.Ajax.postRequest('EspoDental/SalaryEntry/pay', {id: model.id, method: method || 'cash'})
                    .then(function () {
                        Espo.Ui.success(view.getLanguage().translate('Paid', 'labels', 'SalaryEntry'));
                        model.fetch();
                    })
                    .catch(function (xhr) {
                        Espo.Ui.error((xhr && xhr.responseText) || 'Pay failed');
                    });
            });
        }
    });
});
