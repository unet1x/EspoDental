define('espo-dental:views/dashlets/inventory-workspace', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, SimpleStomUi) {
    return Dep.extend({
        name: 'InventoryWorkspace',
        templateContent: '<div class="espo-dental-inventory-workspace"></div>',

        events: {
            'change [data-name="warehouseId"]': 'changeWarehouse',
            'click [data-action="inventoryReceipt"]': 'openReceiptDialog',
            'click [data-action="inventoryTransfer"]': 'openTransferDialog',
            'click [data-action="inventoryWriteOff"]': 'openWriteOffDialog',
            'click [data-action="inventoryAdjustment"]': 'openAdjustmentDialog'
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.warehouseId = '';
            this.workspaceData = {};
        },

        afterRender: function () {
            SimpleStomUi.ensureStyles();
            this.renderShell();
            this.fetchWorkspace();
        },

        renderShell: function () {
            var html = '<div data-name="inventoryWorkspaceBody"></div>';

            this.$el.find('.espo-dental-inventory-workspace').html(SimpleStomUi.workspace(html));
        },

        fetchWorkspace: function () {
            this.$el.find('[data-name="inventoryWorkspaceBody"]')
                .html(SimpleStomUi.emptyState('Загрузка склада...'));

            Espo.Ajax.getRequest('EspoDental/Inventory/workspace', {
                warehouseId: this.warehouseId,
                limit: parseInt(this.getOption('displayRecords'), 10) || 20
            }).then((function (data) {
                this.renderWorkspace(data || {});
            }).bind(this)).catch((function () {
                this.$el.find('[data-name="inventoryWorkspaceBody"]')
                    .html(SimpleStomUi.emptyState('Не удалось загрузить склад.'));
            }).bind(this));
        },

        renderWorkspace: function (data) {
            this.workspaceData = data || {};
            this.warehouseId = data.selectedWarehouseId || this.warehouseId || '';

            var html = this.renderToolbar(data.warehouses || []);
            html += this.renderSummary(data.summary || {});
            html += '<div class="espo-dental-stom-layout espo-dental-stom-layout--two">' +
                '<div>' +
                this.renderWarehouses(data.warehouses || []) +
                this.renderLinkedRows('Кандидаты к заказу', data.futureOrderCandidates || [], 'Материалов к заказу нет.', 'material') +
                '</div>' +
                '<div>' +
                this.renderLinkedRows('Партии выбранного склада', data.stockLots || [], 'Активных партий нет.', 'lot') +
                this.renderLinkedRows('Сроки годности', data.expiringLots || [], 'Ближайших сроков нет.', 'lot') +
                this.renderLinkedRows('Низкий остаток', data.lowStockRows || [], 'Низких остатков нет.', 'material') +
                this.renderLinkedRows('Выдача в кабинеты', data.cabinetIssueRows || [], 'Выдач в кабинеты нет.', 'movement') +
                this.renderLinkedRows('Последние движения', data.recentMovements || [], 'Движений нет.', 'movement') +
                '</div>' +
                '</div>';

            this.$el.find('[data-name="inventoryWorkspaceBody"]').html(html);
        },

        renderToolbar: function (warehouses) {
            var hasWarehouse = warehouses.length > 0;
            var html = '<div class="espo-dental-stom-toolbar">' +
                '<label style="display:flex;align-items:center;gap:6px;margin:0">' +
                '<span class="espo-dental-stom-muted">Склад</span>' +
                '<select class="form-control input-sm" data-name="warehouseId" style="max-width:260px">';

            warehouses.forEach((function (warehouse) {
                var selected = warehouse.id === this.warehouseId ? ' selected' : '';
                html += '<option value="' + SimpleStomUi.escapeHtml(warehouse.id || '') + '"' + selected + '>' +
                    SimpleStomUi.escapeHtml(warehouse.name || warehouse.id || '') +
                    '</option>';
            }).bind(this));

            html += '</select></label>' +
                '<span class="espo-dental-stom-toolbar__spacer"></span>' +
                SimpleStomUi.button('Поступление', {
                    tone: 'primary',
                    attrs: {'data-action': 'inventoryReceipt', disabled: hasWarehouse ? null : 'disabled'}
                }) +
                SimpleStomUi.button('Переместить', {
                    attrs: {'data-action': 'inventoryTransfer', disabled: hasWarehouse ? null : 'disabled'}
                }) +
                SimpleStomUi.button('Списать', {
                    tone: 'danger',
                    attrs: {'data-action': 'inventoryWriteOff', disabled: hasWarehouse ? null : 'disabled'}
                }) +
                SimpleStomUi.button('Корректировка', {
                    tone: 'quiet',
                    attrs: {'data-action': 'inventoryAdjustment', disabled: hasWarehouse ? null : 'disabled'}
                }) +
                '</div>';

            return html;
        },

        renderSummary: function (summary) {
            var rows = [
                ['Складов', summary.warehouseCount || 0],
                ['Кабинетных', summary.cabinetWarehouseCount || 0],
                ['Низкий остаток', summary.lowStockCount || 0],
                ['Сроки', summary.expiringLotCount || 0],
                ['К заказу', summary.futureOrderCount || 0]
            ];
            var html = '<div class="espo-dental-stom-layout" style="grid-template-columns:repeat(auto-fit,minmax(110px,1fr));margin-bottom:10px">';

            rows.forEach(function (row) {
                html += SimpleStomUi.kpi(row[1], row[0]);
            });

            return html + '</div>';
        },

        renderWarehouses: function (warehouses) {
            var body = SimpleStomUi.emptyState('Склады не найдены.');

            if (warehouses.length) {
                body = '<ul class="espo-dental-stom-list">';
                warehouses.forEach((function (warehouse) {
                    var meta = [];
                    if (warehouse.cabinetName) {
                        meta.push(warehouse.cabinetName);
                    }
                    if (warehouse.responsibleUserName) {
                        meta.push(warehouse.responsibleUserName);
                    }
                    if (warehouse.nextInventoryDueAt) {
                        meta.push('инв. ' + warehouse.nextInventoryDueAt);
                    }

                    body += '<li class="espo-dental-stom-list__item">' +
                        '<span>' +
                        '<a href="#InventoryWarehouse/view/' + SimpleStomUi.escapeHtml(warehouse.id || '') + '">' +
                        SimpleStomUi.escapeHtml(warehouse.name || warehouse.id || '') +
                        '</a>' +
                        (meta.length ? '<span class="espo-dental-stom-muted"> · ' +
                            SimpleStomUi.escapeHtml(meta.join(' · ')) +
                            '</span>' : '') +
                        '</span>' +
                        '<span class="espo-dental-stom-toolbar" style="margin:0;justify-content:flex-end">' +
                        SimpleStomUi.badge(warehouse.warehouseType || 'main', warehouse.warehouseType || 'main') +
                        SimpleStomUi.badge((warehouse.lotCount || 0) + ' партий', 'muted') +
                        '</span>' +
                        '</li>';
                }).bind(this));
                body += '</ul>';
            }

            return SimpleStomUi.panel({
                title: 'Склады',
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderLinkedRows: function (title, rows, emptyMessage, kind) {
            var body = SimpleStomUi.emptyState(emptyMessage);

            if (rows.length) {
                body = '<table class="espo-dental-stom-table"><tbody>';
                rows.forEach((function (row) {
                    body += this.renderLinkedRow(row, kind);
                }).bind(this));
                body += '</tbody></table>';
            }

            return SimpleStomUi.panel({
                title: title,
                body: body,
                classes: ['espo-dental-stom-panel--compact']
            });
        },

        renderLinkedRow: function (row, kind) {
            var href = this.getRowHref(row, kind);
            var label = this.getRowLabel(row, kind);
            var meta = this.getRowMeta(row, kind);
            var badge = this.getRowBadge(row, kind);

            return '<tr>' +
                '<td><a href="' + href + '">' + SimpleStomUi.escapeHtml(label) + '</a>' +
                (meta ? '<div class="espo-dental-stom-muted">' + SimpleStomUi.escapeHtml(meta) + '</div>' : '') +
                '</td>' +
                '<td style="text-align:right">' + badge + '</td>' +
                '</tr>';
        },

        getRowHref: function (row, kind) {
            if (kind === 'lot') {
                return '#InventoryStockLot/view/' + encodeURIComponent(row.id || '');
            }
            if (kind === 'movement') {
                return '#StockMovement/view/' + encodeURIComponent(row.id || '');
            }

            return '#Material/view/' + encodeURIComponent(row.materialId || '');
        },

        getRowLabel: function (row, kind) {
            if (kind === 'lot') {
                return (row.materialName || row.materialId || '') + ' · ' + (row.lotNumber || row.id || '');
            }
            if (kind === 'movement') {
                return (row.materialName || row.materialId || '') + ' · ' + (row.sourceWarehouseName || '') +
                    (row.targetWarehouseName ? ' -> ' + row.targetWarehouseName : '');
            }

            return row.materialName || row.materialId || '';
        },

        getRowMeta: function (row, kind) {
            if (kind === 'lot') {
                return this.formatQuantity(row.quantityInPurchasingUnits) + ' ' + (row.purchasingUnit || '') +
                    (row.expiresAt ? ' · до ' + row.expiresAt : '') +
                    (row.warehouseName ? ' · ' + row.warehouseName : '');
            }
            if (kind === 'movement') {
                return String(row.performedAt || '').slice(0, 16) + ' · ' +
                    this.formatQuantity(row.quantity) + ' ' + (row.unit || '') +
                    (row.reason ? ' · ' + row.reason : '');
            }

            return this.formatQuantity(row.currentStock) + ' ' + (row.unit || '') +
                (row.suggestedQuantity ? ' · заказать ' + this.formatQuantity(row.suggestedQuantity) : '') +
                (row.openAlertCount ? ' · алертов ' + row.openAlertCount : '');
        },

        getRowBadge: function (row, kind) {
            if (kind === 'lot') {
                return SimpleStomUi.badge(row.expiryStatus || 'expiring', row.expiryStatus || 'expiring');
            }
            if (kind === 'movement') {
                return SimpleStomUi.badge(row.type || row.direction || '', row.type || row.direction || 'muted');
            }

            return SimpleStomUi.badge(row.stockLevel || 'normal', row.stockLevel || 'normal');
        },

        openReceiptDialog: function () {
            if (!this.ensureWarehouse()) {
                return;
            }

            this.openInventoryDialog('receipt');
        },

        openTransferDialog: function () {
            if (!this.ensureWarehouse() || !this.ensureLots()) {
                return;
            }

            this.openInventoryDialog('transfer');
        },

        openWriteOffDialog: function () {
            if (!this.ensureWarehouse() || !this.ensureLots()) {
                return;
            }

            this.openInventoryDialog('writeOff');
        },

        openAdjustmentDialog: function () {
            if (!this.ensureWarehouse()) {
                return;
            }

            this.openInventoryDialog('adjustment');
        },

        openInventoryDialog: function (mode) {
            var config = this.getDialogConfig(mode);
            var html =
                '<div class="espo-dental-inventory-dialog-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,0.32);z-index:1050"></div>' +
                '<div class="espo-dental-inventory-dialog" style="position:fixed;top:56px;left:50%;transform:translateX(-50%);width:min(520px,calc(100vw - 24px));max-height:calc(100vh - 88px);overflow:auto;background:#fff;border:1px solid #cfd6df;border-radius:6px;box-shadow:0 14px 40px rgba(0,0,0,0.28);z-index:1060;padding:16px">' +
                    '<h4 style="margin:0 0 12px">' + SimpleStomUi.escapeHtml(config.title) + '</h4>' +
                    '<div class="espo-dental-stom-muted" style="margin-bottom:12px">' +
                        SimpleStomUi.escapeHtml(this.getSelectedWarehouseLabel()) +
                    '</div>' +
                    config.body +
                    '<div style="text-align:right;margin-top:16px">' +
                        '<button class="btn btn-default" data-action="cancel">Отмена</button> ' +
                        '<button class="btn btn-primary" data-action="save">' + SimpleStomUi.escapeHtml(config.submitLabel) + '</button>' +
                    '</div>' +
                '</div>';

            var $dialog = window.jQuery(html);
            window.jQuery(document.body).append($dialog);

            var close = function () {
                $dialog.remove();
            };

            $dialog.find('[data-action="cancel"]').on('click', close);
            $dialog.find('[name="adjustmentType"]').on('change', (function () {
                this.toggleAdjustmentFields($dialog);
            }).bind(this));
            this.toggleAdjustmentFields($dialog);

            $dialog.find('[data-action="save"]').on('click', (function () {
                var payload = this.collectInventoryPayload(mode, $dialog);
                if (!payload) {
                    return;
                }

                close();
                this.postInventoryAction(config.endpoint, payload, config.successMessage);
            }).bind(this));
        },

        getDialogConfig: function (mode) {
            if (mode === 'receipt') {
                return {
                    title: 'Поступление на склад',
                    submitLabel: 'Оприходовать',
                    endpoint: 'EspoDental/Inventory/receipt',
                    successMessage: 'Поступление создано.',
                    body: this.renderReceiptFields()
                };
            }
            if (mode === 'transfer') {
                return {
                    title: 'Перемещение между складами',
                    submitLabel: 'Переместить',
                    endpoint: 'EspoDental/Inventory/transfer',
                    successMessage: 'Перемещение создано.',
                    body: this.renderTransferFields()
                };
            }
            if (mode === 'writeOff') {
                return {
                    title: 'Списание партии',
                    submitLabel: 'Списать',
                    endpoint: 'EspoDental/Inventory/writeOff',
                    successMessage: 'Списание создано.',
                    body: this.renderWriteOffFields()
                };
            }

            return {
                title: 'Корректировка остатка',
                submitLabel: 'Создать корректировку',
                endpoint: 'EspoDental/Inventory/adjustment',
                successMessage: 'Корректировка создана.',
                body: this.renderAdjustmentFields()
            };
        },

        renderReceiptFields: function () {
            return this.renderField('Материал', this.renderMaterialSelect('materialId')) +
                this.renderField('Количество в ед. закупки', '<input class="form-control" name="quantity" type="number" min="0.0001" step="0.001">') +
                this.renderField('Цена за ед.', '<input class="form-control" name="unitPrice" type="number" min="0" step="0.01">') +
                this.renderField('Номер партии', '<input class="form-control" name="lotNumber" type="text">') +
                this.renderField('Срок годности', '<input class="form-control" name="expiresAt" type="date">') +
                this.renderField('Дата прихода', '<input class="form-control" name="receivedAt" type="date">') +
                this.renderField('Комментарий', '<textarea class="form-control" name="reason" rows="2">Поступление на склад</textarea>');
        },

        renderTransferFields: function () {
            return this.renderField('Партия', this.renderLotSelect('stockLotId')) +
                this.renderField('Склад-получатель', this.renderWarehouseSelect('targetWarehouseId', true)) +
                this.renderField('Количество в ед. закупки', '<input class="form-control" name="quantity" type="number" min="0.0001" step="0.001">') +
                this.renderField('Комментарий', '<textarea class="form-control" name="reason" rows="2">Перемещение между складами</textarea>');
        },

        renderWriteOffFields: function () {
            return this.renderField('Партия', this.renderLotSelect('stockLotId')) +
                this.renderField('Количество в ед. закупки', '<input class="form-control" name="quantity" type="number" min="0.0001" step="0.001">') +
                this.renderField('Причина', '<textarea class="form-control" name="reason" rows="2"></textarea>');
        },

        renderAdjustmentFields: function () {
            return this.renderField('Тип корректировки',
                '<select class="form-control" name="adjustmentType">' +
                    '<option value="set">Инвентаризация партии</option>' +
                    '<option value="increase">Добавить остаток</option>' +
                    '<option value="decrease">Уменьшить остаток</option>' +
                '</select>') +
                '<div data-adjustment-section="set">' +
                    this.renderField('Партия', this.renderLotSelect('stockLotId')) +
                    this.renderField('Фактическое количество', '<input class="form-control" name="countedQuantity" type="number" min="0" step="0.001">') +
                '</div>' +
                '<div data-adjustment-section="increase">' +
                    this.renderField('Материал', this.renderMaterialSelect('materialId')) +
                    this.renderField('Количество в ед. закупки', '<input class="form-control" name="quantityIncrease" type="number" min="0.0001" step="0.001">') +
                    this.renderField('Цена за ед.', '<input class="form-control" name="unitPrice" type="number" min="0" step="0.01">') +
                    this.renderField('Номер партии', '<input class="form-control" name="lotNumber" type="text">') +
                    this.renderField('Срок годности', '<input class="form-control" name="expiresAt" type="date">') +
                '</div>' +
                '<div data-adjustment-section="decrease">' +
                    this.renderField('Партия', this.renderLotSelect('stockLotIdDecrease')) +
                    this.renderField('Количество в ед. закупки', '<input class="form-control" name="quantityDecrease" type="number" min="0.0001" step="0.001">') +
                '</div>' +
                this.renderField('Причина', '<textarea class="form-control" name="reason" rows="2"></textarea>');
        },

        renderField: function (label, control) {
            return '<div class="form-group">' +
                '<label>' + SimpleStomUi.escapeHtml(label) + '</label>' +
                control +
                '</div>';
        },

        renderMaterialSelect: function (name) {
            var materials = this.getMaterials();
            var html = '<select class="form-control" name="' + SimpleStomUi.escapeHtml(name) + '">';

            materials.forEach(function (material) {
                var meta = material.code ? ' · ' + material.code : '';
                var unit = material.purchasingUnit ? ' · ' + material.purchasingUnit : '';
                html += '<option value="' + SimpleStomUi.escapeHtml(material.id || '') + '">' +
                    SimpleStomUi.escapeHtml((material.name || material.id || '') + meta + unit) +
                    '</option>';
            });

            return html + '</select>';
        },

        renderLotSelect: function (name) {
            var html = '<select class="form-control" name="' + SimpleStomUi.escapeHtml(name) + '">';

            this.getLots().forEach((function (lot) {
                html += '<option value="' + SimpleStomUi.escapeHtml(lot.id || '') + '">' +
                    SimpleStomUi.escapeHtml(this.getLotLabel(lot)) +
                    '</option>';
            }).bind(this));

            return html + '</select>';
        },

        renderWarehouseSelect: function (name, excludeSelected) {
            var html = '<select class="form-control" name="' + SimpleStomUi.escapeHtml(name) + '">';

            (this.workspaceData.warehouses || []).forEach((function (warehouse) {
                if (excludeSelected && warehouse.id === this.warehouseId) {
                    return;
                }
                html += '<option value="' + SimpleStomUi.escapeHtml(warehouse.id || '') + '">' +
                    SimpleStomUi.escapeHtml(warehouse.name || warehouse.id || '') +
                    '</option>';
            }).bind(this));

            return html + '</select>';
        },

        collectInventoryPayload: function (mode, $dialog) {
            var payload = {warehouseId: this.warehouseId};

            if (mode === 'receipt') {
                payload.materialId = $dialog.find('[name="materialId"]').val() || '';
                payload.quantity = $dialog.find('[name="quantity"]').val();
                payload.unitPrice = $dialog.find('[name="unitPrice"]').val();
                payload.lotNumber = $dialog.find('[name="lotNumber"]').val() || '';
                payload.expiresAt = $dialog.find('[name="expiresAt"]').val() || '';
                payload.receivedAt = $dialog.find('[name="receivedAt"]').val() || '';
                payload.reason = $dialog.find('[name="reason"]').val() || '';
            } else if (mode === 'transfer') {
                payload.stockLotId = $dialog.find('[name="stockLotId"]').val() || '';
                payload.targetWarehouseId = $dialog.find('[name="targetWarehouseId"]').val() || '';
                payload.quantity = $dialog.find('[name="quantity"]').val();
                payload.reason = $dialog.find('[name="reason"]').val() || '';
            } else if (mode === 'writeOff') {
                payload.stockLotId = $dialog.find('[name="stockLotId"]').val() || '';
                payload.quantity = $dialog.find('[name="quantity"]').val();
                payload.reason = $dialog.find('[name="reason"]').val() || '';
            } else {
                payload = this.collectAdjustmentPayload($dialog, payload);
            }

            if (!this.hasRequiredPayload(payload, mode)) {
                this.notify('Заполните обязательные поля складской операции.', 'warning');
                return null;
            }

            return payload;
        },

        collectAdjustmentPayload: function ($dialog, payload) {
            var type = $dialog.find('[name="adjustmentType"]').val() || 'set';

            payload.adjustmentType = type;
            payload.reason = $dialog.find('[name="reason"]').val() || '';

            if (type === 'increase') {
                payload.materialId = $dialog.find('[name="materialId"]').val() || '';
                payload.quantity = $dialog.find('[name="quantityIncrease"]').val();
                payload.unitPrice = $dialog.find('[name="unitPrice"]').val();
                payload.lotNumber = $dialog.find('[name="lotNumber"]').val() || '';
                payload.expiresAt = $dialog.find('[name="expiresAt"]').val() || '';
            } else if (type === 'decrease') {
                payload.stockLotId = $dialog.find('[name="stockLotIdDecrease"]').val() || '';
                payload.quantity = $dialog.find('[name="quantityDecrease"]').val();
            } else {
                payload.stockLotId = $dialog.find('[name="stockLotId"]').val() || '';
                payload.countedQuantity = $dialog.find('[name="countedQuantity"]').val();
            }

            return payload;
        },

        hasRequiredPayload: function (payload, mode) {
            if (mode === 'receipt') {
                return !!(payload.warehouseId && payload.materialId && parseFloat(payload.quantity) > 0);
            }
            if (mode === 'transfer') {
                return !!(payload.stockLotId && payload.targetWarehouseId && parseFloat(payload.quantity) > 0);
            }
            if (mode === 'writeOff') {
                return !!(payload.stockLotId && parseFloat(payload.quantity) > 0 && payload.reason);
            }
            if (payload.adjustmentType === 'increase') {
                return !!(payload.warehouseId && payload.materialId && parseFloat(payload.quantity) > 0 && payload.reason);
            }
            if (payload.adjustmentType === 'decrease') {
                return !!(payload.stockLotId && parseFloat(payload.quantity) > 0 && payload.reason);
            }

            return !!(payload.stockLotId && parseFloat(payload.countedQuantity) >= 0 && payload.reason);
        },

        postInventoryAction: function (endpoint, payload, successMessage) {
            Espo.Ajax.postRequest(endpoint, payload)
                .then((function (data) {
                    this.notify(successMessage, 'success');
                    if (data && data.workspace) {
                        this.renderWorkspace(data.workspace);
                        return;
                    }
                    this.fetchWorkspace();
                }).bind(this))
                .catch((function (xhr) {
                    this.notify(this.getErrorMessage(xhr), 'error');
                }).bind(this));
        },

        getErrorMessage: function (xhr) {
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }

            return 'Не удалось выполнить складскую операцию.';
        },

        toggleAdjustmentFields: function ($dialog) {
            var type = $dialog.find('[name="adjustmentType"]').val() || 'set';

            $dialog.find('[data-adjustment-section]').hide();
            $dialog.find('[data-adjustment-section="' + type + '"]').show();
        },

        getMaterials: function () {
            return ((this.workspaceData.actionOptions || {}).materials || []);
        },

        getLots: function () {
            return this.workspaceData.stockLots || [];
        },

        getLotLabel: function (lot) {
            return (lot.materialName || lot.materialId || '') +
                ' · ' + (lot.lotNumber || lot.id || '') +
                ' · ' + this.formatQuantity(lot.quantityInPurchasingUnits) +
                ' ' + (lot.purchasingUnit || '');
        },

        getSelectedWarehouseLabel: function () {
            var label = 'Склад не выбран';

            (this.workspaceData.warehouses || []).forEach((function (warehouse) {
                if (warehouse.id === this.warehouseId) {
                    label = warehouse.name || warehouse.id || label;
                }
            }).bind(this));

            return label;
        },

        ensureWarehouse: function () {
            if (!this.warehouseId) {
                this.notify('Выберите склад.', 'warning');
                return false;
            }

            return true;
        },

        ensureLots: function () {
            if (!this.getLots().length) {
                this.notify('В выбранном складе нет активных партий.', 'warning');
                return false;
            }

            return true;
        },

        formatQuantity: function (value) {
            var number = parseFloat(value || 0);

            return number.toLocaleString(undefined, {maximumFractionDigits: 3});
        },

        changeWarehouse: function (e) {
            this.warehouseId = $(e.currentTarget).val() || '';
            this.fetchWorkspace();
        }
    });
});
