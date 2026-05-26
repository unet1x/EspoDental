define('espo-dental:views/modals/prompt', ['views/modal'], function (Dep) {

    return Dep.extend({

        templateContent:
            '<p data-name="message" class="text-muted"></p>' +
            '<div class="form-group" data-name="input-container">' +
                '<input type="text" class="form-control" data-name="value">' +
            '</div>',

        setup: function () {
            this.headerText = this.options.title || this.options.message || '';
            this.buttonList = [
                {name: 'submit', label: this.options.submitLabel || 'OK', style: 'primary'},
                {name: 'cancel', label: 'Cancel'}
            ];
        },

        afterRender: function () {
            this.$el.find('[data-name="message"]').text(this.options.message || '');

            if (this.options.hideInput) {
                this.$el.find('[data-name="input-container"]').addClass('hidden');

                return;
            }

            var $input = this.$el.find('[data-name="value"]');
            $input.val(this.options.value || '');

            if (this.options.inputType) {
                $input.attr('type', this.options.inputType);
            }

            setTimeout(function () {
                $input.trigger('focus').trigger('select');
            }, 0);
        },

        actionSubmit: function () {
            var value = this.options.hideInput ? true : this.$el.find('[data-name="value"]').val();

            this.trigger('submit', value);
            this.close();
        },

        actionCancel: function () {
            this.trigger('cancel');
            this.close();
        }
    });
});
