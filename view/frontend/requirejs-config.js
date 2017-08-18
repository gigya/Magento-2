var config = {
    map: {
        '*': {
            gigya_script : 'Gigya_GigyaIM/js/gigya_script',
            'Magento_Customer/js/action/check-email-availability' : 'Gigya_GigyaIM/js/customer/action/check-email-availability'

        }
    },
    shim:{
        'gigya_script':{
            'deps':['jquery', 'tinymce']
        }
    }
};
