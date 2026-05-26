define('espo-dental:views/dashlets/inventory-workspace', [
    'views/dashlets/abstract/base',
    'espo-dental:lib/simple-stom-ui'
], function (Dep, SimpleStomUi) {
    return Dep.extend({
        name: 'InventoryWorkspace',
        templateContent: '<div class="espo-dental-inventory-workspace"></div>',

        events: {
            'change [data-name="warehouseId"]': 'changeWarehouse'
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.warehouseId = '';
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

            html += '</select></label></div>';

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
