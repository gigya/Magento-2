define([], function () {
    'use strict';

    return function (Component) {
        return Component.extend({
            initConfig: function () {
                this._super();

                if (typeof gigya_enabled !== "undefined" && gigya_enabled) {
                    this.template = 'Gigya_GigyaIM/authentication';
                }

                return this;
            },

            loadGigyaForm: function() {
                if (typeof gigya !== "undefined") {
                    console.log('loadGigyaForm .. load');
                    window.gigyaInit.push(popupRaasLoginScreen);
                    requirejs('gigya_script').Functions.performGigyaActions();
                }
            },

            login: function() {
                if (typeof gigya_enabled !== "undefined" && gigya_enabled) {
                    this.switchToScreen('gigya-login-screen');
                } else {
                    return this._super();
                }
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