/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*jshint browser:true jquery:true*/
/*global alert*/
define(
	[
		'Gigya_GigyaIM/js/gigya_script'
	],
	function ($, modal) {
		'use strict';

		return {
			/** Show login popup window */
			showModal: function () {
				showGigyaLoginScreenSet();
			}
		}
	}
);
