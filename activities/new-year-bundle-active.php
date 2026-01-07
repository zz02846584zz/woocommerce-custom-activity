<?php
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'helpers/class-activity-coupon-display.php';
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'helpers/class-virtual-bedding-product.php';

NYB_Activity_Coupon_Display::init();
NYB_Virtual_Bedding_Product::init();

/**
 * =======================================================
 * 模組 1：基礎設定與常數定義
 * =======================================================
 * ⚡ 性能優化：使用 Hash Map 替代 in_array 查詢
 */

// 活動期間
define( 'NYB_CAMPAIGN_START', '2025-01-05 00:00:00' );
define( 'NYB_CAMPAIGN_END', '2026-02-28 23:59:59' );

// 日誌開關（生產環境建議設為 false）
define( 'NYB_DEBUG_MODE', true );
define( 'NYB_GLOBAL_DISCOUNT', 0.9 );

// 床墊相關
define( 'NYB_LAI_MATTRESS_PARENT_IDS', [3444] ); // 所有賴床墊父層ID
define( 'NYB_LAI_MATTRESS_VARS', [3446, 3445, 3447, 3448, 3695, 3696] ); // 賴床墊
define( 'NYB_SPRING_MATTRESS_PARENT_IDS', [1324, 4370] ); // 所有嗜睡床墊父層ID
define( 'NYB_SPRING_MATTRESS_VARS', [
    2735, 2736, 2737, 2738, 2739,      // 嗜睡床墊(大地系列)
    4371, 4372, 4373, 4374, 4375       // 嗜睡床墊(海洋系列)
] );

// ⚡ 性能優化：Hash Map (O(1) 查詢速度)
define( 'NYB_LAI_MATTRESS_PARENT_IDS_MAP', array_flip( NYB_LAI_MATTRESS_PARENT_IDS ) );
define( 'NYB_LAI_MATTRESS_VARS_MAP', array_flip( NYB_LAI_MATTRESS_VARS ) );
define( 'NYB_SPRING_MATTRESS_PARENT_IDS_MAP', array_flip( NYB_SPRING_MATTRESS_PARENT_IDS ) );
define( 'NYB_SPRING_MATTRESS_VARS_MAP', array_flip( NYB_SPRING_MATTRESS_VARS ) );

// 床墊尺寸對應天絲床包價值
define( 'NYB_BEDDING_VALUE_MAP', [
    2735 => 3680,  // 單人
    4371 => 3680,
    2736 => 3880,  // 單人加大
    4372 => 3880,
    2737 => 4580,  // 雙人
    4373 => 4580,
    2738 => 4780,  // 雙人加大
    4374 => 4780,
    2739 => 4980,  // 雙人特大
    4375 => 4980,
] );

// 催眠枕
define( 'NYB_HYPNOTIC_PILLOW_PARENT', 1307 );
define( 'NYB_HYPNOTIC_PILLOW_VARS', [2983, 2984, 3044] );
define( 'NYB_HYPNOTIC_PILLOW_VARS_MAP', array_flip( NYB_HYPNOTIC_PILLOW_VARS ) );

// 天絲枕套對應 (枕頭 -> 枕套)
define( 'NYB_PILLOWCASE_MAP', [
    2983 => 4439,
    2984 => 5663,
    3044 => 5662
] );

define( 'NYB_ALL_ACTIVITY_MAP', array_merge( NYB_LAI_MATTRESS_PARENT_IDS_MAP, NYB_LAI_MATTRESS_VARS_MAP, NYB_SPRING_MATTRESS_PARENT_IDS_MAP, NYB_SPRING_MATTRESS_VARS_MAP, NYB_HYPNOTIC_PILLOW_VARS_MAP ) );

// 贈品
define( 'NYB_GIFT_FLEECE_BLANKET', 4180 );  // 茸茸被
define( 'NYB_GIFT_HUG_PILLOW', 6346 );      // 抱枕
define( 'NYB_GIFT_EYE_MASK', 6300 );        // 眼罩

// 滿額贈門檻
define( 'NYB_THRESHOLD_AMOUNT', 50000 );

// 活動映射配置（統一管理）
define( 'NYB_ACTIVITY_MAP', [
    'activity_1' => [
        'coupon_code' => 'nyb_activity_1',
        'name' => '買催眠枕送天絲枕套一件（買一送一）',
        'short_name' => '購買催眠枕送天絲枕套（限一件）',
        'description' => '購買任一款催眠枕，即可獲得配對天絲枕套一件',
        'priority' => 4
    ],
    'activity_2' => [
        'coupon_code' => 'nyb_activity_2',
        'name' => '嗜睡床墊任一張+催眠枕任一顆，再送兩用茸茸被一件',
        'short_name' => '購買床墊+催眠枕送兩用茸茸被',
        'description' => '購買床墊（嗜睡或賴床墊）搭配催眠枕，贈送兩用茸茸被',
        'priority' => 3
    ],
    'activity_3' => [
        'coupon_code' => 'nyb_activity_3',
        'name' => '買賴床墊，送抱枕+眼罩',
        'short_name' => '購買賴床墊送抱枕+眼罩',
        'description' => '購買賴床墊任一尺寸，即贈抱枕與眼罩各一件',
        'priority' => 2
    ],
    'activity_4' => [
        'coupon_code' => 'nyb_activity_4',
        'name' => '消費滿$50,000，加碼贈天絲四件組床包',
        'short_name' => '滿50000加碼贈天絲床包四件組',
        'description' => '消費金額滿50,000元（需含床墊），即贈送天絲四件組床包',
        'priority' => 1
    ]
] );

// 所有贈品ID集合（用於排除9折）
// define( 'NYB_ALL_GIFT_IDS', [
//     NYB_GIFT_FLEECE_BLANKET,
//     NYB_GIFT_HUG_PILLOW,
//     NYB_GIFT_EYE_MASK,
//     4439, 5663, 5662
// ] );
// define( 'NYB_ALL_GIFT_IDS_MAP', array_flip( NYB_ALL_GIFT_IDS ) );

// 檢查活動期間
$current_time = current_time( 'mysql' );

if ( $current_time < NYB_CAMPAIGN_START || $current_time > NYB_CAMPAIGN_END ) {
    // 非活動期間，記錄 log 並停用所有功能
    add_action( 'init', function() use ( $current_time ) {
        if ( NYB_DEBUG_MODE ) {
            $logger = wc_get_logger();
            $context = array( 'source' => 'newyear-bundle' );
            $logger->info( sprintf(
                "[新年活動期間檢查] 活動未啟用 | 當前時間: %s | 活動期間: %s ~ %s",
                $current_time,
                NYB_CAMPAIGN_START,
                NYB_CAMPAIGN_END
            ), $context );
        }
    }, 999 );

    // 停用所有功能
    return;
}

/**
 * ⚡ 性能優化：統一日誌函數
 */
function nyb_log( $message, $context ) {
    if ( ! NYB_DEBUG_MODE && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
        return;
    }

    $log_file = WP_CONTENT_DIR . '/newyear-bundle.log';
    $timestamp = current_time('Y-m-d H:i:s');
    error_log("[{$timestamp}] {$message}\n", 3, $log_file);
}


/**
 * =======================================================
 * 模組 2：全館9折功能（商品層級價格覆寫）
 * ⚡ 性能優化：快取 + Hash Map 查詢
 * =======================================================
 */

// 一般商品
add_filter( 'woocommerce_product_get_price', 'nyb_apply_site_wide_discount', 99, 2 );
add_filter( 'woocommerce_product_get_sale_price', 'nyb_apply_site_wide_discount_sale', 99, 2 );
// 變體商品
add_filter( 'woocommerce_product_variation_get_price', 'nyb_apply_site_wide_discount', 99, 2 );
add_filter( 'woocommerce_product_variation_get_sale_price', 'nyb_apply_site_wide_discount_sale', 99, 2 );

function nyb_apply_site_wide_discount( $price, $product ) {
    // ⚡ 快取商品折扣價格
    // static $price_cache = [];

    // $product_id = $product->get_id();
    // $parent_id = $product->get_parent_id();

    // // 快取鍵
    // $cache_key = $product_id . '_' . $price;
    // if ( isset( $price_cache[ $cache_key ] ) ) {
    //     return $price_cache[ $cache_key ];
    // }

    // // ⚡ 使用 Hash Map 替代 in_array (O(1) vs O(n))
    // if ( isset( NYB_ALL_GIFT_IDS_MAP[ $product_id ] ) || isset( NYB_ALL_GIFT_IDS_MAP[ $parent_id ] ) ) {
    //     $price_cache[ $cache_key ] = $price;
    //     return $price;
    // }

    // // 如果商品已有促銷價且在促銷期內，使用促銷價
    // $sale_price = $product->get_sale_price();
    // if ( $sale_price && $product->is_on_sale() ) {
    //     $price_cache[ $cache_key ] = $sale_price;
    //     return $sale_price;
    // }

    // 否則返回原價的9折
		$is_free_gift = $product->get_meta( '_is_free_gift' );
		if( $is_free_gift === 'yes' ) {
			return 0;
		}

    $regular_price = $product->get_regular_price();
    if ( $regular_price ) {
        $discounted = $regular_price * NYB_GLOBAL_DISCOUNT;
        // $price_cache[ $cache_key ] = $discounted;
        return $discounted;
    }

		// $product->set_sale_price( $price * NYB_GLOBAL_DISCOUNT );
		// $product->set_regular_price( $price );
		// $product->save();

    // $price_cache[ $cache_key ] = $price;
    return $price;
}

function nyb_apply_site_wide_discount_sale( $sale_price, $product ) {
    // 如果沒有設定促銷價，返回9折價格
    // if ( empty( $sale_price ) ) {
		$regular_price = $product->get_regular_price();
		if ( $regular_price ) {
				return $regular_price * NYB_GLOBAL_DISCOUNT;
		}
    // }

    return $sale_price;
}

