<?php
/**
 * 活動提示生成器
 *
 * 統籌活動資格檢查與提示訊息生成
 */

namespace NewYearBundle\Application\Service;

use NewYearBundle\Domain\Service\ActivityEligibilityChecker;

class ActivityNoticeGenerator
{
    public function __construct(
        private ActivityEligibilityChecker $eligibilityChecker,
        private NoticeBuilder $noticeBuilder
    ) {}

    /**
     * 生成所有活動的提示訊息
     *
     * @param int $productId 可選的商品ID（用於商品頁判斷）
     * @return array
     */
    public function generateAll(int $productId = 0): array
    {
        $activityStatuses = $this->eligibilityChecker->checkAll($productId);
        $notices = [];

        foreach ($activityStatuses as $activityKey => $status) {
            $notices[$activityKey] = $this->noticeBuilder->build(
                $activityKey,
                $status->status,
                $status->missing
            );
        }

        return $notices;
    }

    /**
     * 生成與指定商品相關的活動提示
     *
     * @param int $productId 商品ID
     * @param int $variationId 變體ID
     * @return array
     */
    public function generateForProduct(int $productId, int $variationId = 0): array
    {
        $relatedActivities = $this->eligibilityChecker->getRelatedActivities($productId, $variationId);
        $notices = [];

        foreach ($relatedActivities as $activity) {
            $activityKey = $activity['key'];
            $activityData = $activity['data'];

            $notices[] = $this->noticeBuilder->build(
                $activityKey,
                $activityData->status,
                $activityData->missing
            );
        }

        return $notices;
    }
}

