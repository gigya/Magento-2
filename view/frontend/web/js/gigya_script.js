define([
	'jquery',
	'Magento_Ui/js/modal/modal',
	'Magento_Customer/js/customer-data'
], function ($, modal, customerData) {
	"use strict";
	var gigyaMage2 = {
		Params: {},
		Functions: {}
	};

	/**
	 * Load Gigya script
	 * Sync gigya-magento sessions
	 * Event handlers (login, update)
	 */
	gigyaMage2.Params.gigya_user_logged_in = false; // checked by methods: getAccountInfo & checkLoginStatus
	gigyaMage2.Params.form_key = null;
	var formKeyObj = $('input[name="form_key"]');
	if (formKeyObj.val().length) {
		gigyaMage2.Params.form_key = formKeyObj.val();
	}

	gigyaMage2.Functions.loadGigyaScript = function (api_key, language, domain) {
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
		gig.onreadystatechange = function () {
			if (this.readyState === 'complete') gig_loaded();
		};
		gig.onload = gig_loaded;

		document.getElementsByTagName('head')[0].appendChild(gig);

		window.gigyaCMS = {authenticated: false};
	};

	/**
	 * sync Magento-Gigya sessions logic
	 * if Gigya is logged out, but Magento is logged in: log Magento out
	 * If Gigya is logged in but Magento is logged out: leave Gigya logged in
	 */
	gigyaMage2.Functions.setLoginStatus = function (response) {
		gigyaMage2.Params.gigya_user_logged_in = (response.errorCode === 0);

		window.gigyaCMS.authenticated = gigyaMage2.Params.gigya_user_logged_in;
		var action = login_state_url;

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
		if (enable_login) {
			var guid = response.UID;
			if (guid) {
				var form_key = gigya.utils.cookie.get('form_key');

				var domain = window.location.hostname;
				$.ajax({
					type: "POST",
					url: login_url,
					data: {
						form_key: form_key,
						guid: guid,
						login_data: JSON.stringify(response),
						key: gigyaMage2.Functions.loginEncode(domain + guid + "1234")
					}
				}).always(function (data) {
					if (data.reload) {
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
	 *
	 * @property eventObj.expires_in
	 * @property eventObj.id_token
	 */
	gigyaMage2.Functions.gigyaLoginEventHandler = function (eventObj) {
		var remember = gigyaMage2.Functions.getRememberMeStatus(eventObj);
		var action = login_post_url;
		var loginData = {
			UIDSignature: eventObj.UIDSignature,
			signatureTimestamp: eventObj.signatureTimestamp,
			UID: eventObj.UID,
			idToken: eventObj.id_token
		};

		/* Propagate Remember Me status to the SSO group */
		gigya.setGroupContext({
			"remember": remember
		});

		if (typeof eventObj.expires_in !== 'undefined') {
			loginData.expiresIn = eventObj.expires_in;
		}

		var data = {
			form_key: gigyaMage2.Params.form_key,
			"login[]": "",
			login_data: JSON.stringify(loginData),
			remember: remember
		};

		$('[name=login_data]', '#gigya_login_post').val(JSON.stringify(loginData));
		$('[name=remember]', '#gigya_login_post').val(remember ? 1 : 0);

		$('body').trigger('processStart');
		$('#gigya_login_post').submit();
	};

	gigyaMage2.Functions.getRememberMeStatus = function (eventObj) {
		var remember = false;

		// Pull remember me status from the group context
		if (typeof eventObj.groupContext !== 'undefined') {
			var groupRemember = JSON.parse(eventObj.groupContext).remember;

			if (typeof groupRemember !== 'undefined') {
				remember = groupRemember;
			}
		}

		// "Remember Me" clicked on the current site always overrides the group context remember
		if (typeof eventObj.remember !== 'undefined') {
			remember = eventObj.remember;
		}

		return remember;
	}

	/**
	 * @param eventObj
	 *
	 * @property eventObj.profile.firstName
	 * @property eventObj.profile.lastName
	 */
	gigyaMage2.Functions.gigyaAjaxUpdateProfile = function (eventObj) {
		var action = edit_post_url;
		var data = {
			form_key: gigyaMage2.Params.form_key,
			email: eventObj.profile.email,
			firstname: eventObj.profile.firstName,
			lastname: eventObj.profile.lastName,
			gigya_user: JSON.stringify(eventObj.response)
		};

		gigya_processing_customer_request = true;

		gigyaMage2.Functions.gigyaAjaxSubmit(action, data, $('.gigya-loader-location'));
	};

	gigyaMage2.Functions.gigyaAjaxSubmit = function (action, data, loader_context) {
		$.ajax({
			type: "POST",
			url: action,
			showLoader: true,
			context: loader_context,
			data: data,
			dataType: 'json'
		}).done(function (dataObj) {
			if ((typeof dataObj.location !== 'undefined') && (typeof sendSetSSOToken !== 'undefined') && (sendSetSSOToken)) {
				gigya.accounts.setSSOToken({redirectURL: dataObj.location});
			}
			else if (typeof dataObj.location !== 'undefined') {
				window.location.href = dataObj.location;
			}
			else {
				window.location.reload();
			}
		});
	};

	gigyaMage2.Functions.loginEncode = function (data) {
		return window.btoa(decodeURIComponent(encodeURIComponent(data)));
	};

	gigyaMage2.Functions.gigyaLogoutEventHandler = function () {
		gigyaMage2.Functions.logoutMagento();
	};

	gigyaMage2.Functions.waitForElementToDisplay = function(selector, callable, params, time, ttl) {
		if ($(selector).length < 1 && ttl > 0) {
			setTimeout(function () {
				gigyaMage2.Functions.waitForElementToDisplay(selector, callable, params, time, ttl - time);
			}, time);
		} else {
			callable(params);
			return;
		}
	};

	gigyaMage2.Functions.performGigyaActions = function () {
		if (window.gigyaInit) {

			/* If this is the edit profile page, then add the update profile callback function */
			if (window.gigyaInit[0]) {
				if (window.gigyaInit[0].parameters.containerID === "gigya-edit-profile") {
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
						/* showScreenSet should always wait for the container DOM element. It usually loads on time, but not always. */
						if (func === 'accounts.showScreenSet' && typeof element.parameters.containerID !== 'undefined') {
							var containerIdSelector = '#' + element.parameters.containerID;
							gigyaMage2.Functions.waitForElementToDisplay(containerIdSelector, f, element.parameters, 200, 5000);
						}
						else {
							f(element.parameters);
						}
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
	window.onGigyaServiceReady = function (serviceName) {
		gigyaMage2.Functions.performGigyaActions();
	};
	return gigyaMage2;
});
