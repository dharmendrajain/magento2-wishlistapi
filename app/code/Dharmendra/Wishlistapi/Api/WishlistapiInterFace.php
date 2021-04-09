<?php
namespace Dharmendra\Wishlistapi\Api;

interface WishlistapiInterface
{
   /**
    * add products to wishlist
    *
    * @api
    * @param int $customerId
    * @param int $productId
    * @return array
    * @throws \Magento\Framework\Exception\NoSuchEntityException If customer with the specified ID does not exist.
    * @throws \Magento\Framework\Exception\LocalizedException
    */
   public function addToWishlist($customerId, $productId);

   /**
     * Return Wishlist items.
     *
     * @param int $customerId
     * @return array
     */
    public function getWishlistForCustomer($customerId);


    /**
    * Return Move Wishlist Item To Cart.
    *
    * @param int $customerId
    * @param int $itemId
    * @param int $qty
    * @return array
    */
    public function moveToCart($customerId, $itemId, $qty);

    /**
     * Delete wishlist item for customer
     * @param int $customerId
     * @param int $wishlistItemId
     * @return array
     *
     */
    public function deleteWishlistItemForCustomer($customerId, $wishlistItemId);

}