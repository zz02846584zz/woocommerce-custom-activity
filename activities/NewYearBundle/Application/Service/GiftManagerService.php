<?php

namespace CustomActivity\NewYearBundle\Application\Service;

use CustomActivity\NewYearBundle\Domain\Entity\Activity;
use CustomActivity\NewYearBundle\Infrastructure\Adapter\WooCommerceCartAdapter;
use CustomActivity\NewYearBundle\Config\CampaignConfig;

/**
 * 贈品管理服務
 * 負責加入和移除贈品
 */
final class GiftManagerService
{
    /**
     * 為活動加入贈品
     */
    public function addGiftsForActivity(
        Activity $activity,
        WooCommerceCartAdapter $cartAdapter,
        array $categorizedItems
    ): void {
        $gifts = $activity->getGifts();
        $activityKey = $activity->getKey();

        foreach ($gifts as $giftType) {
            $this->addGift($giftType, $activityKey, $cartAdapter, $categorizedItems);
        }
    }

    /**
     * 加入單一贈品
     */
    private function addGift(
        string $giftType,
        string $activityKey,
        WooCommerceCartAdapter $cartAdapter,
        array $categorizedItems
    ): void {
        $cart = $cartAdapter->getWooCommerceCart();

        switch ($giftType) {
            case 'fleece_blanket':
                $this->ensureGiftExists($cart, CampaignConfig::GIFT_FLEECE_BLANKET, 0, $activityKey);
                break;

            case 'side_pillow':
                $this->ensureGiftExists($cart, CampaignConfig::HYPNOTIC_PILLOW_PARENT, CampaignConfig::GIFT_SIDE_PILLOW_VAR, $activityKey);
                break;

            case 'hug_pillow':
                $this->ensureGiftExists($cart, CampaignConfig::GIFT_HUG_PILLOW, 0, $activityKey);
                break;

            case 'eye_mask':
                $this->ensureGiftExists($cart, CampaignConfig::GIFT_EYE_MASK, 0, $activityKey);
                break;

            case 'bedding_set':
                $this->addVirtualBeddingSet($cart, $activityKey, $categorizedItems);
                break;

            case 'same_pillow':
                $this->addSamePillow($cart, $activityKey, $categorizedItems);
                break;

            case 'pillowcase':
                $this->addPillowcase($cart, $activityKey, $categorizedItems);
                break;
        }
    }

    /**
     * 確保贈品存在（避免重複加入）
     */
    private function ensureGiftExists($cart, int $productId, int $variationId, string $bundleKey): void
    {
        // 檢查是否已存在
        foreach ($cart->get_cart() as $cartItem) {
            if (isset($cartItem['_nyb_auto_gift']) &&
                $cartItem['_nyb_auto_gift'] === $bundleKey &&
                $cartItem['product_id'] == $productId &&
                $cartItem['variation_id'] == $variationId) {
                return; // 已存在
            }
        }

        // 加入購物車
        $cart->add_to_cart($productId, 1, $variationId, [], ['_nyb_auto_gift' => $bundleKey]);
    }

    /**
     * 加入虛擬床包商品
     */
    private function addVirtualBeddingSet($cart, string $activityKey, array $categorizedItems): void
    {
        // 取得床墊尺寸
        $springMattresses = $categorizedItems['spring_mattress'] ?? [];

        foreach ($springMattresses as $item) {
            if (!$item->isOccupied() || $item->getOccupiedBy() === $activityKey) {
                $variationId = $item->getVariationId();

                if (isset(CampaignConfig::BEDDING_VALUE_MAP[$variationId])) {
                    // 使用虛擬床包產品類別（需要先實作）
                    \NYB_Virtual_Bedding_Product::add_to_cart($cart, $variationId, $activityKey);
                    break;
                }
            }
        }
    }

    /**
     * 加入相同枕頭（買一送一）
     */
    private function addSamePillow($cart, string $activityKey, array $categorizedItems): void
    {
        $pillows = $categorizedItems['hypnotic_pillow'] ?? [];

        foreach ($pillows as $item) {
            if (!$item->isOccupied() || $item->getOccupiedBy() === $activityKey) {
                $variationId = $item->getVariationId();
                $this->ensureGiftExists($cart, CampaignConfig::HYPNOTIC_PILLOW_PARENT, $variationId, $activityKey);
                break;
            }
        }
    }

    /**
     * 加入天絲枕套
     */
    private function addPillowcase($cart, string $activityKey, array $categorizedItems): void
    {
        $pillows = $categorizedItems['hypnotic_pillow'] ?? [];

        foreach ($pillows as $item) {
            if (!$item->isOccupied() || $item->getOccupiedBy() === $activityKey) {
                $variationId = $item->getVariationId();

                if (isset(CampaignConfig::PILLOWCASE_MAP[$variationId])) {
                    $pillowcaseId = CampaignConfig::PILLOWCASE_MAP[$variationId];
                    $this->ensureGiftExists($cart, CampaignConfig::HYPNOTIC_PILLOW_PARENT, $pillowcaseId, $activityKey);
                    break;
                }
            }
        }
    }

    /**
     * 移除不再符合條件的贈品
     */
    public function removeInvalidGifts(WooCommerceCartAdapter $cartAdapter, array $appliedActivities): void
    {
        $cart = $cartAdapter->getWooCommerceCart();

        foreach ($cart->get_cart() as $cartItemKey => $cartItem) {
            // 檢查一般贈品
            if (isset($cartItem['_nyb_auto_gift'])) {
                $giftType = $cartItem['_nyb_auto_gift'];

                if (!in_array($giftType, $appliedActivities, true)) {
                    $cart->remove_cart_item($cartItemKey);
                }
            }

            // 檢查虛擬床包商品
            if (isset($cartItem['_nyb_virtual_bedding']) && $cartItem['_nyb_virtual_bedding'] === true) {
                $activityType = $cartItem['_nyb_activity_type'] ?? '';

                if (!in_array($activityType, $appliedActivities, true)) {
                    $cart->remove_cart_item($cartItemKey);
                }
            }
        }
    }
}
