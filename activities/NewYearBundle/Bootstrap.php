<?php

namespace CustomActivity\NewYearBundle;

use CustomActivity\NewYearBundle\Config\CampaignConfig;
use CustomActivity\NewYearBundle\Domain\Service\LoggerInterface;
use CustomActivity\NewYearBundle\Presentation\Hook\CartHookHandler;
use CustomActivity\NewYearBundle\Presentation\Hook\ProductPageHookHandler;

/**
 * 應用程式引導類別
 * 負責初始化整個活動系統
 */
final class Bootstrap
{
    private Container $container;
    private static ?self $instance = null;

    private function __construct()
    {
        $this->container = new Container();
    }

    /**
     * 取得單例實例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 啟動應用程式
     */
    public function boot(): void
    {
        // 註冊自動載入器（必須在第一步）
        $this->registerAutoloader();

        // 取得 Logger
        $logger = $this->container->get(LoggerInterface::class);

        // 檢查活動期間
        if (!CampaignConfig::isActivePeriod()) {
            $this->logInactivePeriod($logger);
            return;
        }

        $logger->info('[新年活動系統] 啟動成功');

        // 註冊 Hook 處理器
        $this->registerHooks();

        // 初始化舊有輔助類別（向後相容）
        $this->initLegacyHelpers();
    }

    /**
     * 註冊自動載入器
     */
    private function registerAutoloader(): void
    {
        $autoloader = new Autoloader(__DIR__);
        $autoloader->register();
    }

    /**
     * 註冊所有 Hook 處理器
     */
    private function registerHooks(): void
    {
        // 購物車 Hook
        $cartHandler = $this->container->get(CartHookHandler::class);
        $cartHandler->register();

        // 商品頁 Hook
        $productPageHandler = $this->container->get(ProductPageHookHandler::class);
        $productPageHandler->register();

        // 全館9折（保留舊邏輯）
        $this->registerDiscountHooks();
    }

    /**
     * 註冊全館9折 Hook（保留舊邏輯）
     */
    private function registerDiscountHooks(): void
    {
        add_filter('woocommerce_product_get_price', 'nyb_apply_site_wide_discount', 99, 2);
        add_filter('woocommerce_product_get_sale_price', 'nyb_apply_site_wide_discount_sale', 99, 2);
        add_filter('woocommerce_product_variation_get_price', 'nyb_apply_site_wide_discount', 99, 2);
        add_filter('woocommerce_product_variation_get_sale_price', 'nyb_apply_site_wide_discount_sale', 99, 2);
    }

    /**
     * 初始化舊有輔助類別（向後相容）
     */
    private function initLegacyHelpers(): void
    {
        // 初始化優惠券顯示類別
        if (class_exists('NYB_Activity_Coupon_Display')) {
            \NYB_Activity_Coupon_Display::init();
        }

        // 初始化虛擬床包商品類別
        if (class_exists('NYB_Virtual_Bedding_Product')) {
            \NYB_Virtual_Bedding_Product::init();
        }
    }

    /**
     * 記錄非活動期間的日誌
     */
    private function logInactivePeriod(LoggerInterface $logger): void
    {
        $logger->warning(sprintf(
            "[新年活動期間檢查] 活動未啟用 | 當前時間: %s | 活動期間: %s ~ %s",
            current_time('mysql'),
            CampaignConfig::CAMPAIGN_START,
            CampaignConfig::CAMPAIGN_END
        ));
    }

    /**
     * 取得容器（用於測試或進階使用）
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
}

