<?php
/**
 * 新年活動主檔案（重構版入口）
 *
 * 採用 Clean Architecture 架構
 * 此檔案作為模組入口，啟動新架構並提供向後兼容
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

// 載入 Composer autoloader（如果有的話）
// 注意：目前使用手動 require，未來可考慮使用 Composer PSR-4 autoloading

// 載入核心架構
require_once __DIR__ . '/NewYearBundle/Config.php';
require_once __DIR__ . '/NewYearBundle/ServiceFactory.php';
require_once __DIR__ . '/NewYearBundle/Bootstrap.php';

// Domain Layer
require_once __DIR__ . '/NewYearBundle/Domain/Enum/ActivityType.php';
require_once __DIR__ . '/NewYearBundle/Domain/Enum/ActivityStatusEnum.php';
require_once __DIR__ . '/NewYearBundle/Domain/Entity/CartSnapshot.php';
require_once __DIR__ . '/NewYearBundle/Domain/Entity/ActivityStatus.php';
require_once __DIR__ . '/NewYearBundle/Domain/Service/CartAnalyzer.php';
require_once __DIR__ . '/NewYearBundle/Domain/Service/ActivityEligibilityChecker.php';

// Infrastructure Layer
require_once __DIR__ . '/NewYearBundle/Infrastructure/WordPress/Logger.php';
require_once __DIR__ . '/NewYearBundle/Infrastructure/WooCommerce/CartAdapter.php';
require_once __DIR__ . '/NewYearBundle/Infrastructure/WooCommerce/PriceAdapter.php';
require_once __DIR__ . '/NewYearBundle/Infrastructure/WooCommerce/OrderAdapter.php';
require_once __DIR__ . '/NewYearBundle/Infrastructure/External/CouponDisplayAdapter.php';
require_once __DIR__ . '/NewYearBundle/Infrastructure/External/VirtualProductAdapter.php';

// Application Layer
require_once __DIR__ . '/NewYearBundle/Application/UseCase/Activity/ActivityInterface.php';
require_once __DIR__ . '/NewYearBundle/Application/UseCase/Activity/Activity1UseCase.php';
require_once __DIR__ . '/NewYearBundle/Application/UseCase/Activity/Activity2UseCase.php';
require_once __DIR__ . '/NewYearBundle/Application/UseCase/Activity/Activity3UseCase.php';
require_once __DIR__ . '/NewYearBundle/Application/UseCase/Activity/Activity4UseCase.php';
require_once __DIR__ . '/NewYearBundle/Application/UseCase/Activity/Activity5UseCase.php';
require_once __DIR__ . '/NewYearBundle/Application/UseCase/Activity/Activity6UseCase.php';
require_once __DIR__ . '/NewYearBundle/Application/UseCase/Activity/Activity7UseCase.php';
require_once __DIR__ . '/NewYearBundle/Application/UseCase/ApplyActivitiesOrchestrator.php';
require_once __DIR__ . '/NewYearBundle/Application/Service/ProductLinkGenerator.php';
require_once __DIR__ . '/NewYearBundle/Application/Service/NoticeBuilder.php';
require_once __DIR__ . '/NewYearBundle/Application/Service/ActivityNoticeGenerator.php';

// Presentation Layer
require_once __DIR__ . '/NewYearBundle/Presentation/Hook/PricingHooks.php';
require_once __DIR__ . '/NewYearBundle/Presentation/Hook/CartHooks.php';
require_once __DIR__ . '/NewYearBundle/Presentation/Hook/CheckoutHooks.php';
require_once __DIR__ . '/NewYearBundle/Presentation/Hook/OrderHooks.php';
require_once __DIR__ . '/NewYearBundle/Presentation/View/NoticeRenderer.php';
require_once __DIR__ . '/NewYearBundle/Presentation/View/GiftSeparatorRenderer.php';
require_once __DIR__ . '/NewYearBundle/Presentation/View/Activity4SelectorView.php';
require_once __DIR__ . '/NewYearBundle/Presentation/Controller/ProductPageController.php';
require_once __DIR__ . '/NewYearBundle/Presentation/Controller/CartPageController.php';
require_once __DIR__ . '/NewYearBundle/Presentation/Controller/Activity4SelectorController.php';

// 防止重複加載
if (defined('NYB_BOOTSTRAP_LOADED')) {
    return;
}
define('NYB_BOOTSTRAP_LOADED', true);

// 啟動新架構
$nybBootstrap = new \NewYearBundle\Bootstrap();
$nybBootstrap->init();

// ==================== 向後兼容層 ====================
// 提供全局函數以維持向後兼容（如果有外部代碼調用）

/**
 * 向後兼容：計算活動狀態
 *
 * @deprecated 使用 ServiceFactory::createActivityEligibilityChecker() 代替
 */
