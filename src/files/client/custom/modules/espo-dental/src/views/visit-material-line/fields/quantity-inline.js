define('espo-dental:views/visit-material-line/fields/quantity-inline', ['views/fields/float'], function (Dep) {

    return Dep.extend({

        listTemplateContent: '{{{value}}}',

        setup: function () {
            Dep.prototype.setup.call(this);

            var parent = this.getParentVisitModel();
            if (parent) {
                this.listenTo(parent, 'change:status sync', this.reRender);
            }
        },

        getValueForDisplay: function () {
            if (!this.isListMode() || !this.canInlineEditQuantity()) {
                return this.escapeHtml(Dep.prototype.getValueForDisplay.call(this));
            }

            return this.renderInlineEditor();
        },

        afterRenderList: function () {
            Dep.prototype.afterRenderList.call(this);

            if (!this.canInlineEditQuantity()) {
                return;
            }

            this.bindInlineEditor();
            this.updateInlineControls();
        },

        canInlineEditQuantity: function () {
            if (this.readOnly || this.disabled || !this.model || !this.model.id) {
                return false;
            }

            var parent = this.getParentVisitModel();
            if (!parent || parent.get('status') !== 'in_progress') {
                return false;
            }

            return this.getAcl().checkModel(this.model, 'edit');
        },

        getParentVisitModel: function () {
            var collection = this.model ? this.model.collection : null;
            var parent = collection ? collection.parentModel : null;
            var parentType = parent ? (parent.entityType || parent.name) : null;

            return parentType === 'Visit' ? parent : null;
        },

        renderInlineEditor: function () {
            var label = this.escapeAttribute(this.getLabelText());
            var value = this.escapeAttribute(this.formatInlineValue(this.model.get(this.name)));
            var update = this.escapeAttribute(this.translate('Update'));
            var cancel = this.escapeAttribute(this.translate('Cancel'));

            return '<div class="espo-dental-quantity-inline" data-name="quantityInline" ' +
                'style="display:flex;align-items:center;gap:4px;min-width:132px">' +
                    '<input type="text" class="form-control input-sm" ' +
                        'data-name="quantityInlineInput" aria-label="' + label + '" ' +
                        'value="' + value + '" style="width:76px;min-width:64px">' +
                    '<button type="button" class="btn btn-default btn-xs" ' +
                        'data-action="saveQuantityInline" title="' + update + '" disabled>' +
                        '<span class="fas fa-check"></span>' +
                    '</button>' +
                    '<button type="button" class="btn btn-link btn-xs" ' +
                        'data-action="resetQuantityInline" title="' + cancel + '" style="display:none">' +
                        '<span class="fas fa-arrow-right-to-bracket"></span>' +
                    '</button>' +
                '</div>';
        },

        bindInlineEditor: function () {
            var $input = this.$el.find('[data-name="quantityInlineInput"]');

            $input.off('.espoDentalQuantityInline');
            this.$el.find('[data-action="saveQuantityInline"]').off('.espoDentalQuantityInline');
            this.$el.find('[data-action="resetQuantityInline"]').off('.espoDentalQuantityInline');

            $input.on('input.espoDentalQuantityInline', function () {
                this.updateInlineControls();
            }.bind(this));

            $input.on('keydown.espoDentalQuantityInline', function (e) {
                var key = Espo.Utils.getKeyFromKeyEvent(e);

                if (key === 'Enter' || key === 'Control+Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.saveQuantityInline();
                    return;
                }

                if (key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.resetQuantityInline();
                }
            }.bind(this));

            this.$el.find('[data-action="saveQuantityInline"]')
                .on('click.espoDentalQuantityInline', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.saveQuantityInline();
                }.bind(this));

            this.$el.find('[data-action="resetQuantityInline"]')
                .on('click.espoDentalQuantityInline', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.resetQuantityInline();
                }.bind(this));
        },

        updateInlineControls: function () {
            var changed = this.hasInlineQuantityChanged();
            var saving = this.inlineQuantitySaving === true;

            this.$el.find('[data-action="saveQuantityInline"]')
                .prop('disabled', saving || !changed)
                .toggleClass('disabled', saving || !changed);

            this.$el.find('[data-action="resetQuantityInline"]').toggle(changed && !saving);
            this.$el.find('[data-name="quantityInlineInput"]').prop('disabled', saving);
        },

        hasInlineQuantityChanged: function () {
            var value = this.readInlineQuantity();
            var current = this.model.get(this.name);

            if (value === null || isNaN(value)) {
                return true;
            }

            current = current === null || current === undefined ? 0 : parseFloat(current);

            return Math.abs(value - current) > 0.000001;
        },

        saveQuantityInline: function () {
            if (this.inlineQuantitySaving === true) {
                return;
            }

            var value = this.readInlineQuantity();
            if (!this.isInlineQuantityValid(value)) {
                Espo.Ui.warning(this.translate('Not valid'));
                return;
            }

            if (!this.hasInlineQuantityChanged()) {
                this.updateInlineControls();
                return;
            }

            this.inlineQuantitySaving = true;
            this.updateInlineControls();
            Espo.Ui.notify(this.translate('saving', 'messages'));

            var attrs = {};
            attrs[this.name] = value;

            this.model.save(attrs, {patch: true, wait: true})
                .then(function () {
                    this.inlineQuantitySaving = false;
                    this.syncInlineInput();
                    this.updateInlineControls();
                    this.trigger('after:save');
                    Espo.Ui.success(this.translate('Saved'));
                }.bind(this))
                .catch(function () {
                    this.inlineQuantitySaving = false;
                    this.syncInlineInput();
                    this.updateInlineControls();
                    Espo.Ui.error(this.translate('Error occurred'));
                }.bind(this));
        },

        resetQuantityInline: function () {
            this.syncInlineInput();
            this.updateInlineControls();
        },

        syncInlineInput: function () {
            this.$el.find('[data-name="quantityInlineInput"]')
                .val(this.formatInlineValue(this.model.get(this.name)));
        },

        readInlineQuantity: function () {
            var raw = this.$el.find('[data-name="quantityInlineInput"]').val();

            return this.parse(String(raw == null ? '' : raw));
        },

        isInlineQuantityValid: function (value) {
            if (value === null || isNaN(value)) {
                return false;
            }

            var min = this.getMinValue();
            var max = this.getMaxValue();

            if (min !== null && value < min) {
                return false;
            }

            return !(max !== null && value > max);
        },

        formatInlineValue: function (value) {
            if (value === null || value === undefined || value === '') {
                return '';
            }

            var number = parseFloat(value);

            if (isNaN(number)) {
                return '';
            }

            return this.formatNumberDetail(number);
        },

        escapeHtml: function (value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        escapeAttribute: function (value) {
            return this.escapeHtml(value);
        }
    });
});
