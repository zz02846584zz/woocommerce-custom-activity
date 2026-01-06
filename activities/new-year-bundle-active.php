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

// 床墊相關
define( 'NYB_LAI_MATTRESS_PARENT_IDS', [3444] ); // 所有賴床墊父層ID
define( 'NYB_SPRING_MATTRESS_PARENT_IDS', [1324, 4370] ); // 所有嗜睡床墊父層ID

define( 'NYB_LAI_MATTRESS_VARS', [3446, 3445, 3447, 3448, 3695, 3696] ); // 賴床墊

define( 'NYB_SPRING_MATTRESS_VARS', [
    2735, 2736, 2737, 2738, 2739,      // 嗜睡床墊(大地系列)
    4371, 4372, 4373, 4374, 4375       // 嗜睡床墊(海洋系列)
] );

// ⚡ 性能優化：Hash Map (O(1) 查詢速度)
define( 'NYB_LAI_MATTRESS_PARENT_IDS_MAP', array_flip( NYB_LAI_MATTRESS_PARENT_IDS ) );
define( 'NYB_SPRING_MATTRESS_PARENT_IDS_MAP', array_flip( NYB_SPRING_MATTRESS_PARENT_IDS ) );
define( 'NYB_LAI_MATTRESS_VARS_MAP', array_flip( NYB_LAI_MATTRESS_VARS ) );
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

// 床架
define( 'NYB_BED_FRAME_PARENT', 4421 );
define( 'NYB_BED_FRAME_IDS', [4930, 4929, 4422, 4423, 4424, 4425, 4426] );
define( 'NYB_BED_FRAME_IDS_MAP', array_flip( NYB_BED_FRAME_IDS ) );

// 贈品
define( 'NYB_GIFT_FLEECE_BLANKET', 4180 );  // 茸茸被
define( 'NYB_GIFT_HUG_PILLOW', 6346 );      // 抱枕
define( 'NYB_GIFT_EYE_MASK', 6300 );        // 眼罩
define( 'NYB_GIFT_SIDE_PILLOW_VAR', 3044 ); // 側睡枕variation

// 天絲枕套對應 (枕頭 -> 枕套)
define( 'NYB_PILLOWCASE_MAP', [
    2983 => 4439,
    2984 => 5663,
    3044 => 5662
] );

// 活動3特價組合價格
define( 'NYB_COMBO_SPECIAL_PRICE', 8888 );

// 所有贈品ID集合（用於排除9折）
define( 'NYB_ALL_GIFT_IDS', [
    NYB_GIFT_FLEECE_BLANKET,
    NYB_GIFT_HUG_PILLOW,
    NYB_GIFT_EYE_MASK,
    NYB_HYPNOTIC_PILLOW_PARENT, // 枕頭父層（BOGO贈品）
    4439, 5663, 5662            // 天絲枕套
] );
define( 'NYB_ALL_GIFT_IDS_MAP', array_flip( NYB_ALL_GIFT_IDS ) );

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
        $discounted = $regular_price * 0.9;
        // $price_cache[ $cache_key ] = $discounted;
        return $discounted;
    }

		// $product->set_sale_price( $price * 0.9 );
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
				return $regular_price * 0.9;
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
 * 計算所有活動的符合狀態
 * ⚡ 使用靜態變數快取結果
 * @return array
 */
function nyb_calculate_activity_status($product_id = 0) {

	nyb_log( 'nyb_calculate_activity_status' . $product_id, $product_id );

    // ⚡ 靜態快取
    static $cached_status = null;
    static $cached_cart_hash = null;

    $cart = WC()->cart;
    // if ( ! $cart || $cart->is_empty() ) {
    //     return [];
    // }

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

    // 活動1: 床墊+催眠枕送茸茸被
    $has_spring_mattress = $stats['spring_mattress_count'] > 0;
    $has_hypnotic = $stats['hypnotic_pillow_count'] > 0;

    if ( $has_spring_mattress && $has_hypnotic ) {
        $results['activity_1'] = ['status' => 'qualified', 'missing' => []];
    } elseif ( $has_spring_mattress && ! $has_hypnotic && !isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $product_id ] ) ) {
        $results['activity_1'] = ['status' => 'almost', 'missing' => ['催眠枕']];
    } elseif ( ! $has_spring_mattress && $has_hypnotic && !isset( NYB_SPRING_MATTRESS_VARS_MAP[ $product_id ] ) ) {
        $results['activity_1'] = ['status' => 'almost', 'missing' => ['嗜睡床墊']];
    } else {
        $results['activity_1'] = ['status' => 'not_qualified', 'missing' => ['嗜睡床墊', '催眠枕']];
    }

    // 活動2: 賴床墊送抱枕+眼罩
    if ( $stats['lai_mattress_count'] > 0 ) {
        $results['activity_2'] = ['status' => 'qualified', 'missing' => []];
    } else {
        $results['activity_2'] = ['status' => 'almost', 'missing' => ['賴床墊']];
    }

    // 活動3: 枕頭組合特價$8888（任意2個枕頭）
    if ( $stats['hypnotic_pillow_count'] >= 2 ) {
        $results['activity_3'] = ['status' => 'qualified', 'missing' => []];
    } elseif ( $stats['hypnotic_pillow_count'] == 1 ) {
        $results['activity_3'] = ['status' => 'almost', 'missing' => ['再1個催眠枕']];
    } else {
        $results['activity_3'] = ['status' => 'not_qualified', 'missing' => ['2個催眠枕']];
    }

    // 活動4: 枕頭買一送一+天絲枕套
    if ( $stats['hypnotic_pillow_count'] > 0 ) {
        $results['activity_4'] = ['status' => 'qualified', 'missing' => []];
    } else {
        $results['activity_4'] = ['status' => 'not_qualified', 'missing' => ['催眠枕']];
    }

    // 活動5: 大禮包送天絲四件組
    $has_spring_mattress = $stats['spring_mattress_count'] > 0;
    $has_lai_mattress = $stats['lai_mattress_count'] > 0;
    $has_2_hypnotic = $stats['hypnotic_pillow_count'] >= 2;

    if ( $has_spring_mattress && $has_hypnotic && $has_lai_mattress && $has_2_hypnotic ) {
        $results['activity_5'] = ['status' => 'qualified', 'missing' => []];
    } else {
        $missing = [];
        if ( ! $has_spring_mattress ) $missing[] = '嗜睡床墊';
        if ( ! $has_lai_mattress ) $missing[] = '賴床墊';
        if ( ! $has_2_hypnotic ) $missing[] = sprintf( '催眠枕(需2個，目前%d個)', $stats['hypnotic_pillow_count'] );
        $results['activity_5'] = ['status' => 'almost', 'missing' => $missing];
    }

    // 活動6: 床墊+床架送側睡枕
    $has_bed_frame = $stats['bed_frame_count'] > 0;

    if ( $has_spring_mattress && $has_bed_frame ) {
        $results['activity_6'] = ['status' => 'qualified', 'missing' => []];
    } elseif ( $has_spring_mattress && ! $has_bed_frame ) {
        $results['activity_6'] = ['status' => 'almost', 'missing' => ['床架']];
    } elseif ( ! $has_spring_mattress && $has_bed_frame ) {
        $results['activity_6'] = ['status' => 'almost', 'missing' => ['嗜睡床墊']];
    } else {
        $results['activity_6'] = ['status' => 'not_qualified', 'missing' => ['嗜睡床墊', '床架']];
    }

    // 活動7: 終極組合

    if ( $has_spring_mattress && $has_bed_frame && $has_2_hypnotic ) {
        $results['activity_7'] = ['status' => 'qualified', 'missing' => []];
    } elseif ( $has_spring_mattress && ! $has_bed_frame && !isset( NYB_BED_FRAME_IDS_MAP[ $product_id ] ) ) {
        $missing = [];
        if ( ! $has_spring_mattress ) $missing[] = '嗜睡床墊';
        if ( ! $has_bed_frame ) $missing[] = '床架';
        if ( ! $has_2_hypnotic ) $missing[] = sprintf( '催眠枕(需2個，目前%d個)', $stats['hypnotic_pillow_count'] );
				$results['activity_7'] = ['status' => 'almost', 'missing' => $missing];
    } else {
				$results['activity_7'] = ['status' => 'not_qualified', 'missing' => ['嗜睡床墊', '床架', sprintf( '催眠枕(需2個，目前%d個)', $stats['hypnotic_pillow_count'] )]];
		}

    // 快取結果
    $cached_status = $results;
    $cached_cart_hash = $cart_hash;

    return $results;
}

