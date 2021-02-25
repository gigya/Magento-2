var gigyaHelper = {
	addGigyaScript: function (apiKey, lang) {
		var gig = document.createElement('script');
		gig.type = 'text/javascript';
		gig.async = true;
		gig.src = ('https:' === document.location.protocol ? 'https://cdns' : 'http://cdn') + '.gigya.com/js/gigya.js?apiKey=' + apiKey + '&lang=' + lang;
		document.getElementsByTagName('head')[0].appendChild(gig);
	},

	checkLogout: function () {
		var logoutCookie = gigya.utils.cookie.get("gigyaLogout");
		if (logoutCookie) {
			gigya.accouts.logout();
		}
	},

	addGigyaFunctionCall: function (method, params) {
		window.gigyaCmsInit = window.gigyaCmsInit || [];
		var func = {function: method, parameters: params};
		window.gigyaCmsInit.push(func);
	},
	onLoginHandler: function (res) {
		/* This is an example for an onLogin event handler it uses jQuery, if jQuery is not available at your system
		 *  replace with your own version of ajax call
		 *  NOTE: this example should be a edited to work.
		 */

		var data = {
				"uid": res.UID,
				"uigSig": res.UIDSignature,
				"sigTimestamp": res.signatureTimestamp
			},
			serverSideUrl = "edit this to point to the url that would receive the the info";
		jQuery.ajax(serverSideUrl, {
			data: data,
			dataType: "text",
			type: "POST",
			async: false,
			global: false
		}).done(function (res) {
			var response = JSON.parse(res);
			if (response.success === "success") {
				// Do what is needed to show that the user is logged in (reload the page etc...)
			} else {
				// Logout user from gigya
				gigya.account.logout();
				// Show error etc...
			}
		}).fail(function () {
			// Logout user from gigya
			gigya.account.logout();
			// Show error etc...
		});
	},

	runGigyaCmsInit: function () {
		if (window.gigyaCmsInit) {
			var length = window.gigyaCmsInit.length,
				element = null;
			if (length > 0) {
				for (var i = 0; i < length; i++) {
					element = window.gigyaCmsInit[i];
					var func = element.function;
					var parts = func.split("\.");
					var f = gigya[parts[0]][parts[1]];
					if (typeof f === "function") {
						f(element.parameters);
					}
				}
			}
		}
	}
};

(function addGigya() {
	var apiKey = gigyaCmsConfig.apiKey,
		lang = gigyaCmsConfig.lang;
	gigyaHelper.addGigyaScript(apiKey, lang);
})();

function onGigyaServiceReady(serviceName) {
	gigyaHelper.checkLogout();
	gigyaHelper.runGigyaCmsInit();
	gigya.accounts.addEventHandlers(
		{onLogin: gigyaHelper.onLoginHandler}
	);
}
