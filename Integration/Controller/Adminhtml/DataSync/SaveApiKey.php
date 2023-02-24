<?php

namespace ViaAds\Integration\Controller\Adminhtml\DataSync;

use ViaAds\Integration\Model\ConfigFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Context;

class SaveApiKey extends \Magento\Backend\App\Action
{
    protected $_configFactory;
    protected $resultRedirect;
    protected $_pageFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \ViaAds\Integration\Model\ConfigFactory $configFactory,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Controller\ResultFactory $result
    )
    {
        $this->_configFactory = $configFactory;
        $this->_pageFactory = $pageFactory;
        $this->resultRedirect = $result;
        parent::__construct($context);
    }

    public function execute()
    {
        $PostValue = $this->getRequest()->getPost('api_key');

        $resultRedirect = $this->resultRedirect->create(ResultFactory::TYPE_REDIRECT);

        $config = $this->_configFactory->create();
        $collection = $config->getCollection();
        if ($PostValue) {
            if (count($collection) > 0) {
                foreach ($collection as $item) {

                    $item['api_key'] = $PostValue;
                    $item['updated_at'] = date("Y-m-d h:m:s");
                    $item->save();
                }
            } else {
                $config->addData([
                    "api_key" => $PostValue,
                    "created_at" => date("Y-m-d h:m:s"),
                    "updated_at" => date("Y-m-d h:m:s")
                ]);
                $config->save();
            }
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