// 在商品頁顯示「全館9折」標籤
add_action( 'woocommerce_before_single_product', 'nyb_show_discount_badge', 5 );
function nyb_show_discount_badge() {
    // global $product;

    // 檢查是否為贈品
    // $product_id = $product->get_id();
    // $parent_id = $product->get_parent_id();

    // ⚡ 使用 Hash Map
    // if ( isset( NYB_ALL_GIFT_IDS_MAP[ $product_id ] ) || isset( NYB_ALL_GIFT_IDS_MAP[ $parent_id ] ) ) {
    //     return;
    // }

    // 檢查是否已有促銷價
		echo '<div class="nyb-discount-badge" style="background: #df565f; color: white; padding: 8px 15px; display: inline-block; margin-bottom: 15px; border-radius: 5px; font-weight: bold;">🎉 新年優惠：全館9折</div>';
}

/**
 * =======================================================
 * 模組 12：活動資格計算引擎
 * ⚡ 性能優化：靜態快取避免重複計算
 * =======================================================
 */

/**
 * 計算所有活動的符合狀態（使用數量扣減邏輯）
 * ⚡ 與贈品應用邏輯完全同步
 * @return array
 */
function nyb_calculate_activity_status($product_id = 0) {
    // ⚡ 靜態快取
    static $cached_status = null;
    static $cached_cart_hash = null;

    $cart = WC()->cart;
    if ( ! $cart || $cart->is_empty() ) {
        return [];
    }

    // 計算購物車 hash
    $cart_contents = $cart->get_cart_contents();
    $cart_hash = md5( serialize( $cart_contents ) );

    // 如果購物車未變更，返回快取結果
    if ( $cached_cart_hash === $cart_hash && $cached_status !== null ) {
        return $cached_status;
    }

    // 統計購物車內容
    $stats = nyb_analyze_cart_contents();
    $results = [];

    // === 步驟1: 使用數量扣減邏輯計算實際會應用的活動 ===
    $applied_activities = [];
    $stats_copy = $stats; // 複製一份用於扣減計算

    // 【優先級1】活動4: 滿額贈天絲床包四件組
    // 手動計算購物車小計（排除贈品）
    $cart_subtotal = 0;
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( ! isset( $cart_item['_nyb_auto_gift'] ) ) {
            $cart_subtotal += $cart_item['line_subtotal'];
        }
    }

    if ( $cart_subtotal >= NYB_THRESHOLD_AMOUNT && $stats_copy['available']['any_mattress'] >= 1 ) {
        if ( nyb_consume_item( $stats_copy, 'any_mattress', 1, 'bundle4' ) ) {
            $applied_activities[] = 'activity_4';
            $results['activity_4'] = ['status' => 'qualified', 'missing' => []];
        }
    }

    // 【優先級2】活動3: 賴床墊送抱枕+眼罩
    if ( $stats_copy['available']['lai_mattress'] >= 1 ) {
        if ( nyb_consume_item( $stats_copy, 'lai_mattress', 1, 'bundle3' ) ) {
            $applied_activities[] = 'activity_3';
            $results['activity_3'] = ['status' => 'qualified', 'missing' => []];
        }
    }

    // 【優先級3】活動2: 床墊+催眠枕送茸茸被
    if ( $stats_copy['available']['any_mattress'] >= 1 &&
         $stats_copy['available']['hypnotic_pillow'] >= 1 ) {
        if ( nyb_consume_item( $stats_copy, 'any_mattress', 1, 'bundle2' ) &&
             nyb_consume_item( $stats_copy, 'hypnotic_pillow', 1, 'bundle2' ) ) {
            $applied_activities[] = 'activity_2';
            $results['activity_2'] = ['status' => 'qualified', 'missing' => []];
        }
    }

    // 【優先級4】活動1: 買枕頭送枕套（只送一個）
    if ( $stats_copy['available']['hypnotic_pillow'] >= 1 ) {
        // 買一送一：只消耗1個枕頭，送1個枕套
        if ( nyb_consume_item( $stats_copy, 'hypnotic_pillow', 1, 'bundle1' ) ) {
            $applied_activities[] = 'activity_1';
            $results['activity_1'] = ['status' => 'qualified', 'missing' => []];
        }
    }

    // === 步驟2: 計算未應用活動的 almost/not_qualified 狀態 ===
    // 使用 $stats（原始數量）和 $stats_copy['available']（剩餘數量）來判斷

    // 活動1: 買枕頭送枕套
    if ( ! in_array( 'activity_1', $applied_activities ) ) {
        $total_pillow = $stats['hypnotic_pillow_count'];
        $avail_pillow = $stats_copy['available']['hypnotic_pillow'];

        // 如果購物車原本沒有枕頭 → almost（引導購買）
        // 如果購物車有但被用完 → not_qualified（已被其他活動使用）
        if ( $total_pillow == 0 ) {
            $results['activity_1'] = ['status' => 'almost', 'missing' => ['催眠枕']];
        } else {
            $results['activity_1'] = ['status' => 'not_qualified', 'missing' => ['催眠枕']];
        }
    }

    // 活動2: 床墊+催眠枕送茸茸被
    if ( ! in_array( 'activity_2', $applied_activities ) ) {
        $total_mattress = $stats['any_mattress_count'];
        $total_pillow = $stats['hypnotic_pillow_count'];
        $avail_mattress = $stats_copy['available']['any_mattress'];
        $avail_pillow = $stats_copy['available']['hypnotic_pillow'];

        // 判斷缺少什麼（排除「已被使用完」的商品）
        $missing = [];

        if ( $avail_mattress < 1 ) {
            $missing[] = '床墊';
        }

        if ( $avail_pillow < 1 ) {
            $missing[] = '催眠枕';
        }

        if ( count( $missing ) == 1 ) {
            $results['activity_2'] = ['status' => 'almost', 'missing' => $missing];
        } else {
            $results['activity_2'] = ['status' => 'not_qualified', 'missing' => empty( $missing ) ? ['商品已被其他活動使用'] : $missing];
        }
    }

    // 活動3: 賴床墊送抱枕+眼罩
    if ( ! in_array( 'activity_3', $applied_activities ) ) {
        $total_lai = $stats['lai_mattress_count'];
        $avail_lai = $stats_copy['available']['lai_mattress'];

        // 如果購物車原本沒有賴床墊 → almost（引導購買）
        // 如果購物車有但被用完 → not_qualified（已被其他活動使用）
        if ( $total_lai == 0 ) {
            $results['activity_3'] = ['status' => 'almost', 'missing' => ['賴床墊']];
        } else {
            $results['activity_3'] = ['status' => 'not_qualified', 'missing' => ['賴床墊']];
        }
    }

    // 活動4: 滿額贈天絲床包四件組
    if ( ! in_array( 'activity_4', $applied_activities ) ) {
        // 手動計算購物車小計（排除贈品）
        $cart_subtotal = 0;
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! isset( $cart_item['_nyb_auto_gift'] ) ) {
                $cart_subtotal += $cart_item['line_subtotal'];
            }
        }

        // $total_mattress = $stats['any_mattress_count'];
        // $avail_mattress = $stats_copy['available']['any_mattress'];

        $missing = [];
        $amount_needed = NYB_THRESHOLD_AMOUNT - $cart_subtotal;

        if ( $cart_subtotal < NYB_THRESHOLD_AMOUNT ) {
            $missing[] = sprintf( '還差 %s', wc_price( $amount_needed ) );
        }

        // if ( $avail_mattress < 1 && $total_mattress == 0 ) {
        //     $missing[] = '床墊（作為贈品尺寸依據）';
        // }

        if ( ! empty( $missing ) && $cart_subtotal >= NYB_THRESHOLD_AMOUNT * 0.8 ) {
            $results['activity_4'] = ['status' => 'almost', 'missing' => $missing];
        } elseif ( ! empty( $missing ) ) {
            $results['activity_4'] = ['status' => 'not_qualified', 'missing' => $missing];
        } else {
            $results['activity_4'] = ['status' => 'not_qualified', 'missing' => ['商品已被其他活動使用']];
        }
    }

    // 快取結果
    $cached_status = $results;
    $cached_cart_hash = $cart_hash;

    return $results;
}

/**
 * 分析購物車內容（帶數量追蹤）
 * ⚡ 使用靜態快取 + Hash Map + 數量扣減機制
 * @return array
 */
