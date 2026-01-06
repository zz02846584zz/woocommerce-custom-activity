<?php
/**
 * 訂單適配器
 *
 * 包裝 WooCommerce 訂單相關操作
 * 負責訂單記錄、活動標記等
 */

namespace NewYearBundle\Infrastructure\WooCommerce;

use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class OrderAdapter
{
    public function __construct(
        private Logger $logger
    ) {}

    /**
     * 保存活動資訊到訂單
     *
     * @param \WC_Order $order WooCommerce 訂單對象
     * @param array $activities 已應用的活動列表 [['key' => 'activity_1', 'name' => '...', 'applied_at' => '...']]
     */
    public function saveActivitiesToOrder(\WC_Order $order, array $activities): void
    {
        if (empty($activities)) {
            return;
        }

        // 儲存活動列表到訂單 meta
        $order->update_meta_data('_nyb_applied_activities', $activities);
        $order->update_meta_data('_nyb_activity_count', count($activities));
        $order->update_meta_data('_nyb_has_activities', 'yes');

        // 添加訂單備註
        $activityNames = array_map(function($activity) {
            return '✓ ' . $activity['name'];
        }, $activities);

        $note = "【2026新年優惠活動】\n" . implode("\n", $activityNames);
        $order->add_order_note($note);

        $this->logger->info("訂單 #{$order->get_id()} 已記錄活動: " . implode(', ', array_column($activities, 'key')));
    }

    /**
     * 獲取訂單已應用的活動
     */
    public function getOrderActivities(\WC_Order $order): array
    {
        $activities = $order->get_meta('_nyb_applied_activities');
        return is_array($activities) ? $activities : [];
    }

    /**
     * 檢查訂單是否有活動優惠
     */
    public function hasActivities(\WC_Order $order): bool
    {
        return $order->get_meta('_nyb_has_activities') === 'yes';
    }

    /**
     * 獲取訂單活動數量
     */
    public function getActivityCount(\WC_Order $order): int
    {
        $count = $order->get_meta('_nyb_activity_count');
        return $count ? (int)$count : 0;
    }

    /**
     * 保存贈品資訊到訂單項目
     */
    public function saveGiftMetaToOrderItem(\WC_Order_Item_Product $item, \WC_Product $product): void
    {
        if ($product->get_meta('_is_free_gift') === 'yes') {
            $item->add_meta_data('贈品', '免費贈送 🎁', true);

            $originalPrice = $product->get_meta('_original_price');
            if ($originalPrice) {
                $item->add_meta_data('_gift_original_price', $originalPrice, true);
            }

            $this->logger->debug("訂單項目已標記為贈品 | Product: {$product->get_id()}");
        }
    }

    /**
     * 保存虛擬床包資訊到訂單項目
     */
    public function saveVirtualBeddingToOrderItem(\WC_Order_Item_Product $item, array $cartItemData): void
    {
        if (isset($cartItemData['_nyb_virtual_bedding']) && $cartItemData['_nyb_virtual_bedding'] === true) {
            $item->add_meta_data('贈品', '免費贈送 🎁', true);
            $item->add_meta_data('尺寸', $cartItemData['_nyb_bedding_size'] ?? '依床墊尺寸', true);
            $item->add_meta_data('_gift_original_price', $cartItemData['_nyb_bedding_value'] ?? 0, true);
            $item->add_meta_data('_nyb_virtual_bedding', 'yes', true);
            $item->add_meta_data('_nyb_activity_type', $cartItemData['_nyb_activity_type'] ?? '', true);

            $item->set_name('天絲四件組床包');
            $item->set_subtotal($cartItemData['_nyb_bedding_value'] ?? 0);
            $item->set_total(0);

            $this->logger->debug("訂單項目已標記為虛擬床包贈品");
        }
    }
}

