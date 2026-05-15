define('espo-dental:handlers/patient/issue-questionnaire', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionIssueQuestionnaire: function () {
            var view = this.view;
            var model = view.model;

            if (!model.get('questionnaireExpired')) {
                Espo.Ui.warning(view.translate('Questionnaire is up to date', 'messages', 'Patient'));
                return;
            }

            view.createView('issueQuestionnaireDialog', 'espo-dental:views/patient/modals/issue-questionnaire', {
                model: model
            }, function (modalView) {
                modalView.render();
                view.listenToOnce(modalView, 'done', function (result) {
                    Espo.Ui.success(view.translate('Questionnaire issued', 'messages', 'PreliminaryPatient'));

                    view.createView('qrDialog', 'espo-dental:views/health-questionnaire/qr-modal', {
                        url: result.tokenUrl,
                        expiresAt: result.expiresAt
                    }, function (qrView) {
                        qrView.render();
                    });

                    model.fetch();
                });
            });
        },

        isIssueQuestionnaireAvailable: function () {
            var recordView = this.view.getRecordView ? this.view.getRecordView() : null;

            if (recordView && recordView.isEditMode && recordView.isEditMode()) {
                return false;
            }

            return !!this.view.model.get('questionnaireExpired');
        }
    });
});
