define('espo-dental:handlers/payment/refund', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionRefund: function () {
            var view = this.view;
            var model = view.model;
            if (model.get('status') !== 'completed' || model.get('direction') !== 'in') {
                Espo.Ui.warning(view.translate('Cannot refund this payment', 'messages', 'Payment'));
                return;
            }
            var max = parseFloat(model.get('amount') || 0);
            var amount = window.prompt(
                view.translate('Refund amount', 'messages', 'Payment'),
                max.toFixed(2)
            );
            if (!amount) { return; }
            amount = parseFloat(amount);
            if (!(amount > 0) || amount > max) {
                Espo.Ui.warning('Invalid amount');
                return;
            }
            var reason = window.prompt(view.translate('Refund reason', 'messages', 'Payment'), '') || '';

            Espo.Ajax.postRequest('Payment/action/refund', {
                id: model.id,
                amount: amount,
                reason: reason
            }).then(function (response) {
                Espo.Ui.success(view.translate('Refund created', 'messages', 'Payment'));
                if (response && response.refundPaymentId) {
                    view.getRouter().navigate('#Payment/view/' + response.refundPaymentId, {trigger: true});
                } else {
                    model.fetch();
                }
            });
        }
    });
});
