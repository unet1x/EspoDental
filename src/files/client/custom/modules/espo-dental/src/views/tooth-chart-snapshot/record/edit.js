define('espo-dental:views/tooth-chart-snapshot/record/edit', [
    'espo-dental:views/tooth-chart-snapshot/record/detail',
    'views/record/edit'
], function (Detail, EditDep) {

    return Detail.extend({
        type: 'edit',
        gridLayoutType: 'detail'
    });
});
