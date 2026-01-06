<?php
/**
 * 價格相關 Hooks
 *
 * 負責全館9折等價格調整相關的 WordPress/WooCommerce hooks
 * 重構自原模組 2
 */

namespace NewYearBundle\Presentation\Hook;

use NewYearBundle\Infrastructure\WooCommerce\PriceAdapter;
use NewYearBundle\Infrastructure\WordPress\Logger;

class PricingHooks
{
    public function __construct(
        private PriceAdapter $priceAdapter,
				private Logger $logger
    ) {}

    /**
     * 註冊所有 hooks
     */
    public function register(): void
    {
        // 一般商品價格
        add_filter('woocommerce_product_get_price', [$this, 'applySiteWideDiscount'], 99, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'applySiteWideDiscountSale'], 99, 2);

        // 變體商品價格
        add_filter('woocommerce_product_variation_get_price', [$this, 'applySiteWideDiscount'], 99, 2);
        add_filter('woocommerce_product_variation_get_sale_price', [$this, 'applySiteWideDiscountSale'], 99, 2);

        // 顯示全館9折標籤
        add_action('woocommerce_before_single_product', [$this, 'showDiscountBadge'], 5);
    }

    /**
     * 應用全館9折
     */
    public function applySiteWideDiscount(float $price, \WC_Product $product): float
    {
        return $this->priceAdapter->applySiteWideDiscount($price, $product);
    }

    /**
     * 應用全館9折到促銷價
     *
     * @param mixed $salePrice WooCommerce 可能傳遞 float|string|null
     * @param \WC_Product $product
     * @return float
     */
    public function applySiteWideDiscountSale($salePrice, $product)
    {
        return $this->priceAdapter->applySiteWideDiscountToSalePrice($salePrice, $product);
    }

    /**
     * 顯示全館9折標籤
     */
    public function showDiscountBadge(): void
    {
        echo '<div class="nyb-discount-badge" style="background: #df565f; color: white; padding: 8px 15px; display: inline-block; margin-bottom: 15px; border-radius: 5px; font-weight: bold;">🎉 新年優惠：全館9折</div>';
    }
}

