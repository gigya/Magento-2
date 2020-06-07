<?php

namespace Gigya\GigyaIM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LanguageFallback implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {

        return [
            ['value' => "en_US", 'label' => __('English')],
            ['value' => "ar", 'label' => __('Arabic')],
            ['value' => "br", 'label' => __('Bulgarian')],
            ['value' => "ca", 'label' => __('Catalan')],
            ['value' => "hr", 'label' => __('Croatian')],
            ['value' => "cs", 'label' => __('Czech')],
            ['value' => "da", 'label' => __('Danish')],
            ['value' => "nl", 'label' => __('Dutch')],
            ['value' => "fi", 'label' => __('Finnish')],
            ['value' => "fr", 'label' => __('French')],
            ['value' => "de", 'label' => __('German')],
            ['value' => "el", 'label' => __('Greek')],
            ['value' => "he", 'label' => __('Hebrew')],
            ['value' => "hu", 'label' => __('Hungarian')],
            ['value' => "id", 'label' => __('Indonesian (Bahasa)')],
            ['value' => "it", 'label' => __('Italian')],
            ['value' => "ja", 'label' => __('Japanese')],
            ['value' => "ko", 'label' => __('Korean')],
            ['value' => "ms", 'label' => __('Malay')],
            ['value' => "no", 'label' => __('Norwegian')],
            ['value' => "fa", 'label' => __('Persian (Farsi)')],
            ['value' => "pl", 'label' => __('Polish')],
            ['value' => "pt", 'label' => __('Portuguese')],
            ['value' => "ro", 'label' => __('Romanian')],
            ['value' => "ru", 'label' => __('Russian')],
            ['value' => "sr", 'label' => __('Serbian (Cyrillic)')],
            ['value' => "sk", 'label' => __('Slovak')],
            ['value' => "sl", 'label' => __('Slovenian')],
            ['value' => "es", 'label' => __('Spanish')],
            ['value' => "sv", 'label' => __('Swedish')],
            ['value' => "tl", 'label' => __('Tagalog')],
            ['value' => "th", 'label' => __('Thai')],
            ['value' => "tr", 'label' => __('Turkish')],
            ['value' => "uk", 'label' => __('Ukrainian')],
            ['value' => "vi", 'label' => __('Vietnamese')],
            ['value' => "zh-cn", 'label' => __('Chinese (Mandarin)')],
            ['value' => "zh-hk", 'label' => __('Chinese (Hong Kong)')],
            ['value' => "zh-tw", 'label' => __('Chinese (Taiwan)')],
            ['value' => "nl-inf", 'label' => __('Dutch Informal')],
            ['value' => "fr-inf", 'label' => __('French Informal')],
            ['value' => "de-inf", 'label' => __('German Informal')],
            ['value' => "pt-br", 'label' => __('Portuguese (Brazil)')],
            ['value' => "es-inf", 'label' => __('Spanish Informal')],
            ['value' => "es-mx", 'label' => __('Spanish (Lat-Am)')],
        ];
    }
}