/**
 * 分析購物車內容
 * ⚡ 使用靜態快取 + Hash Map
 * @return array
 */
function nyb_analyze_cart_contents() {
    // ⚡ 靜態快取
    static $cached_stats = null;
    static $cached_cart_hash = null;

    $cart = WC()->cart;
    $cart_contents = $cart->get_cart_contents();
    $cart_hash = md5( serialize( $cart_contents ) );

    $stats = [
        'mattress_count' => 0,
        'spring_mattress_count' => 0,
        'lai_mattress_count' => 0,
        'hypnotic_pillow_count' => 0,
        'hypnotic_pillow_count:other' => 0,
        'hypnotic_pillow_count:high' => 0,
        'hypnotic_pillow_vars' => [],
        'bed_frame_count' => 0,
        'mattress_vars' => []
    ];

    foreach ( $cart->get_cart() as $cart_item ) {
        $product_id = $cart_item['product_id'];
        $variation_id = $cart_item['variation_id'];
        $quantity = $cart_item['quantity'];

        // 排除自動贈品
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            continue;
        }

        // ⚡ 使用 Hash Map 替代 in_array
				// 嗜睡床墊
				if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
						$stats['spring_mattress_count'] += $quantity;
				}

				// 賴床墊
				if ( isset( NYB_LAI_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
						$stats['lai_mattress_count'] += $quantity;
				}

        // 催眠枕
        if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ] ) ) {
            $stats['hypnotic_pillow_count'] += $quantity;
            if ( $variation_id == 2984 ) {
                $stats['hypnotic_pillow_count:high'] += $quantity;
            } else {
                $stats['hypnotic_pillow_count:other'] += $quantity;
            }
            if ( ! isset( $stats['hypnotic_pillow_vars'][ $variation_id ] ) ) {
                $stats['hypnotic_pillow_vars'][ $variation_id ] = 0;
            }
            $stats['hypnotic_pillow_vars'][ $variation_id ] += $quantity;
        }

        // 床架
        if ( isset( NYB_BED_FRAME_IDS_MAP[ $variation_id ] ) || $product_id == NYB_BED_FRAME_PARENT ) {
            $stats['bed_frame_count'] += $quantity;
        }
    }

    // 快取結果
    $cached_stats = $stats;
    $cached_cart_hash = $cart_hash;

    return $stats;
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

		nyb_log( 'nyb_get_related_activities_status' . print_r( $all_status, true ) . count( $all_status ), $all_status );
		nyb_log( 'nyb_get_related_activities' . $product_id . ' ' . $variation_id, $all_status );

    $check_id = $variation_id != 0 ? $variation_id : $product_id;

    // ⚡ 使用 Hash Map 判斷商品屬於哪些活動
		// 賴床墊相關
		if ( isset( NYB_LAI_MATTRESS_VARS_MAP[ $check_id ] ) || isset( NYB_LAI_MATTRESS_PARENT_IDS_MAP[ $product_id ] ) ) {
				if ( isset( $all_status['activity_2'] ) ) {
						$related[] = ['key' => 'activity_2', 'data' => $all_status['activity_2'], 'priority' => 6];
				}
				if ( isset( $all_status['activity_5'] ) ) {
						$related[] = ['key' => 'activity_5', 'data' => $all_status['activity_5'], 'priority' => 3];
				}
		}

		// 嗜睡床墊相關
		if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $check_id ] ) || isset( NYB_SPRING_MATTRESS_PARENT_IDS_MAP[ $product_id ] ) ) {
			nyb_log( 'nyb_get_related_activities Spring Mattress', $all_status['activity_1'] );
				if ( isset( $all_status['activity_1'] ) ) {
					nyb_log( 'nyb_get_related_activities Spring Mattress activity_1', $all_status['activity_1'] );
						$related[] = ['key' => 'activity_1', 'data' => $all_status['activity_1'], 'priority' => 7];
				}
				if ( isset( $all_status['activity_5'] ) ) {
					nyb_log( 'nyb_get_related_activities Spring Mattress activity_5', $all_status['activity_5'] );
						$related[] = ['key' => 'activity_5', 'data' => $all_status['activity_5'], 'priority' => 3];
				}
				if ( isset( $all_status['activity_6'] ) ) {
					nyb_log( 'nyb_get_related_activities Spring Mattress activity_6', $all_status['activity_6'] );
						$related[] = ['key' => 'activity_6', 'data' => $all_status['activity_6'], 'priority' => 2];
				}
				if ( isset( $all_status['activity_7'] ) ) {
					nyb_log( 'nyb_get_related_activities Spring Mattress activity_7', $all_status['activity_7'] );
						$related[] = ['key' => 'activity_7', 'data' => $all_status['activity_7'], 'priority' => 1];
				}
		}

    // 催眠枕相關
    if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $check_id ] ) || $product_id == NYB_HYPNOTIC_PILLOW_PARENT ) {
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
    if ( isset( NYB_BED_FRAME_IDS_MAP[ $check_id ] ) || $product_id == NYB_BED_FRAME_PARENT) {
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
            'activity_1' => '嗜睡床墊+催眠枕送茸茸被',
            'activity_2' => '賴床墊送抱枕+眼罩',
            'activity_3' => '催眠枕任選2顆特價$8,888',
            'activity_4' => '枕頭買一送一，再送天絲枕套',
            'activity_5' => '嗜睡床墊+催眠枕×2+賴床墊，贈天絲四件組床包',
            'activity_6' => '嗜睡床墊+床架送側睡枕',
            'activity_7' => '嗜睡床墊+床架+催眠枕×2，贈天絲四件組床包+茸茸被'
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
				'hypnotic_pillow_high' => nyb_get_product_link( 2984, '高枕' ),
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
    $mattress_link = nyb_get_category_links( 'mattress' );
    $spring_mattress_link = nyb_get_category_links( 'spring_mattress' );
    $hypnotic_pillow_link = nyb_get_category_links( 'hypnotic_pillow' );
		$hypnotic_pillow_link_high = nyb_get_category_links( 'hypnotic_pillow_high' );
    $lai_mattress_link = nyb_get_category_links( 'lai_mattress' );
    $bed_frame_link = nyb_get_category_links( 'bed_frame' );
    $fleece_blanket_link = nyb_get_category_links( 'fleece_blanket' );
    $hug_pillow_link = nyb_get_category_links( 'hug_pillow' );
    $eye_mask_link = nyb_get_category_links( 'eye_mask' );
    $side_pillow_link = nyb_get_category_links( 'side_pillow' );
    $pillowcase_link = nyb_get_category_links( 'pillowcase' );
    $bedding_set_link = nyb_get_category_links( 'bedding_set' );

    $notices = [
        'activity_1' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買' . $spring_mattress_link . '和' . $hypnotic_pillow_link . '，將獲贈' . $fleece_blanket_link,
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $mattress_link, $hypnotic_pillow_link, $fleece_blanket_link ) {
                    $links = [];
                    $has_spring_mattress = true;
                    $has_pillow = true;

                    foreach ( $missing as $item ) {
                        if ( $item === '嗜睡床墊' ) {
                            $links[] = $mattress_link;
                            $has_spring_mattress = false;
                        } elseif ( $item === '催眠枕' ) {
                            $links[] = $hypnotic_pillow_link;
                            $has_pillow = false;
                        }
                    }

                    if ( empty( $links ) ) {
                        return '購買' . $mattress_link . '和' . $hypnotic_pillow_link . '，即可獲得' . $fleece_blanket_link;
                    }

                    $prefix = ( $has_spring_mattress || $has_pillow ) ? '再購買' : '購買';
                    return $prefix . implode( '和', $links ) . '，即可獲得' . $fleece_blanket_link;
                },
                'type' => 'info'
            ],
						'not_qualified' => [
							'title' => '',
							'message' => function() use ( $missing, $mattress_link, $hypnotic_pillow_link, $fleece_blanket_link ) {
								return '購買' . $mattress_link . '和' . $hypnotic_pillow_link . '，即可獲得' . $fleece_blanket_link;
							},
							'type' => 'info'
						]
        ],
        'activity_2' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買' . $lai_mattress_link . '，將獲贈' . $hug_pillow_link . '和' . $eye_mask_link,
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $lai_mattress_link, $hug_pillow_link, $eye_mask_link ) {
                    if ( empty( $missing ) || in_array( '賴床墊', $missing ) ) {
                        return '購買' . $lai_mattress_link . '，即可獲得' . $hug_pillow_link . '和' . $eye_mask_link;
                    }
                    return '購買' . $lai_mattress_link . '，即可獲得' . $hug_pillow_link . '和' . $eye_mask_link;
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
        'activity_3' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買2個' . $hypnotic_pillow_link . '，享特價<strong>$8,888</strong>（最高價2個枕頭組合）',
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $hypnotic_pillow_link ) {
                    // 獲取當前購物車統計
                    $stats = nyb_analyze_cart_contents();
                    $pillow_count = $stats['hypnotic_pillow_count'] ?? 0;

                    if ( $pillow_count == 1 ) {
                        return '再購買1個' . $hypnotic_pillow_link . '，即享特價<strong>$8,888</strong>（任意2個枕頭）';
                    }

                    return '購買任意2個' . $hypnotic_pillow_link . '，即享特價<strong>$8,888</strong>';
                },
                'type' => 'info'
            ],
						'not_qualified' => [
							'title' => '',
							'message' => function() use ( $missing, $hypnotic_pillow_link ) {
								return '購買' . $hypnotic_pillow_link . '，即可獲得<strong>相同枕頭</strong>和' . $pillowcase_link . '（買一送一）';
							},
							'type' => 'info'
						]
        ],
        'activity_4' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買' . $hypnotic_pillow_link . '，將獲贈<strong>相同枕頭</strong>和' . $pillowcase_link . '（買一送一）',
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $hypnotic_pillow_link, $pillowcase_link ) {
                    if ( empty( $missing ) || in_array( '催眠枕', $missing ) ) {
                        return '購買' . $hypnotic_pillow_link . '，即可獲得<strong>相同枕頭</strong>和' . $pillowcase_link . '（買一送一）';
                    }
                    return '購買' . $hypnotic_pillow_link . '，即可獲得<strong>相同枕頭</strong>和' . $pillowcase_link . '（買一送一）';
                },
                'type' => 'info'
            ],
						'not_qualified' => [
							'title' => '',
							'message' => function() use ( $missing, $hypnotic_pillow_link, $pillowcase_link ) {
								return '購買' . $hypnotic_pillow_link . '，即可獲得<strong>相同枕頭</strong>和' . $pillowcase_link . '（買一送一）';
							},
							'type' => 'info'
						]
        ],
        'activity_5' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買' . $spring_mattress_link . '、' . $hypnotic_pillow_link . '×2和' . $lai_mattress_link . '，將獲贈' . $bedding_set_link,
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $spring_mattress_link, $hypnotic_pillow_link, $lai_mattress_link, $bedding_set_link ) {
                    $links = [];
                    foreach ( $missing as $item ) {
                        if ( strpos( $item, '嗜睡床墊' ) !== false ) {
                            $links[] = $spring_mattress_link;
                        } elseif ( strpos( $item, '賴床墊' ) !== false ) {
                            $links[] = $lai_mattress_link;
                        } elseif ( strpos( $item, '催眠枕' ) !== false ) {
                            $links[] = $hypnotic_pillow_link . '<small>（' . $item . '）</small>';
                        }
                    }
                    $prefix = ! empty( $links ) && count( $missing ) < 3 ? '再購買' : '購買';
                    return $prefix . implode( '、', $links ) . '，即可獲得' . $bedding_set_link;
                },
                'type' => 'info'
            ],
						'not_qualified' => [
							'title' => '',
							'message' => function() use ( $missing, $spring_mattress_link, $hypnotic_pillow_link, $lai_mattress_link, $bedding_set_link ) {
								return '購買' . $spring_mattress_link . '、' . $hypnotic_pillow_link . '<small>（2個）</small>和' . $lai_mattress_link . '，即可獲得' . $bedding_set_link;
							},
							'type' => 'info'
						]
        ],
        'activity_6' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買' . $mattress_link . '和' . $bed_frame_link . '，將獲贈' . $side_pillow_link,
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $mattress_link, $bed_frame_link, $side_pillow_link ) {
                    $links = [];
                    $has_something = false;

                    foreach ( $missing as $item ) {
                        if ( $item === '嗜睡床墊' ) {
                            $links[] = $mattress_link;
                        } elseif ( $item === '床架' ) {
                            $links[] = $bed_frame_link;
                        }
                    }

                    if ( empty( $links ) ) {
                        return '購買' . $mattress_link . '和' . $bed_frame_link . '，即可獲得' . $side_pillow_link;
                    }

                    $prefix = count( $missing ) < 2 ? '再購買' : '購買';
                    return $prefix . implode( '和', $links ) . '，即可獲得' . $side_pillow_link;
                },
                'type' => 'info'
							],
							'not_qualified' => [
								'title' => '',
								'message' => function() use ( $missing, $mattress_link, $bed_frame_link, $side_pillow_link ) {
									return '購買' . $mattress_link . '和' . $bed_frame_link . '，即可獲得' . $side_pillow_link;
								},
								'type' => 'info'
							]
        ],
        'activity_7' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買' . $mattress_link . '、' . $bed_frame_link . '和' . $hypnotic_pillow_link . '×2，將獲贈' . $bedding_set_link . '和' . $fleece_blanket_link,
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $mattress_link, $bed_frame_link, $hypnotic_pillow_link, $bedding_set_link, $fleece_blanket_link ) {
                    $links = [];
                    foreach ( $missing as $item ) {
                        if ( $item === '嗜睡床墊' ) {
                            $links[] = $mattress_link;
                        } elseif ( $item === '床架' ) {
                            $links[] = $bed_frame_link;
                        } elseif ( strpos( $item, '催眠枕' ) !== false ) {
                            $links[] = $hypnotic_pillow_link . '<small>（' . $item . '）</small>';
                        }
                    }

                    if ( empty( $links ) ) {
                        return '購買' . $mattress_link . '、' . $bed_frame_link . '和' . $hypnotic_pillow_link . '<small>（2個）</small>，即可獲得' . $bedding_set_link . '和' . $fleece_blanket_link;
                    }

                    $prefix = count( $missing ) < 3 ? '再購買' : '購買';
                    return $prefix . implode( '、', $links ) . '，即可獲得' . $bedding_set_link . '和' . $fleece_blanket_link;
                },
                'type' => 'info'
						],
						'not_qualified' => [
							'title' => '',
							'message' => function() use ( $missing, $mattress_link, $bed_frame_link, $hypnotic_pillow_link, $bedding_set_link, $fleece_blanket_link ) {
								return '購買' . $mattress_link . '、' . $bed_frame_link . '和' . $hypnotic_pillow_link . '<small>（2個）</small>，即可獲得' . $bedding_set_link . '和' . $fleece_blanket_link;
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

		nyb_log( 'nyb_smart_product_page_notice 872' . $product_id, $product_id );
		nyb_log( 'nyb_smart_product_page_notice 873' . $parent_id, $parent_id );
		nyb_log( 'nyb_smart_product_page_notice 874' . ($parent_id != 0 ? $parent_id : $product_id), $parent_id ? $parent_id : $product_id );

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
				// 如果是商品頁面
				if ( is_product() ) {
					foreach ( $qualified as $act ) {
						$notice = nyb_get_activity_notice( $act['key'], 'qualified', [] );

						echo '<div class="woocommerce-info" style="margin-bottom: 15px; padding: 12px 15px; background: #e8f5e9; border-left: 4px solid #4caf50;">';
						// echo '<div style="font-weight: bold; margin-bottom: 5px; color: #2e7d32;">' . $notice['title'] . '</div>';
						echo '<div data-missing="' . json_encode( $notice['missing'] ) . '" style="font-size: 14px; color: #1b5e20;">' . $notice['message'] . '</div>';
						echo '</div>';
					}
				}
    }

    // 顯示「差一點」的活動（每個獨立顯示）- 不論是否有已符合的活動
    if ( ! empty( $almost ) ) {
        foreach ( $almost as $act ) {
            $notice = nyb_get_activity_notice( $act['key'], 'almost', $act['data']['missing'] );

						// if( is_product() ) {
						// 	$page_id = get_the_ID();
						// 	$product = wc_get_product( $page_id );
						// 	if( $product ) {
						// 		$product_name = $product->get_name();

						// 		if(str_contains($product_name, '嗜睡床墊')) {
						// 			$product_name = '嗜睡床墊';
						// 		} else if(str_contains($product_name, '賴床墊')) {
						// 			$product_name = '賴床墊';
						// 		} else if(str_contains($product_name, '催眠枕')) {
						// 			$product_name = '催眠枕';
						// 		} else if(str_contains($product_name, '床架')) {
						// 			$product_name = '床架';
						// 		}

						// 		$exists = array_any(
						// 				$act['data']['missing'],
						// 				fn($item) => str_contains($item, $product_name)
						// 		);

						// 		if( !$exists ) {
						// 			continue;
						// 		}
						// 	}
						// }

            echo '<div class="woocommerce-message" style="margin-bottom: 15px; padding: 12px 15px; background: #fff3e0 !important; border-left: 4px solid #ff9800 !important;">';
            // echo '<div style="font-weight: bold; margin-bottom: 5px; color: #e65100;">' . $notice['title'] . '</div>';
            echo '<div data-missing="' . json_encode( $notice['missing'] ) . '" style="font-size: 14px; color: #e65100;">' . $notice['message'] . '</div>';
            echo '</div>';
        }
    }

		if ( ! empty( $not_qualified ) ) {
			if ( is_product() ) {
				foreach ( $not_qualified as $act ) {
					$notice = nyb_get_activity_notice( $act['key'], 'not_qualified', $act['data']['missing'] );

					echo '<div class="woocommerce-message" style="margin-bottom: 15px; padding: 12px 15px; background: #fff3e0 !important; border-left: 4px solid #ff9800 !important;">';
					echo '<div data-missing="' . json_encode( $notice['missing'] ) . '" style="font-size: 14px; color: #e65100;">' . $notice['message'] . '</div>';
					echo '</div>';
				}
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

    // 只顯示「已符合」的活動（每個獨立顯示）
    // $qualified = array_filter( $activity_status, function( $status ) {
    //     return $status['status'] === 'qualified';
    // });

    // if ( ! empty( $qualified ) ) {
    //     foreach ( $qualified as $key => $data ) {
    //         $notice = nyb_get_activity_notice( $key, 'qualified', [] );

    //         echo '<div class="woocommerce-info" style="margin-bottom: 15px; padding: 12px 15px; background: #e8f5e9; border-left: 4px solid #4caf50;">';
    //         echo '<div style="font-weight: bold; margin-bottom: 5px; color: #2e7d32;">' . $notice['title'] . '</div>';
    //         echo '<div style="font-size: 14px; color: #1b5e20;">' . $notice['message'] . '</div>';
    //         echo '</div>';
    //     }
    // }

    // 顯示「差一點」的活動（每個獨立顯示，次要）
    $almost = array_filter( $activity_status, function( $status ) {
        return $status['status'] === 'almost';
    });

    if ( ! empty( $almost ) ) {
        foreach ( $almost as $key => $data ) {
            $notice = nyb_get_activity_notice( $key, 'almost', $data['missing'] );

            echo '<div class="woocommerce-message" style="margin-bottom: 15px; padding: 12px 15px; background: #fff3e0 !important; border-left: 4px solid #ff9800 !important;">';
            // echo '<div style="font-weight: bold; margin-bottom: 5px; color: #e65100;">' . $notice['title'] . '</div>';
            echo '<div style="color: #e65100;">' . $notice['message'] . '</div>';
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
 * 在購物車優惠券區域顯示已符合的活動
 */
// add_action( 'woocommerce_cart_coupon', 'nyb_display_qualified_activities_as_coupons', 10, 1 );
// function nyb_display_qualified_activities_as_coupons() {
//     $cart = WC()->cart;
//     if ( ! $cart ) {
//         return;
//     }

//     // 檢查是否有優惠券，有優惠券就不顯示活動
//     $applied_coupons = $cart->get_applied_coupons();
//     if ( ! empty( $applied_coupons ) ) {
//         return;
//     }

//     $activity_status = nyb_calculate_activity_status();

//     // 獲取已符合的活動
//     $qualified = array_filter( $activity_status, function( $status ) {
//         return $status['status'] === 'qualified';
//     });

//     if ( empty( $qualified ) ) {
//         return;
//     }

//     if(count($qualified) > 1) {
//         echo '<tr><th><h3 style="color: #1a1a1a;">活動優惠</h3></th></tr>';
//     }

//     // 為每個已符合的活動顯示類似優惠券的樣式
//     foreach ( $qualified as $key => $data ) {
//         $activity_name = nyb_get_activity_name( $key );
//         echo '<tr class="cart-discount nyb-activity-coupon nyb-activity-' . esc_attr( $key ) . '">';
//         echo '<td colspan="2" data-title="活動優惠">';
//         echo '<div class="nyb-coupon-style">';
//         echo '<span class="nyb-activity-badge">🎁</span>';
//         echo '<span class="nyb-activity-name">' . esc_html( $activity_name ) . '</span>';
//         echo '<span class="nyb-activity-tag">已套用</span>';
//         echo '</div>';
//         echo '</td>';
//         echo '</tr>';
//     }
// }

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
        /* .nyb-activity-coupon {
            background: linear-gradient(135deg, #fff9f0 0%, #ffe8cc 100%) !important;
            border-left: 4px solid #df565f !important;
        } */

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
// add_action( 'woocommerce_review_order_after_cart_contents', 'nyb_display_qualified_activities_in_checkout' );
// function nyb_display_qualified_activities_in_checkout() {
//     nyb_display_qualified_activities_as_coupons();
// }

/**
 * 獲取活動名稱
 */
function nyb_get_activity_name( $activity_key ) {
    $names = [
        'activity_1' => '嗜睡床墊+催眠枕，送茸茸被',
        'activity_2' => '賴床墊送抱枕+眼罩',
        'activity_3' => '催眠枕任選2顆特價$8,888',
        'activity_4' => '催眠枕買一送一，送天絲枕套',
        'activity_5' => '嗜睡床墊+催眠枕*2+賴床墊，送天絲四件組床包',
        'activity_6' => '嗜睡床墊+床架，送側睡枕',
        'activity_7' => '嗜睡床墊+床架+催眠枕*2，送天絲四件組床包+茸茸被'
    ];

    return isset( $names[ $activity_key ] ) ? $names[ $activity_key ] : '新年優惠活動';
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

    // --- 步驟 1: 檢查優惠券衝突 ---
    // $applied_coupons = $cart->get_applied_coupons();
    // if ( ! empty( $applied_coupons ) ) {
    //     // 有優惠券時，移除所有自動加入的贈品
    //     foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
    //         if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
    //             $cart->remove_cart_item( $cart_item_key );
    //             nyb_log( sprintf( "[新年活動] 檢測到優惠券衝突，已移除贈品 | 類型: %s", $cart_item['_nyb_auto_gift'] ), $context );
    //         }
    //     }
    //     nyb_log( "========== 新年活動檢測結束（優惠券衝突）==========", $context );
    //     return;
    // }

    // --- 步驟 2: 分析購物車內容 ---
    $stats = nyb_analyze_cart_contents();

    nyb_log( sprintf(
        "[新年活動] 購物車統計 | 床墊:%d, 嗜睡床墊:%d, 賴床墊:%d, 催眠枕:%d, 其他枕:%d, 高枕:%d, 床架:%d",
        $stats['mattress_count'],
        $stats['spring_mattress_count'],
        $stats['lai_mattress_count'],
        $stats['hypnotic_pillow_count'],
        $stats['hypnotic_pillow_count:other'],
        $stats['hypnotic_pillow_count:high'],
        $stats['bed_frame_count']
    ), $context );

    // --- 步驟 3: 按優先級檢查活動並應用 ---
    $applied_activities = [];

    // ⚡ 優化：最不可能滿足的條件放前面（提前結束）
    // 活動7: 終極組合（優先級最高）
    if ( $stats['hypnotic_pillow_count'] >= 2 &&
         $stats['bed_frame_count'] > 0 &&
         $stats['spring_mattress_count'] > 0 ) {
        nyb_apply_activity_7( $cart, $stats, $context );
        $applied_activities[] = 'bundle7';
    }

    // 活動6: 床墊+床架送側睡枕
    if ( $stats['bed_frame_count'] > 0 &&
             $stats['spring_mattress_count'] > 0 ) {
        nyb_apply_activity_6( $cart, $stats, $context );
        $applied_activities[] = 'bundle6';
    }

    // 活動5: 大禮包送天絲四件組（獨立檢查，可與其他活動疊加）
    if ( $stats['hypnotic_pillow_count'] >= 2 &&
         $stats['lai_mattress_count'] > 0 &&
         $stats['spring_mattress_count'] > 0 ) {
        nyb_apply_activity_5( $cart, $stats, $context );
        $applied_activities[] = 'bundle5';
    }

    // 活動4: 枕頭買一送一+天絲枕套（總是檢查）
    if ( $stats['hypnotic_pillow_count'] > 0 ) {
        nyb_apply_activity_4( $cart, $stats, $context );
        $applied_activities[] = 'bundle4';
    }

    // 活動3: 枕頭組合特價$8888（購買任意2個枕頭即可，取最高價的2個）
    if ( $stats['hypnotic_pillow_count'] >= 2 ) {
        nyb_apply_activity_3( $cart, $stats, $context );
        $applied_activities[] = 'bundle3';
    }

    // 活動2: 賴床墊送抱枕+眼罩（總是檢查）
    if ( $stats['lai_mattress_count'] > 0 ) {
        nyb_apply_activity_2( $cart, $stats, $context );
        $applied_activities[] = 'bundle2';
    }

    // 活動1: 床墊+催眠枕送茸茸被（總是檢查）
    if ( $stats['hypnotic_pillow_count'] > 0 && $stats['spring_mattress_count'] > 0 ) {
        nyb_apply_activity_1( $cart, $stats, $context );
        $applied_activities[] = 'bundle1';
    }

    nyb_log( sprintf( "[新年活動] 已應用活動: %s", implode( ', ', $applied_activities ) ), $context );

    // --- 步驟 4: 移除不再符合條件的贈品 ---
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
 * 模組 4：活動1 - 床墊+催眠枕送茸茸被
 * =======================================================
 */
function nyb_apply_activity_1( $cart, $stats, $context ) {
    // 檢查是否已有此贈品
    $gift_exists = false;

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle1' ) {
            $gift_exists = true;
            break;
        }
    }

    if ( ! $gift_exists ) {
        $cart->add_to_cart( NYB_GIFT_FLEECE_BLANKET, 1, 0, array(), array( '_nyb_auto_gift' => 'bundle1' ) );
        nyb_log( sprintf( "[活動1] 自動加入茸茸被 | ID: %s", NYB_GIFT_FLEECE_BLANKET ), $context );
    }

    // 將贈品價格設為 0
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle1' ) {
            $original_price = $cart_item['data']->get_regular_price();
            $cart_item['data']->set_price( 0 );
            $cart_item['data']->add_meta_data( '_is_free_gift', 'yes', true );
            $cart_item['data']->add_meta_data( '_original_price', $original_price, true );
            nyb_log( sprintf( "[活動1] 將贈品價格設為 0 | 原價: %s", $original_price ), $context );
        }
    }
}

/**
 * =======================================================
 * 模組 5：活動2 - 賴床墊送抱枕+眼罩
 * =======================================================
 */
function nyb_apply_activity_2( $cart, $stats, $context ) {
    $gifts_needed = [
        NYB_GIFT_HUG_PILLOW => false,
        NYB_GIFT_EYE_MASK => false
    ];

    // 檢查已有的贈品
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle2' ) {
            $product_id = $cart_item['product_id'];
            if ( isset( $gifts_needed[ $product_id ] ) ) {
                $gifts_needed[ $product_id ] = true;
            }
        }
    }

    // 加入缺少的贈品
    foreach ( $gifts_needed as $gift_id => $exists ) {
        if ( ! $exists ) {
            $cart->add_to_cart( $gift_id, 1, 0, array(), array( '_nyb_auto_gift' => 'bundle2' ) );
            nyb_log( sprintf( "[活動2] 自動加入贈品 | ID: %s", $gift_id ), $context );
        }
    }

    // 將贈品價格設為 0
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle2' ) {
            $original_price = $cart_item['data']->get_regular_price();
            $cart_item['data']->set_price( 0 );
            $cart_item['data']->add_meta_data( '_is_free_gift', 'yes', true );
            $cart_item['data']->add_meta_data( '_original_price', $original_price, true );
            nyb_log( sprintf( "[活動2] 將贈品價格設為 0 | ID: %s, 原價: %s", $cart_item['product_id'], $original_price ), $context );
        }
    }
}

