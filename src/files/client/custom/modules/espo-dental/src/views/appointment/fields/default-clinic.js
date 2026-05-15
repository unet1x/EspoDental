define('espo-dental:views/appointment/fields/default-clinic', ['views/fields/link'], function (Dep) {

    return Dep.extend({

        setup: function () {
            this.defaultClinicRequested = false;
            this.applyDefaultClinic(false);

            Dep.prototype.setup.call(this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.applyDefaultClinic(true);
        },

        applyDefaultClinic: function (rerender) {
            if (!this.model || !this.model.isNew || !this.model.isNew() || this.model.get(this.name + 'Id')) {
                return;
            }

            var config = typeof this.getConfig === 'function' ? this.getConfig() : null;
            var clinicId = config && typeof config.get === 'function' ? config.get('espoDentalDefaultClinicId') : null;
            var clinicName = config && typeof config.get === 'function' ?
                config.get('espoDentalDefaultClinicName') : null;

            if (clinicId) {
                this.setDefaultClinic(clinicId, clinicName || '', rerender);
                return;
            }

            if (this.defaultClinicRequested) {
                return;
            }

            this.defaultClinicRequested = true;

            Espo.Ajax.getRequest('Clinic', {maxSize: 1})
                .then(function (response) {
                    var clinic = response && response.list && response.list[0];
                    if (!clinic || this.model.get(this.name + 'Id')) {
                        return;
                    }

                    this.setDefaultClinic(clinic.id, clinic.name || '', true);
                }.bind(this));
        },

        setDefaultClinic: function (id, name, rerender) {
            if (!id) {
                return;
            }

            var attrs = {};
            attrs[this.name + 'Id'] = id;
            attrs[this.name + 'Name'] = name || '';

            this.model.set(attrs, {ui: true});

            if (rerender && typeof this.reRender === 'function') {
                this.reRender();
            }
        }
    });
});
