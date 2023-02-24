<?php

namespace ViaAds\Integration\Controller\Adminhtml\DataSync;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;

class Test extends \Magento\Backend\App\Action
{
	protected $productCollectionFactory;
	protected $orderCollectionFactory;

	protected $_productloader;
	protected $storeManager;

    protected $stockItemRepository;

	private $objectManager;

	protected $transactions;


	public function __construct(
		\Magento\Backend\App\Action\Context $context,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
		\Magento\Catalog\Api\ProductRepositoryInterface $productrepository,
        \Magento\Store\Model\StoreManagerInterface $storemanager,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,
		\Magento\Framework\ObjectManagerInterface $objectmanager,
		\Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactions
	)
	{
        $this->productCollectionFactory = $productCollectionFactory;
		$this->orderCollectionFactory = $orderCollectionFactory;
		$this->categoryCollectionFactory = $categoryCollectionFactory;

		$this->productrepository = $productrepository;
        $this->storeManager =  $storemanager;
        $this->stockItemRepository = $stockItemRepository;

		$this->objectManager = $objectmanager;
		$this->transactions = $transactions;
		parent::__construct($context);
	}

	public function execute()
	{


		/*echo '<pre>';
		print_r($this->getCategories());
		echo '</pre>';*/
		$this->getCategories();

		/*echo '<pre>';
		print_r($this->getProducts());
		echo '</pre>';*/
		$this->getProducts();

		echo '<pre>';
		print_r($this->getOrders());
		echo '</pre>';
		//$this->getOrders()
	}



