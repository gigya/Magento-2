define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'tinymce',
    'Magento_Customer/js/customer-data'
], function($, modal, tinymce, customerData){
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
    gigyaMage2.Params.gigya_user_logged_in = false; // checked by methods: getAccountInfo & checkLoginStatus
    gigyaMage2.Params.form_key = null;
    if ( $('input[name="form_key"]').val().length ){
        gigyaMage2.Params.form_key = $('input[name="form_key"]').val();
    }

    gigyaMage2.Functions.loadGigyaScript = function(api_key, language, domain) {

        if (!domain) {
            domain = 'gigya.com';
        }

        var gig = document.createElement('script');
        gig.type = 'text/javascript';
        gig.async = false;
        gig.src = 'https://cdns.' + domain +
            '/js/gigya.js?apiKey=' + api_key + '&lang=' + language;
        var gig_loaded = function () {
                gigya.accounts.addEventHandlers(
                    {
                        onLogin: gigyaMage2.Functions.gigyaLoginEventHandler,
                        onLogout: gigyaMage2.Functions.gigyaLogoutEventHandler
                    }
                );
            };
        gig.onreadystatechange= function () {
            if (this.readyState == 'complete') gig_loaded();
        }
        gig.onload= gig_loaded;

        document.getElementsByTagName('head')[0].appendChild(gig);

        window.gigyaCMS = {authenticated: false};
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
        window.gigyaCMS.authenticated = gigyaMage2.Params.gigya_user_logged_in;
        var action = login_state_url;
        // console.log('GIGYA LOGGED IN: '+gigyaMage2.Params.gigya_user_logged_in);
        // console.log('  CMS LOGGED IN: '+gigyaMage2.Params.magento_user_logged_in);
        // if Gigya is logged out, but Magento is logged in: log Magento out
        // this scenario may result in double page load for user, but is used only to fix an end case situation.
        if ((!gigyaMage2.Params.gigya_user_logged_in) && gigyaMage2.Params.magento_user_logged_in) {
            gigyaMage2.Functions.logoutMagento();
        }

        // if Gigya is logged in, but Magento is logged out: log Magento in
        if (gigyaMage2.Params.gigya_user_logged_in && (!gigyaMage2.Params.magento_user_logged_in)) {
            gigyaMage2.Functions.loginMagento(response);
        }
    };

    gigyaMage2.Functions.loginMagento = function (response) {
        if(enable_login)
        {
            var guid = response.UID;
            if(guid)
            {
                var form_key = tinymce.util.Cookie.get('form_key');
                var domain = window.location.hostname;
                $.ajax({
                    type : "POST",
                    url : login_url,
                    data : {
                        form_key:form_key, guid: guid, login_data: JSON.stringify(response),
                        key: gigyaMage2.Functions.loginEncode(domain+guid+"1234")
                    }
                })
                .always(function(data) {
                    if(data.reload)
                    {
                        window.location.reload();
                    }
                });
            }
        }
    };

    gigyaMage2.Functions.logoutMagento = function () {
        window.location.href = logout_url;
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

    gigyaMage2.Functions.loginEncode = function(data)
    {
        return window.btoa(decodeURIComponent(encodeURIComponent( data )));
    };

    gigyaMage2.Functions.gigyaLogoutEventHandler = function() {
    };

    gigyaMage2.Functions.performGigyaActions = function() {
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
            window.gigyaInit = [];
        }
    };



    /**
     * Things to do when gigya script finishes loading
     * init registered Gigya functions (e.g. showScreenSet from layout files)
     * register event listeners
     * @param serviceName
     */
    window.onGigyaServiceReady =  function (serviceName) {
        gigyaMage2.Functions.performGigyaActions();

        /**
         * add popup modal for gigya login screen
         */
        var gigya_login_modal = {
            type: 'popup',
            responsive: true,
            innerScroll: false,
            buttons: [],
            clickableOverlay: true
        };
        var gigya_login_popup = modal(gigya_login_modal, $('#gigya-login-popup'));
        window.showGigyaLoginScreenSet = function()
        {
            $("#gigya-login-popup").modal("openModal");
        };
        // // add popup opener script:
        $(".open-gigya-login").on('click',function(){
            showGigyaLoginScreenSet();
        });

    };
    return gigyaMage2;
});
