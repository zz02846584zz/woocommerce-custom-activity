<?php
/**
 * 新年活動主檔案（模組化版本）
 *
 * 架構說明：
 * - 遵循 SOLID 原則：單一職責、開放封閉、依賴倒置
 * - 遵循 YAGNI 原則：不過度設計，保持簡潔
 *
 * 目錄結構：
 * ├── config/          - 常數定義
 * ├── engine/          - 核心引擎（購物車分析、活動檢測）
 * ├── activities/      - 各個活動的實作
 * ├── gift/            - 贈品管理
 * ├── discount/        - 折扣管理
 * └── bootstrap.php    - 自動載入器
 */

// 引入必要的輔助類別（虛擬床包商品）
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'helpers/class-virtual-bedding-product.php';

// 初始化虛擬床包商品
NYB_Virtual_Bedding_Product::init();

// 載入模組化架構
require_once __DIR__ . '/new-year-bundle/bootstrap.php';

/**
 * ========================================
 * 以下是未模組化的遺留代碼
 * 這些功能較為獨立或與前端顯示相關
 * 未來可以進一步重構
 * ========================================
 */

/**
 * 商品頁智慧提示系統
 */
add_action( 'woocommerce_before_single_product', 'nyb_smart_product_page_notice', 15 );
function nyb_smart_product_page_notice() {
    if ( is_admin() ) {
        return;
    }

    global $product;

    $product_id = $product->get_id();
    $parent_id = $product->get_parent_id();

    $related_activities = nyb_get_related_activities( $parent_id != 0 ? $parent_id : $product_id, 0 );

    if ( empty( $related_activities ) ) {
        return;
    }

    nyb_display_conditional_notice( $related_activities );
}

/**
 * 顯示條件式提示
 */
function nyb_display_conditional_notice( $activities ) {
    $qualified = [];
    $almost = [];
    $not_qualified = [];

    foreach ( $activities as $activity ) {
        if ( $activity['data']['status'] === 'qualified' ) {
            $qualified[] = $activity;
        } elseif ( $activity['data']['status'] === 'almost' ) {
            $almost[] = $activity;
        } elseif ( $activity['data']['status'] === 'not_qualified' ) {
            $not_qualified[] = $activity;
        }
    }

    // 顯示「已符合」的活動
    if ( ! empty( $qualified ) && is_product() ) {
        foreach ( $qualified as $act ) {
            $notice = nyb_get_activity_notice( $act['key'], 'qualified', [] );

            echo '<div class="woocommerce-info" style="margin-bottom: 15px; padding: 12px 15px; background: #e8f5e9; border-left: 4px solid #4caf50;">';
            echo '<div data-missing="' . json_encode( $notice['missing'] ) . '" style="font-size: 14px; color: #1b5e20;">' . $notice['message'] . '</div>';
            echo '</div>';
        }
    }

    // 顯示「差一點」的活動
    if ( ! empty( $almost ) ) {
        foreach ( $almost as $act ) {
            $notice = nyb_get_activity_notice( $act['key'], 'almost', $act['data']['missing'] );

            echo '<div class="woocommerce-message" style="margin-bottom: 15px; padding: 12px 15px; background: #fff3e0 !important; border-left: 4px solid #ff9800 !important;">';
            echo '<div data-missing="' . json_encode( $notice['missing'] ) . '" style="font-size: 14px; color: #e65100;">' . $notice['message'] . '</div>';
            echo '</div>';
        }
    }

    // 顯示「不符合」的活動
    if ( ! empty( $not_qualified ) && is_product() ) {
        foreach ( $not_qualified as $act ) {
            $notice = nyb_get_activity_notice( $act['key'], 'not_qualified', $act['data']['missing'] );

            echo '<div class="woocommerce-message" style="margin-bottom: 15px; padding: 12px 15px; background: #fff3e0 !important; border-left: 4px solid #ff9800 !important;">';
            echo '<div data-missing="' . json_encode( $notice['missing'] ) . '" style="font-size: 14px; color: #e65100;">' . $notice['message'] . '</div>';
            echo '</div>';
        }
    }
}

/**
 * 購物車頁提示系統
 */
add_action( 'woocommerce_before_cart', 'nyb_cart_page_notice', 10 );
function nyb_cart_page_notice() {
    $cart = WC()->cart;
    if ( ! $cart ) {
        return;
    }

    $activity_status = nyb_calculate_activity_status();

    // 顯示「差一點」的活動
    $almost = array_filter( $activity_status, function( $status ) {
        return $status['status'] === 'almost';
    });

    if ( ! empty( $almost ) ) {
        foreach ( $almost as $key => $data ) {
            $notice = nyb_get_activity_notice( $key, 'almost', $data['missing'] );

            echo '<div class="woocommerce-message" style="margin-bottom: 15px; padding: 12px 15px; background: #fff3e0 !important; border-left: 4px solid #ff9800 !important;">';
            echo '<div style="color: #e65100;">' . $notice['message'] . '</div>';
            echo '</div>';
        }
    }
}

/**
 * 活動4提示訊息
 */
