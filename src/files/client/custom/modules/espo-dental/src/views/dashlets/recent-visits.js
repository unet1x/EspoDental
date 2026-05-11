define('espo-dental:views/dashlets/recent-visits', ['views/dashlets/record-list'], function (Dep) {
    return Dep.extend({
        name: 'RecentVisits',
        scope: 'Visit',
        getSearchData: function () {
            return {
                primary: null,
                bool: {thisMonth: true},
                advanced: {}
            };
        },
        getListLayout: function () {
            return [
                {name: 'startedAt'},
                {name: 'parent'},
                {name: 'doctor'},
                {name: 'status'},
                {name: 'amountTotal'}
            ];
        }
    });
});
