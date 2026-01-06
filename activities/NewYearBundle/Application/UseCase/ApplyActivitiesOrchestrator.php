<?php
/**
 * 活動應用統籌器
 *
 * Facade Pattern：統籌所有活動的應用邏輯
 * 重構自原 nyb_activity_detection_engine() 函數
 *
 * 職責：
 * 1. 分析購物車
 * 2. 按優先級檢查並應用所有活動
 * 3. 移除不符合條件的贈品
 */

namespace NewYearBundle\Application\UseCase;

use NewYearBundle\Domain\Service\CartAnalyzer;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;
use NewYearBundle\Infrastructure\WordPress\Logger;

class ApplyActivitiesOrchestrator
{
    /**
     * @param CartAnalyzer $cartAnalyzer
     * @param CartAdapter $cartAdapter
     * @param array $activities ActivityInterface[]
     * @param Logger $logger
     */
    public function __construct(
        private CartAnalyzer $cartAnalyzer,
        private CartAdapter $cartAdapter,
        private array $activities,
        private Logger $logger
    ) {}

    /**
     * 執行活動檢測與應用
     *
     * @param \WC_Cart $cart WooCommerce 購物車對象
     */
    public function execute(\WC_Cart $cart): void
    {
        // 防止重複執行
        static $executionCount = 0;
        $executionCount++;

        if ($executionCount >= 2) {
            $this->logger->debug("[Orchestrator] 防止重複執行，跳過");
            return;
        }

        if (!$cart || $cart->is_empty()) {
            $this->logger->debug("[Orchestrator] 購物車為空，跳過");
            return;
        }

        $this->logger->info("========== 新年活動檢測開始 ==========");

        // 步驟 1: 分析購物車內容
        $snapshot = $this->cartAnalyzer->analyze($cart);

        $this->logger->debug(sprintf(
            "[Orchestrator] 購物車快照 | 嗜睡床墊:%d, 賴床墊:%d, 催眠枕:%d, 床架:%d",
            $snapshot->springMattressCount,
            $snapshot->laiMattressCount,
            $snapshot->hypnoticPillowCount,
            $snapshot->bedFrameCount
        ));

        // 步驟 2: 按優先級排序活動
        $sortedActivities = $this->sortActivitiesByPriority();

        // 步驟 3: 檢查並應用符合條件的活動
        $appliedActivityTypes = [];

        foreach ($sortedActivities as $activity) {
            if ($activity->isEligible($snapshot)) {
                try {
                    $activity->apply($this->cartAdapter);
                    $appliedActivityTypes[] = $activity->getType();

                    $this->logger->info("[Orchestrator] 已應用活動: " . $activity->getType());
                } catch (\Exception $e) {
                    $this->logger->error("[Orchestrator] 活動應用失敗: " . $activity->getType() . " | Error: " . $e->getMessage());
                }
            }
        }

        // 步驟 4: 移除不再符合條件的贈品
        $this->cartAdapter->removeInvalidGifts($appliedActivityTypes);

        $this->logger->info(sprintf(
            "[Orchestrator] 已應用活動: %s",
            empty($appliedActivityTypes) ? '無' : implode(', ', $appliedActivityTypes)
        ));

        $this->logger->info("========== 新年活動檢測結束 ==========");
    }

    /**
     * 按優先級排序活動（數字越小優先級越高）
     *
     * @return array ActivityInterface[]
     */
    private function sortActivitiesByPriority(): array
    {
        $sorted = $this->activities;

        usort($sorted, function($a, $b) {
            return $a->getPriority() <=> $b->getPriority();
        });

        return $sorted;
    }

    /**
     * 重置執行計數器（用於測試）
     */
    public static function resetExecutionCount(): void
    {
        static $executionCount = 0;
        $executionCount = 0;
    }
}

