<?php
/**
 * 活動6: 床墊+床架送側睡枕
 */
class NYB_Activity6 extends NYB_ActivityBase {

    public function get_code() {
        return 'bundle6';
    }

    public function get_name() {
        return '嗜睡床墊+床架，送側睡枕';
    }

    public function get_description() {
        return '嗜睡床墊+床架送側睡枕';
    }

    public function get_priority() {
        return 2;
    }

    public function is_qualified( $stats ) {
        return $stats['available']['spring_mattress'] >= 1 &&
               $stats['available']['bed_frame'] >= 1;
    }

    public function apply( $cart, &$stats, $context ) {
        // 扣減數量
        if ( ! NYB_CartAnalyzer::consume_item( $stats, 'spring_mattress', 1, $this->get_code() ) ||
             ! NYB_CartAnalyzer::consume_item( $stats, 'bed_frame', 1, $this->get_code() ) ) {
            return false;
        }

        // 檢查是否已有此贈品
        if ( ! $this->gift_exists( $cart, $this->get_code(), NYB_Constants::HYPNOTIC_PILLOW_PARENT ) ) {
            $this->add_gift(
                $cart,
                NYB_Constants::HYPNOTIC_PILLOW_PARENT,
                1,
                NYB_Constants::GIFT_SIDE_PILLOW_VAR,
                $this->get_code()
            );
        }

        // 將贈品價格設為 0
        $this->set_gifts_free( $cart, $this->get_code() );

        NYB_Constants::log( sprintf( "[%s] 套用成功 | 剩餘: 嗜睡床墊:%d, 床架:%d",
            $this->get_code(),
            $stats['available']['spring_mattress'],
            $stats['available']['bed_frame']
        ), $context );

        return true;
    }
}

