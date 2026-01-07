<?php
/**
 * 贈品管理器
 * 單一職責：管理贈品的顯示、排序和樣式
 */
class NYB_GiftManager {

    /**
     * 初始化
     */
    public static function init() {
        // 購物車排序：贈品放在最後
        add_filter( 'woocommerce_get_cart_contents', [ __CLASS__, 'sort_cart_items' ], 99 );

        // 贈品分隔線
        add_action( 'woocommerce_before_cart_contents', [ __CLASS__, 'inject_gift_separator_script' ] );
        add_action( 'woocommerce_review_order_before_cart_contents', [ __CLASS__, 'inject_gift_separator_script' ] );

        // 贈品樣式
        add_filter( 'woocommerce_cart_item_class', [ __CLASS__, 'add_gift_item_class' ], 10, 3 );
        add_action( 'wp_head', [ __CLASS__, 'gift_separator_styles' ] );

        // 贈品價格顯示
        add_filter( 'woocommerce_cart_item_price', [ __CLASS__, 'display_gift_original_price' ], 1000, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', [ __CLASS__, 'display_gift_original_subtotal' ], 1000, 3 );
        add_filter( 'woocommerce_checkout_cart_item_quantity', [ __CLASS__, 'display_gift_quantity_on_checkout' ], 10, 3 );

        // 禁用贈品數量修改
        add_filter( 'woocommerce_cart_item_quantity', [ __CLASS__, 'disable_gift_quantity_input' ], 10, 3 );
        add_filter( 'woocommerce_update_cart_validation', [ __CLASS__, 'prevent_gift_quantity_change' ], 10, 4 );

        // 將贈品資訊存入訂單項目
        add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_gift_meta_to_order_item' ], 10, 4 );
    }

    /**
     * 購物車排序：贈品放在最後
     */
    public static function sort_cart_items( $cart_contents ) {
        if ( empty( $cart_contents ) ) {
            return $cart_contents;
        }

        $regular_items = [];
        $gift_items = [];

        foreach ( $cart_contents as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
                $gift_items[ $cart_item_key ] = $cart_item;
            } else {
                $regular_items[ $cart_item_key ] = $cart_item;
            }
        }

        return array_merge( $regular_items, $gift_items );
    }