function nyb_analyze_cart_contents() {
    $cart = WC()->cart;

    $stats = [
        // 總數量（購買的商品數量）
        'spring_mattress_count' => 0,
        'lai_mattress_count' => 0,
        'any_mattress_count' => 0,  // 所有床墊（嗜睡+賴床墊）
        'hypnotic_pillow_count' => 0,
        'hypnotic_pillow_vars' => [],

        // 可用數量（扣除已被活動使用的數量）
        'available' => [
            'spring_mattress' => 0,
            'lai_mattress' => 0,
            'any_mattress' => 0,
            'hypnotic_pillow' => 0,
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
        if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
            $stats['spring_mattress_count'] += $quantity;
            $stats['available']['spring_mattress'] += $quantity;
            $stats['any_mattress_count'] += $quantity;
            $stats['available']['any_mattress'] += $quantity;
        }

        // 賴床墊
        if ( isset( NYB_LAI_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
            $stats['lai_mattress_count'] += $quantity;
            $stats['available']['lai_mattress'] += $quantity;
            $stats['any_mattress_count'] += $quantity;
            $stats['available']['any_mattress'] += $quantity;
        }

        // 催眠枕
        if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ] ) ) {
            $stats['hypnotic_pillow_count'] += $quantity;
            $stats['available']['hypnotic_pillow'] += $quantity;

            if ( ! isset( $stats['hypnotic_pillow_vars'][ $variation_id ] ) ) {
                $stats['hypnotic_pillow_vars'][ $variation_id ] = 0;
            }
            $stats['hypnotic_pillow_vars'][ $variation_id ] += $quantity;
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
function nyb_consume_item( &$stats, $item_type, $quantity, $activity ) {
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
 * 過濾與指定商品相關的活動
 * ⚡ 使用 Hash Map
 * @param int $product_id 商品ID
 * @param int $variation_id 變體ID
 * @return array
 */
function nyb_get_related_activities( $product_id, $variation_id = 0 ) {
    $all_status = nyb_calculate_activity_status();
    $related = [];

    $check_id = $variation_id != 0 ? $variation_id : $product_id;

		if( !isset( NYB_ALL_ACTIVITY_MAP[ $check_id ] ) ) {
			$check_id = 0;
		}

		// 賴床墊相關
		if ( isset( NYB_LAI_MATTRESS_VARS_MAP[ $check_id ] ) || isset( NYB_LAI_MATTRESS_PARENT_IDS_MAP[ $product_id ] ) ) {
				if ( isset( $all_status['activity_2'] ) ) {
						$related[] = ['key' => 'activity_2', 'data' => $all_status['activity_2'], 'priority' => 3];
				}
				if ( isset( $all_status['activity_3'] ) ) {
						$related[] = ['key' => 'activity_3', 'data' => $all_status['activity_3'], 'priority' => 2];
				}
		}

		// 嗜睡床墊相關
		if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $check_id ] ) || isset( NYB_SPRING_MATTRESS_PARENT_IDS_MAP[ $product_id ] ) ) {
				if ( isset( $all_status['activity_2'] ) ) {
						$related[] = ['key' => 'activity_2', 'data' => $all_status['activity_2'], 'priority' => 3];
				}
		}

		// 催眠枕相關
		if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $check_id ] ) || $product_id == NYB_HYPNOTIC_PILLOW_PARENT ) {
				if ( isset( $all_status['activity_1'] ) ) {
						$related[] = ['key' => 'activity_1', 'data' => $all_status['activity_1'], 'priority' => 4];
				}
				if ( isset( $all_status['activity_2'] ) ) {
						$related[] = ['key' => 'activity_2', 'data' => $all_status['activity_2'], 'priority' => 3];
				}
		}

		$related[] = ['key' => 'activity_4', 'data' => $all_status['activity_4'], 'priority' => 1];

		// 按優先級排序
		usort( $related, function( $a, $b ) {
				return $a['priority'] - $b['priority'];
		});

		return $related;
}

/**
 * 獲取活動描述
 * ⚡ 靜態常數避免重複定義
 * @param string $activity_key
 * @return string
 */
function nyb_get_activity_description( $activity_key ) {
    static $descriptions = null;

    if ( $descriptions === null ) {
        $descriptions = [
            'activity_1' => '購買催眠枕送天絲枕套一件（買一送一）',
            'activity_2' => '購買床墊+催眠枕送兩用茸茸被',
            'activity_3' => '購買賴床墊送抱枕+眼罩',
            'activity_4' => '滿50000加碼贈天絲床包四件組'
        ];
    }

    return isset( $descriptions[ $activity_key ] ) ? $descriptions[ $activity_key ] : '';
}

/**
 * 生成商品連結
 * @param int $product_id 商品ID
 * @param string $text 連結文字
 * @return string HTML 連結
 */
function nyb_get_product_link( $product_id, $text ) {
    if ( ! $product_id ) {
        return $text;
    }

    $url = get_permalink( $product_id );
    if ( ! $url ) {
        return $text;
    }

    return '<a href="' . esc_url( $url ) . '" style="color: inherit; text-decoration: underline; font-weight: bold;" target="_blank">' . esc_html( $text ) . '</a>';
}

/**
 * 獲取商品類別的連結 HTML
 * @param string $category 商品類別 (mattress/hypnotic_pillow/lai_mattress/bed_frame/fleece_blanket等)
 * @return string 帶連結的 HTML
 */
function nyb_get_category_links( $category ) {
    $links = [
        'mattress' => nyb_get_product_link( 1324, '嗜睡床墊' ),  // 使用父層 ID
        'spring_mattress' => nyb_get_product_link( 1324, '嗜睡床墊' ),
        'hypnotic_pillow' => nyb_get_product_link( NYB_HYPNOTIC_PILLOW_PARENT, '催眠枕' ),
        'lai_mattress' => nyb_get_product_link( 3444, '賴床墊' ),
        'bed_frame' => nyb_get_product_link( 4930, '床架' ),
        'fleece_blanket' => nyb_get_product_link( NYB_GIFT_FLEECE_BLANKET, '茸茸被' ),
        'hug_pillow' => nyb_get_product_link( NYB_GIFT_HUG_PILLOW, '抱枕' ),
        'eye_mask' => nyb_get_product_link( NYB_GIFT_EYE_MASK, '眼罩' ),
        'side_pillow' => nyb_get_product_link( NYB_HYPNOTIC_PILLOW_PARENT, '側睡枕' ),
        'pillowcase' => nyb_get_product_link( NYB_HYPNOTIC_PILLOW_PARENT, '天絲枕套' ),
        'bedding_set' => '<strong>天絲四件組床包</strong>'  // 無連結，未上架
    ];

    return isset( $links[ $category ] ) ? $links[ $category ] : $category;
}

/**
 * 獲取活動的詳細提示資訊（帶商品連結）
 * @param string $activity_key 活動代碼
 * @param string $status 狀態 (qualified/almost/not_qualified)
 * @param array $missing 缺少的商品
 * @return array ['title' => '標題', 'message' => '訊息', 'type' => 'success/info/warning']
 */
function nyb_get_activity_notice( $activity_key, $status, $missing = [] ) {
    // 獲取商品連結
    $spring_mattress_link = nyb_get_category_links( 'spring_mattress' );
    $hypnotic_pillow_link = nyb_get_category_links( 'hypnotic_pillow' );
    $lai_mattress_link = nyb_get_category_links( 'lai_mattress' );
    $bed_frame_link = nyb_get_category_links( 'bed_frame' );
    $fleece_blanket_link = nyb_get_category_links( 'fleece_blanket' );
    $hug_pillow_link = nyb_get_category_links( 'hug_pillow' );
    $eye_mask_link = nyb_get_category_links( 'eye_mask' );
    $side_pillow_link = nyb_get_category_links( 'side_pillow' );
    $pillowcase_link = nyb_get_category_links( 'pillowcase' );
    $bedding_set_link = nyb_get_category_links( 'bedding_set' );

    // 獲取活動名稱
    $activity_name = nyb_get_activity_name( $activity_key, 'full' );

    $notices = [
        'activity_1' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買' . $hypnotic_pillow_link . '，將獲贈配對' . $pillowcase_link . '一件（買一送一）',
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $hypnotic_pillow_link, $activity_name ) {
                    if ( empty( $missing ) || in_array( '催眠枕', $missing ) ) {
                        return '購買' . $hypnotic_pillow_link . '，可享優惠「' . $activity_name . '」。';
                    }
                    return '購買' . $hypnotic_pillow_link . '，可享優惠「' . $activity_name . '」。';
                },
                'type' => 'info'
            ],
            'not_qualified' => [
                'title' => '',
                'message' => function() use ( $missing, $hypnotic_pillow_link, $pillowcase_link ) {
                    return '購買' . $hypnotic_pillow_link . '，即可獲得配對' . $pillowcase_link . '一件（買一送一）';
                },
                'type' => 'info'
            ]
        ],
        'activity_2' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買床墊和' . $hypnotic_pillow_link . '，將獲贈' . $fleece_blanket_link,
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $spring_mattress_link, $hypnotic_pillow_link, $activity_name ) {
                    $links = [];
                    foreach ( $missing as $item ) {
                        if ( $item === '床墊' ) {
                            $links[] = $spring_mattress_link;
                        } elseif ( $item === '催眠枕' ) {
                            $links[] = $hypnotic_pillow_link;
                        }
                    }

                    if ( empty( $links ) ) {
                        return '購買' . $spring_mattress_link . '和' . $hypnotic_pillow_link . '，可享優惠「' . $activity_name . '」。';
                    }

                    $prefix = count( $missing ) < 2 ? '再購買' : '購買';
                    return $prefix . implode( '和', $links ) . '，可享優惠「' . $activity_name . '」。';
                },
                'type' => 'info'
            ],
            'not_qualified' => [
                'title' => '',
                'message' => function() use ( $missing, $spring_mattress_link, $hypnotic_pillow_link, $fleece_blanket_link ) {
                    return '購買' . $spring_mattress_link . '和' . $hypnotic_pillow_link . '，即可獲得' . $fleece_blanket_link;
                },
                'type' => 'info'
            ]
        ],
        'activity_3' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買' . $lai_mattress_link . '，將獲贈' . $hug_pillow_link . '和' . $eye_mask_link,
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $lai_mattress_link, $activity_name ) {
                    if ( empty( $missing ) || in_array( '賴床墊', $missing ) ) {
                        return '購買' . $lai_mattress_link . '，可享優惠「' . $activity_name . '」。';
                    }
                    return '購買' . $lai_mattress_link . '，可享優惠「' . $activity_name . '」。';
                },
                'type' => 'info'
            ],
            'not_qualified' => [
                'title' => '',
                'message' => function() use ( $missing, $lai_mattress_link, $hug_pillow_link, $eye_mask_link ) {
                    return '購買' . $lai_mattress_link . '，即可獲得' . $hug_pillow_link . '和' . $eye_mask_link;
                },
                'type' => 'info'
            ]
        ],
        'activity_4' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已達消費滿額，將獲贈' . $bedding_set_link,
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $activity_name ) {
                    $message_parts = [];
                    foreach ( $missing as $item ) {
                        if ( strpos( $item, '還差' ) !== false ) {
                            $message_parts[] = $item;
                        } elseif ( strpos( $item, '床墊' ) !== false ) {
                            $message_parts[] = $item;
                        }
                    }

                    if ( ! empty( $message_parts ) ) {
                        return implode( '、', $message_parts ) . '，可享優惠「' . $activity_name . '」。';
                    }

                    return '消費滿' . wc_price( NYB_THRESHOLD_AMOUNT ) . '（含床墊），可享優惠「' . $activity_name . '」。';
                },
                'type' => 'info'
            ],
            'not_qualified' => [
                'title' => '',
                'message' => function() use ( $bedding_set_link ) {
                    return '消費滿' . wc_price( NYB_THRESHOLD_AMOUNT ) . '（含床墊），即可獲贈' . $bedding_set_link;
                },
                'type' => 'info'
            ]
        ]
    ];

    if ( isset( $notices[ $activity_key ][ $status ] ) ) {
        $notice = $notices[ $activity_key ][ $status ];

        // 如果 message 是閉包函數，執行它
        if ( is_callable( $notice['message'] ) ) {
            $notice['message'] = call_user_func( $notice['message'] );
        }

        return $notice;
    }

    return [
        'title' => '優惠活動',
				'missing' => $missing,
        'message' => nyb_get_activity_description( $activity_key ),
        'type' => 'info'
    ];
}

