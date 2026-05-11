define('espo-dental:views/dashlets/open-invoices', ['views/dashlets/record-list'], function (Dep) {
    return Dep.extend({
        name: 'OpenInvoices',
        scope: 'Invoice',
        getSearchData: function () {
            return {
                primary: null,
                bool: {onlyOpen: true},
                advanced: {}
            };
        },
        getListLayout: function () {
            return [
                {name: 'number'},
                {name: 'patient'},
                {name: 'issuedAt'},
                {name: 'dueDate'},
                {name: 'amountTotal'},
                {name: 'status'}
            ];
        }
    });
});
