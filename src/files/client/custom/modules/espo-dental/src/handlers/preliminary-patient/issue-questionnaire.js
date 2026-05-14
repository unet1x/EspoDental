define('espo-dental:handlers/preliminary-patient/issue-questionnaire', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionIssueQuestionnaire: function () {
            var model = this.view.model;

            if (model.get('convertedToPatientId')) {
                Espo.Ui.warning(this.view.translate('Already converted', 'messages', 'PreliminaryPatient'));
                return;
            }

            this.view.createView('issueQuestionnaireDialog', 'espo-dental:views/preliminary-patient/modals/issue-questionnaire', {
                model: model
            }, function (view) {
                view.render();
                this.view.listenToOnce(view, 'done', function (result) {
                    Espo.Ui.success(this.view.translate('Questionnaire issued', 'messages', 'PreliminaryPatient'));

                    this.view.createView('qrDialog', 'espo-dental:views/health-questionnaire/qr-modal', {
                        url: result.tokenUrl,
                        expiresAt: result.expiresAt
                    }, function (qrView) {
                        qrView.render();
                    });

                    model.fetch();
                }, this);
            }, this);
        }
    });
});
