define('espo-dental:views/dashlets/low-stock-materials', ['espo-dental:views/dashlets/record-list'], function (Dep) {
    return Dep.extend({
        name: 'LowStockMaterials',
        scope: 'Material',
        getSearchData: function () {
            return {
                primary: null,
                bool: {lowStock: true, onlyActive: true},
                advanced: {}
            };
        },
        getListLayout: function () {
            return [
                {name: 'name'},
                {name: 'category'},
                {name: 'currentStock'},
                {name: 'minStock'},
                {name: 'stockLevel'}
            ];
        }
    });
});
