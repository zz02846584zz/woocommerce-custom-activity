<?php
/**
 * 服務工廠
 *
 * 簡單工廠模式，統一創建所有服務實例
 * 遵循 Dependency Inversion Principle：提供統一的創建入口
 */

namespace NewYearBundle;

use NewYearBundle\Domain\Service\CartAnalyzer;
use NewYearBundle\Domain\Service\ActivityEligibilityChecker;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;
use NewYearBundle\Infrastructure\WooCommerce\PriceAdapter;
use NewYearBundle\Infrastructure\WooCommerce\OrderAdapter;
use NewYearBundle\Infrastructure\External\CouponDisplayAdapter;
use NewYearBundle\Infrastructure\External\VirtualProductAdapter;
use NewYearBundle\Application\UseCase\ApplyActivitiesOrchestrator;
use NewYearBundle\Application\UseCase\Activity\Activity1UseCase;
use NewYearBundle\Application\UseCase\Activity\Activity2UseCase;
use NewYearBundle\Application\UseCase\Activity\Activity3UseCase;
use NewYearBundle\Application\UseCase\Activity\Activity4UseCase;
use NewYearBundle\Application\UseCase\Activity\Activity5UseCase;
use NewYearBundle\Application\UseCase\Activity\Activity6UseCase;
use NewYearBundle\Application\UseCase\Activity\Activity7UseCase;
use NewYearBundle\Application\Service\NoticeBuilder;
use NewYearBundle\Application\Service\ActivityNoticeGenerator;
use NewYearBundle\Application\Service\ProductLinkGenerator;
use NewYearBundle\Presentation\Controller\ProductPageController;
use NewYearBundle\Presentation\Controller\CartPageController;
use NewYearBundle\Presentation\Controller\Activity4SelectorController;
use NewYearBundle\Presentation\View\NoticeRenderer;
use NewYearBundle\Presentation\View\GiftSeparatorRenderer;
use NewYearBundle\Presentation\View\Activity4SelectorView;
use NewYearBundle\Presentation\Hook\PricingHooks;
use NewYearBundle\Presentation\Hook\CartHooks;
use NewYearBundle\Presentation\Hook\CheckoutHooks;
use NewYearBundle\Presentation\Hook\OrderHooks;

class ServiceFactory
{
    private static ?ServiceFactory $instance = null;

    // 快取已創建的服務實例（單例共享）
    private ?Logger $logger = null;
    private ?CartAdapter $cartAdapter = null;
    private ?PriceAdapter $priceAdapter = null;
    private ?OrderAdapter $orderAdapter = null;
    private ?CartAnalyzer $cartAnalyzer = null;
    private ?ActivityEligibilityChecker $eligibilityChecker = null;
    private ?ProductLinkGenerator $productLinkGenerator = null;
    private ?NoticeBuilder $noticeBuilder = null;
    private ?ActivityNoticeGenerator $activityNoticeGenerator = null;
    private ?ApplyActivitiesOrchestrator $orchestrator = null;