/**
 * =======================================================
 * 模組 6：活動3 - 枕頭組合特價$8888
 * ⚡ 新邏輯：取價格最高的兩個枕頭組成特價組合
 * =======================================================
 */
function nyb_apply_activity_3( $cart, $stats, $context ) {
    // 收集所有購買的枕頭（排除贈品）
    $purchased_pillows = [];

    foreach ( $cart->get_cart() as $cart_item ) {
        $variation_id = $cart_item['variation_id'];

        // 排除贈品
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            continue;
        }

        // 排除活動4的免費贈品
        if ( $cart_item['data']->get_meta( '_is_free_gift' ) === 'yes' ) {
            continue;
        }

        // 只處理催眠枕
        if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ] ) ) {
            $price = $cart_item['data']->get_price();
            $quantity = $cart_item['quantity'];

            // 將每個枕頭單獨加入陣列（考慮數量）
            for ( $i = 0; $i < $quantity; $i++ ) {
                $purchased_pillows[] = [
                    'variation_id' => $variation_id,
                    'price' => $price,
                    'name' => $cart_item['data']->get_name()
                ];
            }
        }
    }

    // 如果少於2個枕頭，不套用活動
    if ( count( $purchased_pillows ) < 2 ) {
        // nyb_log( "[活動3] 枕頭數量不足2個，不套用活動", $context );
        return;
    }

    // 按價格降序排序
    usort( $purchased_pillows, function( $a, $b ) {
        return $b['price'] - $a['price'];
    });

    // 取最高價的兩個枕頭
    $top_two = array_slice( $purchased_pillows, 0, 2 );
    $top_two_total = $top_two[0]['price'] + $top_two[1]['price'];

    // 計算需要的折扣金額
    $discount_needed = $top_two_total - NYB_COMBO_SPECIAL_PRICE;

    if ( $discount_needed > 0 ) {
        // 移除之前的折扣（如果有）
        foreach ( $cart->get_fees() as $fee_key => $fee ) {
            if ( $fee->name === '枕頭組合特價優惠' ) {
                $cart->remove_fee( $fee->name );
            }
        }

        // 套用新折扣
        $cart->add_fee( '枕頭組合特價優惠', -$discount_needed );
        // nyb_log( sprintf( "[活動3] 套用枕頭組合特價 | 原價: %s, 特價: %s, 折扣: %s", $top_two_total, NYB_COMBO_SPECIAL_PRICE, $discount_needed ), $context );
    } else {
        // nyb_log( sprintf( "[活動3] 最高價兩個枕頭總價 ($%s) 已低於特價 ($%s)，不套用折扣", $top_two_total, NYB_COMBO_SPECIAL_PRICE ), $context );
    }
}

