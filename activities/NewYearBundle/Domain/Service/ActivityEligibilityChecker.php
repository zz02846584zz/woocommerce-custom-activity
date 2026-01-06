<?php
/**
 * 活動資格檢查服務
 *
 * Single Responsibility：只負責判斷各活動的符合資格
 * 重構自原 nyb_calculate_activity_status() 函數
 */

namespace NewYearBundle\Domain\Service;

use NewYearBundle\Domain\Entity\CartSnapshot;
use NewYearBundle\Domain\Entity\ActivityStatus;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class ActivityEligibilityChecker
{
    public function __construct(
        private CartAnalyzer $cartAnalyzer,
        private Logger $logger
    ) {}

    /**
     * 檢查所有活動的符合狀態
     *
     * @param int $productId 可選的商品ID（用於商品頁判斷）
     * @return array<string, ActivityStatus> 活動狀態陣列
     */
    public function checkAll(int $productId = 0): array
    {
        $cart = \WC()->cart;
        if (!$cart) {
            return [];
        }

        // 獲取購物車快照
        $snapshot = $this->cartAnalyzer->analyze($cart);

        $results = [];

        // 活動1: 嗜睡床墊任一張+催眠枕任一顆，再送茸茸被一件
        $results['activity_1'] = $this->checkActivity1($snapshot, $productId);

        // 活動2: 買賴床墊，送抱枕+眼罩
        $results['activity_2'] = $this->checkActivity2($snapshot, $productId);

        // 活動3: 枕頭任選2顆 $8888再加碼贈天絲枕套2個
        $results['activity_3'] = $this->checkActivity3($snapshot);

        // 活動4: （買一送一），買催眠枕送天絲枕套一件
        $results['activity_4'] = $this->checkActivity4($snapshot);

        // 活動5: 床墊+催眠枕*2+賴床墊，送天絲床包四件組
        $results['activity_5'] = $this->checkActivity5($snapshot);

        // 活動6: 床墊+床架送側睡枕
        $results['activity_6'] = $this->checkActivity6($snapshot);

        // 活動7: 床墊+床架+枕頭*2，送天絲床包四件組+茸茸被
        $results['activity_7'] = $this->checkActivity7($snapshot, $productId);

        return $results;
    }

    /**
     * 活動1: 嗜睡床墊任一張+催眠枕任一顆，再送茸茸被一件
     */
    private function checkActivity1(CartSnapshot $snapshot, int $productId): ActivityStatus
    {
        $hasSpringMattress = $snapshot->springMattressCount > 0;
        $hasHypnotic = $snapshot->hypnoticPillowCount > 0;

        $hypnoticPillowMap = Config::getHypnoticPillowVarsMap();
        $springMattressMap = Config::getSpringMattressVarsMap();

        if ($hasSpringMattress && $hasHypnotic) {
            return ActivityStatus::qualified();
        } elseif ($hasSpringMattress && !$hasHypnotic && !isset($hypnoticPillowMap[$productId])) {
            return ActivityStatus::almost(['催眠枕']);
        } elseif (!$hasSpringMattress && $hasHypnotic && !isset($springMattressMap[$productId])) {
            return ActivityStatus::almost(['嗜睡床墊']);
        } else {
            return ActivityStatus::notQualified(['嗜睡床墊', '催眠枕']);
        }
    }

    /**
     * 活動2: 買賴床墊，送抱枕+眼罩
     */
    private function checkActivity2(CartSnapshot $snapshot, int $productId): ActivityStatus
    {
        if ($snapshot->laiMattressCount > 0) {
            return ActivityStatus::qualified();
        } else {
            return ActivityStatus::almost(['賴床墊']);
        }
    }

    /**
     * 活動3: 枕頭任選2顆 $8888再加碼贈天絲枕套2個
     */
    private function checkActivity3(CartSnapshot $snapshot): ActivityStatus
    {
        if ($snapshot->hypnoticPillowCount >= 2) {
            return ActivityStatus::qualified();
        } elseif ($snapshot->hypnoticPillowCount == 1) {
            return ActivityStatus::almost(['再1個催眠枕']);
        } else {
            return ActivityStatus::notQualified(['2個催眠枕']);
        }
    }

    /**
     * 活動4: （買一送一），買催眠枕送天絲枕套一件
     */
    private function checkActivity4(CartSnapshot $snapshot): ActivityStatus
    {
        if ($snapshot->hypnoticPillowCount > 0) {
            return ActivityStatus::qualified();
        } else {
            return ActivityStatus::notQualified(['催眠枕']);
        }
    }

    /**
     * 活動5: 床墊+催眠枕*2+賴床墊，送天絲床包四件組
     */
    private function checkActivity5(CartSnapshot $snapshot): ActivityStatus
    {
        $hasSpringMattress = $snapshot->springMattressCount > 0;
        $hasLaiMattress = $snapshot->laiMattressCount > 0;
        $hasHypnotic = $snapshot->hypnoticPillowCount > 0;
        $has2Hypnotic = $snapshot->hypnoticPillowCount >= 2;

        if ($hasSpringMattress && $hasHypnotic && $hasLaiMattress && $has2Hypnotic) {
            return ActivityStatus::qualified();
        } else {
            $missing = [];
            if (!$hasSpringMattress) $missing[] = '嗜睡床墊';
            if (!$hasLaiMattress) $missing[] = '賴床墊';
            if (!$has2Hypnotic) {
                $missing[] = sprintf('催眠枕(需2個，目前%d個)', $snapshot->hypnoticPillowCount);
            }
            return ActivityStatus::almost($missing);
        }
    }

    /**
     * 活動6: 床墊+床架送側睡枕
     */
    private function checkActivity6(CartSnapshot $snapshot): ActivityStatus
    {
        $hasSpringMattress = $snapshot->springMattressCount > 0;
        $hasBedFrame = $snapshot->bedFrameCount > 0;

        if ($hasSpringMattress && $hasBedFrame) {
            return ActivityStatus::qualified();
        } elseif ($hasSpringMattress && !$hasBedFrame) {
            return ActivityStatus::almost(['床架']);
        } elseif (!$hasSpringMattress && $hasBedFrame) {
            return ActivityStatus::almost(['嗜睡床墊']);
        } else {
            return ActivityStatus::notQualified(['嗜睡床墊', '床架']);
        }
    }

    /**
     * 活動7: 床墊+床架+枕頭*2，送天絲床包四件組+茸茸被
     */
    private function checkActivity7(CartSnapshot $snapshot, int $productId): ActivityStatus
    {
        $hasSpringMattress = $snapshot->springMattressCount > 0;
        $hasBedFrame = $snapshot->bedFrameCount > 0;
        $has2Hypnotic = $snapshot->hypnoticPillowCount >= 2;

        $bedFrameMap = Config::getBedFrameIdsMap();

        if ($hasSpringMattress && $hasBedFrame && $has2Hypnotic) {
            return ActivityStatus::qualified();
        } elseif ($hasSpringMattress && !$hasBedFrame && !isset($bedFrameMap[$productId])) {
            $missing = [];
            if (!$hasBedFrame) $missing[] = '床架';
            if (!$has2Hypnotic) {
                $missing[] = sprintf('催眠枕(需2個，目前%d個)', $snapshot->hypnoticPillowCount);
            }
            return ActivityStatus::almost($missing);
        } else {
            $missing = ['嗜睡床墊', '床架'];
            if (!$has2Hypnotic) {
                $missing[] = sprintf('催眠枕(需2個，目前%d個)', $snapshot->hypnoticPillowCount);
            }
            return ActivityStatus::notQualified($missing);
        }
    }

    /**
     * 獲取與指定商品相關的活動
     * 重構自原 nyb_get_related_activities() 函數
     *
     * @return array<int, array> 相關活動列表，按優先級排序
     */
    public function getRelatedActivities(int $productId, int $variationId = 0): array
    {
        $allStatus = $this->checkAll($productId);
        $related = [];

        $checkId = $variationId != 0 ? $variationId : $productId;

        // 使用 Hash Map 判斷商品屬於哪些活動
        $laiMattressMap = Config::getLaiMattressVarsMap();
        $laiMattressParentMap = Config::getLaiMattressParentIdsMap();
        $springMattressMap = Config::getSpringMattressVarsMap();
        $springMattressParentMap = Config::getSpringMattressParentIdsMap();
        $hypnoticPillowMap = Config::getHypnoticPillowVarsMap();
        $hypnoticPillowParent = Config::getHypnoticPillowParent();
        $bedFrameMap = Config::getBedFrameIdsMap();
        $bedFrameParent = Config::getBedFrameParent();

        // 賴床墊相關
        if (isset($laiMattressMap[$checkId]) || isset($laiMattressParentMap[$productId])) {
            if (isset($allStatus['activity_2'])) {
                $related[] = ['key' => 'activity_2', 'data' => $allStatus['activity_2'], 'priority' => 6];
            }
            if (isset($allStatus['activity_5'])) {
                $related[] = ['key' => 'activity_5', 'data' => $allStatus['activity_5'], 'priority' => 3];
            }
        }

        // 嗜睡床墊相關
        if (isset($springMattressMap[$checkId]) || isset($springMattressParentMap[$productId])) {
            if (isset($allStatus['activity_1'])) {
                $related[] = ['key' => 'activity_1', 'data' => $allStatus['activity_1'], 'priority' => 7];
            }
            if (isset($allStatus['activity_5'])) {
                $related[] = ['key' => 'activity_5', 'data' => $allStatus['activity_5'], 'priority' => 3];
            }
            if (isset($allStatus['activity_6'])) {
                $related[] = ['key' => 'activity_6', 'data' => $allStatus['activity_6'], 'priority' => 2];
            }
            if (isset($allStatus['activity_7'])) {
                $related[] = ['key' => 'activity_7', 'data' => $allStatus['activity_7'], 'priority' => 1];
            }
        }

        // 催眠枕相關
        if (isset($hypnoticPillowMap[$checkId]) || $productId == $hypnoticPillowParent) {
            if (isset($allStatus['activity_1'])) {
                $related[] = ['key' => 'activity_1', 'data' => $allStatus['activity_1'], 'priority' => 7];
            }
            if (isset($allStatus['activity_3'])) {
                $related[] = ['key' => 'activity_3', 'data' => $allStatus['activity_3'], 'priority' => 5];
            }
            if (isset($allStatus['activity_4'])) {
                $related[] = ['key' => 'activity_4', 'data' => $allStatus['activity_4'], 'priority' => 4];
            }
            if (isset($allStatus['activity_5'])) {
                $related[] = ['key' => 'activity_5', 'data' => $allStatus['activity_5'], 'priority' => 3];
            }
            if (isset($allStatus['activity_7'])) {
                $related[] = ['key' => 'activity_7', 'data' => $allStatus['activity_7'], 'priority' => 1];
            }
        }

        // 床架相關
        if (isset($bedFrameMap[$checkId]) || $productId == $bedFrameParent) {
            if (isset($allStatus['activity_6'])) {
                $related[] = ['key' => 'activity_6', 'data' => $allStatus['activity_6'], 'priority' => 2];
            }
            if (isset($allStatus['activity_7'])) {
                $related[] = ['key' => 'activity_7', 'data' => $allStatus['activity_7'], 'priority' => 1];
            }
        }

        // 按優先級排序
        usort($related, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $related;
    }
}

