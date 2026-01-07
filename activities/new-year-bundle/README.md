# 新年活動模組化架構

## 📋 概述

本專案將原本 2600+ 行的單一檔案重構為模組化架構，遵循 **SOLID** 和 **YAGNI** 原則。

### 設計原則

#### SOLID 原則
- **S** (Single Responsibility): 每個類別只負責一件事
- **O** (Open/Closed): 對擴展開放，對修改關閉
- **L** (Liskov Substitution): 可透過介面替換實作
- **I** (Interface Segregation): 介面專一化
- **D** (Dependency Inversion): 依賴抽象而非具體

#### YAGNI 原則
- You Aren't Gonna Need It - 不過度設計，保持簡潔

---

## 📁 目錄結構

```
new-year-bundle/
├── config/
│   └── Constants.php              # 常數定義
├── engine/
│   ├── CartAnalyzer.php          # 購物車分析器
│   └── ActivityEngine.php        # 活動引擎
├── activities/
│   ├── ActivityInterface.php     # 活動介面定義
│   ├── Activity1.php             # 活動1: 床墊+枕頭送茸茸被
│   ├── Activity2.php             # 活動2: 賴床墊送抱枕+眼罩
│   ├── Activity3.php             # 活動3: 枕頭組合特價$8888
│   ├── Activity4.php             # 活動4: 買枕頭送枕套
│   ├── Activity5.php             # 活動5: 大禮包送床包
│   ├── Activity6.php             # 活動6: 床墊+床架送側睡枕
│   └── Activity7.php             # 活動7: 終極組合
├── gift/
│   └── GiftManager.php           # 贈品管理器
├── discount/
│   └── SiteWideDiscount.php      # 全館9折管理器
└── bootstrap.php                 # 自動載入器
```

---

## 🎯 各模組職責

### 1. **Constants.php** - 常數管理
**職責**: 統一管理所有活動相關的常數定義

```php
- 活動期間設定
- 商品 ID 定義
- Hash Map 快取
- 活動期間檢查
- 日誌記錄
```

### 2. **CartAnalyzer.php** - 購物車分析器
**職責**: 分析購物車內容，提供商品統計和數量管理

```php
- analyze(): 分析購物車，計算可用數量
- consume_item(): 扣減商品數量（防止重複使用）
- find_gift_in_cart(): 查找購物車中的贈品
```

**核心創新**: 數量扣減機制
```php
$stats = [
    'available' => [
        'spring_mattress' => 2,  // 可用數量
        'hypnotic_pillow' => 3
    ],
    'usage' => [
        'bundle7' => [
            'spring_mattress' => 1,  // 已被活動7使用
            'hypnotic_pillow' => 2
        ]
    ]
];
```

### 3. **ActivityEngine.php** - 活動引擎
**職責**: 管理所有活動的檢測和套用

```php
- register_activities(): 註冊所有活動
- execute(): 執行活動檢測流程
- remove_invalid_gifts(): 移除不符合的贈品
- get_activity_by_code(): 根據代碼取得活動
```

**執行流程**:
1. 分析購物車內容
2. 按優先級依序檢查活動
3. 套用符合的活動並扣減數量
4. 移除不符合的贈品

### 4. **ActivityInterface.php** - 活動介面
**職責**: 定義活動的標準介面

```php
interface NYB_ActivityInterface {
    get_code()         // 活動代碼
    get_name()         // 活動名稱
    get_description()  // 活動描述
    get_priority()     // 優先級
    is_qualified()     // 是否符合資格
    apply()            // 套用活動
}
```

**基礎類別 NYB_ActivityBase**:
```php
- gift_exists(): 檢查贈品是否存在
- add_gift(): 添加贈品
- set_gifts_free(): 設定贈品為免費
```

### 5. **Activity1-7.php** - 各活動實作
**職責**: 實作具體的活動邏輯

每個活動類別:
- 繼承 `NYB_ActivityBase`
- 實作 `NYB_ActivityInterface` 介面
- 獨立的活動邏輯
- 可單獨測試和維護

### 6. **GiftManager.php** - 贈品管理器
**職責**: 管理贈品的顯示、排序和樣式

```php
- sort_cart_items(): 贈品排序（放在最後）
- inject_gift_separator_script(): 贈品分隔線
- add_gift_item_class(): 贈品樣式類別
- display_gift_original_price(): 顯示原價和免費標籤
- disable_gift_quantity_input(): 禁用贈品數量修改
```

### 7. **SiteWideDiscount.php** - 全館9折管理器
**職責**: 管理全館9折功能

```php
- apply_discount(): 套用9折
- apply_discount_sale(): 套用9折（促銷價）
- show_discount_badge(): 顯示9折標籤
```

---

## 🚀 如何新增活動

### 步驟1: 建立活動類別

```php
<?php
// activities/Activity8.php

class NYB_Activity8 extends NYB_ActivityBase {

    public function get_code() {
        return 'bundle8';
    }

    public function get_name() {
        return '新活動名稱';
    }

    public function get_description() {
        return '新活動描述';
    }

    public function get_priority() {
        return 8;  // 優先級（數字越小越優先）
    }

    public function is_qualified( $stats ) {
        // 檢查是否符合活動條件
        return $stats['available']['spring_mattress'] >= 1;
    }

    public function apply( $cart, &$stats, $context ) {
        // 扣減數量
        if ( ! NYB_CartAnalyzer::consume_item( $stats, 'spring_mattress', 1, $this->get_code() ) ) {
            return false;
        }

        // 添加贈品
        if ( ! $this->gift_exists( $cart, $this->get_code(), GIFT_PRODUCT_ID ) ) {
            $this->add_gift( $cart, GIFT_PRODUCT_ID, 1, 0, $this->get_code() );
        }

        // 設定贈品為免費
        $this->set_gifts_free( $cart, $this->get_code() );

        return true;
    }
}
```

