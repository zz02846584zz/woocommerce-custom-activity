<?php
// helpers/class-activity-coupon-display.php

class NYB_Activity_Coupon_Display {

    public static function init() {
        add_filter( 'woocommerce_get_shop_coupon_data', [ __CLASS__, 'create_virtual_coupon' ], 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'sync_coupons' ], 99 );
        add_filter( 'woocommerce_cart_totals_coupon_html', [ __CLASS__, 'prevent_removal' ], 10, 3 );
        add_filter( 'woocommerce_remove_cart_discount', [ __CLASS__, 'block_removal' ], 10, 2 );
        add_filter( 'woocommerce_coupon_message', [ __CLASS__, 'hide_success_message' ], 10, 3 );
    }

    public static function create_virtual_coupon( $data, $code ) {
        if ( strpos( $code, 'nyb_activity_' ) !== 0 ) {
            return $data;
        }

        return [
            'discount_type' => 'fixed_cart',
            'amount'        => 0
        ];
    }

    public static function sync_coupons( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 3 ) {
            return;
        }

        $applied_coupons = $cart->get_applied_coupons();
        $has_external_coupon = false;

        foreach ( $applied_coupons as $coupon_code ) {
            if ( strpos( $coupon_code, 'nyb_activity_' ) !== 0 ) {
                $has_external_coupon = true;
                break;
            }
        }

        if ( $has_external_coupon ) {
            foreach ( $applied_coupons as $coupon_code ) {
                if ( strpos( $coupon_code, 'nyb_activity_' ) === 0 ) {
                    $cart->remove_coupon( $coupon_code );
                }
            }
            return;
        }

        // 使用與贈品應用相同的邏輯（數量扣減機制）
        $applied_activities = nyb_get_actually_applied_activities();

        $activity_map = [
            'activity_1' => 'nyb_activity_1',
            'activity_2' => 'nyb_activity_2',
            'activity_3' => 'nyb_activity_3',
            'activity_4' => 'nyb_activity_4',
            'activity_5' => 'nyb_activity_5',
            'activity_6' => 'nyb_activity_6',
            'activity_7' => 'nyb_activity_7'
        ];

        $should_have = [];
        foreach ( $applied_activities as $key ) {
            if ( isset( $activity_map[ $key ] ) ) {
                $should_have[] = $activity_map[ $key ];
            }
        }

        foreach ( $should_have as $coupon_code ) {
            if ( ! $cart->has_discount( $coupon_code ) ) {
                $cart->apply_coupon( $coupon_code );
            }
        }

        foreach ( $activity_map as $coupon_code ) {
            if ( ! in_array( $coupon_code, $should_have ) && $cart->has_discount( $coupon_code ) ) {
                $cart->remove_coupon( $coupon_code );
            }
        }
    }

    public static function prevent_removal( $html, $coupon, $discount_amount_html ) {
			$code = $coupon->get_code();

			if ( strpos( $code, 'nyb_activity_' ) !== 0 ) {
					return $html;
			}

			$activity_names = [
					'nyb_activity_1' => '嗜睡床墊任一張+催眠枕任一顆，再送兩用茸茸被一件',
					'nyb_activity_2' => '買賴床墊，送抱枕+眼罩',
					'nyb_activity_3' => '枕頭任選2顆 $8888再加碼贈天絲枕套2個',
					'nyb_activity_4' => '（買一送一），買催眠枕送天絲枕套一件',
					'nyb_activity_5' => '床墊+催眠枕*2+賴床墊，贈天絲四件組床包',
					'nyb_activity_6' => '嗜睡床墊+床架，贈側睡枕1顆',
					'nyb_activity_7' => '床墊+床架+枕頭*2，贈天絲四件組床包+兩用茸茸被'
			];

			$name = isset( $activity_names[ $code ] ) ? $activity_names[ $code ] : '新年優惠活動';

			// 修改返回的 HTML 結構，包裹在 th 標籤中
			return '<th colspan="2">' .
						 '<div class="nyb-activity-coupon-display">' .
						 '<span class="nyb-activity-icon">🎁</span>' .
						 '<span class="nyb-activity-name">' . esc_html( $name ) . '</span>' .
						 '<span class="nyb-activity-tag">已套用</span>' .
						 '</div>' .
						 '</th>';
		}

    public static function block_removal( $can_remove, $code ) {
        if ( strpos( $code, 'nyb_activity_' ) === 0 ) {
            return false;
        }
        return $can_remove;
    }

    /**
     * 隱藏活動優惠券的成功訊息
     */
    public static function hide_success_message( $message, $message_code, $coupon ) {
        // 檢查是否為活動優惠券
        if ( $coupon && strpos( $coupon->get_code(), 'nyb_activity_' ) === 0 ) {
            // 如果是套用成功的訊息 (WC_Coupon::WC_COUPON_SUCCESS)
            if ( $message_code === WC_Coupon::WC_COUPON_SUCCESS ) {
                return ''; // 返回空字串，不顯示訊息
            }
        }
        return $message;
    }
}

add_action( 'wp_head', function() {
	if ( ! is_cart() && ! is_checkout() ) {
			return;
	}
	?>
	<style>
			/* 隱藏活動優惠券的左側標籤 */
			.cart-discount.coupon-nyb_activity_1 th:first-child,.cart-discount.coupon-nyb_activity_1 td:empty,
			.cart-discount.coupon-nyb_activity_2 th:first-child,.cart-discount.coupon-nyb_activity_2 td:empty,
			.cart-discount.coupon-nyb_activity_3 th:first-child,.cart-discount.coupon-nyb_activity_3 td:empty,
			.cart-discount.coupon-nyb_activity_4 th:first-child,.cart-discount.coupon-nyb_activity_4 td:empty,
			.cart-discount.coupon-nyb_activity_5 th:first-child,.cart-discount.coupon-nyb_activity_5 td:empty,
			.cart-discount.coupon-nyb_activity_6 th:first-child,.cart-discount.coupon-nyb_activity_6 td:empty,
			.cart-discount.coupon-nyb_activity_7 th:first-child,.cart-discount.coupon-nyb_activity_7 td:empty {
					display: none;
			}

			/* 活動優惠券顯示樣式 */
			.nyb-activity-coupon-display {
					display: flex;
					align-items: center;
					gap: 10px;
					padding: 8px 0;
					margin: -16px 0 -8px;
			}

			.woocommerce-checkout .nyb-activity-coupon-display {
				margin: -20px 0 -20px -0.7em;
			}

			.nyb-activity-icon {
					font-size: 20px;
			}

			.nyb-activity-name {
					flex: 1;
					font-weight: bold;
					color: #df565f;
			}

			.nyb-activity-tag {
					background: #df565f;
					color: white;
					padding: 3px 10px;
					border-radius: 15px;
					font-size: 11px;
					font-weight: bold;
			}

			/* 手機版適配 */
			@media (max-width: 768px) {
					.nyb-activity-coupon-display {
							gap: 8px;
					}

					.nyb-activity-name {
							font-size: 13px;
					}

					.nyb-activity-tag {
							font-size: 10px;
							padding: 2px 8px;
					}
			}
	</style>
	<?php
}, 20 );