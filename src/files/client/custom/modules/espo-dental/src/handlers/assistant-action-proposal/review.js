define('espo-dental:handlers/assistant-action-proposal/review', [
    'action-handler',
    'espo-dental:utils/dialogs'
], function (Dep, Dialogs) {
    return Dep.extend({
        actionApprove: function () {
            this.review('approve', 'Approve assistant proposal', 'Approved');
        },

        actionReject: function () {
            this.review('reject', 'Reject assistant proposal', 'Rejected');
        },

        review: function (action, titleLabel, successLabel) {
            var view = this.view;
            var model = view.model;

            if (model.get('status') !== 'pending_review') {
                Espo.Ui.warning(view.translate('Only pending review proposals can be reviewed', 'messages', 'AssistantActionProposal'));

                return;
            }

            Dialogs.prompt(view, {
                title: view.translate(titleLabel, 'labels', 'AssistantActionProposal'),
                message: view.translate('Review notes are optional', 'messages', 'AssistantActionProposal'),
                value: '',
                submitLabel: view.translate(action === 'approve' ? 'Approve' : 'Reject', 'labels', 'AssistantActionProposal')
            }).then(function (reviewNotes) {
                if (reviewNotes === null) {
                    return;
                }

                Espo.Ajax.postRequest('EspoDental/AssistantActionProposal/' + action, {
                    id: model.id,
                    reviewNotes: reviewNotes || ''
                })
                    .then(function () {
                        Espo.Ui.success(view.translate(successLabel, 'labels', 'AssistantActionProposal'));
                        model.fetch();
                    })
                    .catch(function (xhr) {
                        Espo.Ui.error((xhr && xhr.responseText) || view.translate('Review failed', 'messages', 'AssistantActionProposal'));
                    });
            });
        }
    });
});
