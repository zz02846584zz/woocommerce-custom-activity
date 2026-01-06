<?php
/**
 * 新年活動系統 - 重構版
 *
 * 採用 Clean Architecture 架構，遵循 SOLID 原則
 *
 * 架構說明：
 * - Domain Layer: 核心業務邏輯（Entity, ValueObject, Service, Repository Interface）
 * - Application Layer: 用例協調（UseCase, DTO, Application Service）
 * - Infrastructure Layer: 外部依賴實作（Repository, Adapter, Logger）
 * - Presentation Layer: 使用者介面（Hook, View）
 *
 * @package CustomActivity
 * @version 2.0.0
 */

// 載入舊有輔助類別（向後相容）
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'helpers/class-activity-coupon-display.php';
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'helpers/class-virtual-bedding-product.php';

// 載入自動載入器
require_once __DIR__ . '/NewYearBundle/Autoloader.php';

// 註冊自動載入器
$autoloader = new \CustomActivity\NewYearBundle\Autoloader(__DIR__ . '/NewYearBundle');
$autoloader->register();

// 啟動應用程式
$app = \CustomActivity\NewYearBundle\Bootstrap::getInstance();
$app->boot();

// ==========================================
// 以下為保留的全域函數（向後相容）
// ==========================================

/**
 * 全館9折價格覆寫
 */
function nyb_apply_site_wide_discount($price, $product) {
    $is_free_gift = $product->get_meta('_is_free_gift');
    if ($is_free_gift === 'yes') {
        return 0;
    }

    $regular_price = $product->get_regular_price();
    if ($regular_price) {
        return $regular_price * 0.9;
    }

    return $price;
}

/**
 * 全館9折促銷價覆寫
 */
function nyb_apply_site_wide_discount_sale($sale_price, $product) {
    $regular_price = $product->get_regular_price();
    if ($regular_price) {
        return $regular_price * 0.9;
    }

    return $sale_price;
}

/**
 * 計算活動狀態（舊版相容函數）
 */
function nyb_calculate_activity_status($product_id = 0) {
    $cart = WC()->cart;
    if (!$cart) {
        return [];
    }

    $container = \CustomActivity\NewYearBundle\Bootstrap::getInstance()->getContainer();
    $cartAdapter = new \CustomActivity\NewYearBundle\Infrastructure\Adapter\WooCommerceCartAdapter($cart);
    $detectionService = $container->get(\CustomActivity\NewYearBundle\Domain\Service\ActivityDetectionService::class);
    $activityRepo = $container->get(\CustomActivity\NewYearBundle\Domain\Repository\ActivityRepositoryInterface::class);

    $categorizedItems = $cartAdapter->getItemsByCategory();
    $activities = $activityRepo->getAllActivities();

    $results = [];
    foreach ($activities as $activity) {
        $status = $detectionService->calculateStatus($activity, $categorizedItems);
        $results[$activity->getKey()] = $status->toArray();
    }

    return $results;
}

/**
 * 取得活動名稱
 */
function nyb_get_activity_name($activity_key) {
    $names = [
        'activity_1' => '嗜睡床墊+催眠枕，送茸茸被',
        'activity_2' => '催眠枕買一送一，送天絲枕套',
        'activity_3' => '催眠枕任選2顆特價$8,888+天絲枕套2個',
        'activity_4' => '賴床墊送抱枕+眼罩',
        'activity_5' => '嗜睡床墊+催眠枕*2+賴床墊，送天絲四件組床包',
        'activity_6' => '嗜睡床墊+床架，送側睡枕',
        'activity_7' => '嗜睡床墊+床架+催眠枕*2，送天絲四件組床包+茸茸被'
    ];

    return $names[$activity_key] ?? '新年優惠活動';
}

// ==========================================
// 保留的 Hook 註冊（UI 相關）
// ==========================================

// 購物車頁提示
add_action('woocommerce_before_cart', 'nyb_cart_page_notice', 10);