/**
 * =======================================================
 * 模組 13A：商品頁智慧提示系統
 * ⚡ 性能優化：只在前端執行
 * =======================================================
 */

add_action( 'woocommerce_before_single_product', 'nyb_smart_product_page_notice', 15 );
function nyb_smart_product_page_notice() {
    // ⚡ 只在前端執行
    if ( is_admin() ) {
        return;
    }

    global $product;

    $product_id = $product->get_id();
    $parent_id = $product->get_parent_id();

    // 獲取與此商品相關的活動
    $related_activities = nyb_get_related_activities( $parent_id != 0 ? $parent_id : $product_id, 0 );
		nyb_log( 'nyb_smart_product_page_notice 868', $related_activities );

    if ( empty( $related_activities ) ) {
				nyb_log( 'nyb_smart_product_page_notice 869 empty', $related_activities );
        return;
    }

    // 檢查購物車狀態
    // $cart = WC()->cart;
    // $cart_empty = ! $cart || $cart->is_empty();

    // if ( $cart_empty ) {
        // 情境A: 購物車為空 - 顯示通用活動說明
    //     nyb_display_general_activity_notice( $related_activities );
    // } else {
        // 情境B: 購物車有商品 - 顯示條件式提示
				// nyb_log( 'nyb_display_conditional_notice 882', $related_activities );
        nyb_display_conditional_notice( $related_activities );
    // }
}

/**
 * 顯示通用活動說明（購物車為空時）
 * ⚡ UI/UX 優化：每個活動獨立顯示
 */
// function nyb_display_general_activity_notice( $activities ) {

// 	  nyb_log( 'nyb_display_general_activity_notice', $activities );
//     if ( empty( $activities ) ) {
//         return;
//     }

//     // 每個活動獨立顯示一個提示框
//     foreach ( $activities as $activity ) {
//         $notice = nyb_get_activity_notice( $activity['key'], 'almost', [] );

//         echo '<div class="woocommerce-info" style="margin-bottom: 15px; padding: 12px 15px; border-left: 4px solid #2196f3;">';
//         // echo '<div style="font-weight: bold; margin-bottom: 5px;">' . $notice['title'] . '</div>';
//         echo '<div data-missing="' . json_encode( $notice['missing'] ) . '" style="font-size: 14px;">' . $notice['message'] . '</div>';
//         echo '</div>';
//     }
// }

/**
 * 顯示條件式提示（購物車有商品時）
 * ⚡ UI/UX 優化：每個活動獨立顯示，同時顯示已符合和差一點的活動
 */
function nyb_display_conditional_notice( $activities ) {
		nyb_log( 'nyb_display_conditional_notice 922', $activities );

    $qualified = [];   // 已符合的活動
    $almost = [];      // 差一點符合的活動
		$not_qualified = []; // 不符合的活動

    foreach ( $activities as $activity ) {
        if ( $activity['data']['status'] === 'qualified' ) {
            $qualified[] = $activity;
        } elseif ( $activity['data']['status'] === 'almost' ) {
            $almost[] = $activity;
        } elseif ( $activity['data']['status'] === 'not_qualified' ) {
            $not_qualified[] = $activity;
        }
    }

    // 優先顯示「已符合」的活動（每個獨立顯示）
    if ( ! empty( $qualified ) ) {
        foreach ( $qualified as $act ) {
            $notice = nyb_get_activity_notice( $act['key'], 'qualified', [] );

            echo '<div class="woocommerce-info" style="margin-bottom: 15px; padding: 12px 15px; background: linear-gradient(135deg, #e8f5ed 0%, #d4ede0 100%); border-left: 4px solid #5da882;">';
            echo '<div data-missing="' . json_encode( $notice['missing'] ) . '" style="font-size: 14px; color: #2d5f44;">' . $notice['message'] . '</div>';
            echo '</div>';
        }
    }

    // 顯示「差一點」的活動（每個獨立顯示）
    if ( ! empty( $almost ) ) {
        foreach ( $almost as $act ) {
            $notice = nyb_get_activity_notice( $act['key'], 'almost', $act['data']['missing'] );

            echo '<div class="woocommerce-message" style="margin-bottom: 15px; padding: 12px 15px; background: linear-gradient(135deg, #fff8e8 0%, #ffefc7 100%) !important; border-left: 4px solid #d4a548 !important;">';
            echo '<div data-missing="' . json_encode( $notice['missing'] ) . '" style="font-size: 14px; color: #8b6f1e;">' . $notice['message'] . '</div>';
            echo '</div>';
        }
    }

    // 顯示「未符合」的活動（每個獨立顯示）
    if ( ! empty( $not_qualified ) ) {
        foreach ( $not_qualified as $act ) {
            $notice = nyb_get_activity_notice( $act['key'], 'not_qualified', $act['data']['missing'] );

            echo '<div class="woocommerce-info" style="margin-bottom: 15px; padding: 12px 15px; background: linear-gradient(135deg, #f0f7f9 0%, #e3eff4 100%) !important; border-left: 4px solid #6ba5c1 !important;">';
            echo '<div data-missing="' . json_encode( $notice['missing'] ) . '" style="font-size: 14px; color: #3d6378;">' . $notice['message'] . '</div>';
            echo '</div>';
        }
    }
}

/**
 * =======================================================
 * 模組 13B：購物車頁提示系統
 * ⚡ UI/UX 優化：每個活動獨立顯示為單獨的提示框
 * =======================================================
 */

add_action( 'woocommerce_before_cart', 'nyb_cart_page_notice', 10 );
function nyb_cart_page_notice() {
    $cart = WC()->cart;
    if ( ! $cart ) {
        return;
    }

    // $applied_coupons = $cart->get_applied_coupons();
    // if ( ! empty( $applied_coupons ) ) {
    //     echo '<div class="woocommerce-error" style="margin-bottom: 15px; padding: 12px 15px; border-left: 4px solid #dc3232;">';
    //     echo '<div style="font-weight: bold;">⚠️ 優惠券衝突提醒</div>';
    //     echo '<div style="font-size: 14px; margin-top: 5px;">使用優惠券將無法享受新年優惠活動。請擇一使用。</div>';
    //     echo '</div>';
    //     return;
    // }

    $activity_status = nyb_calculate_activity_status();

		nyb_log( 'nyb_cart_page_notice 1019: ' . json_encode( $activity_status ), $activity_status );

    // 顯示「差一點」的活動（每個獨立顯示）
    $almost = array_filter( $activity_status, function( $status ) {
        return $status['status'] === 'almost';
    });

    if ( ! empty( $almost ) ) {
        foreach ( $almost as $key => $data ) {
            $notice = nyb_get_activity_notice( $key, 'almost', $data['missing'] );

            echo '<div class="woocommerce-message" style="margin-bottom: 15px; padding: 12px 15px; background: linear-gradient(135deg, #fff8e8 0%, #ffefc7 100%) !important; border-left: 4px solid #d4a548 !important;">';
            echo '<div style="color: #8b6f1e;">' . $notice['message'] . '</div>';
            echo '</div>';
        }
    }

    // 顯示「未符合」的活動（每個獨立顯示）
    $not_qualified = array_filter( $activity_status, function( $status ) {
        return $status['status'] === 'not_qualified';
    });

    if ( ! empty( $not_qualified ) ) {
        foreach ( $not_qualified as $key => $data ) {
            $notice = nyb_get_activity_notice( $key, 'not_qualified', $data['missing'] );

            echo '<div class="woocommerce-info" style="margin-bottom: 15px; padding: 12px 15px; background: linear-gradient(135deg, #f0f7f9 0%, #e3eff4 100%) !important; border-left: 4px solid #6ba5c1 !important;">';
            echo '<div style="color: #3d6378;">' . $notice['message'] . '</div>';
            echo '</div>';
        }
    }
}

/**
 * =======================================================
 * 模組 13C：優惠券樣式顯示已符合的活動
 * 在購物車優惠券區域以優惠券樣式顯示已符合的活動
 * =======================================================
 */

/**
 * 計算實際會應用的活動（使用與贈品相同的數量扣減邏輯）
 * @return array 實際會應用的活動列表
 */
