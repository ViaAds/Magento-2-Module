<?php

namespace ViaAds\Integration\Cron;

use Psr\Log\LoggerInterface;

class SyncOrders
{
    protected $logger;
    protected $productCollectionFactory;
    protected $orderCollectionFactory;
    protected $_productloader;
    protected $storeManager;
    protected $stockItemRepository;
    private $objectManager;
    protected $transactions;
    protected $configFactory;
    protected $url;

    public

    function __construct(
        LoggerInterface                                                 $logger,
        \Magento\Backend\App\Action\Context                             $context,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory  $productCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory      $orderCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface                 $productrepository,
        \Magento\Store\Model\StoreManagerInterface                      $storemanager,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository       $stockItemRepository,
        \Magento\Framework\ObjectManagerInterface                       $objectmanager,
        \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactions,
        \ViaAds\Integration\Model\ConfigFactory                         $configFactory,
        \Magento\Framework\UrlInterface                                 $url
    )
    {
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;

        $this->productrepository = $productrepository;
        $this->storeManager = $storemanager;
        $this->stockItemRepository = $stockItemRepository;

        $this->objectManager = $objectmanager;
        $this->transactions = $transactions;
        $this->configFactory = $configFactory;
        $this->url = $url;
    }

    /**
     * Write to system.log
     *
     * @return void
     */
    public

    function execute()
    {
        $this->logger->info('ViaAds Orders start sync');
        try {
            //Orders
            $orders = $this->getOrders();
            $this->logger->info('ViaAds Orders found ' . count($orders));
            if (count($orders) > 0) {
                $this->logger->info('ViaAds Orders: ' . json_encode($orders));
                $this->PostToUrl("https://sync.viaads.dk/magento/order", $orders);
            }
        } catch (Exception $e) {
            $error_object = new\ stdClass();
            $error_object->Error = $e->getMessage();
            $error_object->Url = $this->url->getBaseUrl();
            $this->PostToUrl("https://integration.viaads.dk/error", $error_object);
        }
        $this->logger->info('ViaAds Orders sync end');
    }

    function PostToUrl( $url, $data, $json = true ) {
        if ( $json == true ) {
            $data = json_encode( $data );
        }
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'content-type: application/json' ) );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 0 );

        $response = curl_exec( $ch );
        return $response;
    }


    /**
     * Get order collection
     *
     * @return Orders
     */
    public function getOrders()
    {
        $time = time();
        $to = date('Y-m-d H:i:s', $time);
        $lastTime = $time - (864000); // 1Day = 86400
        $from = date('Y-m-d H:i:s', $lastTime);
        $orderCollection = $this->orderCollectionFactory->create()->addAttributeToSelect('*')->addAttributeToFilter('updated_at', array('from' => $from, 'to' => $to));
        $orders = [];

        foreach ($orderCollection as $order) {
            $order_object = new\ stdClass();
            // Order Number
            $order_object->order_number = $order->getRealOrderId();
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
            $lastModifiedGmt = new\ DateTime($order->getUpdatedAt());
            $order_object->Last_modified = $lastModifiedGmt->format('c');

            $orderFinal = new\ stdClass();
            $config = $this->configFactory->create();
            foreach ($config->getCollection() as $item) {
                $apiKey = preg_replace("/\r|\n/", "", $item['api_key']);
                $apiKey = trim(preg_replace('/\t+/', '', $apiKey));
            }
            $orderFinal->ApiKey = $apiKey;
            $orderFinal->Shop_order = $order_object;

            // Order Date
            $createdAt = new\ DateTime($order->getCreatedAt());
            $orderFinal->Order_date = $createdAt->format('c');

            // Customer
            $customer = new\ stdClass();
            $customer->Email = strtolower($order->getCustomerEmail());
            $orderFinal->customer = $customer;

            //Plugin
            $plugin = new\ stdClass();
            $plugin->Name = "Magento";
            $plugin->Version = "1.0.0";
            $plugin->Version = $this->objectManager->create(\Magento\Framework\Module\ModuleList::class)->getOne('ViaAds_Integration')['setup_version'];
            $orderFinal->plugin = $plugin;

            array_push($orders, $orderFinal);
        }

        return $orders;
    }

    /**
     * get Order Billing Address
     *
     * @param Order $order
     * @return bool
     */
    public function getBillingAddress($order)
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
     * @return bool
     */
    public function getShippingAddress($order)
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
