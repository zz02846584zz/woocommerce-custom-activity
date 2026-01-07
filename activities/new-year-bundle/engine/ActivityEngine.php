<?php
/**
 * 活動引擎
 * 單一職責：管理所有活動的檢測和套用
 */
class NYB_ActivityEngine {

    /**
     * 所有活動實例
     * @var NYB_ActivityInterface[]
     */
    private $activities = [];

    /**
     * 建構子：註冊所有活動
     */
    public function __construct() {
        $this->register_activities();
    }

    /**
     * 註冊所有活動（按優先級排序）
     */
    private function register_activities() {
        $this->activities = [
            new NYB_Activity7(),  // 優先級1
            new NYB_Activity6(),  // 優先級2
            new NYB_Activity5(),  // 優先級3
            new NYB_Activity3(),  // 優先級4
            new NYB_Activity4(),  // 優先級5
            new NYB_Activity2(),  // 優先級6
            new NYB_Activity1(),  // 優先級7
        ];

        // 按優先級排序（確保正確執行順序）
        usort( $this->activities, function( $a, $b ) {
            return $a->get_priority() - $b->get_priority();
        });
    }

    /**
     * 執行活動檢測和套用
     * @param WC_Cart $cart
     */
    public function execute( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        // 防止重複執行
        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
            return;
        }

        if ( ! $cart || $cart->is_empty() ) {
            return;
        }

        $context = array( 'source' => 'newyear-bundle' );

        NYB_Constants::log( "========== 新年活動檢測開始 ==========", $context );

        // 分析購物車內容
        $stats = NYB_CartAnalyzer::analyze();

        NYB_Constants::log( sprintf(
            "[新年活動] 購物車統計 | 嗜睡床墊:%d(可用:%d), 賴床墊:%d(可用:%d), 催眠枕:%d(可用:%d), 床架:%d(可用:%d)",
            $stats['spring_mattress_count'],
            $stats['available']['spring_mattress'],
            $stats['lai_mattress_count'],
            $stats['available']['lai_mattress'],
            $stats['hypnotic_pillow_count'],
            $stats['available']['hypnotic_pillow'],
            $stats['bed_frame_count'],
            $stats['available']['bed_frame']
        ), $context );

        // 按優先級檢查活動並應用
        $applied_activities = [];

        foreach ( $this->activities as $activity ) {
            if ( $activity->is_qualified( $stats ) ) {
                $success = $activity->apply( $cart, $stats, $context );

                if ( $success ) {
                    $applied_activities[] = $activity->get_code();
                }
            }
        }

        NYB_Constants::log( sprintf( "[新年活動] 已應用活動: %s", implode( ', ', $applied_activities ) ), $context );
        NYB_Constants::log( sprintf( "[新年活動] 使用追蹤: %s", json_encode( $stats['usage'], JSON_UNESCAPED_UNICODE ) ), $context );

        // 移除不再符合條件的贈品
        $this->remove_invalid_gifts( $cart, $applied_activities, $context );

