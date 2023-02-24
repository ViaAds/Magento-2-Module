<?php

namespace ViaAds\Integration\Controller\Adminhtml\DataSync;

class EditApiKey extends \Magento\Backend\App\Action
{
    protected $resultPageFactory = false;
    protected $_configFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \ViaAds\Integration\Model\ConfigFactory $configFactory,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    )
    {
        parent::__construct($context);
        $this->_configFactory = $configFactory;
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((__('ViaAds Data Synchronization')));

        return $resultPage;
    }
}
