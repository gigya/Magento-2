define(function () {
    'use strict';

    return function (Component) {
        Component.reloadOriginal = Component.reload;
        Component.reload = function (sectionNames, updateSectionId) {
            if (typeof gigya_enabled !== "undefined" && gigya_enabled && gigya_processing_customer_request == true) {
                return;
            }

            return this.reloadOriginal(sectionNames, updateSectionId);
        }

        return Component;
    }
});