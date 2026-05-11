define('espo-dental:handlers/invoice/storno', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionStorno: function () {
            var view = this.view;
            var model = view.model;

            if (['storno', 'cancelled', 'draft'].indexOf(model.get('status')) !== -1) {
                Espo.Ui.warning(view.translate('Cannot storno', 'messages', 'Invoice'));
                return;
            }

            var reason = window.prompt(view.translate('Storno reason', 'messages', 'Invoice') || 'Reason') || '';

            Espo.Ajax.postRequest('Invoice/action/storno', {id: model.id, reason: reason})
                .then(function (response) {
                    Espo.Ui.success(view.translate('Invoice storno-ed', 'messages', 'Invoice'));
                    if (response && response.stornoInvoiceId) {
                        view.getRouter().navigate('#Invoice/view/' + response.stornoInvoiceId, {trigger: true});
                    } else {
                        model.fetch();
                    }
                });
        }
    });
});
