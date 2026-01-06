<?php

namespace CustomActivity\NewYearBundle\Domain\ValueObject;

use CustomActivity\NewYearBundle\Config\CampaignConfig;

/**
 * 商品分類值物件
 * 判斷商品屬於哪個分類
 */
final class ProductCategory
{
    public const SPRING_MATTRESS = 'spring_mattress';
    public const LAI_MATTRESS = 'lai_mattress';
    public const HYPNOTIC_PILLOW = 'hypnotic_pillow';
    public const BED_FRAME = 'bed_frame';
    public const UNKNOWN = 'unknown';

    private string $category;

    private function __construct(string $category)
    {
        $this->category = $category;
    }

    public static function fromProductIds(int $productId, int $variationId): self
    {
        $checkId = $variationId !== 0 ? $variationId : $productId;

        // 嗜睡床墊
        if (isset(CampaignConfig::getHashMap('spring_mattress_vars')[$checkId]) ||
            isset(CampaignConfig::getHashMap('spring_mattress_parent')[$productId])) {
            return new self(self::SPRING_MATTRESS);
        }

        // 賴床墊
        if (isset(CampaignConfig::getHashMap('lai_mattress_vars')[$checkId]) ||
            isset(CampaignConfig::getHashMap('lai_mattress_parent')[$productId])) {
            return new self(self::LAI_MATTRESS);
        }

        // 催眠枕
        if (isset(CampaignConfig::getHashMap('hypnotic_pillow_vars')[$checkId]) ||
            $productId === CampaignConfig::HYPNOTIC_PILLOW_PARENT) {
            return new self(self::HYPNOTIC_PILLOW);
        }

        // 床架
        if (isset(CampaignConfig::getHashMap('bed_frame_ids')[$checkId]) ||
            $productId === CampaignConfig::BED_FRAME_PARENT) {
            return new self(self::BED_FRAME);
        }

        return new self(self::UNKNOWN);
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function isSpringMattress(): bool
    {
        return $this->category === self::SPRING_MATTRESS;
    }

    public function isLaiMattress(): bool
    {
        return $this->category === self::LAI_MATTRESS;
    }

    public function isHypnoticPillow(): bool
    {
        return $this->category === self::HYPNOTIC_PILLOW;
    }

    public function isBedFrame(): bool
    {
        return $this->category === self::BED_FRAME;
    }

    public function isUnknown(): bool
    {
        return $this->category === self::UNKNOWN;
    }

    public function equals(self $other): bool
    {
        return $this->category === $other->category;
    }
}