	/**
     * Get product collection
     *
     * @return Products
     */
    public function getProducts()
    {
		$productCollection = $this->productCollectionFactory->create();
        $date = new\ DateTime();

        $productCollection->addAttributeToSelect( '*' );
        // Get Configurable Products
        $productCollection->addFieldToFilter( array( array( 'attribute' => 'visibility', 'neq' => "1" ) ) );

        $store = $this->storeManager->getStore();

        echo 'Currency  =  ' . $store->getBaseCurrencyCode();
        echo "</br>";

        $objectManager = \Magento\ Framework\ App\ ObjectManager::getInstance();

        $products = [];
        foreach ( $productCollection as $product ) {
            echo "</br>";
            $product_object = new\ stdClass();
            $productRep = $this->productrepository->getById( $product->getId() );
            $product_object->id = $product->getId();
            // Image
            $imageArray = array();
            $image_object = new\ stdClass();
            $image_object->DateCreated = $date->format( 'Y-m-d' );
            $image_object->DateModified = $date->format( 'Y-m-d' );
            $image_object->Src = $store->getBaseUrl( \Magento\ Framework\ UrlInterface::URL_TYPE_MEDIA ) . 'catalog/product' . $productRep->getImage();
            array_push( $imageArray, $image_object );
            $product_object->Images = $imageArray;

            /*$product_object->imageUrl = $store->getBaseUrl( \Magento\ Framework\ UrlInterface::URL_TYPE_MEDIA ) . 'catalog/product' . $productRep->getImage();*/
            // Rating
            $RatingOb = $objectManager->create( 'Magento\Review\Model\Rating' )->getEntitySummary( $product_object->id );
            $ratings = 0;
            if ( $RatingOb->getCount() > 0 )
                $ratings = $RatingOb->getSum() / $RatingOb->getCount();
            $product_object->RatingCount = $ratings;
            // Category
            //$product1 = Mage::getModel( "catalog/product" )->setId( $product_object->id );
            //$product_object->CategoryTest = $product1->getCategoryCollection();
            // Name
            $product_object->name = $product->getName();
            // Product Url - Permalink
            $product_object->permalink = $product->getProductUrl();
            // SKU
            $product_object->sku = $product->getSku();
            // Updated At
            $lastModifiedGmt = new\ DateTime( $product->getUpdatedAt() );
            $product_object->LastModifiedGmt = $lastModifiedGmt->format( 'c' );
            // Created At
            $createdAt = new\ DateTime( $product->getCreatedAt() );
            $product_object->CreatedGmt = $createdAt->format( 'c' );
            // Status
            $product_object->status = $product->getStatus();
            // Type
            $product_object->type = $product->getTypeID();
            // Catalog Visibility
            $product_object->catalogVisibility = $product->isVisibleInCatalog();
            // Related Product Ids
            $product_object->RelatedProductIds = $product->getRelatedProductIds();
            // Up Sell Product Ids
            $product_object->UpSellProductIds = $product->getUpSellProductIds();
            // Cross Sell Product Ids
            $product_object->CrossSellProductIds = $product->getCrossSellProductIds();
            // Slug
            $product_object->slug = $product->getUrlKey();

            // Prices
            // Gets the products Final Price
            $productPrice = number_format( $product->getPriceInfo()->getPrice( 'final_price' )->getValue(), 2, ".", "," );
            $product_object->price = $productPrice;
            // Regular Price
            $product_object->regularPrice = number_format( $product->getPrice(), 2, ".", "," );

            // On Sale Data
            if ( $product->getSpecialPrice() ) {
                // On Sale
                $product_object->onSale = true;
                // Sale Price
                $product_object->salePrice = number_format( $product->getSpecialPrice(), 2, ".", "," );
                // On Sale From Date
                $dateOnSaleFrom = new\ DateTime( $product->getSpecialFromDate() );
                $product_object->dateOnSaleFrom = $dateOnSaleFrom->format( 'c' );
                //$child_object->dateOnSaleFrom = $child->getSpecialFromDate();
                // On Sale To Date
                $dateOnSaleTo = new\ DateTime( $product->getSpecialToDate() );
                $product_object->dateOnSaleTo = $dateOnSaleTo->format( 'c' );
                //$child_object->dateOnSaleTo = $product->getSpecialToDate();
            } else {
                $product_object->onSale = false;
            }

            // Get All Children (Simple) Products from Parent Configurables
            $objectManager = \Magento\ Framework\ App\ ObjectManager::getInstance();
            $configProduct = $objectManager->create( 'Magento\Catalog\Model\Product' )->load( $product->getId() );
            $children = $configProduct->getTypeInstance()->getUsedProducts( $configProduct );
            $i = 1;
            $lowestPrice;
            $highestPrice;
            $childen = [];
            foreach ( $children as $child ) {
                //echo '<pre>' . print_r( $child->debug() );
                $child_object = new\ stdClass();
                // Product Id
                $child_object->WebshopProductId = $child->getId();
                // Parent Product Id
                $child_object->parentId = $child->getParentId();
                // Name
                $child_object->name = $child->getName();
                // Slug
                $child_object->slug = $child->getUrlKey();
                // Short Description
                $child_object->shortDescription = $child->getShortDescription();
                // Permalink
                $child_object->permalink = $product->getProductUrl();
                // Type
                $child_object->type = $child->getTypeID();
                // Status
                $child_object->status = $child->getStatus();
                // Modified At
                $dateModified = new\ DateTime( $child->getCreatedAt() );
                $child_object->dateModified = $dateModified->format( 'c' );
                // Created At
                $createdAt = new\ DateTime( $child->getCreatedAt() );
                $child_object->dateCreated = $createdAt->format( 'c' );
                // Gets the products Final Price
                $childPrice = number_format( $child->getPriceInfo()->getPrice( 'final_price' )->getValue(), 2, ".", "," );
                $child_object->price = $childPrice;
                // Regular Price
                $child_object->regularPrice = number_format( $child->getPrice(), 2, ".", "," );

                // On Sale Data
                if ( $child->getSpecialPrice() ) {
                    // On Sale
                    $child_object->onSale = true;
                    // Sale Price
                    $child_object->salePrice = number_format( $child->getSpecialPrice(), 2, ".", "," );
                    // On Sale From Date
                    $dateOnSaleFrom = new\ DateTime( $child->getSpecialFromDate() );
                    $child_object->dateOnSaleFrom = $dateOnSaleFrom->format( 'c' );
                    //$child_object->dateOnSaleFrom = $child->getSpecialFromDate();
                    // On Sale To Date
                    $dateOnSaleTo = new\ DateTime( $child->getSpecialToDate() );
                    $child_object->dateOnSaleTo = $dateOnSaleTo->format( 'c' );
                    //$child_object->dateOnSaleTo = $product->getSpecialToDate();
                } else {
                    $child_object->onSale = false;
                }

                // SKU
                $child_object->sku = $child->getSku();
                // Product Type
                $child_object->type = $child->getTypeID();
                // Catalog Visibility
                $child_object->catalogVisibility = $child->isVisibleInCatalog();

                // Stock Status
                $child_object->stockStatus = ( string )$this->getStockStatus( $child->getId() );
                // Stock Quantity
                $child_object->stockQuantity = $this->getStockQuantity( $child->getId() );
                // Weight
                $child_object->weight = number_format( $child->getWeight(), 2, ".", "," );

                // Getting the Lowest and the Highest price from Child (Variant) Products
                if ( $i == 1 ) {
                    $lowestPrice = $childPrice;
                    $highestPrice = $childPrice;
                } else {
                    if ( $childPrice < $lowestPrice )
                        $lowestPrice = $childPrice;
                    if ( $childPrice > $highestPrice )
                        $highestPrice = $childPrice;
                }
                array_push( $childen, $child_object );
                $i++;
            }

            // Variants
            $product_object->Variants = $childen;
            // Lowest Price in Price Range
            $product_object->lowestPrice = $lowestPrice;
            // Highest Price in Price Range
            $product_object->highestPrice = $highestPrice;

            array_push( $products, $product_object );
        }

        return $products;
    }

