define('espo-dental:handlers/invoice/print-pdf', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionPrintPdf: function () {
            var view = this.view;
            var model = view.model;

            Espo.Ajax.postRequest('Invoice/action/buildPdf', {id: model.id})
                .then(function (response) {
                    if (response && response.attachmentId) {
                        window.open('?entryPoint=download&id=' + response.attachmentId, '_blank');
                    } else {
                        Espo.Ui.error(view.translate('PDF build failed', 'messages', 'Invoice'));
                    }
                });
        }
    });
});
