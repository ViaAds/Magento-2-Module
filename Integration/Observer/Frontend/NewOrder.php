<?php

declare (strict_types=1);

namespace ViaAds\Integration\Observer\Frontend;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use ViaAds\Integration\Logger\Logger;

class NewOrder implements ObserverInterface
{
    protected $logger;
    protected $cookieMetadataFactory;
    protected $configFactory;
    protected $moduleList;

    public function __construct(
        Logger $logger,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \ViaAds\Integration\Model\ConfigFactory $configFactory,
        \Magento\Framework\Module\ModuleListInterface $moduleList)
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
            $order = $observer->getEvent()->getOrder();

            if (isset($_COOKIE['via_ads'])) {
                $cookieValues = json_decode(base64_decode($_COOKIE['via_ads']));
                if ($cookieValues->Consent) {
                    $cookieValues->Email = $order->getBillingAddress()->getEmail();
                    setcookie("via_ads", base64_encode(json_encode($cookieValues)), time() + (34560000), "/");
                }
            }

            $orders = [];
            $order_object = new\ stdClass();
            // Order Number
            $order_object->order_number = $order->getIncrementId();
            // Order Status
            $order_object->status = $order->getStatus();
            // Order currency
            $order_object->Currency = $order->getOrderCurrencyCode();
            // Total Price
            $order_object->total_price = $order->getSubTotal();
            // Total Shipping
            $order_object->Total_price_shipping = $order->getShippingAmount();
            // Tax
            if ($order->getSubtotalInclTax() > 0 && $order->getSubTotal() > 0) {
                $order_object->Total_price_tax = $order->getSubtotalInclTax() - $order->getSubTotal();
            } else {
                $order_object->Total_price_tax = 0;
            }
            $order_object->Total_price_tax_included = $order_object->total_price + $order_object->Total_price_tax + $order_object->Total_price_shipping;

            $vat_percentage = 0.25;
            $orderItems = [];
            // Order Items
            foreach ($order->getAllVisibleItems() as $item) {
                $order_item_object = new\ stdClass();
                $product = $item->getProduct();
                // Product id
                $order_item_object->product_id = $product->getId();
                // Name
                $order_item_object->name = $item->getName();
                // SKU
                $order_item_object->sku = $item->getSku();
                // Price
                //$order_item_object->price = number_format($item->getPrice(), 2, ".", "");
                $order_item_object->price = $item->getPrice();
                // Quantity
                $order_item_object->quantity = round($item->getQtyOrdered());
                // Total
                //$order_item_object->total_price = number_format($item->getRowTotal(), 2, ".", "");
                $order_item_object->total_price = $item->getRowTotal();
                //$order_item_object->total_price_tax_included = number_format($item->getRowTotalInclTax(), 2, ".", "");
                $order_item_object->total_price_tax_included = $item->getRowTotalInclTax();
                //$order_item_object->total_price_tax = number_format($item->getRowTotalInclTax() - $item->getRowTotal(), 2, ".", "");
                $order_item_object->total_price_tax = $item->getRowTotalInclTax() - $item->getRowTotal();

                array_push($orderItems, $order_item_object);

                // Vat Percentage
                $tax_rate = $item->getTaxPercent();
                $vat_percentage = max($vat_percentage, $tax_rate);
            }
            $order_object->Vat_percentage = "0." . (float)$vat_percentage;

            // Order Items
            $order_object->order_items = $orderItems;
            // Billing Address
            $order_object->billing_address = $this->getBillingAddress($order);
            // Shipping Address
            $order_object->shipping_address = $this->getShippingAddress($order);
            // Last Modified
            //$lastModifiedGmt = new\ DateTime($order->getUpdatedAt());
            $lastModifiedGmt = new\ DateTime();
            $order_object->Last_modified = $lastModifiedGmt->format('c');

            // Order Final
            $orderFinal = new\ stdClass();
            $config = $this->configFactory->create();
            foreach ($config->getCollection() as $item) {
                $apiKey = preg_replace("/\r|\n/", "", $item['api_key']);
                $apiKey = trim(preg_replace('/\t+/', '', $apiKey));
            }
            $orderFinal->ApiKey = $apiKey;
            $orderFinal->Shop_order = $order_object;

            // Order Date
            //$createdAt = new\ DateTime($order->getCreatedAt());
            $createdAt = new\ DateTime();
            $orderFinal->Order_date = $createdAt->format('c');

            // Customer
            $customer = new\ stdClass();
            $customer->Email = strtolower($order->getCustomerEmail());
            $orderFinal->customer = $customer;

            //Customer IP
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $remote = $objectManager->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
            $ip = $remote->getRemoteAddress();

            // Client info
            $clientInfo = new\ stdClass();
            $clientInfo->ip = $ip;
            $orderFinal->client = $clientInfo;

            //Plugin
            $plugin = new\ stdClass();
            $plugin->Name = "Magento";
            $plugin->Version = $this->moduleList->getOne('ViaAds_Integration')['setup_version'];
            $orderFinal->plugin = $plugin;

            array_push($orders, $orderFinal);

            PostToUrl("https://integration.viaads.dk/magento/order", $orders);
        } catch (\Exception $e) {
            $error_object = new\ stdClass();
            $error_object->Error = $e->getMessage();
            $error_object->Url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            PostToUrlEvent("https://integration.viaads.dk/error", $error_object);
        }

    }

    /**
     * get Order Billing Address
     *
     * @param Order $order
     * @return \stdClass
     */
    public

    function getBillingAddress($order)
    {
        // Order Customer Billing Address
        $order_billing_address = new\ stdClass();
        $order_billing_address->first_name = $order->getBillingAddress()->getFirstname() . ' ' . $order->getBillingAddress()->getMiddlename();
        $order_billing_address->last_name = $order->getBillingAddress()->getLastname();
        $order_billing_address->address1 = $order->getBillingAddress()->getStreet()[0];
        $order_billing_address->city = $order->getBillingAddress()->getCity();
        $order_billing_address->state = $order->getBillingAddress()->getRegion();
        $order_billing_address->zip_Code = $order->getBillingAddress()->getPostcode();
        $order_billing_address->country = $order->getBillingAddress()->getCountryId();
        $order_billing_address->phone_number = $order->getBillingAddress()->getTelephone();
        $order_billing_address->email = strtolower($order->getBillingAddress()->getEmail());

        return $order_billing_address;
    }

    /**
     * get Order Shipping Address
     *
     * @param Order $order
     * @return \stdClass
     */
    public

    function getShippingAddress($order)
    {
        // Order Customer Shipping Address
        $order_shipping_address = new\ stdClass();
        $order_shipping_address->first_name = $order->getShippingAddress()->getFirstname() . ' ' . $order->getShippingAddress()->getMiddlename();
        $order_shipping_address->last_name = $order->getShippingAddress()->getLastname();
        $order_shipping_address->address1 = $order->getShippingAddress()->getStreet()[0];
        $order_shipping_address->city = $order->getShippingAddress()->getCity();
        $order_shipping_address->state = $order->getShippingAddress()->getRegion();
        $order_shipping_address->zip_Code = $order->getShippingAddress()->getPostcode();
        $order_shipping_address->country = $order->getShippingAddress()->getCountryId();
        $order_shipping_address->phone_number = $order->getShippingAddress()->getTelephone();
        $order_shipping_address->email = strtolower($order->getShippingAddress()->getEmail());

        return $order_shipping_address;
    }
}