    private function __construct()
    {
        // 私有構造函數，防止外部實例化
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ==================== Infrastructure Layer ====================

    public function createLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = new Logger('newyear-bundle.log');
        }
        return $this->logger;
    }

    public function createCartAdapter(): CartAdapter
    {
        if ($this->cartAdapter === null) {
            $this->cartAdapter = new CartAdapter(
                $this->createLogger()
            );
        }
        return $this->cartAdapter;
    }

    public function createPriceAdapter(): PriceAdapter
    {
        if ($this->priceAdapter === null) {
            $this->priceAdapter = new PriceAdapter(
                $this->createLogger()
            );
        }
        return $this->priceAdapter;
    }

    public function createOrderAdapter(): OrderAdapter
    {
        if ($this->orderAdapter === null) {
            $this->orderAdapter = new OrderAdapter(
                $this->createLogger()
            );
        }
        return $this->orderAdapter;
    }

    public function createCouponDisplayAdapter(): CouponDisplayAdapter
    {
        return new CouponDisplayAdapter();
    }

    public function createVirtualProductAdapter(): VirtualProductAdapter
    {
        return new VirtualProductAdapter();
    }

    // ==================== Domain Layer ====================

    public function createCartAnalyzer(): CartAnalyzer
    {
        if ($this->cartAnalyzer === null) {
            $this->cartAnalyzer = new CartAnalyzer(
                $this->createLogger()
            );
        }
        return $this->cartAnalyzer;
    }

    public function createActivityEligibilityChecker(): ActivityEligibilityChecker
    {
        if ($this->eligibilityChecker === null) {
            $this->eligibilityChecker = new ActivityEligibilityChecker(
                $this->createCartAnalyzer(),
                $this->createLogger()
            );
        }
        return $this->eligibilityChecker;
    }

    // ==================== Application Layer ====================

    public function createProductLinkGenerator(): ProductLinkGenerator
    {
        if ($this->productLinkGenerator === null) {
            $this->productLinkGenerator = new ProductLinkGenerator();
        }
        return $this->productLinkGenerator;
    }

    public function createNoticeBuilder(): NoticeBuilder
    {
        if ($this->noticeBuilder === null) {
            $this->noticeBuilder = new NoticeBuilder(
                $this->createProductLinkGenerator(),
                $this->createCartAnalyzer()
            );
        }
        return $this->noticeBuilder;
    }

    public function createActivityNoticeGenerator(): ActivityNoticeGenerator
    {
        if ($this->activityNoticeGenerator === null) {
            $this->activityNoticeGenerator = new ActivityNoticeGenerator(
                $this->createActivityEligibilityChecker(),
                $this->createNoticeBuilder()
            );
        }
        return $this->activityNoticeGenerator;
    }

    // ==================== Activity Use Cases ====================

    public function createActivity1UseCase(): Activity1UseCase
    {
        return new Activity1UseCase(
            $this->createCartAdapter(),
            $this->createLogger()
        );
    }

    public function createActivity2UseCase(): Activity2UseCase
    {
        return new Activity2UseCase(
            $this->createCartAdapter(),
            $this->createLogger()
        );
    }

    public function createActivity3UseCase(): Activity3UseCase
    {
        return new Activity3UseCase(
            $this->createCartAdapter(),
            $this->createLogger()
        );
    }

    public function createActivity4UseCase(): Activity4UseCase
    {
        return new Activity4UseCase(
            $this->createCartAdapter(),
            $this->createLogger()
        );
    }

    public function createActivity5UseCase(): Activity5UseCase
    {
        return new Activity5UseCase(
            $this->createCartAdapter(),
            $this->createVirtualProductAdapter(),
            $this->createLogger()
        );
    }

    public function createActivity6UseCase(): Activity6UseCase
    {
        return new Activity6UseCase(
            $this->createCartAdapter(),
            $this->createLogger()
        );
    }

    public function createActivity7UseCase(): Activity7UseCase
    {
        return new Activity7UseCase(
            $this->createCartAdapter(),
            $this->createVirtualProductAdapter(),
            $this->createLogger()
        );
    }

    /**
     * 創建所有活動 Use Cases 陣列
     * @return array
     */
    public function createAllActivityUseCases(): array
    {
        return [
            $this->createActivity1UseCase(),
            $this->createActivity2UseCase(),
            $this->createActivity3UseCase(),
            $this->createActivity4UseCase(),
            $this->createActivity5UseCase(),
            $this->createActivity6UseCase(),
            $this->createActivity7UseCase(),
        ];
    }

    public function createApplyActivitiesOrchestrator(): ApplyActivitiesOrchestrator
    {
        if ($this->orchestrator === null) {
            $this->orchestrator = new ApplyActivitiesOrchestrator(
                $this->createCartAnalyzer(),
                $this->createCartAdapter(),
                $this->createAllActivityUseCases(),
                $this->createLogger()
            );
        }
        return $this->orchestrator;
    }

    // ==================== Presentation Layer ====================

    public function createNoticeRenderer(): NoticeRenderer
    {
        return new NoticeRenderer();
    }

    public function createGiftSeparatorRenderer(): GiftSeparatorRenderer
    {
        return new GiftSeparatorRenderer();
    }

    public function createActivity4SelectorView(): Activity4SelectorView
    {
        return new Activity4SelectorView();
    }

    public function createProductPageController(): ProductPageController
    {
        return new ProductPageController(
            $this->createActivityNoticeGenerator(),
            $this->createNoticeRenderer(),
            $this->createLogger()
        );
    }

    public function createCartPageController(): CartPageController
    {
        return new CartPageController(
            $this->createActivityNoticeGenerator(),
            $this->createNoticeRenderer(),
            $this->createLogger()
        );
    }

    public function createActivity4SelectorController(): Activity4SelectorController
    {
        return new Activity4SelectorController(
            $this->createActivityEligibilityChecker(),
            $this->createActivity4SelectorView(),
            $this->createLogger()
        );
    }

    // ==================== Presentation Hooks ====================

    public function createPricingHooks(): PricingHooks
    {
        return new PricingHooks(
            $this->createPriceAdapter(),
            $this->createLogger()
        );
    }

    public function createCartHooks(): CartHooks
    {
        return new CartHooks(
            $this->createApplyActivitiesOrchestrator(),
            $this->createCartAdapter(),
            $this->createGiftSeparatorRenderer(),
            $this->createLogger()
        );
    }

    public function createCheckoutHooks(): CheckoutHooks
    {
        return new CheckoutHooks(
            $this->createGiftSeparatorRenderer()
        );
    }

    public function createOrderHooks(): OrderHooks
    {
        return new OrderHooks(
            $this->createOrderAdapter(),
            $this->createActivityEligibilityChecker(),
            $this->createLogger()
        );
    }
}

