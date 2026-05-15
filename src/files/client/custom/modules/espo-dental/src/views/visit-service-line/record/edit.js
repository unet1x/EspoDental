define('espo-dental:views/visit-service-line/record/edit', ['views/record/edit'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.serviceCache = {};
            this.catalogLoaded = false;
            this.catalogCategories = [];
            this.catalogServices = [];
            this.catalogServicesById = {};
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
                    '<div style="display:grid;grid-template-columns:minmax(160px,1fr) minmax(220px,2fr);gap:8px">' +
                        '<select class="form-control input-sm" data-name="serviceCategoryPicker" disabled>' +
                            '<option value="">' + this.translate('Loading...', 'labels', 'Global') + '</option>' +
                        '</select>' +
                        '<select class="form-control input-sm" data-name="servicePicker" disabled>' +
                            '<option value="">' + this.translate('Select service', 'labels', 'VisitServiceLine') + '</option>' +
                        '</select>' +
                    '</div>' +
                '</div>';

            if ($native.length) {
                $target.before(html);
                $target.hide();
            } else {
                $target.prepend(html);
            }

            this.loadCatalog().then(function () {
                this.populateCategoryPicker();
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

        populateCategoryPicker: function () {
            var $category = this.$el.find('[data-name="serviceCategoryPicker"]');
            var $service = this.$el.find('[data-name="servicePicker"]');
            if (!$category.length || !$service.length) {
                return;
            }

            var selectedService = this.catalogServicesById[this.model.get('serviceId')];
            var selectedCategoryId = selectedService ? selectedService.categoryId : '';

            var categoryOptions = '<option value="">' + this.translate('Select category', 'labels', 'VisitServiceLine') + '</option>';
            this.catalogCategories.forEach(function (category) {
                var selected = category.id === selectedCategoryId ? ' selected' : '';
                categoryOptions += '<option value="' + this.escapeAttribute(category.id) + '"' + selected + '>' +
                    this.escapeHtml(category.name || '') +
                    '</option>';
            }.bind(this));

            $category.html(categoryOptions).prop('disabled', false);
            $category.off('change.espoDental').on('change.espoDental', function () {
                this.model.set({
                    serviceId: null,
                    serviceName: null
                });
                this.populateServicePicker($category.val(), null);
            }.bind(this));

            $service.off('change.espoDental').on('change.espoDental', function () {
                this.selectCatalogService($service.val());
            }.bind(this));

            this.populateServicePicker(selectedCategoryId, selectedService ? selectedService.id : null);
        },

        populateServicePicker: function (categoryId, selectedServiceId) {
            var $service = this.$el.find('[data-name="servicePicker"]');
            if (!$service.length) {
                return;
            }

            if (!categoryId) {
                $service.html('<option value="">' + this.translate('Select service', 'labels', 'VisitServiceLine') + '</option>')
                    .prop('disabled', true);
                return;
            }

            var options = '<option value="">' + this.translate('Select service', 'labels', 'VisitServiceLine') + '</option>';
            var services = this.catalogServices.filter(function (service) {
                return service.categoryId === categoryId;
            });

            if (!services.length) {
                options += '<option value="" disabled>' + this.translate('No services in category', 'labels', 'VisitServiceLine') + '</option>';
            }

            services.forEach(function (service) {
                var selected = service.id === selectedServiceId ? ' selected' : '';
                options += '<option value="' + this.escapeAttribute(service.id) + '"' + selected + '>' +
                    this.escapeHtml(service.name || '') +
                    '</option>';
            }.bind(this));

            $service.html(options).prop('disabled', false);
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