/**
 * =======================================================
 * 模組 7：活動4 - 枕頭買一送一+天絲枕套
 * ⚡ 性能優化：使用 Hash Map
 * =======================================================
 */
function nyb_apply_activity_4( $cart, $stats, $context ) {
    // 活動4：買一送一（只應用一次）
    // 收集購物車中所有購買的催眠枕
    $purchased_pillows = [];

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        $variation_id = $cart_item['variation_id'];

        // 排除贈品
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            continue;
        }

        // ⚡ 使用 Hash Map
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

    // 如果沒有購買任何催眠枕，清空選擇並返回
    if ( empty( $purchased_pillows ) ) {
        WC()->session->__unset( 'nyb_bundle4_selected_pillow' );
        // WC()->session->__unset( 'nyb_bundle4_selected_pillowcase' );
        return;
    }

    // 獲取用戶選擇
    $selected_pillow = WC()->session->get( 'nyb_bundle4_selected_pillow' );

    // 如果沒有選擇，或選擇的枕頭不在購物車中，使用購物車中的那個
		$selected_pillow_in_cart = nyb_find_gift_product_in_cart( $selected_pillow, '_nyb_auto_gift' );

		if($selected_pillow_in_cart === null) {
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( isset( $cart_item['_nyb_auto_gift'] ) && $cart_item['_nyb_auto_gift'] === 'bundle4' ) {
					$cart->remove_cart_item( $cart_item_key );
				}
			}
		}

    if ( ! $selected_pillow || ! isset( $purchased_pillows[ $selected_pillow ] ) ) {
			// 重新選擇購物車中第一個有效枕頭
			$selected_pillow = array_key_first( $purchased_pillows );
			WC()->session->set( 'nyb_bundle4_selected_pillow', $selected_pillow );
    }

    // 如果沒有選擇枕套，使用對應的枕套
    // if ( ! $selected_pillowcase && isset( NYB_PILLOWCASE_MAP[ $selected_pillow ] ) ) {
    //     $selected_pillowcase = NYB_PILLOWCASE_MAP[ $selected_pillow ];
    //     WC()->session->set( 'nyb_bundle4_selected_pillowcase', $selected_pillowcase );
    // }

    // 儲存可選的枕頭列表到 session（供前端使用）
    WC()->session->set( 'nyb_bundle4_available_pillows', $purchased_pillows );

    // 檢查是否已有贈品
    $gift_pillow_exists = false;
    $gift_pillowcase_exists = false;

		$selected_pillowcase = isset( NYB_PILLOWCASE_MAP[ $selected_pillow ] ) ? NYB_PILLOWCASE_MAP[ $selected_pillow ] : 0;

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) && $cart_item['_nyb_auto_gift'] === 'bundle4' ) {
            $variation_id = $cart_item['variation_id'];

						if(isset(NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ])) {
							$gift_pillow_exists = true;
						}
						if(isset(NYB_PILLOWCASE_MAP[ $variation_id ])) {
							$gift_pillowcase_exists = true;
						}
        }
    }

    // 添加選中的枕頭贈品（只送1個）
    if ( ! $gift_pillow_exists && isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $selected_pillow ] ) ) {
        $cart->add_to_cart(
            NYB_HYPNOTIC_PILLOW_PARENT,
            1,
            $selected_pillow,
            array(),
            array( '_nyb_auto_gift' => 'bundle4', '_nyb_gift_type' => 'pillow' )
        );
        nyb_log( sprintf( "[活動4] 自動加入贈品枕頭 | Variation ID: %s", $selected_pillow ), $context );
    }

    // 添加選中的枕套贈品（只送1個）
    if ( ! $gift_pillowcase_exists && $selected_pillowcase ) {
        $cart->add_to_cart(
            NYB_HYPNOTIC_PILLOW_PARENT,
            1,
            $selected_pillowcase,
            array(),
            array( '_nyb_auto_gift' => 'bundle4', '_nyb_gift_type' => 'pillowcase' )
        );
        nyb_log( sprintf( "[活動4] 自動加入贈品枕套 | Variation ID: %s", $selected_pillowcase ), $context );
    }

    // 將贈品價格設為 0
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) && $cart_item['_nyb_auto_gift'] === 'bundle4' ) {
            $original_price = $cart_item['data']->get_regular_price();
            $cart_item['data']->set_price( 0 );
            $cart_item['data']->add_meta_data( '_is_free_gift', 'yes', true );
            $cart_item['data']->add_meta_data( '_original_price', $original_price, true );
        }
    }
}

