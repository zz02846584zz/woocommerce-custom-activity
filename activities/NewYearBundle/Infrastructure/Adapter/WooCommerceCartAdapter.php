<?php

namespace CustomActivity\NewYearBundle\Infrastructure\Adapter;

use CustomActivity\NewYearBundle\Domain\Entity\CartItem;
use CustomActivity\NewYearBundle\Domain\ValueObject\ProductCategory;

/**
 * WooCommerce 購物車適配器
 * 將 WooCommerce 購物車資料轉換為 Domain 物件
 */
final class WooCommerceCartAdapter
{
    private $cart;

    public function __construct($cart)
    {
        $this->cart = $cart;
    }

    /**
     * 取得所有購物車商品（轉換為 Domain Entity）
     *
     * @return CartItem[]
     */
    public function getAllItems(): array
    {
        $items = [];

        foreach ($this->cart->get_cart() as $cartItemKey => $cartItem) {
            $productId = $cartItem['product_id'];
            $variationId = $cartItem['variation_id'] ?? 0;
            $quantity = $cartItem['quantity'];
            $price = $cartItem['data']->get_price();
            $isGift = isset($cartItem['_nyb_auto_gift']);

            // 根據數量展開商品
            for ($i = 0; $i < $quantity; $i++) {
                $items[] = new CartItem(
                    $cartItemKey . '_' . $i,
                    $productId,
                    $variationId,
                    1,
                    $price,
                    $isGift
                );
            }
        }

        return $items;
    }

    /**
     * 取得分類後的購物車商品
     *
     * @return array<string, CartItem[]>
     */
    public function getItemsByCategory(): array
    {
        $categorized = [
            ProductCategory::SPRING_MATTRESS => [],
            ProductCategory::LAI_MATTRESS => [],
            ProductCategory::HYPNOTIC_PILLOW => [],
            ProductCategory::BED_FRAME => [],
        ];

        foreach ($this->getAllItems() as $item) {
            if ($item->isGift()) {
                continue;
            }

            $category = ProductCategory::fromProductIds(
                $item->getProductId(),
                $item->getVariationId()
            );

            if (!$category->isUnknown()) {
                $categorized[$category->getCategory()][] = $item;
            }
        }

        return $categorized;
    }

    /**
     * 加入商品到購物車
     */
    public function addItem(
        int $productId,
        int $quantity,
        int $variationId,
        array $customData
    ): ?string {
        return $this->cart->add_to_cart(
            $productId,
            $quantity,
            $variationId,
            [],
            $customData
        );
    }

    /**
     * 移除購物車商品
     */
    public function removeItem(string $cartItemKey): bool
    {
        // 移除索引後綴（_0, _1 等）
        $originalKey = preg_replace('/_\d+$/', '', $cartItemKey);
        return $this->cart->remove_cart_item($originalKey);
    }

    /**
     * 檢查購物車是否為空
     */
    public function isEmpty(): bool
    {
        return $this->cart->is_empty();
    }

    /**
     * 取得原始 WooCommerce 購物車物件
     */
    public function getWooCommerceCart()
    {
        return $this->cart;
    }
}
