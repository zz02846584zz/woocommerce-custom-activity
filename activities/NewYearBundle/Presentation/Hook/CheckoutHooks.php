<?php
/**
 * 結帳相關 Hooks
 *
 * 負責結帳頁面相關的 WordPress/WooCommerce hooks
 */

namespace NewYearBundle\Presentation\Hook;

use NewYearBundle\Presentation\View\GiftSeparatorRenderer;

class CheckoutHooks
{
    public function __construct(
        private GiftSeparatorRenderer $giftSeparatorRenderer
    ) {}

    /**
     * 註冊所有 hooks
     */
    public function register(): void
    {
        // 結帳頁贈品顯示
        add_filter('woocommerce_checkout_cart_item_quantity', [$this, 'displayGiftQuantityOnCheckout'], 10, 3);
    }

    /**
     * 結帳頁顯示贈品標籤
     */
    public function displayGiftQuantityOnCheckout(string $quantityHtml, array $cartItem, string $cartItemKey): string
    {
        $product = $cartItem['data'];

        if ($product->get_meta('_is_free_gift') === 'yes') {
            return $cartItem['quantity'] . ' <span style="color: #df565f; font-size: 0.9em;">(贈品)</span>';
        }

        return $quantityHtml;
    }
}

