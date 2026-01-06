<?php
/**
 * 商品頁控制器
 *
 * 負責商品頁面的活動提示顯示
 * 重構自原 nyb_smart_product_page_notice() 函數
 */

namespace NewYearBundle\Presentation\Controller;

use NewYearBundle\Application\Service\ActivityNoticeGenerator;
use NewYearBundle\Presentation\View\NoticeRenderer;
use NewYearBundle\Infrastructure\WordPress\Logger;

class ProductPageController
{
    public function __construct(
        private ActivityNoticeGenerator $activityNoticeGenerator,
        private NoticeRenderer $noticeRenderer,
        private Logger $logger
    ) {}

    /**
     * 渲染商品頁提示
     */
    public function render(): void
    {
        if (is_admin()) {
            return;
        }

        global $product;

        if (!$product) {
            return;
        }

        $productId = $product->get_id();
        $parentId = $product->get_parent_id();

        $effectiveProductId = $parentId != 0 ? $parentId : $productId;

        // 生成與此商品相關的活動提示
        $notices = $this->activityNoticeGenerator->generateForProduct($effectiveProductId, 0);

        if (empty($notices)) {
            $this->logger->debug("[ProductPageController] 無相關活動");
            return;
        }

        // 渲染提示訊息
        $this->noticeRenderer->renderMultiple($notices);
    }
}

