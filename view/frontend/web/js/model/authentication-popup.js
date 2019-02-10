/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function ($, modal) {
    'use strict';

    return {
        modalWindow: null,

        /**
         * Create popUp window for provided element
         *
         * @param {HTMLElement} element
         */
        createPopUp: function (element) {
            var modalClass;
            if (typeof gigya_enabled !== "undefined" && gigya_enabled) {
                modalClass = 'gigya-popup-authentication';
            } else {
                modalClass = 'popup-authentication';
            }

            var options = {
                'type': 'popup',
                'modalClass': modalClass,
                'focus': '[name=username]',
                'responsive': true,
                'innerScroll': true,
                'trigger': '.proceed-to-checkout',
                'buttons': []
            };

            this.modalWindow = element;
            modal(options, $(this.modalWindow));
        },

        /** Show login popup window */
        showModal: function () {
            $(this.modalWindow).modal('openModal');
        }
    };
});