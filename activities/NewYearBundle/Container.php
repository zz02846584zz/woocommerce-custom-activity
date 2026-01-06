<?php

namespace CustomActivity\NewYearBundle;

use CustomActivity\NewYearBundle\Domain\Repository\ActivityRepositoryInterface;
use CustomActivity\NewYearBundle\Domain\Service\ActivityDetectionService;
use CustomActivity\NewYearBundle\Domain\Service\LoggerInterface;
use CustomActivity\NewYearBundle\Infrastructure\Repository\InMemoryActivityRepository;
use CustomActivity\NewYearBundle\Infrastructure\Logger\FileLogger;
use CustomActivity\NewYearBundle\Infrastructure\Logger\WooCommerceLogger;
use CustomActivity\NewYearBundle\Infrastructure\Logger\CompositeLogger;
use CustomActivity\NewYearBundle\Application\UseCase\ApplyActivitiesUseCase;
use CustomActivity\NewYearBundle\Application\Service\GiftManagerService;
use CustomActivity\NewYearBundle\Presentation\Hook\CartHookHandler;
use CustomActivity\NewYearBundle\Presentation\Hook\ProductPageHookHandler;
use CustomActivity\NewYearBundle\Presentation\View\ActivityNoticeRenderer;

/**
 * 簡易依賴注入容器
 * 負責建立和管理所有服務實例
 */
final class Container
{
    private array $services = [];

    /**
     * 取得服務（單例模式）
     */
    public function get(string $serviceName)
    {
        if (!isset($this->services[$serviceName])) {
            $this->services[$serviceName] = $this->create($serviceName);
        }

        return $this->services[$serviceName];
    }

    /**
     * 建立服務實例
     */
    private function create(string $serviceName)
    {
        switch ($serviceName) {
            // Logger (Infrastructure)
            case LoggerInterface::class:
                return new CompositeLogger([
                    new FileLogger(),
                    new WooCommerceLogger()
                ]);

            // Repositories
            case ActivityRepositoryInterface::class:
                return new InMemoryActivityRepository();

            // Domain Services
            case ActivityDetectionService::class:
                return new ActivityDetectionService();

            // Application Services
            case GiftManagerService::class:
                return new GiftManagerService();

            case ApplyActivitiesUseCase::class:
                return new ApplyActivitiesUseCase(
                    $this->get(ActivityRepositoryInterface::class),
                    $this->get(ActivityDetectionService::class),
                    $this->get(GiftManagerService::class),
                    $this->get(LoggerInterface::class)
                );

            // Presentation
            case ActivityNoticeRenderer::class:
                return new ActivityNoticeRenderer();

            case CartHookHandler::class:
                return new CartHookHandler(
                    $this->get(ApplyActivitiesUseCase::class)
                );

            case ProductPageHookHandler::class:
                return new ProductPageHookHandler(
                    $this->get(ActivityRepositoryInterface::class),
                    $this->get(ActivityDetectionService::class),
                    $this->get(ActivityNoticeRenderer::class)
                );

            default:
                throw new \Exception("Service not found: {$serviceName}");
        }
    }
}