function nyb_get_actually_applied_activities() {
    $cart = WC()->cart;
    if ( ! $cart || $cart->is_empty() ) {
        return [];
    }

    // 使用與 nyb_activity_detection_engine 完全相同的邏輯
    $stats = nyb_analyze_cart_contents();
    $applied_activities = [];

    // 【優先級1】活動4: 滿額贈天絲床包四件組
    // 手動計算購物車小計（排除贈品）
    $cart_subtotal = 0;
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( ! isset( $cart_item['_nyb_auto_gift'] ) ) {
            $cart_subtotal += $cart_item['line_subtotal'];
        }
    }

    if ( $cart_subtotal >= NYB_THRESHOLD_AMOUNT && $stats['available']['any_mattress'] >= 1 ) {
        if ( nyb_consume_item( $stats, 'any_mattress', 1, 'bundle4' ) ) {
            $applied_activities[] = 'activity_4';
        }
    }

    // 【優先級2】活動3: 賴床墊送抱枕+眼罩
    if ( $stats['available']['lai_mattress'] >= 1 ) {
        if ( nyb_consume_item( $stats, 'lai_mattress', 1, 'bundle3' ) ) {
            $applied_activities[] = 'activity_3';
        }
    }

    // 【優先級3】活動2: 床墊+催眠枕送茸茸被
    if ( $stats['available']['any_mattress'] >= 1 &&
         $stats['available']['hypnotic_pillow'] >= 1 ) {
        if ( nyb_consume_item( $stats, 'any_mattress', 1, 'bundle2' ) &&
             nyb_consume_item( $stats, 'hypnotic_pillow', 1, 'bundle2' ) ) {
            $applied_activities[] = 'activity_2';
        }
    }

    // 【優先級4】活動1: 買枕頭送枕套（只送一個）
    if ( $stats['available']['hypnotic_pillow'] >= 1 ) {
        // 買一送一：只消耗1個枕頭，送1個枕套
        if ( nyb_consume_item( $stats, 'hypnotic_pillow', 1, 'bundle1' ) ) {
            $applied_activities[] = 'activity_1';
        }
    }

    return $applied_activities;
}

/**
 * 在購物車優惠券區域顯示已符合的活動
 */
add_action( 'woocommerce_cart_coupon', 'nyb_display_qualified_activities_as_coupons', 10, 1 );
function nyb_display_qualified_activities_as_coupons() {
    $cart = WC()->cart;
    if ( ! $cart ) {
        return;
    }

    // 檢查是否有優惠券，有優惠券就不顯示活動
    $applied_coupons = $cart->get_applied_coupons();
    if ( ! empty( $applied_coupons ) ) {
        return;
    }

    // 使用與贈品應用相同的邏輯計算實際會應用的活動
    $applied_activities = nyb_get_actually_applied_activities();

    if ( empty( $applied_activities ) ) {
        return;
    }

    if ( count( $applied_activities ) > 1 ) {
        echo '<tr><th><h3 style="color: #1a1a1a;">活動優惠</h3></th></tr>';
    }

    // 為每個已符合的活動顯示類似優惠券的樣式
    foreach ( $applied_activities as $key ) {
        $activity_name = nyb_get_activity_name( $key );
        echo '<tr class="cart-discount nyb-activity-coupon nyb-activity-' . esc_attr( $key ) . '">';
        echo '<td colspan="2" data-title="活動優惠">';
        echo '<div class="nyb-coupon-style">';
        echo '<span class="nyb-activity-badge">🎁</span>';
        echo '<span class="nyb-activity-name">' . esc_html( $activity_name ) . '</span>';
        echo '<span class="nyb-activity-tag">已套用</span>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
}

/**
 * 添加活動優惠券樣式的 CSS
 */
add_action( 'wp_head', 'nyb_activity_coupon_styles', 20 );
function nyb_activity_coupon_styles() {
    if ( ! is_cart() && ! is_checkout() ) {
        return;
    }

    ?>
    <style type="text/css">
        /* 活動優惠券樣式 */
        .nyb-activity-coupon {
            background: linear-gradient(135deg, #fff9f0 0%, #ffe8cc 100%) !important;
            border-left: 4px solid #df565f !important;
        }

        .nyb-coupon-style {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
        }

        .nyb-activity-badge {
            font-size: 24px;
            line-height: 1;
        }

        .nyb-activity-name {
            flex: 1;
            font-weight: bold;
            color: #df565f;
            font-size: 14px;
        }

        .nyb-activity-tag {
            background: #df565f;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        /* 結帳頁面樣式 */
        .woocommerce-checkout-review-order-table .nyb-activity-coupon td {
            padding: 12px;
        }

        /* 手機版適配 */
        @media (max-width: 768px) {
            .nyb-coupon-style {
                flex-wrap: wrap;
                gap: 8px;
            }

            .nyb-activity-name {
                font-size: 13px;
            }

            .nyb-activity-tag {
                font-size: 11px;
                padding: 3px 10px;
            }
        }
    </style>
    <?php
}

/**
 * 在結帳頁面也顯示已符合的活動
 */
add_action( 'woocommerce_review_order_after_cart_contents', 'nyb_display_qualified_activities_in_checkout' );
function nyb_display_qualified_activities_in_checkout() {
    nyb_display_qualified_activities_as_coupons();
}

/**
 * 獲取活動名稱
 * @param string $activity_key 活動鍵值
 * @param string $type 名稱類型 'short' 或 'full'
 * @return string
 */
function nyb_get_activity_name( $activity_key, $type = 'short' ) {
    $field = $type === 'full' ? 'name' : 'short_name';
    return isset( NYB_ACTIVITY_MAP[ $activity_key ][ $field ] )
        ? NYB_ACTIVITY_MAP[ $activity_key ][ $field ]
        : '新年優惠活動';
}

/**
 * 獲取活動優惠券代碼
 * @param string $activity_key 活動鍵值
 * @return string|null
 */
function nyb_get_activity_coupon_code( $activity_key ) {
    return isset( NYB_ACTIVITY_MAP[ $activity_key ]['coupon_code'] )
        ? NYB_ACTIVITY_MAP[ $activity_key ]['coupon_code']
        : null;
}

/**
 * 根據優惠券代碼獲取活動鍵值
 * @param string $coupon_code 優惠券代碼
 * @return string|null
 */
function nyb_get_activity_key_by_coupon( $coupon_code ) {
    foreach ( NYB_ACTIVITY_MAP as $key => $data ) {
        if ( $data['coupon_code'] === $coupon_code ) {
            return $key;
        }
    }
    return null;
}

/**
 * =======================================================
 * 模組 3：活動檢測引擎
 * ⚡ 性能優化：減少日誌、優化條件順序
 * =======================================================
 */

add_action( 'woocommerce_before_calculate_totals', 'nyb_activity_detection_engine', 10 );
function nyb_activity_detection_engine( $cart ) {
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

    nyb_log( "========== 新年活動檢測開始 ==========", $context );

    // --- 步驟 1: 分析購物車內容 ---
    $stats = nyb_analyze_cart_contents();

    nyb_log( sprintf(
        "[新年活動] 購物車統計 | 所有床墊:%d(可用:%d), 賴床墊:%d(可用:%d), 催眠枕:%d(可用:%d)",
        $stats['any_mattress_count'],
        $stats['available']['any_mattress'],
        $stats['lai_mattress_count'],
        $stats['available']['lai_mattress'],
        $stats['hypnotic_pillow_count'],
        $stats['available']['hypnotic_pillow']
    ), $context );

    // --- 步驟 2: 按優先級檢查活動並應用（數量扣減機制）---
    $applied_activities = [];

    // 【優先級1】活動4: 滿額贈天絲床包四件組
    // 手動計算購物車小計（排除贈品）
    $cart_subtotal = 0;
    foreach ( $cart->get_cart() as $cart_item ) {
        // 排除贈品
        if ( ! isset( $cart_item['_nyb_auto_gift'] ) ) {
            $cart_subtotal += $cart_item['line_subtotal'];
        }
    }

    nyb_log( sprintf( "[活動4 檢查] 購物車金額:%s (手動計算), 門檻:%s, 可用床墊:%d",
        $cart_subtotal, NYB_THRESHOLD_AMOUNT, $stats['available']['any_mattress'] ), $context );

    if ( $cart_subtotal >= NYB_THRESHOLD_AMOUNT && $stats['available']['any_mattress'] >= 1 ) {
        if ( nyb_consume_item( $stats, 'any_mattress', 1, 'bundle4' ) ) {
            nyb_apply_activity_4( $cart, $stats, $context );
            $applied_activities[] = 'bundle4';
            nyb_log( "[活動4] 套用成功 | 剩餘床墊:{$stats['available']['any_mattress']}, 消費金額:" . $cart_subtotal, $context );
        } else {
            nyb_log( "[活動4] 扣減失敗", $context );
        }
    } else {
        nyb_log( "[活動4] 不符合條件", $context );
    }

    // 【優先級2】活動3: 賴床墊送抱枕+眼罩
    if ( $stats['available']['lai_mattress'] >= 1 ) {
        if ( nyb_consume_item( $stats, 'lai_mattress', 1, 'bundle3' ) ) {
            nyb_apply_activity_3( $cart, $stats, $context );
            $applied_activities[] = 'bundle3';
            nyb_log( "[活動3] 套用成功 | 剩餘: 賴床墊:{$stats['available']['lai_mattress']}", $context );
        }
    }

    // 【優先級3】活動2: 床墊+催眠枕送茸茸被
    if ( $stats['available']['any_mattress'] >= 1 &&
         $stats['available']['hypnotic_pillow'] >= 1 ) {
        if ( nyb_consume_item( $stats, 'any_mattress', 1, 'bundle2' ) &&
             nyb_consume_item( $stats, 'hypnotic_pillow', 1, 'bundle2' ) ) {
            nyb_apply_activity_2( $cart, $stats, $context );
            $applied_activities[] = 'bundle2';
            nyb_log( "[活動2] 套用成功 | 剩餘: 床墊:{$stats['available']['any_mattress']}, 催眠枕:{$stats['available']['hypnotic_pillow']}", $context );
        }
    }

    // 【優先級4】活動1: 買枕頭送枕套（只送一個）
    if ( $stats['available']['hypnotic_pillow'] >= 1 ) {
        // 買一送一：只消耗1個枕頭，送1個枕套
        if ( nyb_consume_item( $stats, 'hypnotic_pillow', 1, 'bundle1' ) ) {
            nyb_apply_activity_1( $cart, $stats, $context );
            $applied_activities[] = 'bundle1';
            nyb_log( "[活動1] 套用成功 | 買一送一（只送一個枕套）", $context );
        }
    }

    nyb_log( sprintf( "[新年活動] 已應用活動: %s", implode( ', ', $applied_activities ) ), $context );
    nyb_log( sprintf( "[新年活動] 使用追蹤: %s", json_encode( $stats['usage'], JSON_UNESCAPED_UNICODE ) ), $context );

    // --- 步驟 3: 移除不再符合條件的贈品 ---
    nyb_remove_invalid_gifts( $cart, $applied_activities, $context );

    nyb_log( "========== 新年活動檢測結束 ==========", $context );
}


/**
 * 在購物車中查找指定產品的贈品
 * @param int $product_id 要查找的產品 ID
 * @return array|null 找到的贈品資訊，或 null 未找到
 */
function nyb_find_gift_product_in_cart( $product_id, $metadata_key = '_is_free_gift' ) {
	$cart = WC()->cart;

	nyb_log( sprintf( "[活動4] 查找贈品 | Product ID: %s, Metadata Key: %s", $product_id, $metadata_key ), $context );

	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			// 比對產品 ID
			if ( $cart_item['product_id'] === $product_id ) {
					// 檢查是否為贈品（從 cart meta 或 product meta）
					$is_gift = isset( $cart_item[ $metadata_key ] ) && $cart_item[ $metadata_key ];

					if ( $is_gift ) {
							return $cart_item; // 回傳該購物車項目
					}
			}
	}

	return null; // 未找到
}

/**
 * =======================================================
 * 模組 4：活動1 - 買枕頭送枕套（買一送一，只送一個）
 * =======================================================
 */
function nyb_apply_activity_1( $cart, $stats, $context ) {
    // 收集購物車中所有購買的催眠枕
    $purchased_pillows = [];

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        $variation_id = $cart_item['variation_id'];

        // 排除贈品
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            continue;
        }

        // 只處理催眠枕
        if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ] ) ) {
            if ( ! isset( $purchased_pillows[ $variation_id ] ) ) {
                $purchased_pillows[ $variation_id ] = [
                    'quantity' => 0,
                    'name' => $cart_item['data']->get_name(),
                    'cart_item_key' => $cart_item_key
                ];
            }
            $purchased_pillows[ $variation_id ]['quantity'] += $cart_item['quantity'];
        }
    }

    // 如果沒有購買任何催眠枕，清空 session 並移除贈品
    if ( empty( $purchased_pillows ) ) {
        WC()->session->__unset( 'nyb_bundle1_pillow_gifts' );
        WC()->session->__unset( 'nyb_selected_pillow_variation' );
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['_nyb_auto_gift'] ) && $cart_item['_nyb_auto_gift'] === 'bundle1' ) {
                $cart->remove_cart_item( $cart_item_key );
            }
        }
        return;
    }

    // 檢查用戶之前選擇的款式是否還在購物車中，如果不在則清除選擇
    $user_selected_pillow = WC()->session->get( 'nyb_selected_pillow_variation' );
    if ( $user_selected_pillow && ! isset( $purchased_pillows[ $user_selected_pillow ] ) ) {
        WC()->session->__unset( 'nyb_selected_pillow_variation' );
        nyb_log( sprintf( "[活動1] 用戶選擇的枕頭已移除，清除選擇 | 枕頭 ID: %s", $user_selected_pillow ), $context );
    }

    nyb_log( "[活動1] 買一送一（只送一個枕套）", $context );

    // 獲取用戶選擇（已在上面檢查過有效性）
    $user_selected_pillow = WC()->session->get( 'nyb_selected_pillow_variation' );
    $pillowcase_to_add = null;
    $pillowcase_id = null;

    // 如果用戶已選擇且該款式在購物車中
    if ( $user_selected_pillow && isset( $purchased_pillows[ $user_selected_pillow ] ) ) {
        if ( isset( NYB_PILLOWCASE_MAP[ $user_selected_pillow ] ) ) {
            $pillowcase_id = NYB_PILLOWCASE_MAP[ $user_selected_pillow ];
            $pillowcase_to_add = [ $pillowcase_id => 1 ];
            nyb_log( sprintf( "[活動1] 使用用戶選擇的枕套 | 枕頭 ID: %s", $user_selected_pillow ), $context );
        }
    }

    // 如果沒有用戶選擇，使用第一個找到的枕頭款式
    if ( ! $pillowcase_to_add ) {
        foreach ( $purchased_pillows as $var_id => $pillow_data ) {
            if ( isset( NYB_PILLOWCASE_MAP[ $var_id ] ) ) {
                $pillowcase_id = NYB_PILLOWCASE_MAP[ $var_id ];
                $pillowcase_to_add = [ $pillowcase_id => 1 ];
                nyb_log( sprintf( "[活動1] 使用預設枕套（第一個） | 枕頭 ID: %s", $var_id ), $context );
                break;
            }
        }
    }

    if ( ! $pillowcase_to_add ) {
        nyb_log( "[活動1] 未找到對應的枕套", $context );
        return;
    }

    // 儲存到 session
    WC()->session->set( 'nyb_bundle1_pillow_gifts', $pillowcase_to_add );

    // 移除舊的活動1贈品
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) && $cart_item['_nyb_auto_gift'] === 'bundle1' ) {
            $cart->remove_cart_item( $cart_item_key );
        }
    }

    // 添加枕套贈品（只送1個）
    $cart->add_to_cart(
        NYB_HYPNOTIC_PILLOW_PARENT,
        1, // 數量固定為1
        $pillowcase_id,
        array(),
        array( '_nyb_auto_gift' => 'bundle1', '_nyb_gift_type' => 'pillowcase' )
    );
    nyb_log( sprintf( "[活動1] 自動加入枕套贈品 | Variation ID: %s, 數量: 1", $pillowcase_id ), $context );

    // 將贈品價格設為 0
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) && $cart_item['_nyb_auto_gift'] === 'bundle1' ) {
            $original_price = $cart_item['data']->get_regular_price();
            $cart_item['data']->set_price( 0 );
            $cart_item['data']->add_meta_data( '_is_free_gift', 'yes', true );
            $cart_item['data']->add_meta_data( '_original_price', $original_price, true );
        }
    }
}

