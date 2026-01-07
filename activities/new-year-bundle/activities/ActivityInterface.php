<?php
/**
 * 活動介面
 * 定義所有活動必須實作的方法
 */
interface NYB_ActivityInterface {

    /**
     * 獲取活動代碼
     * @return string
     */
    public function get_code();

    /**
     * 獲取活動名稱
     * @return string
     */
    public function get_name();

    /**
     * 獲取活動描述
     * @return string
     */
    public function get_description();

    /**
     * 獲取活動優先級（數字越小優先級越高）
     * @return int
     */
    public function get_priority();

    /**
     * 檢查是否符合活動條件
     * @param array $stats 購物車統計
     * @return bool
     */
    public function is_qualified( $stats );

    /**
     * 套用活動
     * @param WC_Cart $cart 購物車物件
     * @param array $stats 購物車統計（引用傳遞，會修改）
     * @param array $context 日誌上下文
     * @return bool 是否成功套用
     */
    public function apply( $cart, &$stats, $context );
}

/**
 * 活動基礎類別
 * 提供共用的輔助方法
 */
abstract class NYB_ActivityBase implements NYB_ActivityInterface {

    /**
     * 檢查贈品是否存在
     * @param WC_Cart $cart
     * @param string $bundle_code
     * @param int|array $product_ids 單一 ID 或 ID 陣列
     * @return bool
     */
    protected function gift_exists( $cart, $bundle_code, $product_ids ) {
        if ( ! is_array( $product_ids ) ) {
            $product_ids = [ $product_ids ];
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['_nyb_auto_gift'] ) &&
                 $cart_item['_nyb_auto_gift'] === $bundle_code &&
                 in_array( $cart_item['product_id'], $product_ids ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 添加贈品到購物車
     * @param WC_Cart $cart
     * @param int $product_id
     * @param int $quantity
     * @param int $variation_id
     * @param string $bundle_code
     * @param array $extra_meta
     * @return bool
     */
    protected function add_gift( $cart, $product_id, $quantity, $variation_id, $bundle_code, $extra_meta = [] ) {
        $meta = array_merge(
            [ '_nyb_auto_gift' => $bundle_code ],
            $extra_meta
        );

        $cart->add_to_cart( $product_id, $quantity, $variation_id, [], $meta );
        NYB_Constants::log( sprintf( "[%s] 自動加入贈品 | Product ID: %s, Variation ID: %s", $this->get_code(), $product_id, $variation_id ) );

        return true;
    }

    /**
     * 將贈品價格設為 0
     * @param WC_Cart $cart
     * @param string $bundle_code
     */
    protected function set_gifts_free( $cart, $bundle_code ) {
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['_nyb_auto_gift'] ) &&
                 $cart_item['_nyb_auto_gift'] === $bundle_code ) {
                $original_price = $cart_item['data']->get_regular_price();
                $cart_item['data']->set_price( 0 );
                $cart_item['data']->add_meta_data( '_is_free_gift', 'yes', true );
                $cart_item['data']->add_meta_data( '_original_price', $original_price, true );
            }
        }
    }
}

