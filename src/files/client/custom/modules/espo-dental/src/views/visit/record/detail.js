define('espo-dental:views/visit/record/detail', [
    'views/record/detail',
    'espo-dental:tooth-chart/renderer',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, Renderer, SimpleStomUi) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'sync', this.renderToothChartPreview);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.renderReceptionWorkspace();
            this.renderToothChartPreview();
        },

        renderReceptionWorkspace: function () {
            if (!this.model.id || !this.$el) {
                return;
            }

            SimpleStomUi.ensureStyles();
            var $host = this.ensureReceptionWorkspaceHost();
            $host.find('[data-name="reception-workspace-body"]')
                .html(SimpleStomUi.emptyState('Loading reception workspace...'));

            Espo.Ajax.getRequest('EspoDental/Visit/receptionWorkspace', {id: this.model.id})
                .then((function (data) {
                    this.receptionWorkspaceData = data || {};
                    this.renderReceptionWorkspaceBody(this.receptionWorkspaceData);
                }).bind(this))
                .catch((function () {
                    $host.find('[data-name="reception-workspace-body"]')
                        .html(SimpleStomUi.emptyState('Reception workspace failed to load.'));
                }).bind(this));
        },

        renderReceptionWorkspaceBody: function (data) {
            var counts = data.counts || {};
            var checklist = data.checklist || [];
            var notes = data.notes || {};
            var allowed = data.allowedSections || {};
            var isLocked = !!data.isLocked;

            var summary = '<div class="espo-dental-stom-toolbar">' +
                SimpleStomUi.badge(data.status || 'visit', data.status || 'normal') +
                SimpleStomUi.badge(isLocked ? 'Read only' : 'Autosave on', isLocked ? 'warning' : 'success') +
                '<span class="espo-dental-stom-toolbar__spacer"></span>' +
                this.renderTemplateControls(data.templates || []) +
                '</div>';

            summary += '<div class="espo-dental-stom-layout espo-dental-stom-layout--three">' +
                this.renderNotesPanel('Complaints', 'complaints', notes.complaints, allowed.complaints) +
                this.renderNotesPanel('Treatment notes', 'performed', notes.performed, allowed.performed) +
                this.renderChecklistPanel(checklist, counts) +
                '</div>';

            summary += '<div class="espo-dental-stom-layout espo-dental-stom-layout--two" style="margin-top:12px">' +
                this.renderNotesPanel('Recommendations', 'recommendations', notes.recommendations, allowed.recommendations) +
                this.renderNotesPanel('Treatment plan', 'treatmentPlan', notes.treatmentPlan, allowed.treatmentPlan) +
                '</div>';

            this.ensureReceptionWorkspaceHost()
                .find('[data-name="reception-workspace-body"]')
                .html(SimpleStomUi.workspace(summary));
            this.bindReceptionWorkspaceEvents();
        },

        renderTemplateControls: function (templates) {
            var options = '<option value="">Templates</option>';
            templates.forEach(function (template) {
                options += '<option value="' + SimpleStomUi.escapeHtml(template.id) + '" ' +
                    'data-section="' + SimpleStomUi.escapeHtml(template.section) + '" ' +
                    'data-body="' + SimpleStomUi.escapeHtml(template.body) + '">' +
                    SimpleStomUi.escapeHtml(template.name) +
                    '</option>';
            });

            return '<select class="form-control input-sm" data-name="visitNoteTemplate" style="max-width:220px">' +
                options +
                '</select>' +
                SimpleStomUi.button('Save template', {
                    tone: 'quiet',
                    attrs: {'data-action': 'saveNoteTemplate'}
                });
        },

        renderNotesPanel: function (title, field, value, editable) {
            var textarea = '<textarea class="form-control" rows="5" data-reception-field="' + field + '"' +
                (editable ? '' : ' disabled') +
                ' style="resize:vertical;min-height:110px">' +
                SimpleStomUi.escapeHtml(value || '') +
                '</textarea>';

            return SimpleStomUi.panel({
                title: title,
                body: textarea,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderChecklistPanel: function (checklist, counts) {
            var body = '<ul class="espo-dental-stom-list">';
            checklist.forEach(function (item) {
                body += '<li class="espo-dental-stom-list__item">' +
                    '<span>' + SimpleStomUi.escapeHtml(item.label) + '</span>' +
                    SimpleStomUi.badge(item.done ? 'Done' : 'Open', item.done ? 'success' : 'warning') +
                    '</li>';
            });
            body += '</ul>';

            body += '<table class="espo-dental-stom-table" style="margin-top:10px"><tbody>' +
                '<tr><th>Services</th><td>' + (counts.services || 0) + '</td></tr>' +
                '<tr><th>Materials</th><td>' + (counts.materials || 0) + '</td></tr>' +
                '<tr><th>Photos</th><td>' + (counts.photos || 0) + '</td></tr>' +
                '<tr><th>Invoices</th><td>' + (counts.invoices || 0) + '</td></tr>' +
                '</tbody></table>';

            return SimpleStomUi.panel({
                title: 'Completion checklist',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        bindReceptionWorkspaceEvents: function () {
            var $host = this.ensureReceptionWorkspaceHost();
            $host.off('.receptionWorkspace');
            $host.on('input.receptionWorkspace focus.receptionWorkspace', '[data-reception-field]', (function (e) {
                this.activeReceptionField = $(e.currentTarget).attr('data-reception-field');
                this.scheduleReceptionAutosave();
            }).bind(this));
            $host.on('change.receptionWorkspace', '[data-name="visitNoteTemplate"]', this.applyNoteTemplate.bind(this));
            $host.on('click.receptionWorkspace', '[data-action="saveNoteTemplate"]', this.saveNoteTemplate.bind(this));
        },

        scheduleReceptionAutosave: function () {
            clearTimeout(this.receptionAutosaveTimeout);
            this.receptionAutosaveTimeout = setTimeout(this.autosaveReceptionNotes.bind(this), 500);
        },

        autosaveReceptionNotes: function () {
            var payload = {id: this.model.id};
            this.ensureReceptionWorkspaceHost().find('[data-reception-field]').each(function () {
                var $field = $(this);
                if (!$field.prop('disabled')) {
                    payload[$field.attr('data-reception-field')] = $field.val();
                }
            });

            Espo.Ajax.postRequest('EspoDental/Visit/autosaveReception', payload);
        },

        applyNoteTemplate: function (e) {
            var option = e.currentTarget.options[e.currentTarget.selectedIndex];
            if (!option || !option.value) {
                return;
            }

            var section = $(option).attr('data-section');
            var body = $(option).attr('data-body') || '';
            var $target = this.ensureReceptionWorkspaceHost().find('[data-reception-field="' + section + '"]');
            if ($target.length && !$target.prop('disabled')) {
                $target.val(body);
                this.activeReceptionField = section;
                this.autosaveReceptionNotes();
            }
        },

        saveNoteTemplate: function () {
            var section = this.activeReceptionField || 'performed';
            var $field = this.ensureReceptionWorkspaceHost().find('[data-reception-field="' + section + '"]');
            var body = $field.val() || '';

            if (!body) {
                this.notify('Template body is empty.', 'warning');
                return;
            }

            this.promptTemplateName((function (name) {
                if (!name) {
                    return;
                }

                Espo.Ajax.postRequest('EspoDental/Visit/noteTemplate', {
                    name: name,
                    section: section,
                    body: body
                }).then(this.renderReceptionWorkspace.bind(this));
            }).bind(this));
        },

        promptTemplateName: function (callback) {
            var modalId = 'espoDentalVisitTemplateName';
            $('#' + modalId).remove();

            var $modal = $('<div class="modal fade" tabindex="-1" role="dialog" id="' + modalId + '">' +
                '<div class="modal-dialog" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                            '<h4 class="modal-title">Template name</h4>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<input type="text" class="form-control" data-name="templateName" maxlength="200">' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>' +
                            '<button type="button" class="btn btn-primary" data-action="applyTemplateName">Save</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>');

            $('body').append($modal);
            $modal.on('click', '[data-action="applyTemplateName"]', function () {
                var name = String($modal.find('[data-name="templateName"]').val() || '').trim();
                $modal.modal('hide');
                callback(name);
            });
            $modal.on('shown.bs.modal', function () {
                $modal.find('[data-name="templateName"]').trigger('focus');
            });
            $modal.on('hidden.bs.modal', function () {
                $modal.remove();
            });
            $modal.modal('show');
        },

        ensureReceptionWorkspaceHost: function () {
            var $existing = this.$el.find('[data-name="visit-reception-workspace"]');
            if ($existing.length) {
                return $existing;
            }

            var $panel = $('<div class="panel panel-default" data-name="visit-reception-workspace">' +
                '<div class="panel-heading">' +
                    '<span class="panel-title">Doctor reception workspace</span>' +
                '</div>' +
                '<div class="panel-body" data-name="reception-workspace-body"></div>' +
            '</div>');

            var $firstPanel = this.$el.find('.panel').first();
            if ($firstPanel.length) {
                $panel.insertBefore($firstPanel);
            } else {
                this.$el.prepend($panel);
            }

            return $panel;
        },

        renderToothChartPreview: function () {
            if (!this.model.id || !this.$el) {
                return;
            }

            var $host = this.ensureToothChartHost();
            if (!$host.length) {
                return;
            }

            var self = this;
            var $chart = $host.find('[data-name="tooth-chart-preview"]');
            $chart.html('<span class="text-muted">' +
                this.translate('Loading...', 'messages', 'Global') +
                '</span>');

            Espo.Ajax.getRequest('Visit/action/toothChart', {id: this.model.id})
                .then(function (data) {
                    if (!data || !data.id) {
                        $chart.html('<span class="text-muted">' +
                            self.translate('No Data', 'labels', 'Global') +
                            '</span>');
                        return;
                    }

                    $host.find('[data-action="editToothChart"]')
                        .toggle(self.model.get('status') === 'in_progress')
                        .attr('href', '#ToothChartSnapshot/edit/' + data.id);

                    Renderer.render($chart.get(0), {
                        dentition: data.dentitionType || 'adult',
                        teeth: data.teeth || {},
                        readOnly: true,
                        conditions: self.getDentalSetting('espoDentalToothChartConditions'),
                        surfaces: self.getDentalSetting('espoDentalToothChartSurfaces'),
                        language: self.getDentalSetting('language'),
                        translate: function (key, cat) {
                            return self.translate(key, cat || 'options', 'ToothChartSnapshot');
                        }
                    });
                });
        },

        getDentalSetting: function (name) {
            var config = typeof this.getConfig === 'function' ? this.getConfig() : null;
            if (!config || typeof config.get !== 'function') {
                return null;
            }

            return config.get(name);
        },

        ensureToothChartHost: function () {
            var $existing = this.$el.find('[data-name="visit-tooth-chart-panel"]');
            if ($existing.length) {
                return $existing;
            }

            var $panel = $('<div class="panel panel-default" data-name="visit-tooth-chart-panel">' +
                '<div class="panel-heading clearfix">' +
                    '<span class="panel-title">' +
                        this.translate('Tooth Chart', 'labels', 'Visit') +
                    '</span>' +
                    '<a class="btn btn-default btn-xs pull-right" ' +
                        'data-action="editToothChart" style="display:none">' +
                        this.translate('Edit', 'labels', 'Global') +
                    '</a>' +
                '</div>' +
                '<div class="panel-body">' +
                    '<div data-name="tooth-chart-preview"></div>' +
                '</div>' +
            '</div>');

            var $firstPanel = this.$el.find('.panel').first();
            if ($firstPanel.length) {
                $panel.insertAfter($firstPanel);
            } else {
                this.$el.append($panel);
            }

            return $panel;
        }
    });
});
