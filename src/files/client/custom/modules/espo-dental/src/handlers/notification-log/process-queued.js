define('espo-dental:handlers/notification-log/process-queued', [
    'action-handler',
    'espo-dental:utils/dialogs'
], function (Dep, Dialogs) {
    return Dep.extend({
        actionProcessQueued: function () {
            var view = this.view;
            var model = view.model;

            if (model.get('status') !== 'queued') {
                Espo.Ui.warning(view.translate('Only queued notifications can be processed', 'messages', 'NotificationLog'));

                return;
            }

            Dialogs.confirm(view, {
                message: view.translate('Process queued notification confirmation', 'messages', 'NotificationLog'),
                confirmText: view.translate('Process Queued', 'labels', 'NotificationLog')
            }).then(function (confirmed) {
                if (!confirmed) {
                    return;
                }

                Espo.Ajax.postRequest('EspoDental/NotificationLog/processQueue', {
                    id: model.id
                })
                    .then(function () {
                        Espo.Ui.success(view.translate('Queued notification processed', 'messages', 'NotificationLog'));
                        model.fetch();
                    })
                    .catch(function (xhr) {
                        Espo.Ui.error((xhr && xhr.responseText) || view.translate('Process queued failed', 'messages', 'NotificationLog'));
                    });
            });
        }
    });
});
