<?php

namespace ViaAds\Integration\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeData implements UpgradeDataInterface
{
	protected $_postFactory;

	public function __construct()
	{
	}

	public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
	{
		if (version_compare($context->getVersion(), '1.2.0', '<')) {
			$data = [
				'name'         => "Magento 2 ViaAds",
				'post_content' => "ViaAds Extension",
				'url_key'      => '/magento-2-module-development/magento-2-events.html',
				'tags'         => 'magento 2,viaads datasync, viaads'
			];
		}
	}
}
