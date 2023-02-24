<?php 
namespace ViaAds\Integration\Model\ResourceModel;
class Config extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb {
    public function __construct(
		\Magento\Framework\Model\ResourceModel\Db\Context $context
	)
	{
		parent::__construct($context);
	}

    public function _construct() {
        $this->_init("viaads_config","id");
    }
}
 ?>