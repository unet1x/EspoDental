define('espo-dental:handlers/health-questionnaire/print-pdf', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionPrintPdf: function () {
            var view = this.view;
            var model = view.model;
            var attachmentId = model.get('pdfFileId');

            if (!attachmentId) {
                Espo.Ui.warning(view.translate('PDF file is not available', 'messages', 'HealthQuestionnaire'));
                return;
            }

            window.open('?entryPoint=download&id=' + attachmentId, '_blank');
        }
    });
});
