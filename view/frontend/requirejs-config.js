var config = {
    map: {
        '*': {
            'Magento_Customer/js/model/authentication-popup' : 'Gigya_GigyaIM/js/model/authentication-popup',
            gigya_script : 'Gigya_GigyaIM/js/gigya_script'
        }
    },
    shim: {
        'gigya_script':{
            'deps':['jquery']
        }
    },
    'config': {
        'mixins': {
            'Magento_Customer/js/view/authentication-popup': {
                'Gigya_GigyaIM/js/view/authentication-popup': true
            },
            'Magento_Customer/js/customer-data': {
                'Gigya_GigyaIM/js/customer-data': true
            },
            'Magento_Checkout/js/view/authentication': {
                'Gigya_GigyaIM/js/view/authentication': true
            },
            'Magento_Checkout/js/view/form/element/email': {
                'Gigya_GigyaIM/js/view/form/element/email': true
            }
        }
    }
};
