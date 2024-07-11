<?php

namespace Gigya\GigyaIM\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;

class GigyaEncryptorHelper extends AbstractHelper
{
    /**
     * @var DirectoryList
     */
    protected DirectoryList $directoryList;

    /**
     * @var File
     */
    protected File $file;

    /**
     * @param Context $context
     * @param DirectoryList $directoryList
     * @param File $file
     */
    public function __construct(
        Context $context,
        DirectoryList $directoryList,
        File $file
    ) {
        parent::__construct($context);

        $this->directoryList = $directoryList;
        $this->file = $file;
    }

    /**
     * @param $keyFileLocation
     *
     * @return string|false
     *
     * @throws FileSystemException
     */
    public function getKeyFromFile($keyFileLocation): false|string
    {
        if (empty($keyFileLocation) == false) {
            $varPath = $this->directoryList->getPath(DirectoryList::VAR_DIR);
            $absoluteKeyFileLocation = $varPath . DIRECTORY_SEPARATOR . $keyFileLocation;

            if ($this->file->isExists($absoluteKeyFileLocation)) {
                $gigyaEncryptKey = trim($this->file->fileGetContents($absoluteKeyFileLocation));

                if (empty($gigyaEncryptKey) == false) {
                    return $gigyaEncryptKey;
                }
            }
        }

        return false;
    }
}
