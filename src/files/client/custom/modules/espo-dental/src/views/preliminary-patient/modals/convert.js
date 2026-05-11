define('espo-dental:views/preliminary-patient/modals/convert', ['views/modal'], function (Dep) {

    return Dep.extend({

        templateContent:
            '<p>{{translate "Convert to Patient confirm" category="messages" scope="PreliminaryPatient"}}</p>' +
            '<div class="form-group">' +
                '<label class="control-label">{{translate "language" category="fields" scope="HealthQuestionnaire"}}</label>' +
                '<select name="language" class="form-control">' +
                    '<option value="ru_RU">Русский</option>' +
                    '<option value="en_US">English</option>' +
                    '<option value="es_ES">Español</option>' +
                '</select>' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="checkbox-inline">' +
                    '<input type="checkbox" name="issueToken" checked> ' +
                    '{{translate "Issue questionnaire QR" category="messages" scope="PreliminaryPatient"}}' +
                '</label>' +
            '</div>',

        setup: function () {
            this.headerText = this.translate('Convert to Patient', 'labels', 'PreliminaryPatient');
            this.buttonList = [
                {name: 'convert', label: this.translate('Convert to Patient', 'labels', 'PreliminaryPatient'), style: 'primary'},
                {name: 'cancel', label: 'Cancel'}
            ];

            var defaultLang = this.getConfig().get('defaultLanguage') || 'ru_RU';
            this.defaultLanguage = ['ru_RU', 'en_US', 'es_ES'].indexOf(defaultLang) >= 0 ? defaultLang : 'ru_RU';
        },

        afterRender: function () {
            this.$el.find('select[name="language"]').val(this.defaultLanguage);
        },

        actionConvert: function () {
            var lang = this.$el.find('select[name="language"]').val();
            var issueToken = this.$el.find('input[name="issueToken"]').prop('checked');

            this.disableButton('convert');

            Espo.Ajax.postRequest('PreliminaryPatient/action/convertToPatient', {
                id: this.model.id,
                language: lang,
                issueToken: issueToken
            }).then(function (response) {
                this.trigger('done', response);
                this.close();
            }.bind(this)).catch(function () {
                this.enableButton('convert');
            }.bind(this));
        }
    });
});
