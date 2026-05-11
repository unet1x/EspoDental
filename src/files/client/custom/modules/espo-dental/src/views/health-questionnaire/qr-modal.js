define('espo-dental:views/health-questionnaire/qr-modal', ['views/modal', 'espo-dental:lib/qr-svg'], function (Dep, QrSvg) {

    return Dep.extend({

        templateContent:
            '<p>{{translate "Show QR to patient" category="messages" scope="HealthQuestionnaire"}}</p>' +
            '<div data-name="qr-wrap" style="display:flex;justify-content:center;padding:12px 0;"></div>' +
            '<div class="text-muted small" style="word-break:break-all;text-align:center">' +
                '<a href="{{url}}" target="_blank" rel="noopener">{{url}}</a>' +
            '</div>' +
            '{{#if expiresAt}}<div class="text-muted small" style="margin-top:8px;text-align:center">' +
                '{{translate "Expires at" category="labels" scope="QuestionnaireToken"}}: ' +
                '{{datetime expiresAt}}' +
            '</div>{{/if}}',

        data: function () {
            return {url: this.options.url, expiresAt: this.options.expiresAt};
        },

        setup: function () {
            this.headerText = this.translate('Health Questionnaire', 'scopeNames');
            this.buttonList = [
                {name: 'copy', label: this.translate('Copy URL', 'labels', 'HealthQuestionnaire')},
                {name: 'cancel', label: 'Close'}
            ];
        },

        afterRender: function () {
            var $wrap = this.$el.find('[data-name="qr-wrap"]');
            try {
                var svg = QrSvg.render(this.options.url, {size: 256, margin: 12});
                $wrap.html(svg);
            } catch (e) {
                $wrap.html('<div class="text-danger small">QR rendering failed: ' + e.message + '</div>');
            }
        },

        actionCopy: function () {
            var url = this.options.url;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    Espo.Ui.success(this.translate('Copied'));
                }.bind(this));
            } else {
                window.prompt('Copy URL:', url);
            }
        }
    });
});