/**
 * =======================================================
 * 模組 4B：活動1 - 枕套選擇介面
 * =======================================================
 */

/**
 * AJAX 處理：保存用戶選擇的枕套款式
 */
add_action( 'wp_ajax_nyb_save_pillowcase_choice', 'nyb_save_pillowcase_choice' );
add_action( 'wp_ajax_nopriv_nyb_save_pillowcase_choice', 'nyb_save_pillowcase_choice' );
function nyb_save_pillowcase_choice() {
    check_ajax_referer( 'nyb_pillowcase_choice', 'nonce' );

    $pillow_variation_id = isset( $_POST['pillow_id'] ) ? intval( $_POST['pillow_id'] ) : 0;

    if ( $pillow_variation_id && isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $pillow_variation_id ] ) ) {
        WC()->session->set( 'nyb_selected_pillow_variation', $pillow_variation_id );

        // 記錄日誌
        $context = array( 'source' => 'newyear-bundle' );
        nyb_log( sprintf( "[活動1] 用戶選擇枕套 | 枕頭 ID: %s", $pillow_variation_id ), $context );

        wp_send_json_success( [
            'message' => '已更新枕套選擇',
            'pillow_id' => $pillow_variation_id
        ] );
    } else {
        wp_send_json_error( [ 'message' => '無效的枕頭 ID' ] );
    }
}

/**
 * 在購物車頁面顯示枕套選擇器（如果有多款枕頭）
 */
