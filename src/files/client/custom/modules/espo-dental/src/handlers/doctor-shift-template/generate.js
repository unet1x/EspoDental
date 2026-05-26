define('espo-dental:handlers/doctor-shift-template/generate', [
    'action-handler',
    'espo-dental:utils/dialogs'
], function (Dep, Dialogs) {
    return Dep.extend({
        actionGenerate: function () {
            var view = this.view;
            var model = view.model;
            var language = view.getLanguage();

            Dialogs.confirm(view, {
                message: language.translate('Confirm generate shifts', 'messages', 'DoctorShiftTemplate')
            }).then(function (confirmed) {
                if (!confirmed) {
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
            });
        }
    });
});
