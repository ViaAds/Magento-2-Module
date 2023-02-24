<?php

declare (strict_types=1);

namespace ViaAds\Integration\Observer\Frontend;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use ViaAds\Integration\Logger\Logger;

class RemoveCart implements ObserverInterface
{
    protected Logger $logger;
    protected $cookieMetadataFactory;
    protected $configFactory;
    protected $moduleList;

    public function __construct(
        Logger $logger,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \ViaAds\Integration\Model\ConfigFactory $configFactory,
        \Magento\Framework\Module\ModuleListInterface $moduleList
    )
    {
        $this->logger = $logger;
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
            $itemRaw = $observer->getEvent()->getData('quote_item');
            $item = ($itemRaw->getParentItem() ? $itemRaw->getParentItem() : $itemRaw);

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
            $shopEvent->Event_type = "RemoveCart";
            $shopEvent->Product_sku = $item->getSku();
            $shopEvent->Product_id = $item->getId();
            if ($itemRaw->getParentItem() == null) {
                // get the child product ID
                $shopEvent->Product_variant_id = $itemRaw->getId();
            }

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