/**
 * =======================================================
 * 模組 7A：活動4 選擇介面
 * 在購物車頁面顯示枕頭和枕套的選擇介面
 * =======================================================
 */

/**
 * 在購物車頁面顯示活動4的選擇介面
 */
add_action( 'woocommerce_after_cart_table', 'nyb_display_activity4_selector', 5 );
function nyb_display_activity4_selector() {
    // 檢查是否符合活動4
    $activity_status = nyb_calculate_activity_status();

    if ( ! isset( $activity_status['activity_4'] ) || $activity_status['activity_4']['status'] !== 'qualified' ) {
        return;
    }

    // 獲取可選的枕頭列表
    $available_pillows = WC()->session->get( 'nyb_bundle4_available_pillows' );
    $selected_pillow = WC()->session->get( 'nyb_bundle4_selected_pillow' );

    if ( empty( $available_pillows ) ) {
        return;
    }

    // 如果只有一種枕頭，不需要顯示選擇介面
    if ( count( $available_pillows ) <= 1 && isset( NYB_PILLOWCASE_MAP[ $selected_pillow ] ) ) {
        return;
    }

    ?>
    <div class="nyb-activity4-selector">
        <div class="nyb-selector-header">
            <h3>🎁 買一送一活動 - 請選擇贈品</h3>
            <p>您購買了多種催眠枕，本活動只贈送一組（1個枕頭 + 1個配對天絲枕套），請選擇您要的贈品組合：</p>
        </div>

        <div class="nyb-selector-form">
            <div class="nyb-pillow-grid">
                <?php foreach ( $available_pillows as $var_id => $pillow_data ) :
                    // 獲取對應的枕套名稱
                    $pillowcase_name = '';
                    if ( isset( NYB_PILLOWCASE_MAP[ $var_id ] ) ) {
                        $pillowcase_product = wc_get_product( NYB_PILLOWCASE_MAP[ $var_id ] );
                        if ( $pillowcase_product ) {
                            $pillowcase_name = $pillowcase_product->get_name();
                        }
                    }
                    $is_selected = ($selected_pillow == $var_id);

										$pillow_name = preg_replace('/,.*$/', '', $pillow_data['name']);
                ?>
                    <label class="nyb-pillow-card <?php echo $is_selected ? 'selected' : ''; ?>">
                        <input type="radio" name="nyb_pillow_selection" value="<?php echo esc_attr( $var_id ); ?>" <?php checked( $selected_pillow, $var_id ); ?>>
                        <div class="nyb-card-content">
                            <div class="nyb-check-icon">✓</div>
                            <div class="nyb-item-group">
                                <span class="nyb-item-name pillow"><?php echo esc_html( $pillow_name ); ?> + 枕套</span>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="nyb-action-row">
                <button type="button" id="nyb-update-selection" class="button">
                    確認選擇
                </button>
                <span id="nyb-selection-message">
                    ✓ 已更新
                </span>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // 枕頭與枕套的映射關係
        var pillowcaseMap = <?php echo json_encode( NYB_PILLOWCASE_MAP ); ?>;

        // 初始化函數
        function initNybSelector() {
            // 使用事件委派：綁定到 document.body，避免 DOM 重新渲染後失效
            $(document.body).off('change', '.nyb-pillow-card input[type="radio"]').on('change', '.nyb-pillow-card input[type="radio"]', function() {
                $('.nyb-pillow-card').removeClass('selected');
                if ($(this).is(':checked')) {
                    $(this).closest('.nyb-pillow-card').addClass('selected');
                }
            });

            $(document.body).off('click', '#nyb-update-selection').on('click', '#nyb-update-selection', function() {
                var button = $(this);
                var message = $('#nyb-selection-message');

                // 獲取選中的 radio 值
                var selectedPillow = $('input[name="nyb_pillow_selection"]:checked').val();

                if (!selectedPillow) {
                    alert('請先選擇一個組合');
                    return;
                }

                var selectedPillowcase = pillowcaseMap[selectedPillow] || '';

                button.prop('disabled', true).text('更新中...');

                $.ajax({
                    url: wc_cart_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'nyb_update_activity4_selection',
                        nonce: '<?php echo wp_create_nonce( 'nyb_activity4_selection' ); ?>',
                        pillow: selectedPillow,
                        pillowcase: selectedPillowcase
                    },
                    success: function(response) {
                        if (response.success) {
                            message.fadeIn().delay(2000).fadeOut();
                            button.prop('disabled', false).text('確認選擇');

                            // 重新載入購物車
                            $(document.body).trigger('wc_update_cart');
                        } else {
                            alert('更新失敗，請重試');
                            button.prop('disabled', false).text('確認選擇');
                        }
                    },
                    error: function() {
                        alert('發生錯誤，請重試');
                        button.prop('disabled', false).text('確認選擇');
                    }
                });
            });
        }

        // 初始執行
        initNybSelector();

        // 監聽購物車更新事件，重新初始化
        $(document.body).on('updated_cart_totals', function() {
            initNybSelector();
        });
    });
    </script>

    <style>
        .nyb-activity4-selector {
            margin: 20px 0;
            padding: 25px;
            background: #fff;
            border: 2px solid #df565f;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(223, 86, 95, 0.08);
        }

        .nyb-selector-header h3 {
            margin: 0 0 10px 0;
            color: #df565f;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nyb-selector-header p {
            margin: 0 0 20px 0;
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .nyb-pillow-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .nyb-pillow-card {
            position: relative;
            display: block;
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fff;
        }

        .nyb-pillow-card:hover {
            border-color: #df565f;
            background: #fff9f0;
        }

        .nyb-pillow-card.selected {
            border-color: #df565f;
            background: #fff9f0;
            box-shadow: 0 0 0 1px #df565f;
        }

        .nyb-pillow-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .nyb-card-content {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .nyb-check-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: transparent;
            font-weight: bold;
            flex-shrink: 0;
            transition: all 0.2s;
            background: #fff;
        }

        .nyb-pillow-card.selected .nyb-check-icon {
            background: #df565f;
            border-color: #df565f;
            color: white;
        }

        .nyb-item-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nyb-item-name {
            font-size: 15px;
            color: #333;
            font-weight: 500;
            line-height: 1.4;
        }

        .nyb-item-name.pillow {
            color: #df565f;
            font-weight: bold;
        }

        .nyb-item-plus {
            color: #999;
            font-size: 12px;
            margin: 2px 0;
        }

        .nyb-action-row {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        #nyb-update-selection {
            background: #df565f;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }

        #nyb-update-selection:hover {
            background: #c94a53;
        }

        #nyb-selection-message {
            display: none;
            color: #4caf50;
            font-weight: bold;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .nyb-activity4-selector {
                padding: 15px;
            }

            .nyb-pillow-grid {
                grid-template-columns: 1fr;
            }

            .nyb-pillow-card {
                padding: 12px;
            }

            #nyb-update-selection {
                width: 100%;
            }

            .nyb-action-row {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
    <?php
}