add_action( 'woocommerce_after_cart_table', 'nyb_display_pillowcase_selector', 5 );
function nyb_display_pillowcase_selector() {
    $cart = WC()->cart;
    if ( ! $cart ) {
        return;
    }

    // 檢查是否符合活動1
    $applied_activities = nyb_get_actually_applied_activities();
    if ( ! in_array( 'activity_1', $applied_activities ) ) {
        return;
    }

    // 收集購物車中的催眠枕
    $purchased_pillows = [];
    foreach ( $cart->get_cart() as $cart_item ) {
        $variation_id = $cart_item['variation_id'];

        // 排除贈品
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            continue;
        }

        // 只處理催眠枕
        if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ] ) ) {
            if ( ! isset( $purchased_pillows[ $variation_id ] ) ) {
                $purchased_pillows[ $variation_id ] = [
                    'name' => $cart_item['data']->get_name(),
                    'quantity' => 0
                ];
            }
            $purchased_pillows[ $variation_id ]['quantity'] += $cart_item['quantity'];
        }
    }

    // 如果只有一款枕頭，不顯示選擇器
    if ( count( $purchased_pillows ) <= 1 ) {
        return;
    }

    // 獲取當前選擇
    $current_selection = WC()->session->get( 'nyb_selected_pillow_variation' );
    if ( ! $current_selection || ! isset( $purchased_pillows[ $current_selection ] ) ) {
        // 預設選擇第一個
        $current_selection = key( $purchased_pillows );
    }

    ?>
    <div class="nyb-pillowcase-selector-notice woocommerce-info" style="margin-bottom: 20px; padding: 15px; border-left: 4px solid #83bd9a;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <div class="flex items-center gap-2"><span class="nyb-selector-icon" style="font-size: 24px;">🎁</span><strong style="color: #4a9d6f; font-size: 15px;">活動贈品：天絲枕套一件</strong></div>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">您購買了多款催眠枕，請選擇您想要的枕套款式：</p>
            </div>
            <div style="display: flex; gap: 10px; width: 100%;">
                <select id="nyb-pillow-selector" class="nyb-pillow-selector" style="border: 2px solid #83bd9a; border-radius: 5px;font-size: 14px; min-width: 200px; height: 45px; line-height: 45px; padding: 0 12px;">
                    <?php foreach ( $purchased_pillows as $var_id => $pillow ) :
                        $pillowcase_id = NYB_PILLOWCASE_MAP[ $var_id ] ?? 0;
                        if ( ! $pillowcase_id ) continue;

                        $pillowcase_product = wc_get_product( $pillowcase_id );
                        $pillowcase_name = $pillowcase_product ? $pillowcase_product->get_name() : '天絲枕套';
                    ?>
                        <option value="<?php echo esc_attr( $var_id ); ?>" <?php selected( $current_selection, $var_id ); ?>>
                            <?php echo esc_html( $pillow['name'] ) . ' → ' . esc_html( $pillowcase_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="nyb-update-pillowcase" class="button" style="background: #83bd9a; color: white; border: none; width: 75px; height: 45px; border-radius: 5px; cursor: pointer; font-weight: bold;">
                    更新
                </button>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#nyb-update-pillowcase').on('click', function() {
            var button = $(this);
            var originalText = button.text();
            var pillowId = $('#nyb-pillow-selector').val();

            button.text('更新中...').prop('disabled', true);

            $.ajax({
                url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                type: 'POST',
                data: {
                    action: 'nyb_save_pillowcase_choice',
                    nonce: '<?php echo wp_create_nonce( 'nyb_pillowcase_choice' ); ?>',
                    pillow_id: pillowId
                },
                success: function(response) {
                    if (response.success) {
                        // 重新載入購物車以更新贈品
                        $('body').trigger('update_checkout');
                        $(document.body).trigger('wc_fragment_refresh');

                        // 顯示成功訊息
                        button.text('✓ 已更新').css('background', '#5da882');

                        setTimeout(function() {
                            button.text(originalText).css('background', '#83bd9a').prop('disabled', false);
                            location.reload(); // 重新載入頁面以套用新選擇
                        }, 1000);
                    } else {
                        alert('更新失敗：' + response.data.message);
                        button.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('更新失敗，請重試');
                    button.text(originalText).prop('disabled', false);
                }
            });
        });
    });
    </script>

    <style>
        .nyb-pillowcase-selector-notice {
            position: relative;
            animation: nyb-fade-in 0.3s ease-in;
            background: linear-gradient(135deg, #f0f9f4 0%, #e8f5ed 100%) !important;
        }

		.nyb-pillowcase-selector-notice:before {
			display: none;
		}

        @keyframes nyb-fade-in {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .nyb-pillow-selector:focus {
            outline: none;
            border-color: #5da882;
            box-shadow: 0 0 0 3px rgba(131, 189, 154, 0.15);
        }

        #nyb-update-pillowcase:hover {
            background: #6ba88a !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }

        #nyb-update-pillowcase:active {
            transform: translateY(0);
            background: #5da882 !important;
        }

        @media (max-width: 768px) {
            .nyb-pillowcase-selector-notice > div {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .nyb-pillow-selector {
                width: 100%;
                min-width: auto;
            }

            #nyb-update-pillowcase {
                width: 100%;
            }
        }
    </style>
    <?php
}

/**
 * =======================================================
 * 模組 5：活動2 - 床墊+催眠枕送茸茸被
 * =======================================================
 */
function nyb_apply_activity_2( $cart, $stats, $context ) {
    // 檢查是否已有此贈品
    $gift_exists = false;

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle2' ) {
            $gift_exists = true;
            break;
        }
    }

    if ( ! $gift_exists ) {
        $cart->add_to_cart( NYB_GIFT_FLEECE_BLANKET, 1, 0, array(), array( '_nyb_auto_gift' => 'bundle2' ) );
        nyb_log( sprintf( "[活動2] 自動加入茸茸被 | ID: %s", NYB_GIFT_FLEECE_BLANKET ), $context );
    }

    // 將贈品價格設為 0
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle2' ) {
            $original_price = $cart_item['data']->get_regular_price();
            $cart_item['data']->set_price( 0 );
            $cart_item['data']->add_meta_data( '_is_free_gift', 'yes', true );
            $cart_item['data']->add_meta_data( '_original_price', $original_price, true );
            nyb_log( sprintf( "[活動2] 將贈品價格設為 0 | 原價: %s", $original_price ), $context );
        }
    }
}

/**
 * =======================================================
 * 模組 6：活動3 - 賴床墊送抱枕+眼罩
 * =======================================================
 */
function nyb_apply_activity_3( $cart, $stats, $context ) {
    $gifts_needed = [
        NYB_GIFT_HUG_PILLOW => false,
        NYB_GIFT_EYE_MASK => false
    ];

    // 檢查已有的贈品
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle3' ) {
            $product_id = $cart_item['product_id'];
            if ( isset( $gifts_needed[ $product_id ] ) ) {
                $gifts_needed[ $product_id ] = true;
            }
        }
    }

    // 加入缺少的贈品
    foreach ( $gifts_needed as $gift_id => $exists ) {
        if ( ! $exists ) {
            $cart->add_to_cart( $gift_id, 1, 0, array(), array( '_nyb_auto_gift' => 'bundle3' ) );
            nyb_log( sprintf( "[活動3] 自動加入贈品 | ID: %s", $gift_id ), $context );
        }
    }

    // 將贈品價格設為 0
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle3' ) {
            $original_price = $cart_item['data']->get_regular_price();
            $cart_item['data']->set_price( 0 );
            $cart_item['data']->add_meta_data( '_is_free_gift', 'yes', true );
            $cart_item['data']->add_meta_data( '_original_price', $original_price, true );
            nyb_log( sprintf( "[活動3] 將贈品價格設為 0 | ID: %s, 原價: %s", $cart_item['product_id'], $original_price ), $context );
        }
    }
}

/**
 * =======================================================
 * 模組 7：活動4 - 滿額贈天絲床包四件組
 * =======================================================
 */
function nyb_apply_activity_4( $cart, $stats, $context ) {
    // 找出床墊的尺寸（用於確定床包價值）
    // 優先使用嗜睡床墊，如果沒有則使用賴床墊
    $mattress_var_id = null;

    foreach ( $cart->get_cart() as $cart_item ) {
        $variation_id = $cart_item['variation_id'];

        // 排除贈品
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            continue;
        }

        // 優先使用嗜睡床墊
        if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
            $mattress_var_id = $variation_id;
            break;
        }
    }

    // 如果沒有嗜睡床墊，使用賴床墊
    if ( ! $mattress_var_id ) {
        foreach ( $cart->get_cart() as $cart_item ) {
            $variation_id = $cart_item['variation_id'];

            // 排除贈品
            if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
                continue;
            }

            if ( isset( NYB_LAI_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
                $mattress_var_id = $variation_id;
                break;
            }
        }
    }

    if ( $mattress_var_id && isset( NYB_BEDDING_VALUE_MAP[ $mattress_var_id ] ) ) {
        // 添加虛擬床包商品到購物車
        $result = NYB_Virtual_Bedding_Product::add_to_cart( $cart, $mattress_var_id, 'bundle4' );

        if ( $result ) {
            nyb_log( sprintf( "[活動4] 已添加天絲四件組床包到購物車 | 床墊 Variation ID: %s, 床包價值: %s", $mattress_var_id, NYB_BEDDING_VALUE_MAP[ $mattress_var_id ] ), $context );
        }
    }
}


/**
 * 移除不再符合條件的贈品
 */
function nyb_remove_invalid_gifts( $cart, $applied_activities, $context ) {
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        // 檢查一般贈品
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            $gift_type = $cart_item['_nyb_auto_gift'];

            // 檢查此贈品是否在已應用的活動中
            if ( ! in_array( $gift_type, $applied_activities ) ) {
                $cart->remove_cart_item( $cart_item_key );
                nyb_log( sprintf( "[新年活動] 移除不符合條件的贈品 | 類型: %s", $gift_type ), $context );
            }
        }

        // 檢查虛擬床包商品
        if ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] === true ) {
            $activity_type = $cart_item['_nyb_activity_type'] ?? '';

            if ( ! in_array( $activity_type, $applied_activities ) ) {
                $cart->remove_cart_item( $cart_item_key );
                nyb_log( sprintf( "[新年活動] 移除不符合條件的虛擬床包 | 類型: %s", $activity_type ), $context );
            }
        }
    }
}

/**
 * =======================================================
 * 模組 11：贈品管理核心
 * =======================================================
 */

/**
 * ⚡ 購物車排序：贈品放在最後
 * 重新排序購物車內容，將贈品移到列表底部
 */
add_filter( 'woocommerce_get_cart_contents', 'nyb_sort_cart_items', 99 );
function nyb_sort_cart_items( $cart_contents ) {
    if ( empty( $cart_contents ) ) {
        return $cart_contents;
    }

    $regular_items = [];
    $gift_items = [];

    // 分離一般商品和贈品
    foreach ( $cart_contents as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            $gift_items[ $cart_item_key ] = $cart_item;
        } else {
            $regular_items[ $cart_item_key ] = $cart_item;
        }
    }

    // 合併：一般商品在前，贈品在後
    return array_merge( $regular_items, $gift_items );
}

/**
 * 在購物車和結帳頁中添加贈品分隔線
 * 在第一個贈品前顯示視覺分隔
 */
add_action( 'woocommerce_before_cart_contents', 'nyb_inject_gift_separator_script' );
add_action( 'woocommerce_review_order_before_cart_contents', 'nyb_inject_gift_separator_script' );
function nyb_inject_gift_separator_script() {
    static $script_added = false;

    if ( $script_added ) {
        return;
    }
    $script_added = true;

    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function addGiftSeparator() {
            // 移除已存在的分隔線（避免重複）
            $('.nyb-gift-separator-row').remove();

            // 購物車頁面
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

            // 結帳頁面
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

        // 初始執行
        addGiftSeparator();

        // 購物車更新後重新執行
        $(document.body).on('updated_cart_totals updated_checkout', function() {
            addGiftSeparator();
        });
    });
    </script>
    <?php
}

/**
 * 為贈品行添加特殊樣式類別（購物車頁）
 */
