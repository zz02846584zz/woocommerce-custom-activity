<?php
/**
 * 活動2: 賴床墊送抱枕+眼罩
 */
class NYB_Activity2 extends NYB_ActivityBase {

    public function get_code() {
        return 'bundle2';
    }

    public function get_name() {
        return '賴床墊送抱枕+眼罩';
    }

    public function get_description() {
        return '賴床墊送抱枕+眼罩';
    }

    public function get_priority() {
        return 6;
    }

    public function is_qualified( $stats ) {
        return $stats['available']['lai_mattress'] >= 1;
    }

    public function apply( $cart, &$stats, $context ) {
        // 扣減數量
        if ( ! NYB_CartAnalyzer::consume_item( $stats, 'lai_mattress', 1, $this->get_code() ) ) {
            return false;
        }

        $gifts_needed = [
            NYB_Constants::GIFT_HUG_PILLOW,
            NYB_Constants::GIFT_EYE_MASK
        ];

        // 檢查並添加缺少的贈品
        foreach ( $gifts_needed as $gift_id ) {
            if ( ! $this->gift_exists( $cart, $this->get_code(), $gift_id ) ) {
                $this->add_gift( $cart, $gift_id, 1, 0, $this->get_code() );
            }
        }

        // 將贈品價格設為 0
        $this->set_gifts_free( $cart, $this->get_code() );

        NYB_Constants::log( sprintf( "[%s] 套用成功 | 剩餘: 賴床墊:%d",
            $this->get_code(),
            $stats['available']['lai_mattress']
        ), $context );

        return true;
    }
}

