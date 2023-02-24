<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ViaAds\Integration\Model;
/**
 * Class DataProvider
 */
class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /**
     * @var ConfigFactory
     */
    protected $_configFactory;

    /**
     * DataProvider constructor.
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ConfigFactory $configFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        \ViaAds\Integration\Model\ConfigFactory $configFactory,
        array $meta = [],
        array $data = []
    )
    {
        $this->_configFactory = $configFactory;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        $config = $this->_configFactory->create();
        $collection = $config->getCollection();
        $apiKeys = [];

        if (count($collection) > 0)
            echo '<script type="text/javascript">console.log("Hello1!");</script>';
        echo '<script type="text/javascript">console.log("Hello2!");</script>';

        foreach ($collection as $item) {
            array_push($apiKeys, $item);
            echo '<script type="text/javascript">console.log("' . $item['api_key'] . '");</script>';
            //print_r($item->debug());
        }

        return $apiKeys;
    }

    public function addFilter(\Magento\Framework\Api\Filter $filter)
    {
        return null;
    }
}
