<?php

namespace CustomActivity\NewYearBundle\Domain\Repository;

use CustomActivity\NewYearBundle\Domain\Entity\CartItem;

/**
 * 購物車倉儲介面
 * 定義購物車資料存取的契約
 */
interface CartRepositoryInterface
{
    /**
     * 取得所有購物車商品
     * @return CartItem[]
     */
    public function getAllItems(): array;

    /**
     * 取得分類後的購物車商品
     * @return array<string, CartItem[]>
     */
    public function getItemsByCategory(): array;

    /**
     * 加入商品到購物車
     */
    public function addItem(
        int $productId,
        int $quantity,
        int $variationId,
        array $customData
    ): ?string;

    /**
     * 移除購物車商品
     */
    public function removeItem(string $cartItemKey): bool;

    /**
     * 檢查購物車是否為空
     */
    public function isEmpty(): bool;

    /**
     * 取得購物車中的贈品
     * @return CartItem[]
     */
    public function getGiftItems(): array;

    /**
     * 移除指定活動的贈品
     */
    public function removeGiftsByActivity(string $activityKey): void;
}
