define('espo-dental:views/dashlets/payroll-this-month', ['espo-dental:views/dashlets/record-list'], function (Dep) {
    return Dep.extend({
        name: 'PayrollThisMonth',
        scope: 'SalaryEntry',
        getSearchData: function () {
            return {
                primary: null,
                bool: {thisMonth: true},
                advanced: {}
            };
        },
        getListLayout: function () {
            return [
                {name: 'user'},
                {name: 'periodFrom'},
                {name: 'periodTo'},
                {name: 'totalAmount'},
                {name: 'status'}
            ];
        }
    });
});
