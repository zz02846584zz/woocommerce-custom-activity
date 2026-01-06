<?php

namespace CustomActivity\NewYearBundle\Presentation\View;

use CustomActivity\NewYearBundle\Domain\Entity\Activity;
use CustomActivity\NewYearBundle\Domain\ValueObject\ActivityStatus;

/**
 * 活動提示渲染器
 * 負責將活動狀態轉換為 HTML 輸出
 */
final class ActivityNoticeRenderer
{
    /**
     * 渲染商品頁提示
     */
    public function renderProductPageNotices(array $activitiesWithStatus): void
    {
        $qualified = [];
        $almost = [];
        $notQualified = [];

        foreach ($activitiesWithStatus as $item) {
            $status = $item['status'];

            if ($status->isQualified()) {
                $qualified[] = $item;
            } elseif ($status->isAlmost()) {
                $almost[] = $item;
            } else {
                $notQualified[] = $item;
            }
        }

        // 顯示已符合的活動
        if (!empty($qualified) && is_product()) {
            foreach ($qualified as $item) {
                $this->renderNoticeBox(
                    $item['activity']->getDescription(),
                    'success',
                    '🎁 已符合優惠'
                );
            }
        }

        // 顯示差一點的活動
        if (!empty($almost)) {
            foreach ($almost as $item) {
                $message = $this->buildAlmostMessage($item['activity'], $item['status']);
                $this->renderNoticeBox($message, 'warning');
            }
        }

        // 顯示不符合的活動
        if (!empty($notQualified) && is_product()) {
            foreach ($notQualified as $item) {
                $message = $this->buildNotQualifiedMessage($item['activity']);
                $this->renderNoticeBox($message, 'info');
            }
        }
    }

    /**
     * 渲染提示框
     */
    private function renderNoticeBox(string $message, string $type, string $title = ''): void
    {
        $styles = [
            'success' => 'background: #e8f5e9; border-left: 4px solid #4caf50; color: #1b5e20;',
            'warning' => 'background: #fff3e0 !important; border-left: 4px solid #ff9800 !important; color: #e65100;',
            'info' => 'background: #e3f2fd; border-left: 4px solid #2196f3; color: #0d47a1;'
        ];

        $style = $styles[$type] ?? $styles['info'];

        echo '<div class="woocommerce-message" style="margin-bottom: 15px; padding: 12px 15px; ' . $style . '">';

        if ($title) {
            echo '<div style="font-weight: bold; font-size: 14px;">' . esc_html($title) . '：</div>';
        }

        echo '<div style="font-size: 14px;">' . $message . '</div>';
        echo '</div>';
    }

    /**
     * 建立「差一點」的訊息
     */
    private function buildAlmostMessage(Activity $activity, ActivityStatus $status): string
    {
        $missing = $status->getMissing();
        $missingText = [];

				logToFile($missing);

        foreach ($missing as $item) {
            $category = $item['category'];
            $required = $item['required'];
            $current = $item['current'];

            $categoryName = $this->getCategoryDisplayName($category);

            if ($required - $current > 1) {
                $missingText[] = $categoryName . '（需' . $required . '個，目前' . $current . '個）';
            } else {
                $missingText[] = $categoryName;
            }
        }

        $prefix = count($missing) === 1 && $missing[0]['current'] > 0 ? '再購買' : '購買';

        return $prefix . implode('、', $missingText) . '，即可享受「' . $activity->getName() . '」優惠';
    }

    /**
     * 建立「不符合」的訊息
     */
    private function buildNotQualifiedMessage(Activity $activity): string
    {
        return '購買指定商品，即可享受「' . $activity->getName() . '」優惠';
    }

    /**
     * 取得分類顯示名稱
     */
    private function getCategoryDisplayName(string $category): string
    {
        $names = [
            'spring_mattress' => '嗜睡床墊',
            'lai_mattress' => '賴床墊',
            'hypnotic_pillow' => '催眠枕',
            'bed_frame' => '床架'
        ];

        return $names[$category] ?? $category;
    }
}

