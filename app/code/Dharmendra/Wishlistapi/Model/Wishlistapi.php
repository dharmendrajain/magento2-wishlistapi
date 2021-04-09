<?php

namespace Dharmendra\Wishlistapi\Model;

use Dharmendra\Wishlistapi\Api\WishlistapiInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory;
use Magento\Wishlist\Model\ItemFactory;

use Magento\Catalog\Helper\ImageFactory as ProductImageHelper;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Wishlist\Controller\WishlistProviderInterface;
use Magento\Wishlist\Model\LocaleQuantityProcessor;
use Magento\Wishlist\Model\Item\OptionFactory;

class Wishlistapi implements WishlistapiInterface
{

    /**
     * @var CollectionFactory
     */
    protected $_wishlistCollectionFactory;

    

    /**
     * @var WishlistRepository
     */
    protected $_wishlistRepository;

    /**
    * @var CustomerRegistry
    */
    protected $customerRegistry;

    /**
     *@var \Magento\Catalog\Model\Product
     */
    protected $_productload;

    /**
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storemanagerinterface;

    /**
     *@var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;

    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @var WishlistProviderInterface
     */
    protected $wishlistProvider;

    /**
     * @var LocaleQuantityProcessor
     */
    protected $quantityProcessor;

    /**
     * @var OptionFactory
     */
    private $optionFactory;

    public function __construct(
        CustomerRegistry $customerRegistry,
        \Magento\Wishlist\Model\WishlistFactory $wishlistRepository,
        CollectionFactory $wishlistCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storemanagerinterface,
        \Magento\Catalog\Model\Product $productload,
        AppEmulation $appEmulation,
        ProductImageHelper $productImageHelper,
        ItemFactory $itemFactory,
        WishlistProviderInterface $wishlistProvider,
        LocaleQuantityProcessor $quantityProcessor,
        OptionFactory $optionFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    )
    {
        $this->customerRegistry = $customerRegistry;
        $this->_wishlistRepository = $wishlistRepository;
        $this->_wishlistCollectionFactory = $wishlistCollectionFactory;
        $this->storemanagerinterface = $storemanagerinterface;
        $this->_productload = $productload;
        $this->appEmulation = $appEmulation;
        $this->productImageHelper = $productImageHelper;
        $this->itemFactory = $itemFactory;
        $this->wishlistProvider = $wishlistProvider;
        $this->quantityProcessor = $quantityProcessor;
        $this->optionFactory = $optionFactory;
        $this->_productRepository = $productRepository;
    }

   /**
    * add to wishlist
    *
    * @api
    * @param int $customerId
    * @param int $productId
    * @return array
    * @throws \Magento\Framework\Exception\NoSuchEntityException If customer with the specified ID does not exist.
    * @throws \Magento\Framework\Exception\LocalizedException
    */

