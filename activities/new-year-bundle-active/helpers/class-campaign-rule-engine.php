<?php
/**
 * 活動規則引擎
 * 職責：定義規則邏輯、優先級、互斥關係
 */
class NYB_Campaign_Rule_Engine {

    /**
     * 規則優先級（數字越大優先級越高）
     */
    const RULE_PRIORITY = [
        'rule_7' => 70,  // 嗜睡+枕*2+賴 → 床包+茸茸被
        'rule_6' => 60,  // 嗜睡+床架+枕*2 → 床包+茸茸被
        'rule_5' => 50,  // 嗜睡+床架 → 側睡枕
        'rule_2' => 40,  // 枕*2 → $8888+枕套*2
        'rule_1' => 30,  // 嗜睡+枕 → 茸茸被
        'rule_3' => 20,  // 催眠枕 → 枕套
        'rule_4' => 10,  // 賴床墊 → 抱枕+眼罩
    ];

    /**
     * 規則互斥組（同組規則只能觸發一個）
     */
    const MUTEX_GROUPS = [
        'combo_major' => ['rule_7', 'rule_6', 'rule_5', 'rule_1'],  // 主要組合活動
        'pillow_gift' => ['rule_2', 'rule_3'],  // 枕頭相關贈品
    ];

    /**
     * 驗證購物車並返回符合的規則
     *
     * @param array $cart_items WooCommerce 購物車項目
     * @return array 符合的規則及贈品資訊
     */
    public static function validate_cart( $cart_items ) {
        $cart_analysis = self::analyze_cart( $cart_items );
        $matched_rules = [];

        // 按優先級檢查規則
        $rules = self::RULE_PRIORITY;
        arsort( $rules );  // 降序排列

        foreach ( $rules as $rule_name => $priority ) {
            $method = 'check_' . $rule_name;
            if ( method_exists( __CLASS__, $method ) ) {
                $result = self::$method( $cart_analysis );
                if ( $result ) {
                    $matched_rules[] = array_merge( $result, [
                        'rule_name' => $rule_name,
                        'priority'  => $priority,
                    ] );
                }
            }
        }

        // 處理互斥規則
        return self::resolve_mutex_rules( $matched_rules );
    }

