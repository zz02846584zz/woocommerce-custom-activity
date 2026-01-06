<?php
/**
 * 活動4 Use Case: 買一送一，買催眠枕送天絲枕套一件
 *
 * 重構自原 nyb_apply_activity_4() 函數
 * 邏輯：買催眠枕送對應款式的天絲枕套（只應用一次），用戶可選擇要送哪款枕套
 */

namespace NewYearBundle\Application\UseCase\Activity;

use NewYearBundle\Domain\Entity\CartSnapshot;
use NewYearBundle\Domain\Enum\ActivityType;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class Activity4UseCase implements ActivityInterface
{
    public function __construct(
        private CartAdapter $cartAdapter,
        private Logger $logger
    ) {}

    public function isEligible(CartSnapshot $snapshot): bool
    {
        return $snapshot->hypnoticPillowCount > 0;
    }

    public function apply(CartAdapter $cartAdapter): void
    {
        // 收集購物車中所有購買的催眠枕
        $purchasedPillows = [];
        $hypnoticPillowMap = Config::getHypnoticPillowVarsMap();

        foreach ($cartAdapter->getCartContents() as $cartItemKey => $cartItem) {
            $variationId = $cartItem['variation_id'];

            // 排除贈品
            if (isset($cartItem['_nyb_auto_gift'])) {
                continue;
            }

            if (isset($hypnoticPillowMap[$variationId])) {
                if (!isset($purchasedPillows[$variationId])) {
                    $purchasedPillows[$variationId] = [
                        'quantity' => 0,
                        'name' => $cartItem['data']->get_name(),
                        'cart_item_key' => $cartItemKey
                    ];
                }
                $purchasedPillows[$variationId]['quantity'] += $cartItem['quantity'];
            }
        }

        // 如果沒有購買任何催眠枕，清空選擇並返回
        if (empty($purchasedPillows)) {
            if (function_exists('WC') && WC()->session) {
                WC()->session->__unset('nyb_bundle4_selected_pillow');
            }
            return;
        }

        // 清除舊的活動4贈品
        $cartAdapter->removeGift(ActivityType::ACTIVITY_4);

        // 獲取用戶選擇
        $selectedPillow = null;
        if (function_exists('WC') && WC()->session) {
            $selectedPillow = WC()->session->get('nyb_bundle4_selected_pillow');
        }

        // 如果沒有選擇，或選擇的枕頭不在購物車中，使用第一個有效枕頭
        if (!$selectedPillow || !isset($purchasedPillows[$selectedPillow])) {
            $selectedPillow = array_key_first($purchasedPillows);
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('nyb_bundle4_selected_pillow', $selectedPillow);
            }
        }

        // 儲存可選的枕頭列表到 session（供前端使用）
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('nyb_bundle4_available_pillows', $purchasedPillows);
        }

        // 獲取對應的枕套
        $pillowcaseMap = Config::getPillowcaseMap();
        $selectedPillowcase = $pillowcaseMap[$selectedPillow] ?? 0;

        // 添加選中的枕套贈品
        if ($selectedPillowcase) {
            $hypnoticPillowParent = Config::getHypnoticPillowParent();
            $cartAdapter->addGift($hypnoticPillowParent, $selectedPillowcase, ActivityType::ACTIVITY_4);
        }

        // 設置贈品價格為0
        $cartAdapter->setAllGiftsFree(ActivityType::ACTIVITY_4);

        $this->logger->info("[活動4] 已應用：買枕頭送枕套 | 選中枕頭款式: {$selectedPillow} | 枕套: {$selectedPillowcase}");
    }

    public function getType(): string
    {
        return ActivityType::ACTIVITY_4;
    }

    public function getPriority(): int
    {
        return ActivityType::getPriority(ActivityType::ACTIVITY_4);
    }
}

