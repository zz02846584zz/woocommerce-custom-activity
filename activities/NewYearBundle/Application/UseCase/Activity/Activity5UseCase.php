<?php
/**
 * 活動5 Use Case: 床墊+催眠枕*2+賴床墊，送天絲床包四件組（1床包+2枕套+1被套）
 *
 * 重構自原 nyb_apply_activity_5() 函數
 * 條件：嗜睡床墊 + 催眠枕×2 + 賴床墊
 */

namespace NewYearBundle\Application\UseCase\Activity;

use NewYearBundle\Domain\Entity\CartSnapshot;
use NewYearBundle\Domain\Enum\ActivityType;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;
use NewYearBundle\Infrastructure\External\VirtualProductAdapter;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class Activity5UseCase implements ActivityInterface
{
    public function __construct(
        private CartAdapter $cartAdapter,
        private VirtualProductAdapter $virtualProductAdapter,
        private Logger $logger
    ) {}

    public function isEligible(CartSnapshot $snapshot): bool
    {
        return $snapshot->springMattressCount > 0
            && $snapshot->hypnoticPillowCount >= 2
            && $snapshot->laiMattressCount > 0;
    }

    public function apply(CartAdapter $cartAdapter): void
    {
        // 找出嗜睡床墊的尺寸（用於確定床包價值）
        $mattressVarId = $this->findSpringMattressVariation($cartAdapter);

        if (!$mattressVarId) {
            $this->logger->warning("[活動5] 未找到嗜睡床墊變體");
            return;
        }

        $beddingValueMap = Config::getBeddingValueMap();
        if (!isset($beddingValueMap[$mattressVarId])) {
            $this->logger->warning("[活動5] 床墊變體 {$mattressVarId} 沒有對應的床包價值");
            return;
        }

        // 添加虛擬床包商品到購物車
        $cart = $cartAdapter->getCart();
        if ($cart) {
            $result = $this->virtualProductAdapter->addToCart($cart, $mattressVarId, ActivityType::ACTIVITY_5);

            if ($result) {
                $this->logger->info("[活動5] 已應用：天絲四件組床包 | 床墊變體: {$mattressVarId}, 價值: {$beddingValueMap[$mattressVarId]}");
            }
        }
    }

    /**
     * 找出購物車中的嗜睡床墊變體ID
     */
    private function findSpringMattressVariation(CartAdapter $cartAdapter): ?int
    {
        $springMattressMap = Config::getSpringMattressVarsMap();

        foreach ($cartAdapter->getCartContents() as $cartItem) {
            $variationId = $cartItem['variation_id'];

            // 排除贈品
            if (isset($cartItem['_nyb_auto_gift'])) {
                continue;
            }

            if (isset($springMattressMap[$variationId])) {
                return $variationId;
            }
        }

        return null;
    }

    public function getType(): string
    {
        return ActivityType::ACTIVITY_5;
    }

    public function getPriority(): int
    {
        return ActivityType::getPriority(ActivityType::ACTIVITY_5);
    }
}

