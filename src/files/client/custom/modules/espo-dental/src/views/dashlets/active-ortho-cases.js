define('espo-dental:views/dashlets/active-ortho-cases', ['espo-dental:views/dashlets/record-list'], function (Dep) {
    return Dep.extend({
        name: 'ActiveOrthoCases',
        scope: 'OrthodonticCard',
        getSearchData: function () {
            return {
                primary: null,
                bool: {activeCards: true},
                advanced: {}
            };
        },
        getListLayout: function () {
            return [
                {name: 'cardNumber'},
                {name: 'patient'},
                {name: 'doctor'},
                {name: 'malocclusionClass'},
                {name: 'apparatusType'},
                {name: 'status'}
            ];
        }
    });
});
