define('espo-dental:handlers/doctor-shift-template/generate', ['action-handler'], function (Dep) {
    return Dep.extend({
        actionGenerate: function () {
            var view = this.view;
            var model = view.model;
            var language = view.getLanguage();

            if (!window.confirm(language.translate('Confirm generate shifts', 'messages', 'DoctorShiftTemplate'))) {
                return;
            }

            Espo.Ajax.postRequest('DoctorShiftTemplate/action/generate', {id: model.id})
                .then(function (result) {
                    var message = language.translate('Generated', 'labels', 'DoctorShiftTemplate') +
                        ': ' + result.created + ' / skipped: ' + result.skipped;
                    Espo.Ui.success(message);
                    model.fetch();
                })
                .catch(function (xhr) {
                    Espo.Ui.error(
                        (xhr && xhr.responseText) ||
                        language.translate('Generate shifts failed', 'messages', 'DoctorShiftTemplate')
                    );
                });
        }
    });
});
