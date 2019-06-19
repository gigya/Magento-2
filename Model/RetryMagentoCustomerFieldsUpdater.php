<?php

namespace Gigya\GigyaIM\Model;

use Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;

/**
 * RetryMagentoCustomerFieldsUpdater
 *
 * @inheritdoc
 *
 * For the context of a Gigya or Magento update retry : as we saved the new Gigya data in the scheduled retry entry we have to re inject these to the Magento Customer entity.
 * For doing this we inverse the mapping directions set in the json field mapping file : cms2g becomes g2cms and vice-versa.
 * That allows us to retrieve the Customer data that were initially set when the retry was scheduled.
 *
 * @author vlemaire <vincentlemaire@clever-age.com>
 *
 */
class RetryMagentoCustomerFieldsUpdater extends MagentoCustomerFieldsUpdater
{
    /** @var fieldMapping\Conf|bool  */
    protected $retryConfMapping = false;

    /**
     * @inheritdoc
     *
     * By pass the parent class for returning our own fieldMapping\Conf object ($this->retryConfMapping) that was built with inversion of mapping directions.
     */
    protected function getMappingFromCache()
    {
        return $this->retryConfMapping;
    }

    /**
     * @inheritdoc
	 *
	 * @param fieldMapping\Conf $mappingConf
     *
     * Builds $this->retryConfMapping based on the $mappingConf param, but with inversion of the mapping directions : cms2g becomes g2cms and vice-versa.
     * In turns this is this object that we set on parent::setMappingCache, instead of $mappingConf.
     */
    protected function setMappingCache($mappingConf)
    {
        $newMappingConf = $mappingConf->getMappingConf();

        foreach ($newMappingConf as &$entry) {
            if (array_key_exists('direction', $entry)) {
                switch ($entry['direction']) {
                    case 'cms2g' :
                        $entry['direction'] = 'g2cms';
                        break;

                    case 'g2cms' :
                        $entry['direction'] = 'cms2g';
                        break;
                }
            }
        }

        $this->retryConfMapping = new fieldMapping\Conf(json_encode($newMappingConf));

        parent::setMappingCache($this->retryConfMapping);
    }

    /**
     * @inheritdoc
     *
     * The $gigyaMapping param is ignored : we call parent::setGigyaMapping with our own retrieved from $this->retryConfMapping->getGigyaKeyed()
     */
	public function setGigyaMapping($gigyaMapping)
	{
		if (!empty($this->retryConfMapping)) {
			$gigyaMapping = $this->retryConfMapping->getGigyaKeyed();
		}

		parent::setGigyaMapping($gigyaMapping);
	}
}