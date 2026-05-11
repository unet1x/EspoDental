define('espo-dental:handlers/invoice/issue', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionIssue: function () {
            var view = this.view;
            var model = view.model;

            if (model.get('status') !== 'draft') {
                Espo.Ui.warning(view.translate('Only draft can be issued', 'messages', 'Invoice'));
                return;
            }

            view.confirm({
                message: view.translate('Confirm issue invoice', 'messages', 'Invoice'),
                confirmText: view.translate('Issue', 'labels', 'Invoice')
            }, function () {
                Espo.Ajax.postRequest('Invoice/action/issue', {id: model.id})
                    .then(function () {
                        Espo.Ui.success(view.translate('Invoice issued', 'messages', 'Invoice'));
                        model.fetch();
                    });
            });
        }
    });
});
