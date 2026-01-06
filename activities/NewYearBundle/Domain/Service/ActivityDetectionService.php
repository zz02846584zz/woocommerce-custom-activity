<?php

namespace CustomActivity\NewYearBundle\Domain\Service;

use CustomActivity\NewYearBundle\Domain\Entity\Activity;
use CustomActivity\NewYearBundle\Domain\Entity\CartItem;
use CustomActivity\NewYearBundle\Domain\ValueObject\ActivityStatus;

/**
 * 活動檢測服務
 * 核心業務邏輯：判斷購物車是否符合活動條件
 */
final class ActivityDetectionService
{
    /**
     * 計算活動符合狀態
     *
     * @param Activity $activity
     * @param array<string, CartItem[]> $categorizedItems
     * @return ActivityStatus
     */
    public function calculateStatus(Activity $activity, array $categorizedItems): ActivityStatus
    {
        $requirements = $activity->getRequirements();
        $missing = [];

        foreach ($requirements as $category => $requiredCount) {
            $availableItems = $this->getAvailableItems($categorizedItems[$category] ?? []);
            $currentCount = count($availableItems);

            if ($currentCount < $requiredCount) {
                $missing[] = [
                    'category' => $category,
                    'required' => $requiredCount,
                    'current' => $currentCount
                ];
            }
        }

        if (empty($missing)) {
            return ActivityStatus::qualified();
        }

        // 判斷是 almost 還是 not_qualified
        $totalRequired = array_sum(array_column($requirements, 0));
        $totalMissing = array_sum(array_column($missing, 'required')) -
                        array_sum(array_column($missing, 'current'));

        if ($totalMissing <= 1 || count($missing) === 1) {
            return ActivityStatus::almost($missing);
        }

        return ActivityStatus::notQualified($missing);
    }

    /**
     * 取得未被佔用的商品
     *
     * @param CartItem[] $items
     * @return CartItem[]
     */
    private function getAvailableItems(array $items): array
    {
        return array_filter($items, function (CartItem $item) {
            return !$item->isOccupied() && !$item->isGift();
        });
    }

    /**
     * 佔用商品（用於互斥邏輯）
     *
     * @param CartItem[] $items
     * @param string $activityKey
     * @param int $count
     * @return CartItem[]
     */
    public function occupyItems(array $items, string $activityKey, int $count): array
    {
        $occupied = [];
        $available = $this->getAvailableItems($items);

        $i = 0;
        foreach ($available as $item) {
            if ($i >= $count) {
                break;
            }

            $item->occupy($activityKey);
            $occupied[] = $item;
            $i++;
        }

        return $occupied;
    }

    /**
     * 檢查是否可以套用活動
     *
     * @param Activity $activity
     * @param array<string, CartItem[]> $categorizedItems
     * @return bool
     */
    public function canApplyActivity(Activity $activity, array $categorizedItems): bool
    {
        $status = $this->calculateStatus($activity, $categorizedItems);
        return $status->isQualified();
    }
}
