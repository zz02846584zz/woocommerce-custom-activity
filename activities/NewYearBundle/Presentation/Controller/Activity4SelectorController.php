<?php
/**
 * 活動4選擇器控制器
 *
 * 負責活動4枕套選擇介面的顯示與AJAX處理（透過枕頭選擇對應枕套）
 * 重構自原 nyb_display_activity4_selector() 函數
 */

namespace NewYearBundle\Presentation\Controller;

use NewYearBundle\Domain\Service\ActivityEligibilityChecker;
use NewYearBundle\Presentation\View\Activity4SelectorView;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class Activity4SelectorController
{
    public function __construct(
        private ActivityEligibilityChecker $eligibilityChecker,
        private Activity4SelectorView $view,
        private Logger $logger
    ) {}

    /**
     * 渲染選擇器
     */
    public function render(): void
    {
        // 檢查是否符合活動4
        $activityStatus = $this->eligibilityChecker->checkAll();

        if (!isset($activityStatus['activity_4']) || !$activityStatus['activity_4']->isQualified()) {
            return;
        }

        // 獲取可選的枕頭列表
        $availablePillows = [];
        $selectedPillow = null;

        if (function_exists('WC') && WC()->session) {
            $availablePillows = WC()->session->get('nyb_bundle4_available_pillows');
            $selectedPillow = WC()->session->get('nyb_bundle4_selected_pillow');
        }

        if (empty($availablePillows)) {
            return;
        }

        // 如果只有一種枕頭且有對應枕套，不需要顯示選擇介面
        $pillowcaseMap = Config::getPillowcaseMap();
        if (count($availablePillows) <= 1 && isset($pillowcaseMap[$selectedPillow])) {
            return;
        }

        // 渲染視圖
        $this->view->render($availablePillows, $selectedPillow);
    }

    /**
     * 處理AJAX更新
     */
    public function handleAjaxUpdate(): void
    {
        // 驗證 nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nyb_activity4_selection')) {
            wp_send_json_error(['message' => '安全驗證失敗']);
        }

        $selectedPillow = isset($_POST['pillow']) ? intval($_POST['pillow']) : 0;

        if (!$selectedPillow) {
            wp_send_json_error(['message' => '請選擇枕頭']);
        }

        // 驗證選擇是否有效
        $availablePillows = [];
        if (function_exists('WC') && WC()->session) {
            $availablePillows = WC()->session->get('nyb_bundle4_available_pillows');
        }

        if (!isset($availablePillows[$selectedPillow])) {
            wp_send_json_error(['message' => '選擇的枕頭無效']);
        }

        // 更新 session
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('nyb_bundle4_selected_pillow', $selectedPillow);
        }

        // 移除購物車中舊的活動4贈品
        $cart = \WC()->cart;
        if ($cart) {
            foreach ($cart->get_cart() as $cartItemKey => $cartItem) {
                if (isset($cartItem['_nyb_auto_gift']) && $cartItem['_nyb_auto_gift'] === 'bundle4') {
                    $cart->remove_cart_item($cartItemKey);
                }
            }

            // 觸發購物車重新計算
            $cart->calculate_totals();
        }

        $this->logger->info("[Activity4Selector] 用戶選擇枕頭: {$selectedPillow}");

        wp_send_json_success([
            'message' => '選擇已更新',
            'pillow' => $selectedPillow,
        ]);
    }
}

