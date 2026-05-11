define('espo-dental:handlers/orthodontic-card/close', ['action-handler'], function (Dep) {
    return Dep.extend({
        actionClose: function () {
            var view = this.view;
            var model = view.model;
            var finalStatus = window.prompt('Final status (completed/cancelled)', 'completed') || 'completed';
            Espo.Ajax.postRequest('EspoDental/OrthodonticCard/close', {
                id: model.id, finalStatus: finalStatus
            }).then(function () {
                Espo.Ui.success(view.getLanguage().translate('Closed', 'labels', 'OrthodonticCard'));
                model.fetch();
            }).catch(function (xhr) {
                Espo.Ui.error((xhr && xhr.responseText) || 'Close failed');
            });
        }
    });
});