function nyb_calculate_activity_status($productId = 0) {
    $service = \NewYearBundle\ServiceFactory::getInstance()
        ->createActivityEligibilityChecker();

    $results = $service->checkAll($productId);

    // 轉換為舊格式
    $legacy = [];
    foreach ($results as $key => $status) {
        $legacy[$key] = $status->toArray();
    }

    return $legacy;
}

/**
 * 向後兼容：獲取相關活動
 *
 * @deprecated 使用 ServiceFactory::createActivityEligibilityChecker()->getRelatedActivities() 代替
 */
function nyb_get_related_activities($productId, $variationId = 0) {
    $service = \NewYearBundle\ServiceFactory::getInstance()
        ->createActivityEligibilityChecker();

    return $service->getRelatedActivities($productId, $variationId);
}

/**
 * 向後兼容：獲取活動描述
 *
 * @deprecated 使用 Config::getActivityDescriptions() 代替
 */
function nyb_get_activity_description($activityKey) {
    $descriptions = \NewYearBundle\Config::getActivityDescriptions();
    return $descriptions[$activityKey] ?? '';
}

/**
 * 向後兼容：日誌函數
 *
 * @deprecated 使用 ServiceFactory::createLogger() 代替
 */
function nyb_log($message, $context = []) {
    $logger = \NewYearBundle\ServiceFactory::getInstance()->createLogger();
    $logger->info($message, is_array($context) ? $context : []);
}

/**
 * 向後兼容：分析購物車內容
 *
 * @deprecated 使用 ServiceFactory::createCartAnalyzer() 代替
 */
function nyb_analyze_cart_contents() {
    $cart = WC()->cart;
    if (!$cart) {
        return [];
    }

    $analyzer = \NewYearBundle\ServiceFactory::getInstance()->createCartAnalyzer();
    $snapshot = $analyzer->analyze($cart);

    // 轉換為舊格式
    return $snapshot->toArray();
}

// ==================== 定義舊常數（向後兼容）====================
// 保留舊常數以防其他代碼有直接使用

define('NYB_CAMPAIGN_START', \NewYearBundle\Config::getCampaignStart());
define('NYB_CAMPAIGN_END', \NewYearBundle\Config::getCampaignEnd());
define('NYB_DEBUG_MODE', \NewYearBundle\Config::isDebugMode());

define('NYB_LAI_MATTRESS_PARENT_IDS', \NewYearBundle\Config::getLaiMattressParentIds());
define('NYB_SPRING_MATTRESS_PARENT_IDS', \NewYearBundle\Config::getSpringMattressParentIds());
define('NYB_LAI_MATTRESS_VARS', \NewYearBundle\Config::getLaiMattressVars());
define('NYB_SPRING_MATTRESS_VARS', \NewYearBundle\Config::getSpringMattressVars());

define('NYB_LAI_MATTRESS_PARENT_IDS_MAP', \NewYearBundle\Config::getLaiMattressParentIdsMap());
define('NYB_SPRING_MATTRESS_PARENT_IDS_MAP', \NewYearBundle\Config::getSpringMattressParentIdsMap());
define('NYB_LAI_MATTRESS_VARS_MAP', \NewYearBundle\Config::getLaiMattressVarsMap());
define('NYB_SPRING_MATTRESS_VARS_MAP', \NewYearBundle\Config::getSpringMattressVarsMap());

define('NYB_BEDDING_VALUE_MAP', \NewYearBundle\Config::getBeddingValueMap());

define('NYB_HYPNOTIC_PILLOW_PARENT', \NewYearBundle\Config::getHypnoticPillowParent());
define('NYB_HYPNOTIC_PILLOW_VARS', \NewYearBundle\Config::getHypnoticPillowVars());
define('NYB_HYPNOTIC_PILLOW_VARS_MAP', \NewYearBundle\Config::getHypnoticPillowVarsMap());

define('NYB_BED_FRAME_PARENT', \NewYearBundle\Config::getBedFrameParent());
define('NYB_BED_FRAME_IDS', \NewYearBundle\Config::getBedFrameIds());
define('NYB_BED_FRAME_IDS_MAP', \NewYearBundle\Config::getBedFrameIdsMap());

define('NYB_GIFT_FLEECE_BLANKET', \NewYearBundle\Config::getGiftFleeceBlanket());
define('NYB_GIFT_HUG_PILLOW', \NewYearBundle\Config::getGiftHugPillow());
define('NYB_GIFT_EYE_MASK', \NewYearBundle\Config::getGiftEyeMask());
define('NYB_GIFT_SIDE_PILLOW_VAR', \NewYearBundle\Config::getGiftSidePillowVar());

define('NYB_PILLOWCASE_MAP', \NewYearBundle\Config::getPillowcaseMap());
define('NYB_COMBO_SPECIAL_PRICE', \NewYearBundle\Config::getComboSpecialPrice());

define('NYB_ALL_GIFT_IDS', \NewYearBundle\Config::getAllGiftIds());
define('NYB_ALL_GIFT_IDS_MAP', \NewYearBundle\Config::getAllGiftIdsMap());

