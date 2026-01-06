<?php
/**
 * 購物車適配器
 *
 * 包裝 WooCommerce 購物車 API
 * 提供領域層需要的購物車操作介面
 */

namespace NewYearBundle\Infrastructure\WooCommerce;

use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class CartAdapter
{
    public function __construct(
        private Logger $logger
    ) {}

    /**
     * 獲取 WooCommerce 購物車實例
     */
    public function getCart(): ?\WC_Cart
    {
        if (!function_exists('WC') || !WC()->cart) {
            return null;
        }
        return WC()->cart;
    }

    /**
     * 檢查購物車是否為空
     */
    public function isEmpty(): bool
    {
        $cart = $this->getCart();
        return !$cart || $cart->is_empty();
    }

    /**
     * 添加贈品到購物車
     */
    public function addGift(int $productId, int $variationId, string $giftType): bool
    {
        if ($this->hasGift($productId, $variationId, $giftType)) {
            $this->logger->debug("贈品已存在，跳過添加 | Product: {$productId}, Type: {$giftType}");
            return false;
        }

        $cart = $this->getCart();
        if (!$cart) {
            $this->logger->error("購物車不存在，無法添加贈品");
            return false;
        }

        $result = $cart->add_to_cart(
            $productId,
            1,
            $variationId,
            [],
            ['_nyb_auto_gift' => $giftType]
        );

        if ($result) {
            $this->logger->info("成功添加贈品 | Product: {$productId}, Variation: {$variationId}, Type: {$giftType}");
        } else {
            $this->logger->warning("添加贈品失敗 | Product: {$productId}, Type: {$giftType}");
        }

        return (bool)$result;
    }

    /**
     * 檢查購物車中是否已有指定贈品
     */
    public function hasGift(int $productId, int $variationId, string $giftType): bool
    {
        $cart = $this->getCart();
        if (!$cart) {
            return false;
        }

        foreach ($cart->get_cart() as $cartItem) {
            if (isset($cartItem['_nyb_auto_gift']) &&
                $cartItem['_nyb_auto_gift'] === $giftType &&
                $cartItem['product_id'] === $productId) {

                // 如果指定了變體ID，也要匹配變體
                if ($variationId > 0 && $cartItem['variation_id'] !== $variationId) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * 設置贈品價格為0
     */
    public function setGiftPriceFree(string $cartItemKey): void
    {
        $cart = $this->getCart();
        if (!$cart || !isset($cart->cart_contents[$cartItemKey])) {
            $this->logger->warning("購物車項目不存在: {$cartItemKey}");
            return;
        }

        $cartItem = $cart->cart_contents[$cartItemKey];
        $originalPrice = $cartItem['data']->get_regular_price();

        $cartItem['data']->set_price(0);
        $cartItem['data']->add_meta_data('_is_free_gift', 'yes', true);
        $cartItem['data']->add_meta_data('_original_price', $originalPrice, true);

        $this->logger->debug("設置贈品價格為0 | Key: {$cartItemKey}, 原價: {$originalPrice}");
    }

    /**
     * 遍歷購物車項目並對贈品設置免費
     */
    public function setAllGiftsFree(string $giftType): void
    {
        $cart = $this->getCart();
        if (!$cart) {
            return;
        }

        foreach ($cart->get_cart() as $cartItemKey => $cartItem) {
            if (isset($cartItem['_nyb_auto_gift']) && $cartItem['_nyb_auto_gift'] === $giftType) {
                $this->setGiftPriceFree($cartItemKey);
            }
        }
    }

    /**
     * 移除指定類型的贈品
     */
    public function removeGift(string $giftType): void
    {
        $cart = $this->getCart();
        if (!$cart) {
            return;
        }

        foreach ($cart->get_cart() as $cartItemKey => $cartItem) {
            if (isset($cartItem['_nyb_auto_gift']) && $cartItem['_nyb_auto_gift'] === $giftType) {
                $cart->remove_cart_item($cartItemKey);
                $this->logger->info("移除贈品 | Type: {$giftType}");
            }
        }
    }

    /**
     * 移除所有不在指定列表中的贈品
     */
    public function removeInvalidGifts(array $validGiftTypes): void
    {
        $cart = $this->getCart();
        if (!$cart) {
            return;
        }

        $removed = [];

        foreach ($cart->get_cart() as $cartItemKey => $cartItem) {
            // 檢查一般贈品
            if (isset($cartItem['_nyb_auto_gift'])) {
                $giftType = $cartItem['_nyb_auto_gift'];

                if (!in_array($giftType, $validGiftTypes, true)) {
                    $cart->remove_cart_item($cartItemKey);
                    $removed[] = $giftType;
                }
            }

            // 檢查虛擬床包商品
            if (isset($cartItem['_nyb_virtual_bedding']) && $cartItem['_nyb_virtual_bedding'] === true) {
                $activityType = $cartItem['_nyb_activity_type'] ?? '';

                if (!in_array($activityType, $validGiftTypes, true)) {
                    $cart->remove_cart_item($cartItemKey);
                    $removed[] = $activityType . '(虛擬床包)';
                }
            }
        }

        if (!empty($removed)) {
            $this->logger->info("移除無效贈品: " . implode(', ', $removed));
        }
    }

    /**
     * 添加購物車費用（用於折扣）
     */
    public function addFee(string $name, float $amount): void
    {
        $cart = $this->getCart();
        if (!$cart) {
            return;
        }

        // 移除舊的同名費用
        $this->removeFee($name);

        $cart->add_fee($name, $amount);
        $this->logger->info("添加購物車費用 | Name: {$name}, Amount: {$amount}");
    }

    /**
     * 移除購物車費用
     */
    public function removeFee(string $name): void
    {
        $cart = $this->getCart();
        if (!$cart) {
            return;
        }

        foreach ($cart->get_fees() as $feeKey => $fee) {
            if ($fee->name === $name) {
                $cart->remove_fee($fee->name);
                $this->logger->debug("移除購物車費用 | Name: {$name}");
            }
        }
    }

    /**
     * 獲取購物車內容
     */
    public function getCartContents(): array
    {
        $cart = $this->getCart();
        if (!$cart) {
            return [];
        }

        return $cart->get_cart();
    }
}