add_action( 'woocommerce_after_cart_table', 'nyb_display_activity4_notice', 5 );
function nyb_display_activity4_notice() {
    $activity_status = nyb_calculate_activity_status();

    if ( ! isset( $activity_status['activity_4'] ) || $activity_status['activity_4']['status'] !== 'qualified' ) {
        return;
    }

    $pillow_gifts = WC()->session->get( 'nyb_bundle4_pillow_gifts' );

    if ( empty( $pillow_gifts ) ) {
        return;
    }

    ?>
    <div class="nyb-activity4-notice" style="margin: 20px 0; padding: 20px; background: linear-gradient(135deg, #fff9f0 0%, #ffe8cc 100%); border: 2px solid #df565f; border-radius: 12px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
            <span style="font-size: 28px;">🎁</span>
            <h3 style="margin: 0; color: #df565f; font-size: 18px;">買枕頭送枕套活動</h3>
        </div>
        <div>
            <p style="margin: 0 0 12px 0; color: #666; font-size: 14px;">您購買的每個催眠枕都將獲贈對應的天絲枕套！</p>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php
                foreach ( $pillow_gifts as $pillowcase_id => $quantity ) :
                    $pillowcase = wc_get_product( $pillowcase_id );
                    if ( $pillowcase ) :
                        $pillowcase_name = $pillowcase->get_name();
                ?>
                    <li style="padding: 8px 12px; background: white; border-left: 3px solid #df565f; margin-bottom: 8px; border-radius: 4px;">✓ <?php echo esc_html( $pillowcase_name ); ?> × <?php echo $quantity; ?></li>
                <?php
                    endif;
                endforeach;
                ?>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * 優惠券樣式CSS
 */
add_action( 'wp_head', 'nyb_activity_coupon_styles', 20 );
function nyb_activity_coupon_styles() {
    if ( ! is_cart() && ! is_checkout() ) {
        return;
    }

    ?>
    <style type="text/css">
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

        .woocommerce-checkout-review-order-table .nyb-activity-coupon td {
            padding: 12px;
        }

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
 * 訂單活動記錄系統
 */
add_action( 'woocommerce_checkout_create_order', 'nyb_save_applied_activities_to_order', 20, 2 );
function nyb_save_applied_activities_to_order( $order, $data ) {
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

    $order->update_meta_data( '_nyb_applied_activities', $applied_activities );
    $order->update_meta_data( '_nyb_activity_count', count( $applied_activities ) );

    if ( ! empty( $activity_notes ) ) {
        $note = "【2026新年優惠活動】\n" . implode( "\n", $activity_notes );
        $order->add_order_note( $note );
    }

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
    <section class="woocommerce-order-activities" style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #fff9f0 0%, #ffe8cc 100%); border: 2px solid #df565f; border-radius: 8px;">
        <h2 style="margin: 0 0 15px 0; font-size: 18px; color: #df565f; border-bottom: 2px solid #df565f; padding-bottom: 10px;">已享優惠活動</h2>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ( $applied_activities as $activity ) : ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; background: white; border: 2px dashed #df565f; border-radius: 6px;">
                    <span style="font-size: 24px;">🎁</span>
                    <span style="flex: 1; font-weight: bold; color: #333; font-size: 14px;"><?php echo esc_html( $activity['name'] ); ?></span>
                    <span style="background: #df565f; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold;">已套用</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
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

/**
 * 輔助函數：獲取商品連結
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
 * 輔助函數：獲取商品類別的連結 HTML
 */
function nyb_get_category_links( $category ) {
    $links = [
        'mattress' => nyb_get_product_link( 1324, '嗜睡床墊' ),
        'spring_mattress' => nyb_get_product_link( 1324, '嗜睡床墊' ),
        'hypnotic_pillow' => nyb_get_product_link( NYB_Constants::HYPNOTIC_PILLOW_PARENT, '催眠枕' ),
        'hypnotic_pillow_high' => nyb_get_product_link( 2984, '高枕' ),
        'lai_mattress' => nyb_get_product_link( 3444, '賴床墊' ),
        'bed_frame' => nyb_get_product_link( 4930, '床架' ),
        'fleece_blanket' => nyb_get_product_link( NYB_Constants::GIFT_FLEECE_BLANKET, '茸茸被' ),
        'hug_pillow' => nyb_get_product_link( NYB_Constants::GIFT_HUG_PILLOW, '抱枕' ),
        'eye_mask' => nyb_get_product_link( NYB_Constants::GIFT_EYE_MASK, '眼罩' ),
        'side_pillow' => nyb_get_product_link( NYB_Constants::HYPNOTIC_PILLOW_PARENT, '側睡枕' ),
        'pillowcase' => nyb_get_product_link( NYB_Constants::HYPNOTIC_PILLOW_PARENT, '天絲枕套' ),
        'bedding_set' => '<strong>天絲四件組床包</strong>'
    ];

    return isset( $links[ $category ] ) ? $links[ $category ] : $category;
}

/**
 * 輔助函數：獲取活動的詳細提示資訊
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
                    $stats = NYB_CartAnalyzer::analyze();
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
                'message' => function() use ( $missing, $hypnotic_pillow_link, $pillowcase_link ) {
                    return '購買' . $hypnotic_pillow_link . '，即可獲得配對' . $pillowcase_link . '（買一送一）';
                },
                'type' => 'info'
            ]
        ],
        'activity_4' => [
            'qualified' => [
                'title' => '🎁 已符合優惠',
                'message' => '已購買' . $hypnotic_pillow_link . '，將獲贈配對' . $pillowcase_link . '（買一送一）',
                'type' => 'success'
            ],
            'almost' => [
                'title' => '',
                'message' => function() use ( $missing, $hypnotic_pillow_link, $pillowcase_link ) {
                    if ( empty( $missing ) || in_array( '催眠枕', $missing ) ) {
                        return '購買' . $hypnotic_pillow_link . '，即可獲得配對' . $pillowcase_link . '（買一送一）';
                    }
                    return '購買' . $hypnotic_pillow_link . '，即可獲得配對' . $pillowcase_link . '（買一送一）';
                },
                'type' => 'info'
            ],
            'not_qualified' => [
                'title' => '',
                'message' => function() use ( $missing, $hypnotic_pillow_link, $pillowcase_link ) {
                    return '購買' . $hypnotic_pillow_link . '，即可獲得配對' . $pillowcase_link . '（買一送一）';
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

