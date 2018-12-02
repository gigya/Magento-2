define([], function () {
    'use strict';

    return function (Component) {
        return Component.extend({
            initConfig: function () {
                this._super();

                if (typeof gigya_enabled != "undefined" && gigya_enabled == true) {
                    this.template = 'Gigya_GigyaIM/authentication-popup';
                }

                return this;
            }
        });
    }
});