    /**
     * 分析購物車商品組成
     */
    private static function analyze_cart( $cart_items ) {
        $analysis = [
            'spring_mattress_count' => 0,
            'spring_mattress_ids'   => [],
            'lai_mattress_count'    => 0,
            'lai_mattress_ids'      => [],
            'hypnotic_pillow_count' => 0,
            'hypnotic_pillow_ids'   => [],
            'bed_frame_count'       => 0,
            'bed_frame_ids'         => [],
        ];

        foreach ( $cart_items as $cart_item ) {
            $product_id   = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? 0;
            $quantity     = $cart_item['quantity'];

            // 嗜睡床墊
            if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
                $analysis['spring_mattress_count'] += $quantity;
                $analysis['spring_mattress_ids'][] = [
                    'variation_id' => $variation_id,
                    'quantity'     => $quantity,
                ];
            }

            // 賴床墊
            if ( isset( NYB_LAI_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
                $analysis['lai_mattress_count'] += $quantity;
                $analysis['lai_mattress_ids'][] = [
                    'variation_id' => $variation_id,
                    'quantity'     => $quantity,
                ];
            }

            // 催眠枕
            if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ] ) ) {
                $analysis['hypnotic_pillow_count'] += $quantity;
                $analysis['hypnotic_pillow_ids'][] = [
                    'variation_id' => $variation_id,
                    'quantity'     => $quantity,
                ];
            }

            // 床架
            if ( isset( NYB_BED_FRAME_IDS_MAP[ $variation_id ] ) ) {
                $analysis['bed_frame_count'] += $quantity;
                $analysis['bed_frame_ids'][] = [
                    'variation_id' => $variation_id,
                    'quantity'   => $quantity,
                ];
            }
        }

        return $analysis;
    }

    /**
     * 規則1：嗜睡床墊 + 催眠枕 → 茸茸被
     */
    private static function check_rule_1( $analysis ) {
        if ( $analysis['spring_mattress_count'] >= 1 && $analysis['hypnotic_pillow_count'] >= 1 ) {
            return [
                'gifts' => [
                    [ 'product_id' => NYB_GIFT_FLEECE_BLANKET, 'quantity' => 1 ]
                ],
                'description' => '嗜睡床墊+催眠枕，贈兩用茸茸被',
            ];
        }
        return false;
    }

    /**
     * 規則2：枕頭任選2顆 → $8888 + 枕套*2
     */
    private static function check_rule_2( $analysis ) {
        if ( $analysis['hypnotic_pillow_count'] >= 2 ) {
            $pillowcases = [];
            $pillow_count = 0;

            foreach ( $analysis['hypnotic_pillow_ids'] as $pillow ) {
                $variation_id = $pillow['variation_id'];
                $qty = min( $pillow['quantity'], 2 - $pillow_count );

                if ( isset( NYB_PILLOWCASE_MAP[ $variation_id ] ) ) {
                    $pillowcases[] = [
                        'product_id' => NYB_PILLOWCASE_MAP[ $variation_id ],
                        'quantity'   => $qty,
                    ];
                }

                $pillow_count += $qty;
                if ( $pillow_count >= 2 ) break;
            }

            return [
                'gifts'        => $pillowcases,
                'price_override' => [
                    'target'   => 'hypnotic_pillow',
                    'quantity' => 2,
                    'price'    => NYB_COMBO_SPECIAL_PRICE,
                ],
                'description' => '枕頭任選2顆 $8888，贈天絲枕套2個',
            ];
        }
        return false;
    }

    /**
     * 規則3：買催眠枕 → 送枕套
     */
    private static function check_rule_3( $analysis ) {
        if ( $analysis['hypnotic_pillow_count'] >= 1 ) {
            $pillowcases = [];
            foreach ( $analysis['hypnotic_pillow_ids'] as $pillow ) {
                $variation_id = $pillow['variation_id'];
                if ( isset( NYB_PILLOWCASE_MAP[ $variation_id ] ) ) {
                    $pillowcases[] = [
                        'product_id' => NYB_PILLOWCASE_MAP[ $variation_id ],
                        'quantity'   => $pillow['quantity'],
                    ];
                }
            }

            return [
                'gifts'       => $pillowcases,
                'description' => '買催眠枕送天絲枕套',
            ];
        }
        return false;
    }

    /**
     * 規則4：買賴床墊 → 抱枕+眼罩
     */
    private static function check_rule_4( $analysis ) {
        if ( $analysis['lai_mattress_count'] >= 1 ) {
            return [
                'gifts' => [
                    [ 'product_id' => NYB_GIFT_HUG_PILLOW, 'quantity' => 1 ],
                    [ 'product_id' => NYB_GIFT_EYE_MASK, 'quantity' => 1 ],
                ],
                'description' => '買賴床墊送抱枕+眼罩',
            ];
        }
        return false;
    }

    /**
     * 規則5：嗜睡床墊+床架 → 側睡枕
     */
    private static function check_rule_5( $analysis ) {
        if ( $analysis['spring_mattress_count'] >= 1 && $analysis['bed_frame_count'] >= 1 ) {
            return [
                'gifts' => [
                    [ 'variation_id' => NYB_GIFT_SIDE_PILLOW_VAR, 'quantity' => 1 ]
                ],
                'description' => '嗜睡床墊+床架，贈側睡枕',
            ];
        }
        return false;
    }

    /**
     * 規則6：嗜睡床墊+床架+枕*2 → 床包+茸茸被
     */
    private static function check_rule_6( $analysis ) {
        if ( $analysis['spring_mattress_count'] >= 1
             && $analysis['bed_frame_count'] >= 1
             && $analysis['hypnotic_pillow_count'] >= 2 ) {

            // 取得床墊尺寸對應的床包
            $bedding_product = self::get_bedding_for_mattress( $analysis['spring_mattress_ids'][0]['variation_id'] );

            return [
                'gifts' => [
                    [ 'virtual_product' => $bedding_product, 'quantity' => 1, 'rule_name' => 'rule_6' ],
                    [ 'product_id' => NYB_GIFT_FLEECE_BLANKET, 'quantity' => 1 ],
                ],
                'description' => '嗜睡床墊+床架+枕*2，贈天絲床包+茸茸被',
            ];
        }
        return false;
    }

    /**
     * 規則7：嗜睡床墊+枕*2+賴床墊 → 床包+茸茸被
     */
    private static function check_rule_7( $analysis ) {
        if ( $analysis['spring_mattress_count'] >= 1
             && $analysis['hypnotic_pillow_count'] >= 2
             && $analysis['lai_mattress_count'] >= 1 ) {

            $bedding_product = self::get_bedding_for_mattress( $analysis['spring_mattress_ids'][0]['variation_id'] );

            return [
                'gifts' => [
                    [ 'virtual_product' => $bedding_product, 'quantity' => 1, 'rule_name' => 'rule_7' ],
                    [ 'product_id' => NYB_GIFT_FLEECE_BLANKET, 'quantity' => 1 ],
                ],
                'description' => '嗜睡床墊+枕*2+賴床墊，贈天絲床包+茸茸被',
            ];
        }
        return false;
    }

    /**
     * 根據床墊尺寸取得對應床包虛擬商品
     */
    private static function get_bedding_for_mattress( $variation_id ) {
        $value = NYB_BEDDING_VALUE_MAP[ $variation_id ] ?? 0;
        return [
            'name'  => '天絲四件組床包',
            'value' => $value,
            'sku'   => 'GIFT-BEDDING-' . $variation_id,
        ];
    }

    /**
     * 處理互斥規則（同組只保留優先級最高的）
     */
    private static function resolve_mutex_rules( $matched_rules ) {
        $mutex_applied = [];

        foreach ( self::MUTEX_GROUPS as $group_name => $group_rules ) {
            $group_matched = array_filter( $matched_rules, function( $rule ) use ( $group_rules ) {
                return in_array( $rule['rule_name'], $group_rules );
            } );

            if ( ! empty( $group_matched ) ) {
                // 只保留優先級最高的
                usort( $group_matched, function( $a, $b ) {
                    return $b['priority'] - $a['priority'];
                } );
                $mutex_applied[] = $group_matched[0];
            }
        }

        // 加入不在互斥組的規則
        foreach ( $matched_rules as $rule ) {
            $in_mutex = false;
            foreach ( self::MUTEX_GROUPS as $group_rules ) {
                if ( in_array( $rule['rule_name'], $group_rules ) ) {
                    $in_mutex = true;
                    break;
                }
            }
            if ( ! $in_mutex ) {
                $mutex_applied[] = $rule;
            }
        }

        return $mutex_applied;
    }

    /**
     * 記錄除錯日誌
     */
    private static function log( $message, $data = [] ) {
        if ( NYB_DEBUG_MODE ) {
            error_log( '[NYB Campaign] ' . $message . ' ' . print_r( $data, true ) );
        }
    }
}