/**
 * AJAX 處理函數：更新活動4的選擇
 */
add_action( 'wp_ajax_nyb_update_activity4_selection', 'nyb_handle_activity4_selection_update' );
add_action( 'wp_ajax_nopriv_nyb_update_activity4_selection', 'nyb_handle_activity4_selection_update' );
function nyb_handle_activity4_selection_update() {
    // 驗證 nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nyb_activity4_selection' ) ) {
        wp_send_json_error( ['message' => '安全驗證失敗'] );
    }

    $selected_pillow = isset( $_POST['pillow'] ) ? intval( $_POST['pillow'] ) : 0;

    if ( ! $selected_pillow ) {
        wp_send_json_error( ['message' => '請選擇枕頭'] );
    }

    // 驗證選擇是否有效
    $available_pillows = WC()->session->get( 'nyb_bundle4_available_pillows' );

    if ( ! isset( $available_pillows[ $selected_pillow ] ) ) {
        wp_send_json_error( ['message' => '選擇的枕頭無效'] );
    }

    // 更新 session
    WC()->session->set( 'nyb_bundle4_selected_pillow', $selected_pillow );
    // WC()->session->set( 'nyb_bundle4_selected_pillowcase', $selected_pillowcase );

    // 移除購物車中舊的活動4贈品
    $cart = WC()->cart;
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) && $cart_item['_nyb_auto_gift'] === 'bundle4' ) {
            $cart->remove_cart_item( $cart_item_key );
        }
    }

    // 觸發購物車重新計算（會自動添加新選擇的贈品）
    $cart->calculate_totals();

    wp_send_json_success( [
        'message' => '選擇已更新',
        'pillow' => $selected_pillow,
        // 'pillowcase' => $selected_pillowcase
    ] );
}

