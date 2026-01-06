<?php
/**
 * Bootstrap 啟動類
 *
 * 負責：
 * 1. 檢查活動期間
 * 2. 初始化服務工廠
 * 3. 註冊所有 WordPress/WooCommerce Hooks
 * 4. 啟動外部適配器（舊有 helper 類）
 */

namespace NewYearBundle;

class Bootstrap
{
    private ServiceFactory $factory;
    private bool $isActive = false;
    private static bool $initialized = false;

    public function init(): void
    {
        // 防止重複初始化
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // 跳過不相關的請求（優化性能）
        if ($this->shouldSkipRequest()) {
            return;
        }

        // 檢查活動期間
        if (!$this->checkCampaignPeriod()) {
            $this->logInactivePeriod();
            return;
        }

        $this->isActive = true;
        $this->factory = ServiceFactory::getInstance();

        // 記錄啟動日誌（包含進程資訊以便追蹤）
        if (Config::isDebugMode()) {
            $logger = $this->factory->createLogger();
            $logger->info('========================================');
            $logger->info('新年活動系統已啟動（Clean Architecture 版本）');
            $logger->info('架構：Domain → Application → Infrastructure → Presentation');
            $logger->info('請求類型: ' . (wp_doing_ajax() ? 'AJAX' : (is_admin() ? 'Admin' : 'Frontend')));
            $logger->info('請求 URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
            $logger->info('========================================');
        }

        // 初始化外部適配器（舊有 helper 類）
        $this->initExternalAdapters();

        // 註冊所有 Hooks
        $this->registerHooks();
    }

    /**
     * 判斷是否應跳過此請求（優化性能）
     */
    private function shouldSkipRequest(): bool
    {
        // 跳過 WordPress Cron（定時任務與活動無關）
        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }

        // 跳過 REST API 請求（除非是 WooCommerce 相關）
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            // 只允許 WooCommerce REST API
            if (strpos($uri, '/wp-json/wc/') === false) {
                return true;
            }
        }

        // 跳過第三方追蹤插件的 AJAX
        if (wp_doing_ajax()) {
            $action = $_REQUEST['action'] ?? '';
            $skipActions = [
                'pys_get_pbid',           // PixelYourSite
                'pys_',                   // 其他 PixelYourSite 動作
                'heartbeat',              // WordPress 心跳
                'wordfence_',             // Wordfence 安全插件
            ];

            foreach ($skipActions as $skip) {
                if (strpos($action, $skip) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 檢查是否在活動期間
     */
    private function checkCampaignPeriod(): bool
    {
        $currentTime = current_time('mysql');
        $start = Config::getCampaignStart();
        $end = Config::getCampaignEnd();

        return $currentTime >= $start && $currentTime <= $end;
    }

    /**
     * 記錄非活動期間日誌
     */
    private function logInactivePeriod(): void
    {
        add_action('init', function() {
            if (!Config::isDebugMode()) {
                return;
            }

            $logger = $this->factory->createLogger();
            $currentTime = current_time('mysql');

            $logger->info(sprintf(
                "[新年活動期間檢查] 活動未啟用 | 當前時間: %s | 活動期間: %s ~ %s",
                $currentTime,
                Config::getCampaignStart(),
                Config::getCampaignEnd()
            ));
        }, 999);
    }

    /**
     * 初始化外部適配器
     */
    private function initExternalAdapters(): void
    {
        // 初始化優惠券顯示適配器
        $couponAdapter = $this->factory->createCouponDisplayAdapter();
        $couponAdapter->init();

        // 初始化虛擬商品適配器
        $virtualProductAdapter = $this->factory->createVirtualProductAdapter();
        $virtualProductAdapter->init();
    }

    /**
     * 註冊所有 Hooks
     */
    private function registerHooks(): void
    {
        // 價格相關 Hooks（全館9折）
        $pricingHooks = $this->factory->createPricingHooks();
        $pricingHooks->register();

        // 購物車相關 Hooks
        $cartHooks = $this->factory->createCartHooks();
        $cartHooks->register();

        // 結帳相關 Hooks
        $checkoutHooks = $this->factory->createCheckoutHooks();
        $checkoutHooks->register();

        // 訂單相關 Hooks
        $orderHooks = $this->factory->createOrderHooks();
        $orderHooks->register();

        // 商品頁控制器
        $productPageController = $this->factory->createProductPageController();
        add_action('woocommerce_before_single_product', [$productPageController, 'render'], 15);

        // 購物車頁控制器
        $cartPageController = $this->factory->createCartPageController();
        add_action('woocommerce_before_cart', [$cartPageController, 'render'], 10);

        // 活動4選擇器控制器
        $activity4Controller = $this->factory->createActivity4SelectorController();
        add_action('woocommerce_after_cart_table', [$activity4Controller, 'render'], 5);
        add_action('wp_ajax_nyb_update_activity4_selection', [$activity4Controller, 'handleAjaxUpdate']);
        add_action('wp_ajax_nopriv_nyb_update_activity4_selection', [$activity4Controller, 'handleAjaxUpdate']);
    }

    /**
     * 檢查活動是否啟用
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * 獲取服務工廠實例
     */
    public function getFactory(): ServiceFactory
    {
        return $this->factory;
    }
}

