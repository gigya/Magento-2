define([], function () {
    'use strict';

    return function (Popup) {
        return Popup.extend({
            defaults: {
                template: 'Gigya_GigyaIM/authentication-popup'
            }
        });
    }
});