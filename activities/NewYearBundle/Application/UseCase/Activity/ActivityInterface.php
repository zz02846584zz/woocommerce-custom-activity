<?php
/**
 * 活動介面
 *
 * Interface Segregation Principle：定義活動的基本行為
 * Open/Closed Principle：新活動實現此介面即可，無需修改現有代碼
 */

namespace NewYearBundle\Application\UseCase\Activity;

use NewYearBundle\Domain\Entity\CartSnapshot;
use NewYearBundle\Infrastructure\WooCommerce\CartAdapter;

interface ActivityInterface
{
    /**
     * 檢查是否符合活動資格
     */
    public function isEligible(CartSnapshot $snapshot): bool;

    /**
     * 應用活動（添加贈品、設置折扣等）
     */
    public function apply(CartAdapter $cartAdapter): void;

    /**
     * 獲取活動類型
     */
    public function getType(): string;

    /**
     * 獲取活動優先級（數字越小優先級越高）
     */
    public function getPriority(): int;
}

