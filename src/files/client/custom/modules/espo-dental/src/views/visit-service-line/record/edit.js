define('espo-dental:views/visit-service-line/record/edit', ['views/record/edit'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.serviceCache = {};
            this.catalogLoaded = false;
            this.catalogCategories = [];
            this.catalogServices = [];
            this.catalogServicesById = {};
            this.catalogExpandedCategoryIds = {};
            this.catalogFilter = '';
            this.listenTo(
                this.model,
                'change:serviceId change:quantity change:discount',
                this.updateCatalogPrice
            );
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.renderServiceCatalogPicker();
            this.updateCatalogPrice();
        },

        renderServiceCatalogPicker: function () {
            var $native = this.$el.find('[data-name="service"]').first();
            var $target = $native.closest('.cell, .form-group').first();

            if (!$target.length && $native.length) {
                $target = $native;
            }
            if (!$target.length) {
                $target = this.$el.find('.panel-body').first();
            }
            if (!$target.length || this.$el.find('.espo-dental-service-catalog-picker').length) {
                return;
            }

            var html =
                '<div class="espo-dental-service-catalog-picker cell form-group" style="margin-bottom:12px">' +
                    '<label class="control-label">' + this.translate('Service Catalog', 'labels', 'VisitServiceLine') + '</label>' +
                    '<input class="form-control input-sm" data-name="serviceCatalogSearch" disabled ' +
                        'placeholder="' + this.escapeAttribute(this.translate('Search service', 'labels', 'VisitServiceLine')) + '" ' +
                        'style="margin-bottom:8px">' +
                    '<div class="espo-dental-service-catalog-tree" data-name="serviceCatalogTree" ' +
                        'style="max-height:320px;overflow:auto;border:1px solid #ddd;border-radius:4px">' +
                        '<div class="text-muted" style="padding:8px">' +
                            this.translate('Loading...', 'labels', 'Global') +
                        '</div>' +
                    '</div>' +
                '</div>';

            if ($native.length) {
                $target.before(html);
                $target.hide();
            } else {
                $target.prepend(html);
            }

            this.loadCatalog().then(function () {
                this.bindCatalogTreeEvents();
                this.renderServiceCatalogTree();
            }.bind(this));
        },

        loadCatalog: function () {
            if (this.catalogPromise) {
                return this.catalogPromise;
            }

            this.catalogPromise = Promise.all([
                Espo.Ajax.getRequest('ServiceCategory', {maxSize: 200}),
                Espo.Ajax.getRequest('Service', {maxSize: 200})
            ]).then(function (responses) {
                this.catalogCategories = this.extractList(responses[0])
                    .filter(function (category) {
                        return category.isActive !== false;
                    })
                    .sort(function (a, b) {
                        var orderA = parseInt(a.order, 10) || 0;
                        var orderB = parseInt(b.order, 10) || 0;
                        if (orderA !== orderB) {
                            return orderA - orderB;
                        }
                        return String(a.name || '').localeCompare(String(b.name || ''));
                    });
                this.catalogServices = this.extractList(responses[1])
                    .filter(function (service) {
                        return service.isActive !== false;
                    })
                    .sort(function (a, b) {
                        return String(a.name || '').localeCompare(String(b.name || ''));
                    });
                this.catalogServicesById = {};
                this.catalogServices.forEach(function (service) {
                    this.catalogServicesById[service.id] = service;
                    this.serviceCache[service.id] = service;
                }.bind(this));
                this.catalogCategories.forEach(function (category) {
                    if (this.catalogExpandedCategoryIds[category.id] === undefined) {
                        this.catalogExpandedCategoryIds[category.id] = true;
                    }
                }, this);
                this.catalogLoaded = true;
            }.bind(this));

            return this.catalogPromise;
        },

        extractList: function (response) {
            if (!response) {
                return [];
            }
            if (Array.isArray(response)) {
                return response;
            }
            if (Array.isArray(response.list)) {
                return response.list;
            }
            if (Array.isArray(response.collection)) {
                return response.collection;
            }
            return [];
        },

        bindCatalogTreeEvents: function () {
            var $picker = this.$el.find('.espo-dental-service-catalog-picker');
            var $search = $picker.find('[data-name="serviceCatalogSearch"]');

            $search.prop('disabled', false);
            $search.off('input.espoDental').on('input.espoDental', function () {
                this.catalogFilter = String($search.val() || '').toLowerCase();
                this.renderServiceCatalogTree();
            }.bind(this));
        },

        renderServiceCatalogTree: function () {
            var $tree = this.$el.find('[data-name="serviceCatalogTree"]');
            if (!$tree.length) {
                return;
            }

            var selectedService = this.catalogServicesById[this.model.get('serviceId')];
            var selectedCategoryId = selectedService ? selectedService.categoryId : '';
            var filter = this.catalogFilter || '';
            var html = '';
            var visibleCategories = 0;

            this.catalogCategories.forEach(function (category) {
                var services = this.catalogServices.filter(function (service) {
                    return service.categoryId === category.id && this.matchesServiceFilter(service, category, filter);
                }, this);

                if (filter && !services.length) {
                    return;
                }

                visibleCategories++;

                var expanded = Boolean(this.catalogExpandedCategoryIds[category.id]) ||
                    Boolean(filter) ||
                    category.id === selectedCategoryId;
                var color = category.color || '#ddd';
                var count = services.length;

                html += '<div class="espo-dental-service-category" style="border-left:4px solid ' +
                    this.escapeAttribute(color) + '">' +
                    '<button type="button" class="btn btn-link btn-sm btn-block text-left" ' +
                        'data-name="serviceCategoryToggle" data-category-id="' + this.escapeAttribute(category.id) + '" ' +
                        'style="text-align:left;text-decoration:none;padding:7px 8px;color:inherit">' +
                        '<strong>' + this.escapeHtml(expanded ? '- ' : '+ ') +
                            this.escapeHtml(category.name || '') +
                        '</strong>' +
                        '<span class="text-muted"> (' + count + ')</span>' +
                    '</button>';

                if (expanded) {
                    html += this.renderServiceCatalogItems(services, selectedService ? selectedService.id : null);
                }

                html += '</div>';
            }, this);

            if (!visibleCategories) {
                html = '<div class="text-muted" style="padding:8px">' +
                    this.translate('No matching services', 'labels', 'VisitServiceLine') +
                    '</div>';
            }

            $tree.html(html);
            this.bindRenderedCatalogTreeEvents();
        },

        bindRenderedCatalogTreeEvents: function () {
            var tree = this.$el.find('[data-name="serviceCatalogTree"]').get(0);
            if (!tree) {
                return;
            }

            Array.prototype.forEach.call(
                tree.querySelectorAll('[data-name="serviceCategoryToggle"]'),
                function (button) {
                    button.addEventListener('click', function (e) {
                        e.preventDefault();
                        var categoryId = button.getAttribute('data-category-id');
                        if (!categoryId) {
                            return;
                        }

                        this.catalogExpandedCategoryIds[categoryId] = !this.catalogExpandedCategoryIds[categoryId];
                        this.renderServiceCatalogTree();
                    }.bind(this));
                }.bind(this)
            );

            Array.prototype.forEach.call(
                tree.querySelectorAll('[data-name="serviceCatalogItem"]'),
                function (button) {
                    button.addEventListener('click', function (e) {
                        e.preventDefault();
                        this.selectCatalogService(button.getAttribute('data-service-id'));
                    }.bind(this));
                }.bind(this)
            );
        },

        renderServiceCatalogItems: function (services, selectedServiceId) {
            if (!services.length) {
                return '<div class="text-muted" style="padding:0 8px 8px 20px">' +
                    this.translate('No services in category', 'labels', 'VisitServiceLine') +
                    '</div>';
            }

            var html = '<div class="list-group" style="margin:0 8px 8px 16px">';

            services.forEach(function (service) {
                var active = service.id === selectedServiceId;
                html += '<button type="button" class="list-group-item' + (active ? ' active' : '') + '" ' +
                    'data-name="serviceCatalogItem" data-service-id="' + this.escapeAttribute(service.id) + '" ' +
                    'style="padding:7px 9px">' +
                    '<div>' + this.escapeHtml(service.name || '') + '</div>' +
                    this.renderServiceMeta(service) +
                '</button>';
            }.bind(this));

            html += '</div>';

            return html;
        },

        matchesServiceFilter: function (service, category, filter) {
            if (!filter) {
                return true;
            }

            return String(service.name || '').toLowerCase().indexOf(filter) !== -1 ||
                String(service.code || '').toLowerCase().indexOf(filter) !== -1 ||
                String(category.name || '').toLowerCase().indexOf(filter) !== -1;
        },

        renderServiceMeta: function (service) {
            var parts = [];
            var price = parseFloat(service.price);
            var currency = service.priceCurrency || 'RUB';
            var duration = parseInt(service.duration, 10) || 0;

            if (!isNaN(price)) {
                parts.push(price.toFixed(2) + ' ' + currency);
            }
            if (duration > 0) {
                parts.push(duration + ' min');
            }
            if (service.code) {
                parts.push(service.code);
            }

            return parts.length ?
                '<div class="small text-muted">' + this.escapeHtml(parts.join(' / ')) + '</div>' :
                '';
        },

        selectCatalogService: function (serviceId) {
            if (!serviceId || !this.catalogServicesById[serviceId]) {
                return;
            }

            var service = this.catalogServicesById[serviceId];
            this.model.set({
                serviceId: service.id,
                serviceName: service.name
            });
            this.catalogExpandedCategoryIds[service.categoryId] = true;
            this.renderServiceCatalogTree();
            this.applyServicePrice(service);
        },

        updateCatalogPrice: function () {
            var serviceId = this.model.get('serviceId');
            if (!serviceId) {
                return;
            }

            if (this.serviceCache[serviceId]) {
                this.applyServicePrice(this.serviceCache[serviceId]);
                return;
            }

            Espo.Ajax.getRequest('Service/' + serviceId)
                .then(function (service) {
                    this.serviceCache[serviceId] = service;
                    this.applyServicePrice(service);
                }.bind(this));
        },

        applyServicePrice: function (service) {
            var price = parseFloat(service.price) || 0;
            var vatRate = parseFloat(service.vatRate) || 0;
            var quantity = parseInt(this.model.get('quantity'), 10) || 1;
            var discount = parseFloat(this.model.get('discount')) || 0;
            var currency = service.priceCurrency || 'RUB';

            quantity = Math.max(1, quantity);
            discount = Math.max(0, Math.min(100, discount));

            var amount = quantity * price * (1 - discount / 100);
            var vatAmount = amount * (vatRate / 100);

            this.model.set({
                name: service.name,
                unitPrice: price,
                unitPriceCurrency: currency,
                vatRate: vatRate,
                amount: Math.round(amount * 100) / 100,
                amountCurrency: currency,
                vatAmount: Math.round(vatAmount * 100) / 100,
                vatAmountCurrency: currency
            });
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
