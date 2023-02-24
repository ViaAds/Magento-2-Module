<?php

declare (strict_types=1);

namespace ViaAds\Integration\Observer\Frontend;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use ViaAds\Integration\Logger\Logger;

class RemoveWishlist implements ObserverInterface
{
    protected Logger $logger;
    protected $_request;
    protected $_itemFactory;
    protected $cookieMetadataFactory;
    protected $configFactory;
    protected $moduleList;

    public

    function __construct(
        Logger $logger,
        \Magento\Framework\App\RequestInterface $_request,
        \Magento\Wishlist\Model\ItemFactory $_itemFactory,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \ViaAds\Integration\Model\ConfigFactory $configFactory,
        \Magento\Framework\Module\ModuleListInterface $moduleList
    )
    {
        $this->logger = $logger;
        $this->_request = $_request;
        $this->_itemFactory = $_itemFactory;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->configFactory = $configFactory;
        $this->moduleList = $moduleList;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        //Post function
        require_once($_SERVER['DOCUMENT_ROOT'] . '/app/code/ViaAds/Integration/Observer/http.php');
        try {
            //Checking for cookie consent
            if (!isset($_COOKIE['via_ads'])){
                return;
            }
            $cookieValues = json_decode(base64_decode($_COOKIE['via_ads']));
            if (!$cookieValues->Consent) {
                return;
            }

            //Item data
            $itemId = ( int )$this->_request->getParam('item');
            $item = $this->_itemFactory->create()->load($itemId);

            //Customer IP
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $remote = $objectManager->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
            $ip = $remote->getRemoteAddress();

            //Customer Email
            $email = "";
            $customerSession = $objectManager->get('Magento\Customer\Model\Session');
            if ($customerSession->isLoggedIn()) {
                $email = strtolower($customerSession->getCustomer()->getEmail());
            }

            // Client Info
            $data = new\ stdClass();
            $clientInfo = new\ stdClass();
            $clientInfo->ip = $ip;

            // Url
            $productPageUrl = new\ stdClass();
            $productPageUrl->full = $item->getProduct()->getProductUrl();

            // Customer
            $customer = new\ stdClass();
            if (strlen($email) > 4) {
                $customer->Email = $email;
            } else {
                $customer->Email = strtolower($cookieValues->Email);
            }
            $customer->Session_id = $cookieValues->Session;
            $customer->Email_guid = $cookieValues->EG;
            $customer->Fingerprint  = $cookieValues->FP;
            $data->customer = $customer;

            // Shop event
            $shopEvent = new\ stdClass();
            $shopEvent->Event_type = "RemoveWishlist";
            $shopEvent->Product_sku = $item->getProduct()->getSku();
            $shopEvent->Product_id = $item->getProduct()->getId();

            // Api Key
            $config = $this->configFactory->create();
            foreach ($config->getCollection() as $item) {
                $apiKey = preg_replace("/\r|\n/", "", $item['api_key']);
                $apiKey = trim(preg_replace('/\t+/', '', $apiKey));
            }
            $data->ApiKey = $apiKey;

            //Plugin
            $plugin = new\ stdClass();
            $plugin->Name = "Magento";
            $plugin->Version = $this->moduleList->getOne('ViaAds_Integration')['setup_version'];
            $data->plugin = $plugin;

            // Client Info
            $data->client = $clientInfo;
            // Url
            $data->url = $productPageUrl;
            // Shop event
            $data->Shop_event = $shopEvent;
            // Date
            $date = new\ DateTime();
            $data->Event_date = $date->format('Y-m-d\TH:i:s');

            PostToUrlEvent("https://integration.viaads.dk/magento/event", $data);
        } catch (\Exception $e) {
            $error_object = new\ stdClass();
            $error_object->Error = $e->getMessage();
            $error_object->Url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            PostToUrlEvent("https://integration.viaads.dk/error", $error_object);
        }
    }
}
