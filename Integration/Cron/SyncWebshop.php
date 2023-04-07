<?php

namespace ViaAds\Integration\Cron;

use Psr\Log\LoggerInterface;

class SyncWebshop
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
    protected $directoryList;
    protected $url;

    public

    function __construct(
        LoggerInterface                                                 $logger,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory  $productCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory      $orderCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface                 $productrepository,
        \Magento\Store\Model\StoreManagerInterface                      $storemanager,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository       $stockItemRepository,
        \Magento\Framework\ObjectManagerInterface                       $objectmanager,
        \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactions,
        \ViaAds\Integration\Model\ConfigFactory                         $configFactory,
        \Magento\Framework\App\Filesystem\DirectoryList                 $directoryList,
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
        $this->directoryList = $directoryList;
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
        $this->logger->info('ViaAds Product sync start');
        try {
            //Post function

            $config = $this->configFactory->create();
            foreach ($config->getCollection() as $item) {
                $apiKey = preg_replace("/\r|\n/", "", $item['api_key']);
                $apiKey = trim(preg_replace('/\t+/', '', $apiKey));
            }

            // Webshop
            $webshop = new\ stdClass();
            $webshop->ApiKey = $apiKey;
            $webshop->Name = "Magento";
            $webshop->Type = "Magento";
            $webshop->Categories = $this->getCategories();
            $webshop->Products = $this->getProducts();
            $this->logger->info('ViaAds Product found ' . count($webshop->Products));
            $this->logger->info('ViaAds Product: ' . json_encode($webshop));

            //Split array in chucks for max data size
            $splitted = array_chunk($webshop->Products, 2500);
            foreach ($splitted as $interval) {
                $webshop->Products = $interval;
                $this->PostToUrl("https://sync.viaads.dk/magento/webshop", $webshop);
                $webshop->Categories = [];
            }
        } catch (Exception $e) {
            $error_object = new\ stdClass();
            $error_object->Error = $e->getMessage();
            $error_object->Url = $this->url->getBaseUrl();
            $this->PostToUrl("https://integration.viaads.dk/error", $error_object);
        }
        $this->logger->info('ViaAds Product sync end');
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
     * Get product collection
     *
     * @return Products
     */
    public function getProducts()
    {
        $productCollection = $this->productCollectionFactory->create();
        $date = new\ DateTime();

        $productCollection->addAttributeToSelect('*');
        $productCollection->addMediaGalleryData();
        // Get Configurable Products
        $productCollection->addFieldToFilter(array(array('attribute' => 'visibility', 'neq' => "1")));
        $store = $this->storeManager->getStore();

        $products = [];
        foreach ($productCollection as $product) {
            $product_object = new\ stdClass();
            $productRep = $this->productrepository->getById($product->getId());
            $product_object->id = $product->getId();
            $product_object->WebshopProductId = "{$product->getId()}";
            // Image
            $imageArray = array();
            $image_object = new\ stdClass();
            $image_object->DateCreated = $date->format('Y-m-d');
            $image_object->DateModified = $date->format('Y-m-d');
            $image_object->Src = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $productRep->getImage();
            array_push($imageArray, $image_object);

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $configProduct = $objectManager->create('Magento\Catalog\Model\Product')->load($product->getId());

            foreach ($configProduct->getMediaGalleryImages() as $image) { //will load all gallery images in loop
                $image_object = new\ stdClass();
                $image_object->DateCreated = $date->format('Y-m-d');
                $image_object->DateModified = $date->format('Y-m-d');
                $image_object->Src = $image->getUrl();
                $image_object->Id = $image->getId();
                array_push($imageArray, $image_object);
            }
            $product_object->ProductImages = $imageArray;

            // Rating
            $RatingOb = $objectManager->create('Magento\Review\Model\Rating')->getEntitySummary($product_object->id);
            $ratings = 0;
            if ($RatingOb->getCount() > 0)
                $ratings = $RatingOb->getSum() / $RatingOb->getCount();
            $product_object->RatingCount = $ratings;
            // Category
            $categories = array();
            $categoryIds = $product->getCategoryIds();
            $categoriesProducts = $product->getCategoryCollection()->addAttributeToFilter('entity_id', $categoryIds);
            foreach ($categoriesProducts as $categoryValue) {
                $category = new\ stdClass();
                $category->WebshopCategoryId = $categoryValue->getId();
                $category->Name = $categoryValue->getName();
                $category->Slug = $categoryValue->getUrlKey();
                array_push($categories, $category);
            }
            $product_object->categories = $categories;

            // Name
            $product_object->name = $product->getName();
            // Product Url - Permalink
            $product_object->permalink = $product->getProductUrl();
            // SKU
            $product_object->sku = $product->getSku();
            // Updated At
            $lastModifiedGmt = new\ DateTime($product->getUpdatedAt());
            $product_object->LastModifiedGmt = $lastModifiedGmt->format('c');
            // Created At
            $createdAt = new\ DateTime($product->getCreatedAt());
            $product_object->CreatedGmt = $createdAt->format('c');
            // Status
            $product_object->status = $product->getStatus();
            // Type
            $product_object->type = $product->getTypeID();
            // Catalog Visibility
            $product_object->catalogVisibility = $product->isVisibleInCatalog();
            // Related Product Ids
            $product_object->RelatedProductIds = array_map('strval', $product->getRelatedProductIds());
            // Up Sell Product Ids
            $product_object->UpSellProductIds = array_map('strval', $product->getUpSellProductIds());
            // Cross Sell Product Ids
            $product_object->CrossSellProductIds = array_map('strval', $product->getCrossSellProductIds());
            // Slug
            $product_object->slug = $product->getUrlKey();

            // Prices
            // Gets the products Final Price
            $productPrice = $product->getPriceInfo()->getPrice('final_price')->getValue();
            $product_object->price = !empty($productPrice) ? $productPrice : 0;
            // Regular Price
            $product_object->regularPrice =  !empty($product->getPrice()) ? $product->getPrice() : 0;
            // On Sale Data
            if ($product->getSpecialPrice()) {
                // On Sale
                $product_object->onSale = true;
                // Sale Price
                $product_object->salePrice = $product->getSpecialPrice();
                // On Sale From Date
                $dateOnSaleFrom = new\ DateTime($product->getSpecialFromDate());
                $product_object->dateOnSaleFrom = $dateOnSaleFrom->format('c');
                //$child_object->dateOnSaleFrom = $child->getSpecialFromDate();
                // On Sale To Date
                $dateOnSaleTo = new\ DateTime($product->getSpecialToDate());
                $product_object->dateOnSaleTo = $dateOnSaleTo->format('c');
                //$child_object->dateOnSaleTo = $product->getSpecialToDate();
            } else {
                $product_object->onSale = false;
            }

            if ($product_object->type != 'simple') {
                // Get All Children (Simple) Products from Parent Configurables
                $children = $configProduct->getTypeInstance()->getUsedProducts($configProduct);
                $i = 1;
                $lowestPrice;
                $highestPrice;
                $childs = [];
                if ($children) {
                    foreach ($children as $child) {
                        //echo '<pre>' . print_r( $child->debug() );
                        $child_object = new\ stdClass();
                        // Product Id
                        $child_object->WebshopProductId = "{$child->getId()}";
                        // Parent Product Id
                        $child_object->parentId = "{$child->getParentId()}";
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
                        $dateModified = new\ DateTime($child->getCreatedAt());
                        $child_object->dateModified = $dateModified->format('c');
                        // Created At
                        $createdAt = new\ DateTime($child->getCreatedAt());
                        $child_object->dateCreated = $createdAt->format('c');
                        // Gets the products Final Price
                        $childPrice = $child->getPriceInfo()->getPrice('final_price')->getValue();
                        $child_object->price = !empty($childPrice) ? $childPrice : 0;
                        // Regular Price
                        $child_object->regularPrice = !empty($child->getPrice()) ? $child->getPrice() : 0;

                        // On Sale Data
                        if ($child->getSpecialPrice()) {
                            // On Sale
                            $child_object->onSale = true;
                            // Sale Price
                            $child_object->salePrice = $child->getSpecialPrice();
                            // On Sale From Date
                            $dateOnSaleFrom = new\ DateTime($child->getSpecialFromDate());
                            $child_object->dateOnSaleFrom = $dateOnSaleFrom->format('c');
                            // On Sale To Date
                            $dateOnSaleTo = new\ DateTime($child->getSpecialToDate());
                            $child_object->dateOnSaleTo = $dateOnSaleTo->format('c');
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
                        $child_object->stockStatus = ( string )$this->getStockStatus($child->getId());
                        // Stock Quantity
                        $child_object->stockQuantity = $this->getStockQuantity($child->getId());

                        // Getting the Lowest and the Highest price from Child (Variant) Products
                        if ($i == 1) {
                            $lowestPrice = $childPrice;
                            $highestPrice = $childPrice;
                        } else {
                            if ($childPrice < $lowestPrice)
                                $lowestPrice = $childPrice;
                            if ($childPrice > $highestPrice)
                                $highestPrice = $childPrice;
                        }
                        array_push($childs, $child_object);
                        $i++;
                    }
                }

                // Variants
                $product_object->Variants = $childs;
                // Lowest Price in Price Range
                $product_object->lowestPrice = $lowestPrice;
                // Highest Price in Price Range
                $product_object->highestPrice = $highestPrice;
            }
            array_push($products, $product_object);
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
        if (intval($qty) || $qty === null)
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
            if ($category->getLevel() > 0 && $category->getIsActive()) {
                $category_object = new\ stdClass();
                // Name
                $category_object->name = $category->getName();
                // Slug
                $category_object->slug = $category->getUrlKey();
                // Id
                $category_object->id = $category->getId();
                $category_object->WebshopCategoryId = $category->getId();
                // Parent Id
                $category_object->parentId = $category->getParentId();

                if ($category_object->parentId != 0) {
                    // Parent Name
                    $category_object->parentName = $category->getParentCategory()->getName();
                }
                array_push($categories, $category_object);
            }
        }

        return $categories;
    }
}
