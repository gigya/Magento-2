define([], function () {
    'use strict';

    return function (Component) {
        return Component.extend({
            initConfig: function () {
                this._super();

                if (typeof gigya_enabled !== "undefined" && gigya_enabled) {
                    this.template = 'Gigya_GigyaIM/form/element/email';
                }

                return this;
            },

            login: function (loginForm) {
                if (typeof gigya_enabled !== "undefined" && gigya_enabled) {
                    this.switchToScreen('gigya-login-screen');
                    jQuery('.block-authentication').modal('openModal');
                } else {
                    this._super();
                }
            },

            forgotPassword: function() {
                this.switchToScreen('gigya-forgot-password-screen');
                jQuery('.block-authentication').modal('openModal');
            },

            switchToScreen: function(screen) {
                window.gigyaInit.push({
                    "function": "accounts.switchScreen",
                    "parameters": {
                        screenSet: popupLoginScreenSetParams.screenSet,
                        containerID: popupLoginScreenSetParams.containerID,
                        screen: screen
                    }
                });

                if (typeof gigya !== "undefined") {
                    requirejs('gigya_script').Functions.performGigyaActions();
                }
            }
        });
    }
});