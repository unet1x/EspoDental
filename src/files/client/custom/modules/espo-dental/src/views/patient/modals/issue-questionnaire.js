define('espo-dental:views/patient/modals/issue-questionnaire', ['views/modal'], function (Dep) {

    return Dep.extend({

        templateContent:
            '<p>{{translate "Issue questionnaire confirm" category="messages" scope="Patient"}}</p>' +
            '<div class="form-group">' +
                '<label class="control-label">{{translate "language" category="fields" scope="HealthQuestionnaire"}}</label>' +
                '<select name="language" class="form-control">' +
                    '<option value="ru_RU">Русский</option>' +
                    '<option value="en_US">English</option>' +
                    '<option value="es_ES">Español</option>' +
                '</select>' +
            '</div>',

        setup: function () {
            this.headerText = this.translate('Health Questionnaire', 'labels', 'Patient');
            this.buttonList = [
                {name: 'issue', label: this.translate('Show QR', 'labels', 'PreliminaryPatient'), style: 'primary'},
                {name: 'cancel', label: 'Cancel'}
            ];

            var defaultLang = this.getConfig().get('defaultLanguage') || 'ru_RU';
            this.defaultLanguage = ['ru_RU', 'en_US', 'es_ES'].indexOf(defaultLang) >= 0 ? defaultLang : 'ru_RU';
        },

        afterRender: function () {
            this.$el.find('select[name="language"]').val(this.defaultLanguage);
        },

        actionIssue: function () {
            var lang = this.$el.find('select[name="language"]').val();

            this.disableButton('issue');

            Espo.Ajax.postRequest('Patient/action/issueQuestionnaire', {
                id: this.model.id,
                language: lang
            }).then(function (response) {
                this.trigger('done', response);
                this.close();
            }.bind(this)).catch(function () {
                this.enableButton('issue');
            }.bind(this));
        }
    });
});