// 贈品分隔線
add_action('woocommerce_before_cart_contents', 'nyb_inject_gift_separator_script');
add_action('woocommerce_review_order_before_cart_contents', 'nyb_inject_gift_separator_script');

// 贈品樣式
add_action('wp_head', 'nyb_gift_separator_styles');
add_action('wp_head', 'nyb_activity_coupon_styles', 20);

// 贈品顯示
add_filter('woocommerce_cart_item_price', 'nyb_display_gift_original_price', 1000, 3);
add_filter('woocommerce_cart_item_subtotal', 'nyb_display_gift_original_subtotal', 1000, 3);
add_filter('woocommerce_checkout_cart_item_quantity', 'nyb_display_gift_quantity_on_checkout', 10, 3);

// 訂單記錄
add_action('woocommerce_checkout_create_order_line_item', 'nyb_save_gift_meta_to_order_item', 10, 4);
add_action('woocommerce_checkout_create_order', 'nyb_save_applied_activities_to_order', 20, 2);
add_action('woocommerce_order_details_after_order_table', 'nyb_display_applied_activities_on_order', 10, 1);
add_action('woocommerce_admin_order_data_after_billing_address', 'nyb_display_applied_activities_in_admin', 10, 1);
add_filter('manage_edit-shop_order_columns', 'nyb_add_order_activity_column', 20);
add_action('manage_shop_order_posts_custom_column', 'nyb_display_order_activity_column_content', 10, 2);

// ==========================================
// 保留的函數實作（從原檔案複製）
// ==========================================

function nyb_cart_page_notice() {
    $cart = WC()->cart;
    if (!$cart) {
        return;
    }

    $activity_status = nyb_calculate_activity_status();

    $almost = array_filter($activity_status, function($status) {
        return $status['status'] === 'almost';
    });

    if (!empty($almost)) {
        foreach ($almost as $key => $data) {
            echo '<div class="woocommerce-message" style="margin-bottom: 15px; padding: 12px 15px; background: #fff3e0 !important; border-left: 4px solid #ff9800 !important;">';
            echo '<div style="color: #e65100;">' . nyb_get_activity_name($key) . '</div>';
            echo '</div>';
        }
    }
}

function nyb_inject_gift_separator_script() {
    static $script_added = false;
    if ($script_added) {
        return;
    }
    $script_added = true;
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function addGiftSeparator() {
            $('.nyb-gift-separator-row').remove();

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

        addGiftSeparator();
        $(document.body).on('updated_cart_totals updated_checkout', function() {
            addGiftSeparator();
        });
    });
    </script>
    <?php
}

function nyb_gift_separator_styles() {
    if (!is_cart() && !is_checkout()) {
        return;
    }
    ?>
    <style type="text/css">
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
    </style>
    <?php
}

function nyb_activity_coupon_styles() {
    if (!is_cart() && !is_checkout()) {
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
    </style>
    <?php
}

function nyb_display_gift_original_price($price, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];

    if ($product->get_meta('_is_free_gift') === 'yes') {
        $original_price = $product->get_meta('_original_price');
        if ($original_price) {
            return '<del>' . wc_price($original_price) . '</del> <ins>' . wc_price(0) . '</ins><br><span style="color: #df565f; font-weight: bold;">🎁 免費贈送</span>';
        }
    }

    return $price;
}

function nyb_display_gift_original_subtotal($subtotal, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];

    if ($product->get_meta('_is_free_gift') === 'yes') {
        $original_price = $product->get_meta('_original_price');
        if ($original_price) {
            $original_subtotal = $original_price * $cart_item['quantity'];
            return '<del>' . wc_price($original_subtotal) . '</del> <ins>' . wc_price(0) . '</ins>';
        }
    }

    return $subtotal;
}

