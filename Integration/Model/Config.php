<?php

namespace ViaAds\Integration\Model;

class Config extends \Magento\Framework\Model\AbstractModel
{
    public function _construct()
    {
        $this->_init("ViaAds\Integration\Model\ResourceModel\Config");
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }
}