        NYB_Constants::log( "========== 新年活動檢測結束 ==========", $context );
    }

    /**
     * 移除不再符合條件的贈品
     * @param WC_Cart $cart
     * @param array $applied_activities
     * @param array $context
     */
    private function remove_invalid_gifts( $cart, $applied_activities, $context ) {
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            // 檢查一般贈品
            if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
                $gift_type = $cart_item['_nyb_auto_gift'];

                // 檢查此贈品是否在已應用的活動中
                if ( ! in_array( $gift_type, $applied_activities ) ) {
                    $cart->remove_cart_item( $cart_item_key );
                    NYB_Constants::log( sprintf( "[新年活動] 移除不符合條件的贈品 | 類型: %s", $gift_type ), $context );
                }
            }

            // 檢查虛擬床包商品
            if ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] === true ) {
                $activity_type = $cart_item['_nyb_activity_type'] ?? '';

                if ( ! in_array( $activity_type, $applied_activities ) ) {
                    $cart->remove_cart_item( $cart_item_key );
                    NYB_Constants::log( sprintf( "[新年活動] 移除不符合條件的虛擬床包 | 類型: %s", $activity_type ), $context );
                }
            }
        }
    }

    /**
     * 獲取所有活動實例
     * @return NYB_ActivityInterface[]
     */
    public function get_activities() {
        return $this->activities;
    }

    /**
     * 根據代碼獲取活動
     * @param string $code
     * @return NYB_ActivityInterface|null
     */
    public function get_activity_by_code( $code ) {
        foreach ( $this->activities as $activity ) {
            if ( $activity->get_code() === $code ) {
                return $activity;
            }
        }
        return null;
    }

    /**
     * 計算所有活動的符合狀態（採用扣減機制模擬實際執行）
     * @param int $product_id 商品ID（用於智慧判斷）
     * @return array
     */
    public function calculate_status( $product_id = 0 ) {
        // 靜態快取
        static $cached_status = null;
        static $cached_cart_hash = null;

        $cart = WC()->cart;
        if ( ! $cart ) {
            return [];
        }

        // 計算購物車 hash
        $cart_contents = $cart->get_cart_contents();
        $cart_hash = md5( serialize( $cart_contents ) );

        // 如果購物車未變更，返回快取結果
        if ( $cached_cart_hash === $cart_hash && $cached_status !== null ) {
            return $cached_status;
        }

        // 分析購物車內容，建立臨時 available 陣列模擬扣減
        $stats = NYB_CartAnalyzer::analyze();
        $maps = NYB_Constants::get_hash_maps();

        // 建立臨時可用數量（模擬扣減）
        $temp_available = $stats['available'];

        $results = [];

        // 按優先級順序檢查活動（與 execute() 一致）
        foreach ( $this->activities as $activity ) {
            $activity_key = $this->get_activity_key_by_code( $activity->get_code() );

            // 檢查是否符合條件（使用臨時可用數量）
            $temp_stats = $stats;
            $temp_stats['available'] = $temp_available;

            if ( $activity->is_qualified( $temp_stats ) ) {
                $results[ $activity_key ] = ['status' => 'qualified', 'missing' => []];

                // 模擬扣減數量
                $this->simulate_consume( $activity, $temp_available, $stats );
            } else {
                // 計算缺少的項目
                $missing = $this->calculate_missing_items( $activity, $temp_stats, $product_id );

                if ( ! empty( $missing ) ) {
                    // 判斷是 almost 還是 not_qualified
                    $status = $this->determine_status( $activity, $stats, $temp_available );
                    $results[ $activity_key ] = ['status' => $status, 'missing' => $missing];
                }
            }
        }

        // 快取結果
        $cached_status = $results;
        $cached_cart_hash = $cart_hash;

        return $results;
    }

    /**
     * 模擬活動消耗數量
     * @param NYB_ActivityInterface $activity
     * @param array &$temp_available
     * @param array $stats
     */
    private function simulate_consume( $activity, &$temp_available, $stats ) {
        $code = $activity->get_code();

        // 根據活動代碼模擬扣減
        switch ( $code ) {
            case 'bundle1': // Activity 1
                $temp_available['spring_mattress'] -= 1;
                $temp_available['hypnotic_pillow'] -= 1;
                break;

            case 'bundle2': // Activity 2
                $temp_available['lai_mattress'] -= 1;
                break;

            case 'bundle3': // Activity 3
                $temp_available['hypnotic_pillow'] -= 2;
                break;

            case 'bundle4': // Activity 4
                $temp_available['hypnotic_pillow'] -= $stats['hypnotic_pillow_count'];
                break;

            case 'bundle5': // Activity 5
                $temp_available['spring_mattress'] -= 1;
                $temp_available['hypnotic_pillow'] -= 2;
                $temp_available['lai_mattress'] -= 1;
                break;

            case 'bundle6': // Activity 6
                $temp_available['spring_mattress'] -= 1;
                $temp_available['bed_frame'] -= 1;
                break;

            case 'bundle7': // Activity 7
                $temp_available['spring_mattress'] -= 1;
                $temp_available['bed_frame'] -= 1;
                $temp_available['hypnotic_pillow'] -= 2;
                break;
        }
    }

    /**
     * 計算缺少的項目
     * @param NYB_ActivityInterface $activity
     * @param array $stats
     * @param int $product_id
     * @return array
     */
    private function calculate_missing_items( $activity, $stats, $product_id ) {
        $missing = [];
        $available = $stats['available'];
        $code = $activity->get_code();

        switch ( $code ) {
            case 'bundle1':
                if ( $available['spring_mattress'] < 1 ) $missing[] = '嗜睡床墊';
                if ( $available['hypnotic_pillow'] < 1 ) $missing[] = '催眠枕';
                break;

            case 'bundle2':
                if ( $available['lai_mattress'] < 1 ) $missing[] = '賴床墊';
                break;

            case 'bundle3':
                $need = 2 - $available['hypnotic_pillow'];
                if ( $need > 0 ) {
                    $missing[] = $need == 1 ? '再1個催眠枕' : '2個催眠枕';
                }
                break;

            case 'bundle4':
                if ( $available['hypnotic_pillow'] < 1 ) $missing[] = '催眠枕';
                break;

            case 'bundle5':
                if ( $available['spring_mattress'] < 1 ) $missing[] = '嗜睡床墊';
                if ( $available['lai_mattress'] < 1 ) $missing[] = '賴床墊';
                if ( $available['hypnotic_pillow'] < 2 ) {
                    $missing[] = sprintf( '催眠枕(需2個，可用%d個)', $available['hypnotic_pillow'] );
                }
                break;

            case 'bundle6':
                if ( $available['spring_mattress'] < 1 ) $missing[] = '嗜睡床墊';
                if ( $available['bed_frame'] < 1 ) $missing[] = '床架';
                break;

            case 'bundle7':
                if ( $available['spring_mattress'] < 1 ) $missing[] = '嗜睡床墊';
                if ( $available['bed_frame'] < 1 ) $missing[] = '床架';
                if ( $available['hypnotic_pillow'] < 2 ) {
                    $missing[] = sprintf( '催眠枕(需2個，可用%d個)', $available['hypnotic_pillow'] );
                }
                break;
        }

        return $missing;
    }

    /**
     * 判斷活動狀態（almost 或 not_qualified）
     * @param NYB_ActivityInterface $activity
     * @param array $stats
     * @param array $temp_available
     * @return string
     */
    private function determine_status( $activity, $stats, $temp_available ) {
        // 如果購物車有相關商品但數量不足，視為 almost
        // 如果完全沒有相關商品，視為 not_qualified

        $code = $activity->get_code();

        switch ( $code ) {
            case 'bundle1':
                $has_some = $stats['spring_mattress_count'] > 0 || $stats['hypnotic_pillow_count'] > 0;
                return $has_some ? 'almost' : 'not_qualified';

            case 'bundle2':
                return 'almost'; // 賴床墊沒有就是差一點

            case 'bundle3':
                return $stats['hypnotic_pillow_count'] > 0 ? 'almost' : 'not_qualified';

            case 'bundle4':
                return 'not_qualified'; // 沒有枕頭就是不符合

            case 'bundle5':
                $has_some = $stats['spring_mattress_count'] > 0 ||
                            $stats['hypnotic_pillow_count'] > 0 ||
                            $stats['lai_mattress_count'] > 0;
                return $has_some ? 'almost' : 'not_qualified';

            case 'bundle6':
                $has_some = $stats['spring_mattress_count'] > 0 || $stats['bed_frame_count'] > 0;
                return $has_some ? 'almost' : 'not_qualified';

            case 'bundle7':
                $has_some = $stats['spring_mattress_count'] > 0 ||
                            $stats['bed_frame_count'] > 0 ||
                            $stats['hypnotic_pillow_count'] > 0;
                return $has_some ? 'almost' : 'not_qualified';

            default:
                return 'not_qualified';
        }
    }

    /**
     * 根據活動代碼獲取活動鍵名
     * @param string $code
     * @return string
     */
    private function get_activity_key_by_code( $code ) {
        $map = [
            'bundle1' => 'activity_1',
            'bundle2' => 'activity_2',
            'bundle3' => 'activity_3',
            'bundle4' => 'activity_4',
            'bundle5' => 'activity_5',
            'bundle6' => 'activity_6',
            'bundle7' => 'activity_7',
        ];

        return isset( $map[ $code ] ) ? $map[ $code ] : $code;
    }

    /**
     * 獲取與指定商品相關的活動
     * @param int $product_id 商品ID
     * @param int $variation_id 變體ID
     * @return array
     */
    public function get_related_activities( $product_id, $variation_id = 0 ) {
        $all_status = $this->calculate_status( $product_id );
        $related = [];
        $maps = NYB_Constants::get_hash_maps();

        $check_id = $variation_id != 0 ? $variation_id : $product_id;

        // 賴床墊相關
        if ( isset( $maps['lai_mattress_vars'][ $check_id ] ) || isset( $maps['lai_mattress_parent'][ $product_id ] ) ) {
            if ( isset( $all_status['activity_2'] ) ) {
                $related[] = ['key' => 'activity_2', 'data' => $all_status['activity_2'], 'priority' => 6];
            }
            if ( isset( $all_status['activity_5'] ) ) {
                $related[] = ['key' => 'activity_5', 'data' => $all_status['activity_5'], 'priority' => 3];
            }
        }

        // 嗜睡床墊相關
        if ( isset( $maps['spring_mattress_vars'][ $check_id ] ) || isset( $maps['spring_mattress_parent'][ $product_id ] ) ) {
            if ( isset( $all_status['activity_1'] ) ) {
                $related[] = ['key' => 'activity_1', 'data' => $all_status['activity_1'], 'priority' => 7];
            }
            if ( isset( $all_status['activity_5'] ) ) {
                $related[] = ['key' => 'activity_5', 'data' => $all_status['activity_5'], 'priority' => 3];
            }
            if ( isset( $all_status['activity_6'] ) ) {
                $related[] = ['key' => 'activity_6', 'data' => $all_status['activity_6'], 'priority' => 2];
            }
            if ( isset( $all_status['activity_7'] ) ) {
                $related[] = ['key' => 'activity_7', 'data' => $all_status['activity_7'], 'priority' => 1];
            }
        }

        // 催眠枕相關
        if ( isset( $maps['hypnotic_pillow_vars'][ $check_id ] ) || $product_id == NYB_Constants::HYPNOTIC_PILLOW_PARENT ) {
            if ( isset( $all_status['activity_1'] ) ) {
                $related[] = ['key' => 'activity_1', 'data' => $all_status['activity_1'], 'priority' => 7];
            }
            if ( isset( $all_status['activity_3'] ) ) {
                $related[] = ['key' => 'activity_3', 'data' => $all_status['activity_3'], 'priority' => 5];
            }
            if ( isset( $all_status['activity_4'] ) ) {
                $related[] = ['key' => 'activity_4', 'data' => $all_status['activity_4'], 'priority' => 4];
            }
            if ( isset( $all_status['activity_5'] ) ) {
                $related[] = ['key' => 'activity_5', 'data' => $all_status['activity_5'], 'priority' => 3];
            }
            if ( isset( $all_status['activity_7'] ) ) {
                $related[] = ['key' => 'activity_7', 'data' => $all_status['activity_7'], 'priority' => 1];
            }
        }

        // 床架相關
        if ( isset( $maps['bed_frame_ids'][ $check_id ] ) || $product_id == NYB_Constants::BED_FRAME_PARENT ) {
            if ( isset( $all_status['activity_6'] ) ) {
                $related[] = ['key' => 'activity_6', 'data' => $all_status['activity_6'], 'priority' => 2];
            }
            if ( isset( $all_status['activity_7'] ) ) {
                $related[] = ['key' => 'activity_7', 'data' => $all_status['activity_7'], 'priority' => 1];
            }
        }

        // 按優先級排序
        usort( $related, function( $a, $b ) {
            return $a['priority'] - $b['priority'];
        });

        return $related;
    }
}

