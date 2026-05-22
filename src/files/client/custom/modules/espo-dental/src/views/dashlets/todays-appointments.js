define('espo-dental:views/dashlets/todays-appointments', ['espo-dental:views/dashlets/record-list'], function (Dep) {
    return Dep.extend({
        name: 'TodaysAppointments',
        scope: 'Appointment',
        rowActionsView: 'views/record/row-actions/default',
        getSearchData: function () {
            return {
                primary: null,
                bool: {today: true},
                advanced: {}
            };
        },
        getListLayout: function () {
            return [
                {name: 'dateStart'},
                {name: 'parent'},
                {name: 'doctor'},
                {name: 'cabinet'},
                {name: 'status'}
            ];
        }
    });
});
