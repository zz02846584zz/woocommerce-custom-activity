<?php
/**
 * 購物車相關 Hooks
 *
 * 負責購物車相關的 WordPress/WooCommerce hooks
 */

namespace NewYearBundle\Presentation\Hook;

use NewYearBundle\Application\UseCase\ApplyActivitiesOrchestrator;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;
use NewYearBundle\Presentation\View\GiftSeparatorRenderer;
use NewYearBundle\Infrastructure\WordPress\Logger;

class CartHooks
{
    public function __construct(
        private ApplyActivitiesOrchestrator $orchestrator,
        private CartAdapter $cartAdapter,
        private GiftSeparatorRenderer $giftSeparatorRenderer,
        private Logger $logger
    ) {}

    /**
     * 註冊所有 hooks
     */
    public function register(): void
    {
        // 活動檢測引擎
        add_action('woocommerce_before_calculate_totals', [$this, 'applyActivities'], 10);

        // 購物車排序：贈品放最後
        add_filter('woocommerce_get_cart_contents', [$this, 'sortCartItems'], 99);

        // 贈品分隔線
        add_action('woocommerce_before_cart_contents', [$this, 'injectGiftSeparatorScript']);
        add_action('woocommerce_review_order_before_cart_contents', [$this, 'injectGiftSeparatorScript']);

        // 贈品樣式類別
        add_filter('woocommerce_cart_item_class', [$this, 'addGiftItemClass'], 10, 3);

        // 贈品價格顯示
        add_filter('woocommerce_cart_item_price', [$this, 'displayGiftOriginalPrice'], 1000, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'displayGiftOriginalSubtotal'], 1000, 3);

        // 贈品數量控制
        add_filter('woocommerce_cart_item_quantity', [$this, 'disableGiftQuantityInput'], 10, 3);
        add_filter('woocommerce_update_cart_validation', [$this, 'preventGiftQuantityChange'], 10, 4);

        // 贈品樣式CSS
        add_action('wp_head', [$this, 'addGiftSeparatorStyles']);
    }

    /**
     * 應用活動
     */
    public function applyActivities(\WC_Cart $cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // 防止重複執行
        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }

        try {
            $this->orchestrator->execute($cart);
        } catch (\Exception $e) {
            $this->logger->error('[CartHooks] 活動應用失敗: ' . $e->getMessage());
        }
    }

    /**
     * 排序購物車項目：贈品放最後
     */
    public function sortCartItems(array $cartContents): array
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
     * 注入贈品分隔線腳本
     */
    public function injectGiftSeparatorScript(): void
    {
        $this->giftSeparatorRenderer->renderScript();
    }

    /**
     * 添加贈品樣式類別
     */
    public function addGiftItemClass(string $class, array $cartItem, string $cartItemKey): string
    {
        if (isset($cartItem['_nyb_auto_gift'])) {
            $class .= ' nyb-gift-item';
        }
        return $class;
    }

    /**
     * 顯示贈品原價
     */
    public function displayGiftOriginalPrice(string $price, array $cartItem, string $cartItemKey): string
    {
        $product = $cartItem['data'];

        if ($product->get_meta('_is_free_gift') === 'yes') {
            $originalPrice = $product->get_meta('_original_price');
            if ($originalPrice) {
                return '<del>' . wc_price($originalPrice) . '</del> <ins>' . wc_price(0) . '</ins><br><span style="color: #df565f; font-weight: bold;">🎁 免費贈送</span>';
            }
        }

        return $price;
    }

    /**
     * 顯示贈品原小計
     */
    public function displayGiftOriginalSubtotal(string $subtotal, array $cartItem, string $cartItemKey): string
    {
        $product = $cartItem['data'];

        if ($product->get_meta('_is_free_gift') === 'yes') {
            $originalPrice = $product->get_meta('_original_price');
            if ($originalPrice) {
                $originalSubtotal = $originalPrice * $cartItem['quantity'];
                return '<del>' . wc_price($originalSubtotal) . '</del> <ins>' . wc_price(0) . '</ins>';
            }
        }

        return $subtotal;
    }

    /**
     * 禁用贈品數量輸入
     */
    public function disableGiftQuantityInput(string $productQuantity, string $cartItemKey, array $cartItem): string
    {
        if (isset($cartItem['_nyb_auto_gift'])) {
            return '<span class="quantity" style="color: #999;">' . $cartItem['quantity'] . ' <small>(贈品，數量自動調整)</small></span>';
        }

        return $productQuantity;
    }

    /**
     * 防止手動修改贈品數量
     */
    public function preventGiftQuantityChange(bool $passed, string $cartItemKey, array $values, int $quantity): bool
    {
        $cart = $this->cartAdapter->getCart();
        if (!$cart) {
            return $passed;
        }

        $cartItem = $cart->get_cart()[$cartItemKey] ?? null;

        if ($cartItem && isset($cartItem['_nyb_auto_gift'])) {
            $currentQty = $cartItem['quantity'];

            if ($quantity != $currentQty) {
                wc_add_notice('贈品數量不可手動修改，將隨購買商品數量自動調整。', 'error');
                return false;
            }
        }

        return $passed;
    }

    /**
     * 添加贈品樣式CSS
     */
    public function addGiftSeparatorStyles(): void
    {
        $this->giftSeparatorRenderer->renderStyles();
    }
}

