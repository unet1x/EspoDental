define('espo-dental:handlers/orthodontic-card/reopen', ['action-handler'], function (Dep) {
    return Dep.extend({
        actionReopen: function () {
            var view = this.view;
            var model = view.model;
            Espo.Ajax.postRequest('EspoDental/OrthodonticCard/reopen', {id: model.id})
                .then(function () {
                    Espo.Ui.success(view.getLanguage().translate('Reopened', 'labels', 'OrthodonticCard'));
                    model.fetch();
                })
                .catch(function (xhr) {
                    Espo.Ui.error((xhr && xhr.responseText) || 'Reopen failed');
                });
        }
    });
});
