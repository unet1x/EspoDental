define('espo-dental:views/dashlets/record-list', ['views/dashlets/abstract/record-list'], function (Dep) {
    return Dep.extend({
        layoutType: 'expanded',

        setupDefaultOptions: function () {
            if (Dep.prototype.setupDefaultOptions) {
                Dep.prototype.setupDefaultOptions.call(this);
            }

            if (typeof this.getListLayout === 'function') {
                this.defaultOptions = this.defaultOptions || {};
                this.defaultOptions.expandedLayout = this.getListLayout();
            }
        }
    });
});