    /**
     * 注入贈品分隔線腳本
     */
    public static function inject_gift_separator_script() {
        static $script_added = false;

        if ( $script_added ) {
            return;
        }
        $script_added = true;

        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function addGiftSeparator() {
                $('.nyb-gift-separator-row').remove();

                var firstGiftCart = $('.woocommerce-cart-form__cart-item.nyb-gift-item').first();
                if (firstGiftCart.length > 0) {
                    var separator = '<tr class="nyb-gift-separator-row">' +
                        '<td colspan="6" class="nyb-gift-separator" style="padding: 20px 0 15px 0; border-top: 2px dashed #ddd; border-bottom: none;">' +
                        '<div style="text-align: center; position: relative; margin-top: -10px;">' +
                        '<span style="background: #fff; padding: 5px 20px; color: #df565f; font-weight: bold; font-size: 14px; display: inline-block; border: 2px solid #df565f; border-radius: 20px;">' +
                        '🎁 以下為活動贈品' +
                        '</span>' +
                        '</div>' +
                        '</td>' +
                        '</tr>';

                    firstGiftCart.before(separator);
                }

                var firstGiftCheckout = $('.woocommerce-checkout-review-order-table .nyb-gift-item').first();
                if (firstGiftCheckout.length > 0) {
                    var checkoutSeparator = '<tr class="nyb-gift-separator-row">' +
                        '<td colspan="3" class="nyb-gift-separator" style="padding: 15px 0 10px 0; border-top: 2px dashed #ddd; border-bottom: none;">' +
                        '<div style="text-align: center;">' +
                        '<span style="background: #fff; padding: 4px 15px; color: #df565f; font-weight: bold; font-size: 13px; display: inline-block; border: 2px solid #df565f; border-radius: 15px;">' +
                        '🎁 活動贈品' +
                        '</span>' +
                        '</div>' +
                        '</td>' +
                        '</tr>';

                    firstGiftCheckout.before(checkoutSeparator);
                }
            }

            addGiftSeparator();

            $(document.body).on('updated_cart_totals updated_checkout', function() {
                addGiftSeparator();
            });
        });
        </script>
        <?php
    }

    /**
     * 為贈品行添加特殊樣式類別
     */
    public static function add_gift_item_class( $class, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            $class .= ' nyb-gift-item';
        }
        return $class;
    }

    /**
     * 添加購物車贈品區域的 CSS 樣式
     */
    public static function gift_separator_styles() {
        if ( ! is_cart() && ! is_checkout() ) {
            return;
        }

        ?>
        <style type="text/css">
            .nyb-gift-item .product-thumbnail {
                position: relative;
            }

            .nyb-gift-item .product-thumbnail::after {
                content: '🎁';
                position: absolute;
                top: 5px;
                right: 5px;
                background: #df565f;
                color: white;
                border-radius: 3px;
                font-size: 12px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                width: 28px;
                height: 28px;
                display: flex;
                align-items: center;
                justify-content: center;
                text-shadow: 1px 1px 10px #ff9f9f;
            }

            @media (max-width: 768px) {
                .nyb-gift-separator {
                    padding: 15px 0 10px 0 !important;
                }

                .nyb-gift-separator span {
                    font-size: 12px !important;
                    padding: 4px 15px !important;
                }

                .nyb-gift-item {
                    border-left-width: 2px !important;
                }
            }
        </style>
        <?php
    }

    /**
     * 顯示贈品標籤和原價
     */
    public static function display_gift_original_price( $price, $cart_item, $cart_item_key ) {
        $product = $cart_item['data'];

        if ( $product->get_meta( '_is_free_gift' ) === 'yes' ) {
            $original_price = $product->get_meta( '_original_price' );
            if ( $original_price ) {
                return '<del>' . wc_price( $original_price ) . '</del> <ins>' . wc_price( 0 ) . '</ins><br><span style="color: #df565f; font-weight: bold;">🎁 免費贈送</span>';
            }
        }

        return $price;
    }

    /**
     * 顯示小計（購物車頁）
     */
    public static function display_gift_original_subtotal( $subtotal, $cart_item, $cart_item_key ) {
        $product = $cart_item['data'];

        if ( $product->get_meta( '_is_free_gift' ) === 'yes' ) {
            $original_price = $product->get_meta( '_original_price' );
            if ( $original_price ) {
                $original_subtotal = $original_price * $cart_item['quantity'];
                return '<del>' . wc_price( $original_subtotal ) . '</del> <ins>' . wc_price( 0 ) . '</ins>';
            }
        }

        return $subtotal;
    }

    /**
     * 結帳頁顯示贈品標籤
     */
    public static function display_gift_quantity_on_checkout( $quantity_html, $cart_item, $cart_item_key ) {
        $product = $cart_item['data'];

        if ( $product->get_meta( '_is_free_gift' ) === 'yes' ) {
            return $cart_item['quantity'] . ' <span style="color: #df565f; font-size: 0.9em;">(贈品)</span>';
        }

        return $quantity_html;
    }

    /**
     * 禁用贈品數量修改
     */
    public static function disable_gift_quantity_input( $product_quantity, $cart_item_key, $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            return '<span class="quantity" style="color: #999;">' . $cart_item['quantity'] . ' <small>(贈品，數量自動調整)</small></span>';
        }

        return $product_quantity;
    }

    /**
     * 防止手動修改贈品數量
     */
    public static function prevent_gift_quantity_change( $passed, $cart_item_key, $values, $quantity ) {
        $cart = WC()->cart;
        $cart_item = $cart->get_cart()[ $cart_item_key ];

        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            $current_qty = $cart_item['quantity'];

            if ( $quantity != $current_qty ) {
                wc_add_notice( '贈品數量不可手動修改，將隨購買商品數量自動調整。', 'error' );
                return false;
            }
        }

        return $passed;
    }

    /**
     * 將贈品資訊存入訂單項目
     */
    public static function save_gift_meta_to_order_item( $item, $cart_item_key, $values, $order ) {
        $product = $values['data'];

        if ( $product->get_meta( '_is_free_gift' ) === 'yes' ) {
            $item->add_meta_data( '贈品', '免費贈送 🎁', true );
            $original_price = $product->get_meta( '_original_price' );
            if ( $original_price ) {
                $item->add_meta_data( '_gift_original_price', $original_price, true );
            }
        }
    }
}

