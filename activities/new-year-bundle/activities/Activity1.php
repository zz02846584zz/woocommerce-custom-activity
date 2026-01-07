<?php
/**
 * 活動1: 嗜睡床墊+催眠枕送茸茸被
 */
class NYB_Activity1 extends NYB_ActivityBase {

    public function get_code() {
        return 'bundle1';
    }

    public function get_name() {
        return '嗜睡床墊+催眠枕，送茸茸被';
    }

    public function get_description() {
        return '嗜睡床墊+催眠枕送茸茸被';
    }

    public function get_priority() {
        return 7;
    }

    public function is_qualified( $stats ) {
        return $stats['available']['spring_mattress'] >= 1 &&
               $stats['available']['hypnotic_pillow'] >= 1;
    }

    public function apply( $cart, &$stats, $context ) {
        // 扣減數量
        if ( ! NYB_CartAnalyzer::consume_item( $stats, 'spring_mattress', 1, $this->get_code() ) ||
             ! NYB_CartAnalyzer::consume_item( $stats, 'hypnotic_pillow', 1, $this->get_code() ) ) {
            return false;
        }

        // 檢查是否已有此贈品
        if ( ! $this->gift_exists( $cart, $this->get_code(), NYB_Constants::GIFT_FLEECE_BLANKET ) ) {
            $this->add_gift( $cart, NYB_Constants::GIFT_FLEECE_BLANKET, 1, 0, $this->get_code() );
        }

        // 將贈品價格設為 0
        $this->set_gifts_free( $cart, $this->get_code() );

        NYB_Constants::log( sprintf( "[%s] 套用成功 | 剩餘: 嗜睡床墊:%d, 催眠枕:%d",
            $this->get_code(),
            $stats['available']['spring_mattress'],
            $stats['available']['hypnotic_pillow']
        ), $context );

        return true;
    }
}