/**
 * =======================================================
 * 模組 8：活動5 - 大禮包送天絲四件組
 * ⚡ 性能優化：使用 Hash Map + 虛擬商品
 * =======================================================
 */
function nyb_apply_activity_5( $cart, $stats, $context ) {
    // 找出嗜睡床墊的尺寸（用於確定床包價值）
    $mattress_var_id = null;
    foreach ( $cart->get_cart() as $cart_item ) {
        $variation_id = $cart_item['variation_id'];

        // 排除贈品
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            continue;
        }

        // ⚡ 使用 Hash Map
        if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
            $mattress_var_id = $variation_id;
            break;
        }
    }

    if ( $mattress_var_id && isset( NYB_BEDDING_VALUE_MAP[ $mattress_var_id ] ) ) {
        // 添加虛擬床包商品到購物車
        $result = NYB_Virtual_Bedding_Product::add_to_cart( $cart, $mattress_var_id, 'bundle5' );

        if ( $result ) {
            nyb_log( sprintf( "[活動5] 已添加天絲四件組床包到購物車 | 床墊 Variation ID: %s, 床包價值: %s", $mattress_var_id, NYB_BEDDING_VALUE_MAP[ $mattress_var_id ] ), $context );
        }
    }
}

/**
 * =======================================================
 * 模組 9：活動6 - 床墊+床架送側睡枕
 * =======================================================
 */
