<?php

namespace CustomActivity\NewYearBundle\Infrastructure\Repository;

use CustomActivity\NewYearBundle\Domain\Entity\Activity;
use CustomActivity\NewYearBundle\Domain\Repository\ActivityRepositoryInterface;
use CustomActivity\NewYearBundle\Domain\ValueObject\ProductCategory;

/**
 * 記憶體內活動倉儲
 * 儲存所有活動定義（硬編碼配置）
 */
final class InMemoryActivityRepository implements ActivityRepositoryInterface
{
    private ?array $activities = null;

    /**
     * 取得所有活動（依優先級排序）
     *
     * @return Activity[]
     */
    public function getAllActivities(): array
    {
        if ($this->activities === null) {
            $this->activities = $this->buildActivities();
        }

        return $this->activities;
    }

    /**
     * 根據 key 取得活動
     */
    public function getActivityByKey(string $key): ?Activity
    {
        $activities = $this->getAllActivities();

        foreach ($activities as $activity) {
            if ($activity->getKey() === $key) {
                return $activity;
            }
        }

        return null;
    }

    /**
     * 取得與商品相關的活動
     *
     * @return Activity[]
     */
    public function getRelatedActivities(int $productId, int $variationId): array
    {
        $category = ProductCategory::fromProductIds($productId, $variationId);
        $allActivities = $this->getAllActivities();
        $related = [];

        foreach ($allActivities as $activity) {
            $requirements = $activity->getRequirements();

            // 檢查活動是否需要此商品類別
            if (isset($requirements[$category->getCategory()])) {
                $related[] = $activity;
            }
        }

        return $related;
    }

    /**
     * 建立所有活動定義
     *
     * @return Activity[]
     */
    private function buildActivities(): array
    {
        return [
            // 活動7: 床墊+床架+枕頭*2 → 天絲四件組+茸茸被（最高價值）
            new Activity(
                'activity_7',
                '嗜睡床墊+床架+催眠枕*2，送天絲四件組床包+茸茸被',
                '購買嗜睡床墊、床架和催眠枕×2，即可獲得天絲四件組床包和茸茸被',
                1,
                [
                    ProductCategory::SPRING_MATTRESS => 1,
                    ProductCategory::BED_FRAME => 1,
                    ProductCategory::HYPNOTIC_PILLOW => 2,
                ],
                ['bedding_set', 'fleece_blanket']
            ),

            // 活動6: 床墊+床架 → 側睡枕
            new Activity(
                'activity_6',
                '嗜睡床墊+床架，送側睡枕',
                '購買嗜睡床墊和床架，即可獲得側睡枕',
                2,
                [
                    ProductCategory::SPRING_MATTRESS => 1,
                    ProductCategory::BED_FRAME => 1,
                ],
                ['side_pillow']
            ),

            // 活動5: 床墊+催眠枕*2+賴床墊 → 天絲四件組
            new Activity(
                'activity_5',
                '嗜睡床墊+催眠枕*2+賴床墊，送天絲四件組床包',
                '購買嗜睡床墊、催眠枕×2和賴床墊，即可獲得天絲四件組床包',
                3,
                [
                    ProductCategory::SPRING_MATTRESS => 1,
                    ProductCategory::HYPNOTIC_PILLOW => 2,
                    ProductCategory::LAI_MATTRESS => 1,
                ],
                ['bedding_set']
            ),

            // 活動4: 賴床墊 → 抱枕+眼罩
            new Activity(
                'activity_4',
                '賴床墊送抱枕+眼罩',
                '購買賴床墊，即可獲得抱枕和眼罩',
                4,
                [
                    ProductCategory::LAI_MATTRESS => 1,
                ],
                ['hug_pillow', 'eye_mask']
            ),

            // 活動3: 枕頭*2 → $8888+天絲枕套*2
            new Activity(
                'activity_3',
                '催眠枕任選2顆特價$8,888+天絲枕套2個',
                '購買任意2個催眠枕，即享特價$8,888，再贈天絲枕套2個',
                5,
                [
                    ProductCategory::HYPNOTIC_PILLOW => 2,
                ],
                ['pillowcase', 'pillowcase']
            ),

            // 活動2: 催眠枕 → 買一送一+天絲枕套
            new Activity(
                'activity_2',
                '催眠枕買一送一，送天絲枕套',
                '購買催眠枕，即可獲得相同枕頭和天絲枕套（買一送一）',
                6,
                [
                    ProductCategory::HYPNOTIC_PILLOW => 1,
                ],
                ['same_pillow', 'pillowcase']
            ),

            // 活動1: 床墊+催眠枕 → 茸茸被
            new Activity(
                'activity_1',
                '嗜睡床墊+催眠枕，送茸茸被',
                '購買嗜睡床墊和催眠枕，即可獲得茸茸被',
                7,
                [
                    ProductCategory::SPRING_MATTRESS => 1,
                    ProductCategory::HYPNOTIC_PILLOW => 1,
                ],
                ['fleece_blanket']
            ),
        ];
    }
}
