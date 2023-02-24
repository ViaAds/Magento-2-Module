<?php 
namespace ViaAds\Integration\Model\ResourceModel\Config;
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection {
	
	protected $_idFieldName = 'id';
	protected $_eventPrefix = 'viaads_integration_api_key_collection';
	protected $_eventObject = 'api_key_collection';
	
	public function _construct(){
		$this->_init("ViaAds\Integration\Model\Config","ViaAds\Integration\Model\ResourceModel\Config");
	}
}
?>