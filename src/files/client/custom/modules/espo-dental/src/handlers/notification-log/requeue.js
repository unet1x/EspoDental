define('espo-dental:handlers/notification-log/requeue', [
    'action-handler',
    'espo-dental:utils/dialogs'
], function (Dep, Dialogs) {
    return Dep.extend({
        actionRequeue: function () {
            var view = this.view;
            var model = view.model;

            if (model.get('status') !== 'failed') {
                Espo.Ui.warning(view.translate('Only failed notifications can be requeued', 'messages', 'NotificationLog'));

                return;
            }

            Dialogs.prompt(view, {
                title: view.translate('Requeue notification', 'labels', 'NotificationLog'),
                message: view.translate('Requeue note is optional', 'messages', 'NotificationLog'),
                value: '',
                submitLabel: view.translate('Requeue', 'labels', 'NotificationLog')
            }).then(function (note) {
                if (note === null) {
                    return;
                }

                Espo.Ajax.postRequest('EspoDental/NotificationLog/requeue', {
                    id: model.id,
                    note: note || ''
                })
                    .then(function () {
                        Espo.Ui.success(view.translate('Notification requeued', 'messages', 'NotificationLog'));
                        model.fetch();
                    })
                    .catch(function (xhr) {
                        Espo.Ui.error((xhr && xhr.responseText) || view.translate('Requeue failed', 'messages', 'NotificationLog'));
                    });
            });
        }
    });
});
