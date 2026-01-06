<?php
/**
 * 購物車分析服務
 *
 * Single Responsibility：只負責分析購物車內容並產生快照
 * 重構自原 nyb_analyze_cart_contents() 函數
 */

namespace NewYearBundle\Domain\Service;

use NewYearBundle\Domain\Entity\CartSnapshot;
use NewYearBundle\Infrastructure\WordPress\Logger;
use NewYearBundle\Config;

class CartAnalyzer
{
    public function __construct(
        private Logger $logger
    ) {}

    /**
     * 分析購物車內容
     *
     * @param \WC_Cart $cart WooCommerce 購物車對象
     * @return CartSnapshot 購物車快照
     */
    public function analyze(\WC_Cart $cart): CartSnapshot
    {
        // 初始化統計數據
        $springMattressCount = 0;
        $laiMattressCount = 0;
        $hypnoticPillowCount = 0;
        $hypnoticPillowCountOther = 0;
        $hypnoticPillowCountHigh = 0;
        $bedFrameCount = 0;
        $hypnoticPillowVars = [];
        $mattressVars = [];

        // Hash Maps for O(1) lookup
        $springMattressMap = Config::getSpringMattressVarsMap();
        $laiMattressMap = Config::getLaiMattressVarsMap();
        $hypnoticPillowMap = Config::getHypnoticPillowVarsMap();
        $bedFrameMap = Config::getBedFrameIdsMap();
        $bedFrameParent = Config::getBedFrameParent();

        foreach ($cart->get_cart() as $cartItem) {
            $productId = $cartItem['product_id'];
            $variationId = $cartItem['variation_id'];
            $quantity = $cartItem['quantity'];

            // 排除自動贈品
            if (isset($cartItem['_nyb_auto_gift'])) {
                continue;
            }

            // 統計嗜睡床墊
            if (isset($springMattressMap[$variationId])) {
                $springMattressCount += $quantity;
                $mattressVars[] = $variationId;
            }

            // 統計賴床墊
            if (isset($laiMattressMap[$variationId])) {
                $laiMattressCount += $quantity;
                $mattressVars[] = $variationId;
            }

            // 統計催眠枕
            if (isset($hypnoticPillowMap[$variationId])) {
                $hypnoticPillowCount += $quantity;

                // 區分高枕和其他枕
                if ($variationId == 2984) {
                    $hypnoticPillowCountHigh += $quantity;
                } else {
                    $hypnoticPillowCountOther += $quantity;
                }

                // 記錄每種枕頭的數量
                if (!isset($hypnoticPillowVars[$variationId])) {
                    $hypnoticPillowVars[$variationId] = 0;
                }
                $hypnoticPillowVars[$variationId] += $quantity;
            }

            // 統計床架
            if (isset($bedFrameMap[$variationId]) || $productId == $bedFrameParent) {
                $bedFrameCount += $quantity;
            }
        }

        $snapshot = new CartSnapshot(
            springMattressCount: $springMattressCount,
            laiMattressCount: $laiMattressCount,
            hypnoticPillowCount: $hypnoticPillowCount,
            hypnoticPillowCountOther: $hypnoticPillowCountOther,
            hypnoticPillowCountHigh: $hypnoticPillowCountHigh,
            bedFrameCount: $bedFrameCount,
            hypnoticPillowVars: $hypnoticPillowVars,
            mattressVars: $mattressVars
        );

        $this->logger->debug(sprintf(
            "[購物車分析] 床墊:%d, 嗜睡:%d, 賴:%d, 枕頭:%d, 床架:%d",
            $snapshot->getTotalMattressCount(),
            $snapshot->springMattressCount,
            $snapshot->laiMattressCount,
            $snapshot->hypnoticPillowCount,
            $snapshot->bedFrameCount
        ));

        return $snapshot;
    }

    /**
     * 從原始資料創建快照（用於測試或特殊情況）
     */
    public function createSnapshotFromArray(array $data): CartSnapshot
    {
        return new CartSnapshot(
            springMattressCount: $data['spring_mattress_count'] ?? 0,
            laiMattressCount: $data['lai_mattress_count'] ?? 0,
            hypnoticPillowCount: $data['hypnotic_pillow_count'] ?? 0,
            hypnoticPillowCountOther: $data['hypnotic_pillow_count_other'] ?? 0,
            hypnoticPillowCountHigh: $data['hypnotic_pillow_count_high'] ?? 0,
            bedFrameCount: $data['bed_frame_count'] ?? 0,
            hypnoticPillowVars: $data['hypnotic_pillow_vars'] ?? [],
            mattressVars: $data['mattress_vars'] ?? []
        );
    }
}