   public function addToWishlist($customerId, $productId)
   {
       if ($productId == null) {
            throw new LocalizedException(__
            ('Invalid product, Please select a valid product'));
        }
        try {
            $product = $this->_productRepository->getById($productId);
        } catch (NoSuchEntityException $e) {
            $product = null;
        }
        try {
            $wishlist = $this->_wishlistRepository->create()->loadByCustomerId
            ($customerId, true);
            $wishlist->addNewItem($product);
            $returnData = $wishlist->save();
        } catch (NoSuchEntityException $e) {
            $message = __('oops something wrong happen.');
            $status = false;

            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        $message = __(' Item has been added in wishlist .');
        $status = true;
        $response[] = [
            "message" => $message,
            "status"  => $status
        ];
        return $response;
   }


   /**
     * Get wishlist collection
     * @deprecated
     * @param int $customerId
     * @return WishlistData
     */
    public function getWishlistForCustomer($customerId)
    {

        if (empty($customerId) || !isset($customerId) || $customerId == "") {
            throw new InputException(__('Id required'));
        } else {
            $collection =
                $this->_wishlistCollectionFactory->create()
                    ->addCustomerIdFilter($customerId);
            $baseurl = $this->storemanagerinterface->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product';
            $wishlistData = [];
            foreach ($collection as $item) {
                $productInfo = $item->getProduct()->toArray();
                if(isset($productInfo['small_image'])){
                    if ($productInfo['small_image'] == 'no_selection') {
                      $currentproduct = $this->_productload->load($productInfo['entity_id']);
                      $imageURL = $this->getImageUrl($currentproduct, 'product_base_image');
                      $productInfo['small_image'] = $imageURL;
                      $productInfo['thumbnail'] = $imageURL;
                    }else{
                      $imageURL = $baseurl.$productInfo['small_image'];
                      $productInfo['small_image'] = $imageURL;
                      $productInfo['thumbnail'] = $imageURL;
                    }
                }
                
                $data = [
                    "wishlist_item_id" => $item->getWishlistItemId(),
                    "wishlist_id"      => $item->getWishlistId(),
                    "product_id"       => $item->getProductId(),
                    "store_id"         => $item->getStoreId(),
                    "added_at"         => $item->getAddedAt(),
                    "description"      => $item->getDescription(),
                    "qty"              => round($item->getQty()),
                    "product"          => $productInfo
                ];
                $wishlistData[] = $data;
            }
            return $wishlistData;
        }
    }

    /**
     * Helper function that provides full cache image url
     * @param \Magento\Catalog\Model\Product
     * @return string
     */
    public function getImageUrl($product, string $imageType = ''){
        $storeId = $this->storemanagerinterface->getStore()->getId();
        $this->appEmulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
        $imageUrl = $this->productImageHelper->create()->init($product, $imageType)->getUrl();
        $this->appEmulation->stopEnvironmentEmulation();

        return $imageUrl;
    }


    /**
     * Get move wishlist item to cart
     * @deprecated
     * @param int $customerId
     * @param int $itemId
     * @param int $qty
     * @return CartData
     */
    public function moveToCart($customerId, $itemId, $qty){

        $response = array();
        /* @var $item \Magento\Wishlist\Model\Item */
        $item = $this->itemFactory->create()->load($itemId);
        if (!$item->getId()) {
            $response['result']['status'] =  false;
            $response['result']['message'] =  __("No Item Available");
            return $response;
        }
        $wishlist = $this->_wishlistRepository->create()->load($item->getWishlistId());
        //echo "<pre>"; print_r($wishlist->getData()); exit;
        if (!$wishlist) {
            $response['result']['status'] = false;
            $response['result']['message'] = __("Oops something bad request.");
            return $response;
        }
        $qty = $this->quantityProcessor->process($qty);
        if ($qty) {
            $item->setQty($qty);
        }

        $options = $this->optionFactory->create()->getCollection()->addItemFilter([$itemId]);
        $item->setOptions($options->getOptionsByItem($itemId));

        /*$buyRequest = $this->productHelper->addParamsToBuyRequest(
            $this->getRequest()->getParams(),
            ['current_config' => $item->getBuyRequest()]
        );

        $item->mergeBuyRequest($buyRequest);*/
        $item->addToCart($this->cart, true);
        $this->cart->save()->getQuote()->collectTotals();
        $wishlist->save();
        echo "customerId: $customerId itemId: $itemId qty: $qty"; exit;
    }


    /**
     * Delete wishlist item for customer
     * @param int $customerId
     * @param int $wishlistItemId
     * @return array
     *
     */
    public function deleteWishlistItemForCustomer($customerId, $wishlistItemId)
    {
        $message = "";
        $status = "";
        if ($wishlistItemId == "") {
            $message = __('No Item');
            $status = false;
            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        $item = $this->itemFactory->create()->load($wishlistItemId);
        if (!$item->getId()) {
            $message = __('No wishlist item exist.');
            $status = false;

            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        $wishlistId = $item->getWishlistId();
        $wishlist = $this->_wishlistRepository->create();

        if ($wishlistId) {
            $wishlist->load($wishlistId);
        } elseif ($customerId) {
            $wishlist->loadByCustomerId($customerId, true);
        }
        if (!$wishlist) {
            $message = __('The requested Wish List Item doesn\'t exist .');
            $status = false;
            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        if (!$wishlist->getId() || $wishlist->getCustomerId() != $customerId) {
            $message = __('The requested Wish List Item doesn\'t exist .');
            $status = false;
            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        try {
            $item->delete();
            $wishlist->save();
        } catch (Exception $e) {
            return false;
        }

        $message = __(' Item has been removed from wishlist .');
        $status = true;
        $response[] = [
            "message" => $message,
            "status"  => $status
        ];
        return $response;
    }
}