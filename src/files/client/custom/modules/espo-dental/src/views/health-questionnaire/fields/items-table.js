define('espo-dental:views/health-questionnaire/fields/items-table', ['views/fields/base'], function (Dep) {

    return Dep.extend({

        readOnly: true,

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.renderAnswerTable();
        },

        renderAnswerTable: function () {
            if (!this.model.id || !this.$el) {
                this.$el.html(this.renderLocalFallback());
                return;
            }

            this.$el.html('<span class="text-muted">' +
                this.translate('Loading...', 'messages', 'Global') +
                '</span>');

            Espo.Ajax.getRequest('HealthQuestionnaire/action/answers', {
                id: this.model.id
            }).then(function (data) {
                this.$el.html(this.buildTable(data || {}));
            }.bind(this)).catch(function () {
                this.$el.html(this.renderLocalFallback());
            }.bind(this));
        },

        buildTable: function (data) {
            var groups = Array.isArray(data.groups) ? data.groups.slice() : [];
            var extraAnswers = Array.isArray(data.extraAnswers) ? data.extraAnswers : [];

            if (extraAnswers.length) {
                groups.push({
                    id: 'extra',
                    label: this.translate('Other Answers', 'labels', 'HealthQuestionnaire'),
                    answers: extraAnswers
                });
            }

            if (!groups.length) {
                return this.emptyState();
            }

            var html = '<div class="espo-dental-questionnaire-answers">';

            groups.forEach(function (group) {
                var answers = Array.isArray(group.answers) ? group.answers : [];
                if (!answers.length) {
                    return;
                }

                html += '<h5 style="margin-top:12px">' + this.escapeHtml(group.label || '') + '</h5>' +
                    '<div class="table-responsive">' +
                    '<table class="table table-condensed table-bordered">' +
                    '<thead><tr>' +
                        '<th>' + this.translate('Question', 'labels', 'HealthQuestionnaire') + '</th>' +
                        '<th style="width:180px">' + this.translate('Answer', 'labels', 'HealthQuestionnaire') + '</th>' +
                    '</tr></thead><tbody>';

                answers.forEach(function (answer) {
                    var isFlagged = answer.alert === true && answer.value === true;
                    html += '<tr' + (isFlagged ? ' class="warning"' : '') + '>' +
                        '<td>' +
                            this.escapeHtml(answer.label || answer.id || '') +
                            (isFlagged ? ' <span class="label label-warning">' +
                                this.translate('Requires attention', 'labels', 'HealthQuestionnaire') +
                                '</span>' : '') +
                        '</td>' +
                        '<td>' + this.formatValue(answer.value, answer.type) + '</td>' +
                    '</tr>';
                }.bind(this));

                html += '</tbody></table></div>';
            }.bind(this));

            html += '</div>';

            return html;
        },

        renderLocalFallback: function () {
            var value = this.model.get(this.name);
            if (!value || typeof value !== 'object') {
                return this.emptyState();
            }

            var rows = Object.keys(value).map(function (key) {
                return {
                    id: key,
                    label: key,
                    type: typeof value[key] === 'boolean' ? 'bool' : 'text',
                    value: value[key],
                    alert: false
                };
            });

            return this.buildTable({
                groups: [{
                    id: 'raw',
                    label: this.translate('Answers', 'labels', 'HealthQuestionnaire'),
                    answers: rows
                }]
            });
        },

        formatValue: function (value, type) {
            if (type === 'bool' || typeof value === 'boolean') {
                return value ?
                    this.translate('Yes', 'labels', 'Global') :
                    this.translate('No', 'labels', 'Global');
            }

            if (value === null || value === undefined || value === '') {
                return '<span class="text-muted">&mdash;</span>';
            }

            return this.escapeHtml(value);
        },

        emptyState: function () {
            return '<span class="text-muted">' +
                this.translate('No Data', 'labels', 'Global') +
                '</span>';
        },

        escapeHtml: function (value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    });
});
