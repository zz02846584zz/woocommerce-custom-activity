<?php

namespace CustomActivity\NewYearBundle\Application\UseCase;

use CustomActivity\NewYearBundle\Domain\Repository\ActivityRepositoryInterface;
use CustomActivity\NewYearBundle\Domain\Service\ActivityDetectionService;
use CustomActivity\NewYearBundle\Domain\Service\LoggerInterface;
use CustomActivity\NewYearBundle\Infrastructure\Adapter\WooCommerceCartAdapter;
use CustomActivity\NewYearBundle\Application\Service\GiftManagerService;

/**
 * 套用活動用例
 * 協調各個服務，執行活動檢測與贈品管理
 */
final class ApplyActivitiesUseCase
{
    private ActivityRepositoryInterface $activityRepository;
    private ActivityDetectionService $detectionService;
    private GiftManagerService $giftManager;
    private LoggerInterface $logger;

    public function __construct(
        ActivityRepositoryInterface $activityRepository,
        ActivityDetectionService $detectionService,
        GiftManagerService $giftManager,
        LoggerInterface $logger
    ) {
        $this->activityRepository = $activityRepository;
        $this->detectionService = $detectionService;
        $this->giftManager = $giftManager;
        $this->logger = $logger;
    }

    /**
     * 執行活動檢測與套用
     */
    public function execute(WooCommerceCartAdapter $cartAdapter): array
    {
        $this->logger->info('========== 新年活動檢測開始（互斥模式）==========');

        $appliedActivities = [];

        // 取得分類後的購物車商品
        $categorizedItems = $cartAdapter->getItemsByCategory();

        $this->logger->debug(sprintf(
            '[購物車統計] 嗜睡床墊:%d, 賴床墊:%d, 催眠枕:%d, 床架:%d',
            count($categorizedItems['spring_mattress'] ?? []),
            count($categorizedItems['lai_mattress'] ?? []),
            count($categorizedItems['hypnotic_pillow'] ?? []),
            count($categorizedItems['bed_frame'] ?? [])
        ));

        // 按優先級檢測所有活動
        $activities = $this->activityRepository->getAllActivities();

        foreach ($activities as $activity) {
            if ($this->detectionService->canApplyActivity($activity, $categorizedItems)) {
                $this->logger->info(sprintf('[活動套用] %s - %s', $activity->getKey(), $activity->getName()));

                // 佔用商品
                $this->occupyItemsForActivity($activity, $categorizedItems);

                // 加入贈品
                $this->giftManager->addGiftsForActivity(
                    $activity,
                    $cartAdapter,
                    $categorizedItems
                );

                $appliedActivities[] = $activity->getKey();
            }
        }

        // 移除不再符合條件的贈品
        $this->giftManager->removeInvalidGifts($cartAdapter, $appliedActivities);

        $this->logger->info(sprintf(
            '[已應用活動] %s',
            !empty($appliedActivities) ? implode(', ', $appliedActivities) : '無'
        ));
        $this->logger->info('========== 新年活動檢測結束 ==========');

        return $appliedActivities;
    }

    /**
     * 佔用商品（互斥邏輯）
     */
    private function occupyItemsForActivity($activity, array &$categorizedItems): void
    {
        $requirements = $activity->getRequirements();

        foreach ($requirements as $category => $count) {
            if (isset($categorizedItems[$category])) {
                $this->detectionService->occupyItems(
                    $categorizedItems[$category],
                    $activity->getKey(),
                    $count
                );
            }
        }
    }
}