function nyb_apply_activity_6( $cart, $stats, $context ) {
    // 檢查是否已有此贈品
    $gift_exists = false;

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle6' &&
             $cart_item['variation_id'] == NYB_GIFT_SIDE_PILLOW_VAR ) {
            $gift_exists = true;
            break;
        }
    }

    if ( ! $gift_exists ) {
        $cart->add_to_cart( NYB_HYPNOTIC_PILLOW_PARENT, 1, NYB_GIFT_SIDE_PILLOW_VAR, array(), array( '_nyb_auto_gift' => 'bundle6' ) );
        nyb_log( sprintf( "[活動6] 自動加入側睡枕 | Variation ID: %s", NYB_GIFT_SIDE_PILLOW_VAR ), $context );
    }

    // 將贈品價格設為 0
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle6' ) {
            $original_price = $cart_item['data']->get_regular_price();
            $cart_item['data']->set_price( 0 );
            $cart_item['data']->add_meta_data( '_is_free_gift', 'yes', true );
            $cart_item['data']->add_meta_data( '_original_price', $original_price, true );
            nyb_log( sprintf( "[活動6] 將贈品價格設為 0 | 原價: %s", $original_price ), $context );
        }
    }
}

/**
 * =======================================================
 * 模組 10：活動7 - 終極組合
 * ⚡ 性能優化：使用 Hash Map + 虛擬商品
 * =======================================================
 */
function nyb_apply_activity_7( $cart, $stats, $context ) {
    // 贈品1: 茸茸被
    $fleece_blanket_exists = false;

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
             $cart_item['_nyb_auto_gift'] === 'bundle7' &&
             $cart_item['product_id'] == NYB_GIFT_FLEECE_BLANKET ) {
            $fleece_blanket_exists = true;
            break;
        }
    }

    if ( ! $fleece_blanket_exists ) {
				$cart->add_to_cart( NYB_GIFT_FLEECE_BLANKET, 1, 0, array(), array( '_nyb_auto_gift' => 'bundle7' ) );
				nyb_log( sprintf( "[活動7] 自動加入茸茸被 | ID: %s", NYB_GIFT_FLEECE_BLANKET ), $context );
		}

    // 贈品2: 天絲四件組床包（使用虛擬商品）
    $mattress_var_id = null;
    foreach ( $cart->get_cart() as $cart_item ) {
        $variation_id = $cart_item['variation_id'];

        // 排除贈品
        if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
            continue;
        }

        // ⚡ 使用 Hash Map
        if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
            $mattress_var_id = $variation_id;
            break;
        }
    }

    if ( $mattress_var_id && isset( NYB_BEDDING_VALUE_MAP[ $mattress_var_id ] ) ) {
				nyb_log( sprintf( "[活動7] nyb_apply_activity_7 mattress_var_id: %s", $mattress_var_id ), $context );
        // 添加虛擬床包商品到購物車
        $result = NYB_Virtual_Bedding_Product::add_to_cart( $cart, $mattress_var_id, 'bundle7' );

        if ( $result ) {
            nyb_log( sprintf( "[活動7] 已添加天絲四件組床包到購物車 | 床墊 Variation ID: %s, 床包價值: %s", $mattress_var_id, NYB_BEDDING_VALUE_MAP[ $mattress_var_id ] ), $context );
        }
    }

    // 將贈品價格設為 0
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['_nyb_auto_gift'] ) &&
							$cart_item['_nyb_auto_gift'] === 'bundle7' &&
							$cart_item['product_id'] == NYB_GIFT_FLEECE_BLANKET ) {
							$original_price = $cart_item['data']->get_regular_price();
							$cart_item['data']->set_price( 0 );
							$cart_item['data']->add_meta_data( '_is_free_gift', 'yes', true );
							$cart_item['data']->add_meta_data( '_original_price', $original_price, true );
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