define('espo-dental:views/preliminary-patient/modals/convert', ['views/modal'], function (Dep) {

    return Dep.extend({

        templateContent:
            '<p>{{translate "Convert to Patient confirm" category="messages" scope="PreliminaryPatient"}}</p>',

        setup: function () {
            this.headerText = this.translate('Convert to Patient', 'labels', 'PreliminaryPatient');
            this.buttonList = [
                {name: 'convert', label: this.translate('Convert to Patient', 'labels', 'PreliminaryPatient'), style: 'primary'},
                {name: 'cancel', label: 'Cancel'}
            ];

        },

        actionConvert: function () {
            this.disableButton('convert');

            Espo.Ajax.postRequest('PreliminaryPatient/action/convertToPatient', {
                id: this.model.id
            }).then(function (response) {
                this.trigger('done', response);
                this.close();
            }.bind(this)).catch(function () {
                this.enableButton('convert');
            }.bind(this));
        }
    });
});
