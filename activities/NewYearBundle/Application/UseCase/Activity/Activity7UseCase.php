<?php
/**
 * 活動7 Use Case: 床墊+床架+枕頭*2，送天絲床包四件組（1床包+2枕套+1被套）+ 兩用茸茸被
 *
 * 重構自原 nyb_apply_activity_7() 函數
 * 條件：嗜睡床墊 + 床架 + 催眠枕×2
 * 贈品：天絲四件組床包 + 茸茸被
 */

namespace NewYearBundle\Application\UseCase\Activity;

use NewYearBundle\Domain\Entity\CartSnapshot;
use NewYearBundle\Domain\Enum\ActivityType;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;
use NewYearBundle\Infrastructure\External\VirtualProductAdapter;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class Activity7UseCase implements ActivityInterface
{
    public function __construct(
        private CartAdapter $cartAdapter,
        private VirtualProductAdapter $virtualProductAdapter,
        private Logger $logger
    ) {}

    public function isEligible(CartSnapshot $snapshot): bool
    {
        return $snapshot->springMattressCount > 0
            && $snapshot->bedFrameCount > 0
            && $snapshot->hypnoticPillowCount >= 2;
    }

    public function apply(CartAdapter $cartAdapter): void
    {
        // 贈品1: 茸茸被
        $fleeceBlanketId = Config::getGiftFleeceBlanket();
        $cartAdapter->addGift($fleeceBlanketId, 0, ActivityType::ACTIVITY_7);

        // 贈品2: 天絲四件組床包（使用虛擬商品）
        $mattressVarId = $this->findSpringMattressVariation($cartAdapter);

        if ($mattressVarId) {
            $beddingValueMap = Config::getBeddingValueMap();
            if (isset($beddingValueMap[$mattressVarId])) {
                $cart = $cartAdapter->getCart();
                if ($cart) {
                    $result = $this->virtualProductAdapter->addToCart($cart, $mattressVarId, ActivityType::ACTIVITY_7);

                    if ($result) {
                        $this->logger->info("[活動7] 已添加天絲四件組床包 | 床墊變體: {$mattressVarId}, 價值: {$beddingValueMap[$mattressVarId]}");
                    }
                }
            }
        }

        // 設置茸茸被贈品價格為0
        $cartAdapter->setAllGiftsFree(ActivityType::ACTIVITY_7);

        $this->logger->info("[活動7] 已應用：終極組合");
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
        return ActivityType::ACTIVITY_7;
    }

    public function getPriority(): int
    {
        return ActivityType::getPriority(ActivityType::ACTIVITY_7);
    }
}

