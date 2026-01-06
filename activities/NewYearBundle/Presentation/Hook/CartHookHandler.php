<?php

namespace CustomActivity\NewYearBundle\Presentation\Hook;

use CustomActivity\NewYearBundle\Application\UseCase\ApplyActivitiesUseCase;
use CustomActivity\NewYearBundle\Infrastructure\Adapter\WooCommerceCartAdapter;

/**
 * 購物車 Hook 處理器
 * 負責註冊和處理 WooCommerce 購物車相關的 Hook
 */
final class CartHookHandler
{
    private ApplyActivitiesUseCase $applyActivitiesUseCase;

    public function __construct(ApplyActivitiesUseCase $applyActivitiesUseCase)
    {
        $this->applyActivitiesUseCase = $applyActivitiesUseCase;
    }

    /**
     * 註冊所有 Hook
     */
    public function register(): void
    {
        add_action('woocommerce_before_calculate_totals', [$this, 'handleCartCalculation'], 10);
        add_action('woocommerce_before_calculate_totals', [$this, 'setGiftPricesToZero'], 20);

        // 購物車排序
        add_filter('woocommerce_get_cart_contents', [$this, 'sortCartItems'], 99);

        // 贈品數量控制
        add_filter('woocommerce_cart_item_quantity', [$this, 'disableGiftQuantityInput'], 10, 3);
        add_filter('woocommerce_update_cart_validation', [$this, 'preventGiftQuantityChange'], 10, 4);

        // 購物車樣式
        add_filter('woocommerce_cart_item_class', [$this, 'addGiftItemClass'], 10, 3);
    }

    /**
     * 處理購物車計算（活動檢測）
     */
    public function handleCartCalculation($cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // 防止重複執行
        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }

        if (!$cart || $cart->is_empty()) {
            return;
        }

        $cartAdapter = new WooCommerceCartAdapter($cart);
        $this->applyActivitiesUseCase->execute($cartAdapter);
    }

    /**
     * 將贈品價格設為 0
     */
    public function setGiftPricesToZero($cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cartItem) {
            if (isset($cartItem['_nyb_auto_gift'])) {
                $originalPrice = $cartItem['data']->get_regular_price();
                $cartItem['data']->set_price(0);
                $cartItem['data']->add_meta_data('_is_free_gift', 'yes', true);
                $cartItem['data']->add_meta_data('_original_price', $originalPrice, true);
            }
        }
    }

    /**
     * 購物車排序：贈品放在最後
     */
    public function sortCartItems($cartContents): array
    {
        if (empty($cartContents)) {
            return $cartContents;
        }

        $regularItems = [];
        $giftItems = [];

        foreach ($cartContents as $cartItemKey => $cartItem) {
            if (isset($cartItem['_nyb_auto_gift'])) {
                $giftItems[$cartItemKey] = $cartItem;
            } else {
                $regularItems[$cartItemKey] = $cartItem;
            }
        }

        return array_merge($regularItems, $giftItems);
    }

    /**
     * 禁用贈品數量修改
     */
    public function disableGiftQuantityInput($productQuantity, $cartItemKey, $cartItem): string
    {
        if (isset($cartItem['_nyb_auto_gift'])) {
            return '<span class="quantity" style="color: #999;">' . $cartItem['quantity'] . ' <small>(贈品，數量自動調整)</small></span>';
        }

        return $productQuantity;
    }

    /**
     * 防止手動修改贈品數量
     */
    public function preventGiftQuantityChange($passed, $cartItemKey, $values, $quantity): bool
    {
        $cart = WC()->cart;
        $cartItem = $cart->get_cart()[$cartItemKey];

        if (isset($cartItem['_nyb_auto_gift'])) {
            $currentQty = $cartItem['quantity'];

            if ($quantity != $currentQty) {
                wc_add_notice('贈品數量不可手動修改，將隨購買商品數量自動調整。', 'error');
                return false;
            }
        }

        return $passed;
    }

    /**
     * 為贈品行添加特殊樣式類別
     */
    public function addGiftItemClass($class, $cartItem, $cartItemKey): string
    {
        if (isset($cartItem['_nyb_auto_gift'])) {
            $class .= ' nyb-gift-item';
        }

        return $class;
    }
}
