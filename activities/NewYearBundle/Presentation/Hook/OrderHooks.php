<?php
/**
 * 訂單相關 Hooks
 *
 * 負責訂單記錄、顯示相關的 WordPress/WooCommerce hooks
 */

namespace NewYearBundle\Presentation\Hook;

use NewYearBundle\Infrastructure\WooCommerce\OrderAdapter;
use NewYearBundle\Domain\Service\ActivityEligibilityChecker;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class OrderHooks
{
    public function __construct(
        private OrderAdapter $orderAdapter,
        private ActivityEligibilityChecker $eligibilityChecker,
        private Logger $logger
    ) {}

    /**
     * 註冊所有 hooks
     */
    public function register(): void
    {
        // 保存活動資訊到訂單
        add_action('woocommerce_checkout_create_order', [$this, 'saveActivitiesToOrder'], 20, 2);

        // 保存贈品資訊到訂單項目
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'saveGiftMetaToOrderItem'], 10, 4);

        // 訂單詳情頁顯示活動
        add_action('woocommerce_order_details_after_order_table', [$this, 'displayActivitiesOnOrder'], 10, 1);

        // 後台訂單詳情頁顯示活動
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'displayActivitiesInAdmin'], 10, 1);

        // 訂單列表添加活動標記欄位
        add_filter('manage_edit-shop_order_columns', [$this, 'addOrderActivityColumn'], 20);
        add_action('manage_shop_order_posts_custom_column', [$this, 'displayOrderActivityColumnContent'], 10, 2);

        // 活動優惠券樣式
        add_action('wp_head', [$this, 'addActivityCouponStyles'], 20);
    }

    /**
     * 保存活動資訊到訂單
     */
    public function saveActivitiesToOrder(\WC_Order $order, array $data): void
    {
        $activityStatus = $this->eligibilityChecker->checkAll();

        $qualified = array_filter($activityStatus, function($status) {
            return $status->isQualified();
        });

        if (empty($qualified)) {
            return;
        }

        $appliedActivities = [];
        $activityNames = Config::getActivityNames();

        foreach ($qualified as $key => $statusData) {
            $appliedActivities[] = [
                'key' => $key,
                'name' => $activityNames[$key] ?? '新年優惠活動',
                'applied_at' => current_time('mysql')
            ];
        }

        $this->orderAdapter->saveActivitiesToOrder($order, $appliedActivities);
    }

    /**
     * 保存贈品資訊到訂單項目
     */
    public function saveGiftMetaToOrderItem(\WC_Order_Item_Product $item, string $cartItemKey, array $values, \WC_Order $order): void
    {
        $product = $values['data'];

        // 保存一般贈品資訊
        $this->orderAdapter->saveGiftMetaToOrderItem($item, $product);

        // 保存虛擬床包資訊
        $this->orderAdapter->saveVirtualBeddingToOrderItem($item, $values);
    }

    /**
     * 訂單詳情頁顯示活動（前台）
     */
    public function displayActivitiesOnOrder(\WC_Order $order): void
    {
        $appliedActivities = $this->orderAdapter->getOrderActivities($order);

        if (empty($appliedActivities)) {
            return;
        }

        ?>
        <section class="woocommerce-order-activities">
            <h2 class="woocommerce-order-activities-title">已享優惠活動</h2>
            <div class="nyb-order-activities-list">
                <?php foreach ($appliedActivities as $activity) : ?>
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
     * 後台訂單詳情頁顯示活動
     */
    public function displayActivitiesInAdmin(\WC_Order $order): void
    {
        $appliedActivities = $this->orderAdapter->getOrderActivities($order);

        if (empty($appliedActivities)) {
            return;
        }

        ?>
        <div class="order_data_column" style="clear: both; margin-top: 20px; width: 100%;">
            <h3 style="color: #df565f; border-bottom: 2px solid #df565f; padding-bottom: 8px;">
                🎁 已套用的新年優惠活動
            </h3>
            <div style="margin-top: 12px;">
                <?php foreach ($appliedActivities as $activity) : ?>
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

    /**
     * 訂單列表添加活動標記欄位
     */
    public function addOrderActivityColumn(array $columns): array
    {
        $newColumns = [];

        foreach ($columns as $key => $column) {
            $newColumns[$key] = $column;

            if ($key === 'order_status') {
                $newColumns['nyb_activities'] = '優惠活動';
            }
        }

        return $newColumns;
    }

    /**
     * 顯示訂單列表的活動標記內容
     */
    public function displayOrderActivityColumnContent(string $column, int $postId): void
    {
        if ($column === 'nyb_activities') {
            $order = wc_get_order($postId);
            $activityCount = $this->orderAdapter->getActivityCount($order);

            if ($activityCount) {
                echo '<span style="display: inline-block; background: #df565f; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">';
                echo '🎁 ' . $activityCount . '個';
                echo '</span>';
            } else {
                echo '<span style="color: #999;">-</span>';
            }
        }
    }

    /**
     * 添加活動優惠券樣式
     */
    public function addActivityCouponStyles(): void
    {
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
}

