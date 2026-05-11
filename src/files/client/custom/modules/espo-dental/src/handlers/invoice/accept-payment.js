define('espo-dental:handlers/invoice/accept-payment', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionAcceptPayment: function () {
            var view = this.view;
            var model = view.model;
            if (['storno', 'cancelled', 'draft', 'paid'].indexOf(model.get('status')) !== -1) {
                Espo.Ui.warning(view.translate('Invoice not payable', 'messages', 'Invoice'));
                return;
            }

            var balance = parseFloat(model.get('balance') || 0);
            var amount = window.prompt(
                view.translate('Payment amount', 'messages', 'Invoice'),
                balance.toFixed(2)
            );
            if (!amount) { return; }
            amount = parseFloat(amount);
            if (!(amount > 0)) {
                Espo.Ui.warning('Invalid amount');
                return;
            }
            var method = window.prompt(
                view.translate('Payment method (cash/card/bank_transfer/online/terminal/other)', 'messages', 'Invoice'),
                'cash'
            ) || 'cash';

            var payload = {
                patientId: model.get('patientId'),
                clinicId: model.get('clinicId'),
                invoiceId: model.id,
                amount: amount,
                method: method
            };

            Espo.Ajax.postRequest('Payment/action/accept', payload)
                .then(function (response) {
                    Espo.Ui.success(
                        view.translate('Payment accepted', 'messages', 'Invoice') +
                        ' ' + (response && response.number ? response.number : '')
                    );
                    model.fetch();
                });
        }
    });
});
