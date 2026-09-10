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

        gigyaFormLoaded: false,

        /**
         * Queue the Gigya login screen set for this popup, once.
         *
         * Deferred to the point the popup is actually opened: this block renders on
         * every page, so initialising it on render would load the screen set and its
         * plugins - reCAPTCHA included - site wide.
         */
        loadGigyaForm: function () {
            if (this.gigyaFormLoaded || typeof popupRaasLoginScreen === 'undefined') {
                return;
            }

            window.gigyaInit = window.gigyaInit || [];
            window.gigyaInit.push(popupRaasLoginScreen);
            this.gigyaFormLoaded = true;

            /* If Gigya is not ready yet the queue is consumed by onGigyaServiceReady. */
            if (typeof gigya !== 'undefined') {
                requirejs('gigya_script').Functions.performGigyaActions();
            }
        },

        /** Show login popup window */
        showModal: function () {
            this.loadGigyaForm();
            $(this.modalWindow).modal('openModal');
        }
    };
});