var config = {
    map: {
        '*': {
            gigya_script : 'Gigya_GigyaIM/js/gigya_script',
            'Magento_Customer/js/model/authentication-popup' : 'Gigya_GigyaIM/js/model/authentication-popup'
        }
    },
    shim: {
        'gigya_script':{
            'deps':['jquery', 'tinymce']
        }
    },
    'config': {
        'mixins': {
            'Magento_Customer/js/view/authentication-popup': {
                'Gigya_GigyaIM/js/view/authentication-popup': true
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