add_filter( 'woocommerce_cart_item_class', 'nyb_add_gift_item_class', 10, 3 );
function nyb_add_gift_item_class( $class, $cart_item, $cart_item_key ) {
    if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
        $class .= ' nyb-gift-item';
    }
    return $class;
}

/**
 * 為贈品行添加特殊樣式類別（結帳頁）
 * 使用 cart_item_class 也會應用到結帳頁面
 */

/**
 * 添加購物車贈品區域的 CSS 樣式
 */
add_action( 'wp_head', 'nyb_gift_separator_styles' );
function nyb_gift_separator_styles() {
    if ( ! is_cart() && ! is_checkout() ) {
        return;
    }

    ?>
    <style type="text/css">
        /* 贈品分隔線樣式 */
        /* .nyb-gift-separator-row {
            background: transparent !important;
        }

        .nyb-gift-separator {
nt(to bottom, #fff 0%, #fff9f0 100%) !important;
        } */

        /* 贈品項目樣式 */
        /* .nyb-gift-item {
            background: #fff9f0 !important;
            border-left: 3px solid #df565f !important;
        } */

        /* .nyb-gift-item:hover {
            background: #fff3e0 !important;
        } */

        /* 贈品項目內的圖片添加標誌 */
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

        /* 結帳頁面的贈品樣式 */
        /* .woocommerce-checkout-review-order-table .nyb-gift-item {
            background: #fff9f0 !important;
            border-left: 3px solid #df565f !important;
        } */

        /* 手機版適配 */
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

// 顯示贈品標籤和原價
add_filter( 'woocommerce_cart_item_price', 'nyb_display_gift_original_price', 1000, 3 );
function nyb_display_gift_original_price( $price, $cart_item, $cart_item_key ) {
    $product = $cart_item['data'];

    if ( $product->get_meta( '_is_free_gift' ) === 'yes' ) {
        $original_price = $product->get_meta( '_original_price' );
        if ( $original_price ) {
            return '<del>' . wc_price( $original_price ) . '</del> <ins>' . wc_price( 0 ) . '</ins><br><span style="color: #df565f; font-weight: bold;">🎁 免費贈送</span>';
        }
    }

    return $price;
}

// 顯示小計（購物車頁）
add_filter( 'woocommerce_cart_item_subtotal', 'nyb_display_gift_original_subtotal', 1000, 3 );
function nyb_display_gift_original_subtotal( $subtotal, $cart_item, $cart_item_key ) {
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

// 結帳頁顯示贈品標籤
add_filter( 'woocommerce_checkout_cart_item_quantity', 'nyb_display_gift_quantity_on_checkout', 10, 3 );
function nyb_display_gift_quantity_on_checkout( $quantity_html, $cart_item, $cart_item_key ) {
    $product = $cart_item['data'];

    if ( $product->get_meta( '_is_free_gift' ) === 'yes' ) {
        return $cart_item['quantity'] . ' <span style="color: #df565f; font-size: 0.9em;">(贈品)</span>';
    }

    return $quantity_html;
}

// 禁用贈品數量修改
add_filter( 'woocommerce_cart_item_quantity', 'nyb_disable_gift_quantity_input', 10, 3 );
function nyb_disable_gift_quantity_input( $product_quantity, $cart_item_key, $cart_item ) {
    if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
        return '<span class="quantity" style="color: #999;">' . $cart_item['quantity'] . ' <small>(贈品，數量自動調整)</small></span>';
    }

    return $product_quantity;
}

// 防止手動修改贈品數量
add_filter( 'woocommerce_update_cart_validation', 'nyb_prevent_gift_quantity_change', 10, 4 );
function nyb_prevent_gift_quantity_change( $passed, $cart_item_key, $values, $quantity ) {
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

// 將贈品資訊存入訂單項目
add_action( 'woocommerce_checkout_create_order_line_item', 'nyb_save_gift_meta_to_order_item', 10, 4 );
function nyb_save_gift_meta_to_order_item( $item, $cart_item_key, $values, $order ) {
    $product = $values['data'];

    if ( $product->get_meta( '_is_free_gift' ) === 'yes' ) {
        $item->add_meta_data( '贈品', '免費贈送 🎁', true );
        $original_price = $product->get_meta( '_original_price' );
        if ( $original_price ) {
            $item->add_meta_data( '_gift_original_price', $original_price, true );
        }
    }
}

// 移除之前的訂單備註函數（虛擬商品會自動記錄在訂單項目中）

/**
 * =======================================================
 * 模組 14：訂單活動記錄系統
 * 記錄已應用的活動到訂單中，並在訂單詳情頁顯示
 * =======================================================
 */

/**
 * 在訂單創建時記錄所有已應用的活動
 */
add_action( 'woocommerce_checkout_create_order', 'nyb_save_applied_activities_to_order', 20, 2 );
function nyb_save_applied_activities_to_order( $order, $data ) {
    // 獲取當前已符合的活動
    $activity_status = nyb_calculate_activity_status();

    $qualified = array_filter( $activity_status, function( $status ) {
        return $status['status'] === 'qualified';
    });

    if ( empty( $qualified ) ) {
        return;
    }

    $applied_activities = [];
    $activity_notes = [];

    foreach ( $qualified as $key => $data_item ) {
        $activity_name = nyb_get_activity_name( $key );
        $applied_activities[] = [
            'key' => $key,
            'name' => $activity_name,
            'applied_at' => current_time( 'mysql' )
        ];

        $activity_notes[] = sprintf( '✓ %s', $activity_name );
    }

    // 儲存活動列表到訂單 meta
    $order->update_meta_data( '_nyb_applied_activities', $applied_activities );
    $order->update_meta_data( '_nyb_activity_count', count( $applied_activities ) );

    // 添加訂單備註
    if ( ! empty( $activity_notes ) ) {
        $note = "【2026新年優惠活動】\n" . implode( "\n", $activity_notes );
        $order->add_order_note( $note );
    }

    // 儲存折扣摘要
    $order->update_meta_data( '_nyb_has_activities', 'yes' );
}

/**
 * 在訂單詳情頁（前台）顯示已應用的活動
 */
add_action( 'woocommerce_order_details_after_order_table', 'nyb_display_applied_activities_on_order', 10, 1 );
function nyb_display_applied_activities_on_order( $order ) {
    $applied_activities = $order->get_meta( '_nyb_applied_activities' );

    if ( empty( $applied_activities ) ) {
        return;
    }

    ?>
    <section class="woocommerce-order-activities">
        <h2 class="woocommerce-order-activities-title">已享優惠活動</h2>
        <div class="nyb-order-activities-list">
            <?php foreach ( $applied_activities as $activity ) : ?>
                <div class="nyb-order-activity-item">
                    <span class="nyb-activity-icon">🎁</span>
                    <span class="nyb-activity-label"><?php echo esc_html( $activity['name'] ); ?></span>
                    <span class="nyb-activity-status">已套用</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <style>
        .woocommerce-order-activities {
            margin-top: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #fff9f0 0%, #ffe8cc 100%);
            border: 2px solid #df565f;
            border-radius: 8px;
        }

        .woocommerce-order-activities-title {
            margin: 0 0 15px 0;
            font-size: 18px;
            color: #df565f;
            border-bottom: 2px solid #df565f;
            padding-bottom: 10px;
        }

        .nyb-order-activities-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nyb-order-activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: white;
            border: 2px dashed #df565f;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(223, 86, 95, 0.1);
        }

        .nyb-activity-icon {
            font-size: 24px;
            line-height: 1;
        }

        .nyb-activity-label {
            flex: 1;
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }

        .nyb-activity-status {
            background: #df565f;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .woocommerce-order-activities {
                padding: 15px;
            }

            .nyb-order-activity-item {
                padding: 10px 12px;
            }

            .nyb-activity-label {
                font-size: 13px;
            }
        }
    </style>
    <?php
}

/**
 * 在後台訂單詳情頁顯示已應用的活動
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'nyb_display_applied_activities_in_admin', 10, 1 );
function nyb_display_applied_activities_in_admin( $order ) {
    $applied_activities = $order->get_meta( '_nyb_applied_activities' );

    if ( empty( $applied_activities ) ) {
        return;
    }

    ?>
    <div class="order_data_column" style="clear: both; margin-top: 20px; width: 100%;">
        <h3 style="color: #df565f; border-bottom: 2px solid #df565f; padding-bottom: 8px;">
            🎁 已套用的新年優惠活動
        </h3>
        <div style="margin-top: 12px;">
            <?php foreach ( $applied_activities as $activity ) : ?>
                <p style="margin: 8px 0; padding: 10px !important; background: #fff9f0; border-left: 4px solid #df565f; font-size: 13px;">
                    <strong><?php echo esc_html( $activity['name'] ); ?></strong>
                    <br>
                    <small style="color: #666;">套用時間: <?php echo esc_html( $activity['applied_at'] ); ?></small>
                </p>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * 在訂單列表添加活動標記欄位
 */
add_filter( 'manage_edit-shop_order_columns', 'nyb_add_order_activity_column', 20 );
function nyb_add_order_activity_column( $columns ) {
    $new_columns = [];

    foreach ( $columns as $key => $column ) {
        $new_columns[ $key ] = $column;

        // 在狀態欄位後添加活動欄位
        if ( $key === 'order_status' ) {
            $new_columns['nyb_activities'] = '優惠活動';
        }
    }

    return $new_columns;
}

/**
 * 顯示訂單列表的活動標記內容
 */
add_action( 'manage_shop_order_posts_custom_column', 'nyb_display_order_activity_column_content', 10, 2 );
function nyb_display_order_activity_column_content( $column, $post_id ) {
    if ( $column === 'nyb_activities' ) {
        $order = wc_get_order( $post_id );
        $activity_count = $order->get_meta( '_nyb_activity_count' );

        if ( $activity_count ) {
            echo '<span style="display: inline-block; background: #df565f; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">';
            echo '🎁 ' . $activity_count . '個';
            echo '</span>';
        } else {
            echo '<span style="color: #999;">-</span>';
        }
    }
}