	/**
     * get stock status
     *
     * @param int $productId
     * @return bool
     */
    public function getStockStatus($productId)
    {
        $stockItem = $this->stockItemRepository->get($productId);
		$qty = $stockItem->getQty();
		if(intval($qty) || $qty === null)
			return 1;
		return 0;
    }

	/**
     * get stock quantity
     *
     * @param int $productId
     * @return int
     */
    public function getStockQuantity($productId)
    {
        $stockItem = $this->stockItemRepository->get($productId);
		$qty = $stockItem->getQty();
		return $qty;
    }


	/**
     * Get order collection
     *
     * @return Orders
     */
    public function getOrders()
    {
		$orderCollection = $this->orderCollectionFactory->create()->addAttributeToSelect('*');
		$orders = [];
		foreach ($orderCollection as $order){
			$order_object = new\ stdClass();
			// Order Id
			$order_object->orderId = $order->getId();
			// Transactions
			/*$transactions = $this->transactions->create()->addOrderIdFilter($order->getId());
			foreach($transactions->getItems() as $transaction) {
				echo '<pre>' . print_r($transaction->debug());
			}
			$order_object->transactionId = $order->getPayment()->getTransactionId();*/

			// Order Number
			$order_object->orderNumber = $order->getRealOrderId();
			// Order Status
			$order_object->status = $order->getStatus();
			// Customer Id Of Shop
			$order_object->shopCustomerId = $order->getCustomerId();
			// Payment Method
			$order_object->paymentMethod = $order->getPayment()->getMethodInstance()->getTitle();
			// Total Price
			$order_object->totalPrice = $order->getSubTotal();
			// Customer Note
			$order_object->customerNote = $order->getCustomerNote();

			$orderItems = [];
			// Order Items
			foreach ($order->getAllVisibleItems() as $item) {
				$order_item_object = new\ stdClass();
				// Product Id
				$order_item_object->productId = $item->getId();
				// Name
				$order_item_object->name = $item->getName();
				// SKU
				$order_item_object->sku = $item->getSku();
				// Price
				$order_item_object->price = number_format($item->getPrice(),2,".",",");
				// Quantity
				$order_item_object->quantity = round($item->getQtyOrdered());
				// Total
				$order_item_object->total = number_format($item->getRowTotal(),2,".",",");

				array_push($orderItems, $order_item_object);
			}

			// Order Items
			$order_object->orderItems = $orderItems;
			// Billing Address
			$order_object->billingAddress = $this->getBillingAddress($order);
			// Shipping Address
			$order_object->shippingAddress = $this->getShippingAddress($order);

			array_push($orders, $order_object);
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
		$order_billing_address->firstName = $order->getBillingAddress()->getFirstname() . ' ' . $order->getBillingAddress()->getMiddlename();
		$order_billing_address->lastName = $order->getBillingAddress()->getLastname();
		$order_billing_address->address1 = $order->getBillingAddress()->getStreet()[0];
		$order_billing_address->city = $order->getBillingAddress()->getCity();
		$order_billing_address->state = $order->getBillingAddress()->getRegion();
		$order_billing_address->zipCode = $order->getBillingAddress()->getPostcode();
		$order_billing_address->country = $order->getBillingAddress()->getCountryId();
		$order_billing_address->phoneNumber = $order->getBillingAddress()->getTelephone();
		$order_billing_address->email = $order->getBillingAddress()->getEmail();

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
		$order_shipping_address->firstName = $order->getShippingAddress()->getFirstname() . ' ' . $order->getShippingAddress()->getMiddlename();
		$order_shipping_address->lastName = $order->getShippingAddress()->getLastname();
		$order_shipping_address->address1 = $order->getShippingAddress()->getStreet()[0];
		$order_shipping_address->city = $order->getShippingAddress()->getCity();
		$order_shipping_address->state = $order->getShippingAddress()->getRegion();
		$order_shipping_address->zipCode = $order->getShippingAddress()->getPostcode();
		$order_shipping_address->country = $order->getShippingAddress()->getCountryId();
		$order_shipping_address->phoneNumber = $order->getShippingAddress()->getTelephone();
		$order_shipping_address->email = $order->getShippingAddress()->getEmail();

		return $order_shipping_address;
    }


	/**
     * Get category collection
     *
     * @return category
     */
    public function getCategories()
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('*');

		$categories = [];
		foreach ($collection as $category) {
			if($category->getLevel() > 0 && $category->getIsActive()) {
				$category_object = new\ stdClass();
				//echo '<pre>' . print_r($category->debug());

				// Name
				$category_object->name = $category->getName();
				// Slug
				$category_object->slug = $category->getUrlKey();
				// Id
				$category_object->id = $category->getId();
				// Parent Id
				$category_object->parentId = $category->getParentId();

				if($category_object->parentId != 0) {
					// Parent Name
					$category_object->parentName = $category->getParentCategory()->getName();
				}
				array_push($categories, $category_object);
			}
		}

        return $categories;
    }
}
