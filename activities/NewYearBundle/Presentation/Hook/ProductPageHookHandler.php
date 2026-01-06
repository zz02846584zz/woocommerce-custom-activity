<?php

namespace CustomActivity\NewYearBundle\Presentation\Hook;

use CustomActivity\NewYearBundle\Domain\Repository\ActivityRepositoryInterface;
use CustomActivity\NewYearBundle\Domain\Service\ActivityDetectionService;
use CustomActivity\NewYearBundle\Infrastructure\Adapter\WooCommerceCartAdapter;
use CustomActivity\NewYearBundle\Presentation\View\ActivityNoticeRenderer;

/**
 * 商品頁 Hook 處理器
 * 負責商品頁面的活動提示顯示
 */
final class ProductPageHookHandler
{
    private ActivityRepositoryInterface $activityRepository;
    private ActivityDetectionService $detectionService;
    private ActivityNoticeRenderer $noticeRenderer;

    public function __construct(
        ActivityRepositoryInterface $activityRepository,
        ActivityDetectionService $detectionService,
        ActivityNoticeRenderer $noticeRenderer
    ) {
        $this->activityRepository = $activityRepository;
        $this->detectionService = $detectionService;
        $this->noticeRenderer = $noticeRenderer;
    }

    /**
     * 註冊所有 Hook
     */
    public function register(): void
    {
        add_action('woocommerce_before_single_product', [$this, 'showSmartNotice'], 15);
        add_action('woocommerce_before_single_product', [$this, 'showDiscountBadge'], 5);
    }

    /**
     * 顯示智慧提示
     */
    public function showSmartNotice(): void
    {
        if (is_admin()) {
            return;
        }

        global $product;

        $productId = $product->get_id();
        $parentId = $product->get_parent_id();

        // 獲取與此商品相關的活動
        $relatedActivities = $this->activityRepository->getRelatedActivities(
            $parentId != 0 ? $parentId : $productId,
            0
        );

        if (empty($relatedActivities)) {
            return;
        }

        // 取得購物車狀態
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        $cartAdapter = new WooCommerceCartAdapter($cart);
        $categorizedItems = $cartAdapter->getItemsByCategory();

        // 計算每個活動的狀態
        $activitiesWithStatus = [];
        foreach ($relatedActivities as $activity) {
            $status = $this->detectionService->calculateStatus($activity, $categorizedItems);
            $activitiesWithStatus[] = [
                'activity' => $activity,
                'status' => $status
            ];
        }

        // 渲染提示
        $this->noticeRenderer->renderProductPageNotices($activitiesWithStatus);
    }

    /**
     * 顯示全館9折標籤
     */
    public function showDiscountBadge(): void
    {
        echo '<div class="nyb-discount-badge" style="background: #df565f; color: white; padding: 8px 15px; display: inline-block; margin-bottom: 15px; border-radius: 5px; font-weight: bold;">🎉 新年優惠：全館9折</div>';
    }
}
