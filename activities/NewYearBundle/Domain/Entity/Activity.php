<?php

namespace CustomActivity\NewYearBundle\Domain\Entity;

/**
 * 活動實體
 * 代表一個促銷活動及其規則
 */
final class Activity
{
    private string $key;
    private string $name;
    private string $description;
    private int $priority;
    private array $requirements;
    private array $gifts;

    public function __construct(
        string $key,
        string $name,
        string $description,
        int $priority,
        array $requirements,
        array $gifts
    ) {
        $this->key = $key;
        $this->name = $name;
        $this->description = $description;
        $this->priority = $priority;
        $this->requirements = $requirements;
        $this->gifts = $gifts;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getGifts(): array
    {
        return $this->gifts;
    }

    /**
     * 檢查是否符合活動條件
     */
    public function isQualified(array $availableItems): bool
    {
        foreach ($this->requirements as $type => $count) {
            $available = $availableItems[$type] ?? [];
            if (count($available) < $count) {
                return false;
            }
        }

        return true;
    }

    /**
     * 取得缺少的商品
     */
    public function getMissingItems(array $availableItems): array
    {
        $missing = [];

        foreach ($this->requirements as $type => $count) {
            $available = $availableItems[$type] ?? [];
            $currentCount = count($available);

            if ($currentCount < $count) {
                $missing[] = [
                    'type' => $type,
                    'required' => $count,
                    'current' => $currentCount,
                    'missing' => $count - $currentCount
                ];
            }
        }

        return $missing;
    }
}
