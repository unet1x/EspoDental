define('espo-dental:views/fields/age', ['views/fields/base'], function (Dep) {

    return Dep.extend({

        readOnly: true,

        getAttributeList: function () {
            return [this.name, 'dateOfBirth'];
        },

        getValueForDisplay: function () {
            var dateOfBirth = this.model.get('dateOfBirth');

            if (!dateOfBirth) {
                return '';
            }

            var birth = new Date(dateOfBirth + 'T00:00:00');

            if (isNaN(birth.getTime())) {
                return '';
            }

            var today = new Date();
            var age = today.getFullYear() - birth.getFullYear();
            var monthDelta = today.getMonth() - birth.getMonth();

            if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birth.getDate())) {
                age--;
            }

            return age >= 0 ? String(age) : '';
        }
    });
});
