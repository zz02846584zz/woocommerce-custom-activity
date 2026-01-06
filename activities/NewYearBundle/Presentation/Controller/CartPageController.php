<?php
/**
 * 購物車頁控制器
 *
 * 負責購物車頁面的活動提示顯示
 * 重構自原 nyb_cart_page_notice() 函數
 */

namespace NewYearBundle\Presentation\Controller;

use NewYearBundle\Application\Service\ActivityNoticeGenerator;
use NewYearBundle\Presentation\View\NoticeRenderer;
use NewYearBundle\Infrastructure\WordPress\Logger;

class CartPageController
{
    public function __construct(
        private ActivityNoticeGenerator $activityNoticeGenerator,
        private NoticeRenderer $noticeRenderer,
        private Logger $logger
    ) {}

    /**
     * 渲染購物車頁提示
     */
    public function render(): void
    {
        $cart = \WC()->cart;
        if (!$cart) {
            return;
        }

        // 生成所有活動的提示
        $allNotices = $this->activityNoticeGenerator->generateAll();

        // 只顯示「差一點」的活動
        $almostNotices = array_filter($allNotices, function($notice) {
            return isset($notice['missing']) && !empty($notice['missing']);
        });

        if (empty($almostNotices)) {
            return;
        }

        // 渲染提示訊息
        foreach ($almostNotices as $notice) {
            $this->noticeRenderer->render($notice, $notice['type'] ?? 'info');
        }
    }
}

