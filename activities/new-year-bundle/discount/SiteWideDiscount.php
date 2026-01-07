<?php
/**
 * 全館9折管理器
 * 單一職責：管理全館9折功能
 */
class NYB_SiteWideDiscount {

    /**
     * 初始化
     */
    public static function init() {
        // 一般商品
        add_filter( 'woocommerce_product_get_price', [ __CLASS__, 'apply_discount' ], 99, 2 );
        add_filter( 'woocommerce_product_get_sale_price', [ __CLASS__, 'apply_discount_sale' ], 99, 2 );

        // 變體商品
        add_filter( 'woocommerce_product_variation_get_price', [ __CLASS__, 'apply_discount' ], 99, 2 );
        add_filter( 'woocommerce_product_variation_get_sale_price', [ __CLASS__, 'apply_discount_sale' ], 99, 2 );

        // 在商品頁顯示「全館9折」標籤
        add_action( 'woocommerce_before_single_product', [ __CLASS__, 'show_discount_badge' ], 5 );
    }

    /**
     * 套用9折
     */
    public static function apply_discount( $price, $product ) {
        // 如果是免費贈品，返回0
        $is_free_gift = $product->get_meta( '_is_free_gift' );
        if( $is_free_gift === 'yes' ) {
            return 0;
        }

        // 否則返回原價的9折
        $regular_price = $product->get_regular_price();
        if ( $regular_price ) {
            return $regular_price * 0.9;
        }

        return $price;
    }

    /**
     * 套用9折（促銷價）
     */
    public static function apply_discount_sale( $sale_price, $product ) {
        $regular_price = $product->get_regular_price();
        if ( $regular_price ) {
            return $regular_price * 0.9;
        }

        return $sale_price;
    }

    /**
     * 顯示全館9折標籤
     */
    public static function show_discount_badge() {
        echo '<div class="nyb-discount-badge" style="background: #df565f; color: white; padding: 8px 15px; display: inline-block; margin-bottom: 15px; border-radius: 5px; font-weight: bold;">🎉 新年優惠：全館9折</div>';
    }
}