function nyb_display_gift_quantity_on_checkout($quantity_html, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];

    if ($product->get_meta('_is_free_gift') === 'yes') {
        return $cart_item['quantity'] . ' <span style="color: #df565f; font-size: 0.9em;">(贈品)</span>';
    }

    return $quantity_html;
}

function nyb_save_gift_meta_to_order_item($item, $cart_item_key, $values, $order) {
    $product = $values['data'];

    if ($product->get_meta('_is_free_gift') === 'yes') {
        $item->add_meta_data('贈品', '免費贈送 🎁', true);
        $original_price = $product->get_meta('_original_price');
        if ($original_price) {
            $item->add_meta_data('_gift_original_price', $original_price, true);
        }
    }
}

function nyb_save_applied_activities_to_order($order, $data) {
    $activity_status = nyb_calculate_activity_status();

    $qualified = array_filter($activity_status, function($status) {
        return $status['status'] === 'qualified';
    });

    if (empty($qualified)) {
        return;
    }

    $applied_activities = [];
    $activity_notes = [];

    foreach ($qualified as $key => $data_item) {
        $activity_name = nyb_get_activity_name($key);
        $applied_activities[] = [
            'key' => $key,
            'name' => $activity_name,
            'applied_at' => current_time('mysql')
        ];

        $activity_notes[] = sprintf('✓ %s', $activity_name);
    }

    $order->update_meta_data('_nyb_applied_activities', $applied_activities);
    $order->update_meta_data('_nyb_activity_count', count($applied_activities));

    if (!empty($activity_notes)) {
        $note = "【2026新年優惠活動】\n" . implode("\n", $activity_notes);
        $order->add_order_note($note);
    }

    $order->update_meta_data('_nyb_has_activities', 'yes');
}

function nyb_display_applied_activities_on_order($order) {
    $applied_activities = $order->get_meta('_nyb_applied_activities');

    if (empty($applied_activities)) {
        return;
    }
    ?>
    <section class="woocommerce-order-activities">
        <h2 class="woocommerce-order-activities-title">已享優惠活動</h2>
        <div class="nyb-order-activities-list">
            <?php foreach ($applied_activities as $activity): ?>
                <div class="nyb-order-activity-item">
                    <span class="nyb-activity-icon">🎁</span>
                    <span class="nyb-activity-label"><?php echo esc_html($activity['name']); ?></span>
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
    </style>
    <?php
}

function nyb_display_applied_activities_in_admin($order) {
    $applied_activities = $order->get_meta('_nyb_applied_activities');

    if (empty($applied_activities)) {
        return;
    }
    ?>
    <div class="order_data_column" style="clear: both; margin-top: 20px; width: 100%;">
        <h3 style="color: #df565f; border-bottom: 2px solid #df565f; padding-bottom: 8px;">
            🎁 已套用的新年優惠活動
        </h3>
        <div style="margin-top: 12px;">
            <?php foreach ($applied_activities as $activity): ?>
                <p style="margin: 8px 0; padding: 10px !important; background: #fff9f0; border-left: 4px solid #df565f; font-size: 13px;">
                    <strong><?php echo esc_html($activity['name']); ?></strong>
                    <br>
                    <small style="color: #666;">套用時間: <?php echo esc_html($activity['applied_at']); ?></small>
                </p>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function nyb_add_order_activity_column($columns) {
    $new_columns = [];

    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;

        if ($key === 'order_status') {
            $new_columns['nyb_activities'] = '優惠活動';
        }
    }

    return $new_columns;
}

function nyb_display_order_activity_column_content($column, $post_id) {
    if ($column === 'nyb_activities') {
        $order = wc_get_order($post_id);
        $activity_count = $order->get_meta('_nyb_activity_count');

        if ($activity_count) {
            echo '<span style="display: inline-block; background: #df565f; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">';
            echo '🎁 ' . $activity_count . '個';
            echo '</span>';
        } else {
            echo '<span style="color: #999;">-</span>';
        }
    }
}

