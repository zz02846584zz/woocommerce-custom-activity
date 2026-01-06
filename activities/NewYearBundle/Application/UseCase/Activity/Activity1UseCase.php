<?php
/**
 * 活動1 Use Case: 嗜睡床墊任一張+催眠枕任一顆，再送茸茸被一件
 *
 * 重構自原 nyb_apply_activity_1() 函數
 * Single Responsibility Principle：只負責活動1的邏輯
 */

namespace NewYearBundle\Application\UseCase\Activity;

use NewYearBundle\Domain\Entity\CartSnapshot;
use NewYearBundle\Domain\Enum\ActivityType;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class Activity1UseCase implements ActivityInterface
{
    public function __construct(
        private CartAdapter $cartAdapter,
        private Logger $logger
    ) {}

    public function isEligible(CartSnapshot $snapshot): bool
    {
        return $snapshot->springMattressCount > 0 && $snapshot->hypnoticPillowCount > 0;
    }

    public function apply(CartAdapter $cartAdapter): void
    {
        $giftId = Config::getGiftFleeceBlanket();

        // 添加茸茸被贈品
        $cartAdapter->addGift($giftId, 0, ActivityType::ACTIVITY_1);

        // 設置贈品價格為0
        $cartAdapter->setAllGiftsFree(ActivityType::ACTIVITY_1);

        $this->logger->info("[活動1] 已應用：嗜睡床墊+催眠枕送茸茸被");
    }

    public function getType(): string
    {
        return ActivityType::ACTIVITY_1;
    }

    public function getPriority(): int
    {
        return ActivityType::getPriority(ActivityType::ACTIVITY_1);
    }
}

