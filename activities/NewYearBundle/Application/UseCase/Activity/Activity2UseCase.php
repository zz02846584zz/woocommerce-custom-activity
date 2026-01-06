<?php
/**
 * 活動2 Use Case: 買賴床墊，送抱枕+眼罩
 *
 * 重構自原 nyb_apply_activity_2() 函數
 */

namespace NewYearBundle\Application\UseCase\Activity;

use NewYearBundle\Domain\Entity\CartSnapshot;
use NewYearBundle\Domain\Enum\ActivityType;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class Activity2UseCase implements ActivityInterface
{
    public function __construct(
        private CartAdapter $cartAdapter,
        private Logger $logger
    ) {}

    public function isEligible(CartSnapshot $snapshot): bool
    {
        return $snapshot->laiMattressCount > 0;
    }

    public function apply(CartAdapter $cartAdapter): void
    {
        $hugPillowId = Config::getGiftHugPillow();
        $eyeMaskId = Config::getGiftEyeMask();

        // 添加抱枕贈品
        $cartAdapter->addGift($hugPillowId, 0, ActivityType::ACTIVITY_2);

        // 添加眼罩贈品
        $cartAdapter->addGift($eyeMaskId, 0, ActivityType::ACTIVITY_2);

        // 設置贈品價格為0
        $cartAdapter->setAllGiftsFree(ActivityType::ACTIVITY_2);

        $this->logger->info("[活動2] 已應用：賴床墊送抱枕+眼罩");
    }

    public function getType(): string
    {
        return ActivityType::ACTIVITY_2;
    }

    public function getPriority(): int
    {
        return ActivityType::getPriority(ActivityType::ACTIVITY_2);
    }
}

