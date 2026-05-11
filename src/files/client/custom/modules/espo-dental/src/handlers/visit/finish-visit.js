define('espo-dental:handlers/visit/finish-visit', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionFinishVisit: function (data) {
            var view = this.view;
            var model = view.model;

            if (model.get('status') !== 'in_progress') {
                Espo.Ui.warning(view.translate('Only in-progress visits can be finished', 'messages', 'Visit'));
                return;
            }

            view.confirm({
                message: view.translate('Confirm finish visit', 'messages', 'Visit'),
                confirmText: view.translate('Finish Visit', 'labels', 'Visit')
            }, function () {
                Espo.Ajax.postRequest('Visit/action/finishVisit', {id: model.id})
                    .then(function (response) {
                        var msg = view.translate('Visit finished', 'messages', 'Visit');
                        if (response && typeof response.total === 'number') {
                            msg += ' (' + response.total.toFixed(2) + ')';
                        }
                        Espo.Ui.success(msg);
                        model.fetch();
                    });
            });
        }
    });
});
