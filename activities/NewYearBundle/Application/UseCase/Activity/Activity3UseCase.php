<?php
/**
 * 活動3 Use Case: 枕頭任選2顆 $8888再加碼贈天絲枕套2個
 *
 * 重構自原 nyb_apply_activity_3() 函數
 * 邏輯：取價格最高的兩個枕頭組成特價組合，並加贈天絲枕套2個
 */

namespace NewYearBundle\Application\UseCase\Activity;

use NewYearBundle\Domain\Entity\CartSnapshot;
use NewYearBundle\Domain\Enum\ActivityType;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class Activity3UseCase implements ActivityInterface
{
    public function __construct(
        private CartAdapter $cartAdapter,
        private Logger $logger
    ) {}

    public function isEligible(CartSnapshot $snapshot): bool
    {
        return $snapshot->hypnoticPillowCount >= 2;
    }

    public function apply(CartAdapter $cartAdapter): void
    {
        // 收集所有購買的枕頭（排除贈品）
        $purchasedPillows = [];
        $hypnoticPillowMap = Config::getHypnoticPillowVarsMap();

        foreach ($cartAdapter->getCartContents() as $cartItem) {
            $variationId = $cartItem['variation_id'];

            // 排除贈品
            if (isset($cartItem['_nyb_auto_gift'])) {
                continue;
            }

            // 排除活動4的免費贈品
            if ($cartItem['data']->get_meta('_is_free_gift') === 'yes') {
                continue;
            }

            // 只處理催眠枕
            if (isset($hypnoticPillowMap[$variationId])) {
                $price = $cartItem['data']->get_price();
                $quantity = $cartItem['quantity'];

                // 將每個枕頭單獨加入陣列
                for ($i = 0; $i < $quantity; $i++) {
                    $purchasedPillows[] = [
                        'variation_id' => $variationId,
                        'price' => $price,
                        'name' => $cartItem['data']->get_name()
                    ];
                }
            }
        }

        // 如果少於2個枕頭，不套用活動
        if (count($purchasedPillows) < 2) {
            return;
        }

        // 按價格降序排序
        usort($purchasedPillows, function($a, $b) {
            return $b['price'] - $a['price'];
        });

        // 取最高價的兩個枕頭
        $topTwo = array_slice($purchasedPillows, 0, 2);
        $topTwoTotal = $topTwo[0]['price'] + $topTwo[1]['price'];

        // 計算需要的折扣金額
        $specialPrice = Config::getComboSpecialPrice();
        $discountNeeded = $topTwoTotal - $specialPrice;

        if ($discountNeeded > 0) {
            $cartAdapter->addFee('枕頭組合特價優惠', -$discountNeeded);
            $this->logger->info("[活動3] 已應用：枕頭組合特價 | 原價: {$topTwoTotal}, 特價: {$specialPrice}, 折扣: {$discountNeeded}");
        }

        // 加碼贈送天絲枕套 2 個
        $pillowcaseMap = Config::getPillowcaseMap();
        $hypnoticPillowParent = Config::getHypnoticPillowParent();

        // 贈送與最高價兩顆枕頭對應的天絲枕套
        foreach ($topTwo as $pillow) {
            $variationId = $pillow['variation_id'];
            if (isset($pillowcaseMap[$variationId])) {
                $pillowcaseVariationId = $pillowcaseMap[$variationId];
                $cartAdapter->addGift($hypnoticPillowParent, $pillowcaseVariationId, ActivityType::ACTIVITY_3);
                $this->logger->info("[活動3] 已添加天絲枕套贈品 | 枕頭變體: {$variationId}, 枕套變體: {$pillowcaseVariationId}");
            }
        }

        // 設置贈品價格為0
        $cartAdapter->setAllGiftsFree(ActivityType::ACTIVITY_3);
    }

    public function getType(): string
    {
        return ActivityType::ACTIVITY_3;
    }

    public function getPriority(): int
    {
        return ActivityType::getPriority(ActivityType::ACTIVITY_3);
    }
}

