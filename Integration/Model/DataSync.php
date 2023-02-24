<?php
// namespace ViaAds\Integration\Model;
// class DataSync extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
// {
// 	const CACHE_TAG = 'viaads_datasync_datasync';

// 	protected $_cacheTag = 'viaads_datasync_datasync';

// 	protected $_eventPrefix = 'viaads_datasync_datasync';

// 	protected function _construct()
// 	{
// 		echo '<script type="text/javascript">console.log("7");</script>';
// 		$this->_init('ViaAds\DataSync\Model\ResourceModel\DataSync');
// 	}

// 	public function getIdentities()
// 	{
// 		echo '<script type="text/javascript">console.log("3");</script>';
// 		return [self::CACHE_TAG . '_' . $this->getId()];
// 	}

// 	public function getDefaultValues()
// 	{
// 		$values = [];
// 		echo '<script type="text/javascript">console.log("4");</script>';
// 		return $values;
// 	}
// }