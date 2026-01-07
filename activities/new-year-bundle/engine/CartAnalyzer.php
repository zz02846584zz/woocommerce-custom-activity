<?php
/**
 * 購物車分析器
 * 單一職責：分析購物車內容，提供商品統計和數量管理
 */
class NYB_CartAnalyzer {

    /**
     * 分析購物車內容（帶數量追蹤）
     * @return array
     */
    public static function analyze() {
        $cart = WC()->cart;
        $maps = NYB_Constants::get_hash_maps();

        $stats = [
            // 總數量（購買的商品數量）
            'spring_mattress_count' => 0,
            'lai_mattress_count' => 0,
            'hypnotic_pillow_count' => 0,
            'hypnotic_pillow_vars' => [],
            'bed_frame_count' => 0,

            // 可用數量（扣除已被活動使用的數量）
            'available' => [
                'spring_mattress' => 0,
                'lai_mattress' => 0,
                'hypnotic_pillow' => 0,
                'bed_frame' => 0,
            ],

            // 使用追蹤（記錄哪個商品被哪個活動使用）
            'usage' => []
        ];

        foreach ( $cart->get_cart() as $cart_item ) {
            $product_id = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'];
            $quantity = $cart_item['quantity'];

            // 排除自動贈品
            if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
                continue;
            }

            // 嗜睡床墊
            if ( isset( $maps['spring_mattress_vars'][ $variation_id ] ) ) {
                $stats['spring_mattress_count'] += $quantity;
                $stats['available']['spring_mattress'] += $quantity;
            }

            // 賴床墊
            if ( isset( $maps['lai_mattress_vars'][ $variation_id ] ) ) {
                $stats['lai_mattress_count'] += $quantity;
                $stats['available']['lai_mattress'] += $quantity;
            }

            // 催眠枕
            if ( isset( $maps['hypnotic_pillow_vars'][ $variation_id ] ) ) {
                $stats['hypnotic_pillow_count'] += $quantity;
                $stats['available']['hypnotic_pillow'] += $quantity;

                if ( ! isset( $stats['hypnotic_pillow_vars'][ $variation_id ] ) ) {
                    $stats['hypnotic_pillow_vars'][ $variation_id ] = 0;
                }
                $stats['hypnotic_pillow_vars'][ $variation_id ] += $quantity;
            }

            // 床架
            if ( isset( $maps['bed_frame_ids'][ $variation_id ] ) || $product_id == NYB_Constants::BED_FRAME_PARENT ) {
                $stats['bed_frame_count'] += $quantity;
                $stats['available']['bed_frame'] += $quantity;
            }
        }

        return $stats;
    }

    /**
     * 扣減商品使用數量
     * @param array $stats 購物車統計資料
     * @param string $item_type 商品類型
     * @param int $quantity 使用數量
     * @param string $activity 活動代碼
     * @return bool 是否成功扣減
     */
    public static function consume_item( &$stats, $item_type, $quantity, $activity ) {
        if ( ! isset( $stats['available'][ $item_type ] ) ) {
            return false;
        }

        if ( $stats['available'][ $item_type ] < $quantity ) {
            return false;
        }

        $stats['available'][ $item_type ] -= $quantity;

        if ( ! isset( $stats['usage'][ $activity ] ) ) {
            $stats['usage'][ $activity ] = [];
        }

        if ( ! isset( $stats['usage'][ $activity ][ $item_type ] ) ) {
            $stats['usage'][ $activity ][ $item_type ] = 0;
        }

        $stats['usage'][ $activity ][ $item_type ] += $quantity;

        return true;
    }

    /**
     * 在購物車中查找指定產品的贈品
     * @param int $product_id 要查找的產品 ID
     * @param string $metadata_key Metadata key
     * @return array|null 找到的贈品資訊，或 null 未找到
     */
    public static function find_gift_in_cart( $product_id, $metadata_key = '_is_free_gift' ) {
        $cart = WC()->cart;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( $cart_item['product_id'] === $product_id ) {
                $is_gift = isset( $cart_item[ $metadata_key ] ) && $cart_item[ $metadata_key ];

                if ( $is_gift ) {
                    return $cart_item;
                }
            }
        }

        return null;
    }
}

