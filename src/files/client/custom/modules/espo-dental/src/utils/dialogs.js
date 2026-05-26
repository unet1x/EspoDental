define('espo-dental:utils/dialogs', [], function () {

    var seed = 0;

    function openModal(owner, options) {
        return new Promise(function (resolve) {
            if (!owner || !owner.createView) {
                resolve(null);

                return;
            }

            owner.createView(
                'espoDentalDialog' + (++seed),
                'espo-dental:views/modals/prompt',
                options,
                function (modalView) {
                    var resolved = false;

                    function finish(value) {
                        if (resolved) {
                            return;
                        }

                        resolved = true;
                        resolve(value);
                    }

                    owner.listenToOnce(modalView, 'submit', finish);
                    owner.listenToOnce(modalView, 'cancel', function () {
                        finish(null);
                    });

                    modalView.render();
                }
            );
        });
    }

    function prompt(owner, options) {
        options = options || {};

        return new Promise(function (resolve) {
            if (owner && owner.createView) {
                openModal(owner, options).then(resolve);

                return;
            }

            if (!window.bootbox || !window.bootbox.prompt) {
                Espo.Ui.warning(options.unavailableMessage || 'Dialog is unavailable');
                resolve(null);

                return;
            }

            window.bootbox.prompt({
                title: options.title || options.message || '',
                value: options.value || '',
                inputType: options.inputType || 'text',
                callback: function (result) {
                    resolve(result);
                }
            });
        });
    }

    function confirm(owner, options) {
        options = options || {};

        return new Promise(function (resolve) {
            if (owner && owner.createView) {
                openModal(owner, {
                    title: options.title || options.message || '',
                    message: options.message || '',
                    hideInput: true,
                    submitLabel: options.confirmText || 'OK'
                }).then(function (value) {
                    resolve(value === true);
                });

                return;
            }

            if (window.bootbox && window.bootbox.confirm) {
                window.bootbox.confirm(options.message || '', function (result) {
                    resolve(!!result);
                });

                return;
            }

            Espo.Ui.warning(options.unavailableMessage || 'Dialog is unavailable');
            resolve(false);
        });
    }

    return {
        prompt: prompt,
        confirm: confirm
    };
});
