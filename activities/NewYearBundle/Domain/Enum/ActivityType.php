<?php
/**
 * 活動類型枚舉
 *
 * 使用類常數模擬枚舉（PHP 8.1+ 可使用原生 enum）
 */

namespace NewYearBundle\Domain\Enum;

class ActivityType
{
    public const ACTIVITY_1 = 'bundle1'; // 嗜睡床墊任一張+催眠枕任一顆，再送茸茸被一件
    public const ACTIVITY_2 = 'bundle2'; // 買賴床墊，送抱枕+眼罩
    public const ACTIVITY_3 = 'bundle3'; // 枕頭任選2顆 $8888再加碼贈天絲枕套2個
    public const ACTIVITY_4 = 'bundle4'; // （買一送一），買催眠枕送天絲枕套一件
    public const ACTIVITY_5 = 'bundle5'; // 床墊+催眠枕*2+賴床墊，送天絲床包四件組（1床包+2枕套+1被套）
    public const ACTIVITY_6 = 'bundle6'; // 床墊+床架送側睡枕
    public const ACTIVITY_7 = 'bundle7'; // 床墊+床架+枕頭*2，送天絲床包四件組（1床包+2枕套+1被套）+ 兩用茸茸被

    /**
     * 獲取所有活動類型
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::ACTIVITY_1,
            self::ACTIVITY_2,
            self::ACTIVITY_3,
            self::ACTIVITY_4,
            self::ACTIVITY_5,
            self::ACTIVITY_6,
            self::ACTIVITY_7,
        ];
    }

    /**
     * 獲取活動名稱
     */
    public static function getName(string $type): string
    {
        $names = [
            self::ACTIVITY_1 => '嗜睡床墊任一張+催眠枕任一顆，送茸茸被',
            self::ACTIVITY_2 => '買賴床墊，送抱枕+眼罩',
            self::ACTIVITY_3 => '枕頭任選2顆 $8,888再加碼贈天絲枕套2個',
            self::ACTIVITY_4 => '買催眠枕送天絲枕套（買一送一）',
            self::ACTIVITY_5 => '床墊+催眠枕*2+賴床墊，送天絲床包四件組',
            self::ACTIVITY_6 => '床墊+床架送側睡枕',
            self::ACTIVITY_7 => '床墊+床架+枕頭*2，送天絲四件組床包+茸茸被',
        ];

        return $names[$type] ?? '未知活動';
    }

    /**
     * 獲取活動優先級（數字越小優先級越高）
     */
    public static function getPriority(string $type): int
    {
        $priorities = [
            self::ACTIVITY_7 => 1, // 終極組合優先級最高
            self::ACTIVITY_6 => 2,
            self::ACTIVITY_5 => 3,
            self::ACTIVITY_4 => 4,
            self::ACTIVITY_3 => 5,
            self::ACTIVITY_2 => 6,
            self::ACTIVITY_1 => 7,
        ];

        return $priorities[$type] ?? 999;
    }
}

