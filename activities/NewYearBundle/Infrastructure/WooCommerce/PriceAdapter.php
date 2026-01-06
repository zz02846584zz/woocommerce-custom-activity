<?php
/**
 * 價格適配器
 *
 * 包裝 WooCommerce 價格相關操作
 * 負責全館9折等價格調整邏輯
 */

namespace NewYearBundle\Infrastructure\WooCommerce;

use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class PriceAdapter
{
    public function __construct(
        private Logger $logger
    ) {}

    /**
     * 應用全館9折（排除贈品）
     */
    public function applySiteWideDiscount(float $price, \WC_Product $product): float
    {
        // 檢查是否為贈品
        $isFreeGift = $product->get_meta('_is_free_gift');
        if ($isFreeGift === 'yes') {
            return 0;
        }

        // 獲取原價
        $regularPrice = $product->get_regular_price();
        if (!$regularPrice) {
            return $price;
        }

        // 返回9折價格
        return $regularPrice * 0.9;
    }

    /**
     * 應用全館9折到促銷價
     *
     * @param mixed $salePrice WooCommerce 可能傳遞 float|string|null
     * @param \WC_Product $product
     * @return float
     */
    public function applySiteWideDiscountToSalePrice($salePrice, \WC_Product $product)
    {
        $regularPrice = $product->get_regular_price();
        if ($regularPrice) {
            return (float) $regularPrice * 0.9;
        }

        return (float) ($salePrice ?? 0);
    }

    /**
     * 檢查商品是否應排除折扣（贈品）
     */
    public function shouldExcludeFromDiscount(\WC_Product $product): bool
    {
        $productId = $product->get_id();
        $parentId = $product->get_parent_id();

        $giftIdsMap = Config::getAllGiftIdsMap();

        return isset($giftIdsMap[$productId]) || isset($giftIdsMap[$parentId]);
    }

    /**
     * 設置商品為免費贈品
     */
    public function setProductAsFreeGift(\WC_Product $product, float $originalPrice): void
    {
        $product->set_price(0);
        $product->add_meta_data('_is_free_gift', 'yes', true);
        $product->add_meta_data('_original_price', $originalPrice, true);

        $this->logger->debug("設置商品為免費贈品 | Product ID: {$product->get_id()}, 原價: {$originalPrice}");
    }

    /**
     * 獲取商品原始價格（用於顯示劃線價）
     */
    public function getOriginalPrice(\WC_Product $product): float
    {
        $originalPrice = $product->get_meta('_original_price');
        if ($originalPrice) {
            return (float)$originalPrice;
        }

        return (float)$product->get_regular_price();
    }
}

