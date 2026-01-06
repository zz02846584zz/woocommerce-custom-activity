<?php

namespace CustomActivity\NewYearBundle\Domain\Repository;

use CustomActivity\NewYearBundle\Domain\Entity\Activity;

/**
 * 活動倉儲介面
 * 定義活動資料存取的契約
 */
interface ActivityRepositoryInterface
{
    /**
     * 取得所有活動（依優先級排序）
     * @return Activity[]
     */
    public function getAllActivities(): array;

    /**
     * 根據 key 取得活動
     */
    public function getActivityByKey(string $key): ?Activity;

    /**
     * 取得與商品相關的活動
     * @return Activity[]
     */
    public function getRelatedActivities(int $productId, int $variationId): array;
}
