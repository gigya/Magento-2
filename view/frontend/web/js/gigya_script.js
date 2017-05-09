define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function($, modal){
    "use strict";
    var gigyaMage2 = {
        Params : {},
        Functions : {}
    };
    /**
     * Load Gigya script
     * Sync gigya-magento sessions
     * Event handlers (login, update)
     */
    gigyaMage2.Params.magento_user_logged_in = magento_user_logged_in;
    gigyaMage2.Params.gigya_user_logged_in = false; // checked by methods: getAccountInfo & checkLoginStatus
    gigyaMage2.Params.form_key = null;
    if ( $('input[name="form_key"]').val().length ){
        gigyaMage2.Params.form_key = $('input[name="form_key"]').val();
    }

    gigyaMage2.Functions.loadGigyaScript = function(api_key, language) {
        var gig = document.createElement('script');
        gig.type = 'text/javascript';
        gig.async = false;
        gig.src = ('https:' === document.location.protocol ? 'https://cdns' : 'http://cdn') +
            '.gigya.com/js/gigya.js?apiKey=' + api_key + '&lang=' + language;
        document.getElementsByTagName('head')[0].appendChild(gig);
    };

    /**
     * check gigya user login status
     * get Gigya account status with setLoginStatus as callback, and add it to gigya Init array
     */
    gigyaMage2.Functions.syncSessionStatus = function() {
        var AccountInfoStatus = {
            "function": "accounts.getAccountInfo",
            "parameters": { callback : gigyaMage2.Functions.setLoginStatus }
        };
        window.gigyaInit.push(AccountInfoStatus);
    };

    /**
     * sync Magento-Gigya sessions logic
     * if Gigya is logged out, but Magento is logged in: log Magento out
     * If Gigya is logged in but Magento is logged out: leave Gigya logged in
     */
    gigyaMage2.Functions.setLoginStatus = function (response) {
        if ( response.errorCode === 0 ) {
            gigyaMage2.Params.gigya_user_logged_in = true;
        } else {
            gigyaMage2.Params.gigya_user_logged_in = false;
        }
        // if Gigya is logged out, but Magento is logged in: log Magento out
        // this scenario may result in double page load for user, but is used only to fix an end case situation.
        if (!gigyaMage2.Params.gigya_user_logged_in && gigyaMage2.Params.magento_user_logged_in) {
            $('a[href$="logout/"]').click();
        }

        // If Gigya is logged in but Magento is logged out: currently, do nothing. you can add logout from gigya here.
        if (gigyaMage2.Params.gigya_user_logged_in && !gigyaMage2.Params.magento_user_logged_in )  {
//                var logoutSync = {"function": "accounts.logout", "parameters": {} };
//                window.gigyaInit.push(logoutSync);
        }
    };

    /**
     * Login event handler. set parameters for login submission and call Ajax submission
     * @param eventObj
     */
    gigyaMage2.Functions.gigyaLoginEventHandler = function(eventObj) {
        var action = login_post_url;
        var loginData = {
            UIDSignature : eventObj.UIDSignature,
            signatureTimestamp : eventObj.signatureTimestamp,
            UID : eventObj.UID
        };
        var data = {
            form_key : gigyaMage2.Params.form_key,
            "login[]" : "",
            login_data : JSON.stringify(loginData)
        };
        gigyaMage2.Functions.gigyaAjaxSubmit(action, data, $('.gigya-loader-location'));
    };

    gigyaMage2.Functions.gigyaAjaxUpdateProfile = function(eventObj) {
        var action = edit_post_url;
        var data = {
            form_key : gigyaMage2.Params.form_key,
            email : eventObj.profile.email,
            firstname : eventObj.profile.firstName,
            lastname : eventObj.profile.lastName,
            gigya_user : JSON.stringify(eventObj.response)
        };
        gigyaMage2.Functions.gigyaAjaxSubmit(action, data, $('.gigya-loader-location'));
    };

    gigyaMage2.Functions.gigyaAjaxSubmit = function (action, data, loader_context) {
        $.ajax({
            type : "POST",
            url : action,
            showLoader: true,
            context : loader_context,
            data : data
        })
            .done(function() {
                window.location.reload();
            });
    };



    /**
     * Things to do when gigya script finishes loading
     * init registered Gigya functions (e.g. showScreenSet from layout files)
     * register event listeners
     * @param serviceName
     */
    window.onGigyaServiceReady =  function (serviceName) {
        if (window.gigyaInit) {
            // If this is the edit profile page, then add the update profile callback function.
            if (window.gigyaInit[0]) {
                if( window.gigyaInit[0].parameters.containerID == "gigya-edit-profile") {
                    window.gigyaInit[0].parameters.onAfterSubmit = gigyaMage2.Functions.gigyaAjaxUpdateProfile;
                }
            }

            var length = window.gigyaInit.length,
                element = null;
            if (length > 0) {
                for (var i = 0; i < length; i++) {
                    element = window.gigyaInit[i];
                    var func = element.function;
                    var parts = func.split("\.");
                    var f = gigya[parts[0]][parts[1]];
                    if (typeof f === "function") {
                        f(element.parameters);
                    }
                }
            }

            gigya.accounts.addEventHandlers(
                {
                    onLogin: gigyaMage2.Functions.gigyaLoginEventHandler
                }
            );

            /**
             * add popup modal for gigya login screen
             */
            // var gigya_login_modal = {
            //     type: 'popup',
            //     responsive: true,
            //     innerScroll: false
            // };
            // var gigya_login_popup = modal(gigya_login_modal, $('#gigya-login-popup'));
            //
            // // add popup opener script:
            // jQuery(".open-gigya-login").on('click',function(){
            //     jQuery("#gigya-login-popup").modal("openModal");
            // });
        }
    };

    return gigyaMage2;
});