### 步驟2: 註冊活動

編輯 `engine/ActivityEngine.php`:

```php
private function register_activities() {
    $this->activities = [
        // ... 現有活動
        new NYB_Activity8(),  // 新增
    ];

    // 自動按優先級排序
    usort( $this->activities, function( $a, $b ) {
        return $a->get_priority() - $b->get_priority();
    });
}
```

### 步驟3: 載入類別

編輯 `bootstrap.php`:

```php
require_once $base_dir . '/activities/Activity8.php';
```

完成！新活動會自動整合到系統中。

---

## 🔄 優先級機制

活動按優先級執行（數字越小越優先）:

| 優先級 | 活動 | 說明 |
|--------|------|------|
| 1 | Activity7 | 終極組合 |
| 2 | Activity6 | 床墊+床架 |
| 3 | Activity5 | 大禮包 |
| 4 | Activity3 | 枕頭特價 |
| 5 | Activity4 | 買枕頭送枕套 |
| 6 | Activity2 | 賴床墊 |
| 7 | Activity1 | 床墊+枕頭 |

**執行邏輯**:
```
購物車: 1個床墊 + 3個枕頭 + 1個床架

1. Activity7 檢查: ✓ 符合
   使用: 1床墊 + 2枕頭 + 1床架
   剩餘: 0床墊 + 1枕頭 + 0床架

2. Activity6 檢查: ✗ 不符合（床墊已用完）

3. Activity4 檢查: ✓ 符合
   使用: 1枕頭
   剩餘: 1枕頭

4. Activity1 檢查: ✗ 不符合（床墊已用完）
```

---

## 📊 數量扣減機制

### 問題
原本的設計會讓一個商品被多個活動重複使用，導致邏輯混亂。

### 解決方案
引入 `available` 和 `usage` 追蹤機制：

```php
// 初始狀態
$stats['available']['hypnotic_pillow'] = 3;  // 購物車有3個枕頭

// Activity7 使用2個
NYB_CartAnalyzer::consume_item( $stats, 'hypnotic_pillow', 2, 'bundle7' );
// $stats['available']['hypnotic_pillow'] = 1
// $stats['usage']['bundle7']['hypnotic_pillow'] = 2

// Activity4 使用1個
NYB_CartAnalyzer::consume_item( $stats, 'hypnotic_pillow', 1, 'bundle4' );
// $stats['available']['hypnotic_pillow'] = 0
// $stats['usage']['bundle4']['hypnotic_pillow'] = 1
```

---

## 🧪 測試建議

### 單元測試
```php
// 測試 Activity1
$activity = new NYB_Activity1();
$stats = ['available' => ['spring_mattress' => 1, 'hypnotic_pillow' => 1]];

// 應該符合資格
assert( $activity->is_qualified( $stats ) === true );

// 應該成功套用
$cart = WC()->cart;
$result = $activity->apply( $cart, $stats, [] );
assert( $result === true );

// 數量應該被扣減
assert( $stats['available']['spring_mattress'] === 0 );
assert( $stats['available']['hypnotic_pillow'] === 0 );
```

### 整合測試
```php
// 測試優先級機制
$cart = WC()->cart;
// 添加: 1床墊 + 2枕頭 + 1床架

$engine = new NYB_ActivityEngine();
$engine->execute( $cart );

// 驗證 Activity7 被套用
// 驗證其他活動沒有被套用（商品已用完）
```

---

## 📝 維護指南

### 修改現有活動
1. 找到對應的 `Activity{N}.php`
2. 修改 `is_qualified()` 或 `apply()` 方法
3. 測試變更

### 調整優先級
1. 修改活動的 `get_priority()` 方法
2. 系統會自動重新排序

### 新增常數
1. 編輯 `config/Constants.php`
2. 加入新常數定義
3. 更新 `get_hash_maps()` (如需 Hash Map)

### 修改贈品顯示
1. 編輯 `gift/GiftManager.php`
2. 修改相關方法

---

## 🔍 疑難排解

### 問題: 活動沒有被套用
**檢查**:
1. 活動期間是否正確？（`Constants::is_campaign_active()`）
2. 活動是否被註冊？（`ActivityEngine::register_activities()`）
3. 商品數量是否足夠？（檢查日誌）

### 問題: 商品被多個活動使用
**檢查**:
1. 確認有呼叫 `consume_item()`
2. 檢查數量扣減邏輯

### 問題: 贈品沒有顯示
**檢查**:
1. `GiftManager::init()` 是否被呼叫
2. 檢查購物車中是否有 `_nyb_auto_gift` meta

---

## 📚 參考資源

- [SOLID 原則](https://en.wikipedia.org/wiki/SOLID)
- [YAGNI 原則](https://en.wikipedia.org/wiki/You_aren%27t_gonna_need_it)
- [WooCommerce Hooks](https://woocommerce.com/document/introduction-to-hooks-actions-and-filters/)

---

## 📄 授權

© 2026 新年優惠活動系統

