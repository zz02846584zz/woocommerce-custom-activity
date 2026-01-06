<?php
/**
 * 活動6 Use Case: 床墊+床架送側睡枕
 *
 * 重構自原 nyb_apply_activity_6() 函數
 */

namespace NewYearBundle\Application\UseCase\Activity;

use NewYearBundle\Domain\Entity\CartSnapshot;
use NewYearBundle\Domain\Enum\ActivityType;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class Activity6UseCase implements ActivityInterface
{
    public function __construct(
        private CartAdapter $cartAdapter,
        private Logger $logger
    ) {}

    public function isEligible(CartSnapshot $snapshot): bool
    {
        return $snapshot->springMattressCount > 0 && $snapshot->bedFrameCount > 0;
    }

    public function apply(CartAdapter $cartAdapter): void
    {
        $hypnoticPillowParent = Config::getHypnoticPillowParent();
        $sidePillowVar = Config::getGiftSidePillowVar();

        // 添加側睡枕贈品
        $cartAdapter->addGift($hypnoticPillowParent, $sidePillowVar, ActivityType::ACTIVITY_6);

        // 設置贈品價格為0
        $cartAdapter->setAllGiftsFree(ActivityType::ACTIVITY_6);

        $this->logger->info("[活動6] 已應用：床墊+床架送側睡枕");
    }

    public function getType(): string
    {
        return ActivityType::ACTIVITY_6;
    }

    public function getPriority(): int
    {
        return ActivityType::getPriority(ActivityType::ACTIVITY_6);
    }
}

