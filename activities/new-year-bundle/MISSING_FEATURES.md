# 模組化重構 - 缺失功能分析報告

## 📊 總覽

本報告詳細列出**原始檔案**與**重構後模組化架構**之間的功能差異。

---

## ✅ 已實現功能（100%）

### 核心引擎模組
- ✅ **購物車分析** (`CartAnalyzer::analyze()`)
- ✅ **數量扣減機制** (`CartAnalyzer::consume_item()`)
- ✅ **活動引擎** (`ActivityEngine::execute()`)
- ✅ **活動優先級排序** (`ActivityEngine::register_activities()`)
- ✅ **移除無效贈品** (`ActivityEngine::remove_invalid_gifts()`)

### 活動實作
- ✅ **Activity1-7** 完整實作（7個活動類別）
- ✅ 活動介面定義 (`ActivityInterface`)
- ✅ 活動基類 (`ActivityBase`)

### 贈品管理
- ✅ **贈品排序** (`GiftManager::sort_cart_items()`)
- ✅ **贈品分隔線** (`GiftManager::inject_gift_separator_script()`)
- ✅ **贈品樣式** (`GiftManager::gift_separator_styles()`)
- ✅ **贈品價格顯示** (`GiftManager::display_gift_original_price()`)
- ✅ **禁用數量修改** (`GiftManager::disable_gift_quantity_input()`)
- ✅ **訂單記錄** (`GiftManager::save_gift_meta_to_order_item()`)

### 折扣管理
- ✅ **全館9折** (`SiteWideDiscount::apply_discount()`)
- ✅ **折扣標籤** (`SiteWideDiscount::show_discount_badge()`)

### 配置管理
- ✅ **常數定義** (`Constants.php`)
- ✅ **Hash Map 快取**
- ✅ **活動期間檢查**
- ✅ **日誌函數**

---

## ❌ 缺失功能（需補充）

### 🔴 **高優先級缺失**

#### 1. **活動狀態計算函數** `nyb_calculate_activity_status()`
**原始位置**: `new-year-bundle-active.php` 第 228-339 行

**功能說明**:
- 計算所有活動的符合狀態 (qualified/almost/not_qualified)
- 支援靜態快取機制
- 根據 `product_id` 參數進行智慧判斷
- 返回每個活動的狀態和缺少的商品

**缺失影響**:
- ❌ 商品頁提示系統無法正常工作
- ❌ 購物車頁提示系統無法顯示
- ❌ 訂單記錄系統無法記錄活動狀態

**原始代碼邏輯**:
```php
function nyb_calculate_activity_status($product_id = 0) {
    // 靜態快取
    static $cached_status = null;
    static $cached_cart_hash = null;

    // 購物車 hash 計算
    $cart_hash = md5( serialize( $cart_contents ) );

    // 快取檢查
    if ( $cached_cart_hash === $cart_hash && $cached_status !== null ) {
        return $cached_status;
    }

    // 分析購物車
    $stats = nyb_analyze_cart_contents();

    // 計算每個活動的狀態
    $results = [];

    // Activity 1-7 的狀態判斷
    // qualified: 完全符合
    // almost: 差一點符合
    // not_qualified: 不符合

    return $results;
}
```

**目前實作**: 在 `bootstrap.php` 中返回空陣列 `[]`

---

#### 2. **相關活動篩選函數** `nyb_get_related_activities()`
**原始位置**: `new-year-bundle-active.php` 第 459-534 行

**功能說明**:
- 根據商品 ID 過濾相關活動
- 使用 Hash Map 快速判斷商品類型
- 返回與該商品相關的所有活動及優先級

**缺失影響**:
- ❌ 商品頁無法顯示相關活動提示
- ❌ 無法智慧推薦相關活動

**原始代碼邏輯**:
```php
function nyb_get_related_activities( $product_id, $variation_id = 0 ) {
    $all_status = nyb_calculate_activity_status();
    $related = [];

    $check_id = $variation_id != 0 ? $variation_id : $product_id;

    // 判斷商品屬於哪些活動

    // 賴床墊相關 → Activity 2, 5
    if ( isset( NYB_LAI_MATTRESS_VARS_MAP[ $check_id ] ) ) {
        $related[] = ['key' => 'activity_2', ...];
        $related[] = ['key' => 'activity_5', ...];
    }

    // 嗜睡床墊相關 → Activity 1, 5, 6, 7
    if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $check_id ] ) ) {
        $related[] = ['key' => 'activity_1', ...];
        // ...
    }

    // 催眠枕相關 → Activity 1, 3, 4, 5, 7
    // 床架相關 → Activity 6, 7

    // 按優先級排序
    usort( $related, function( $a, $b ) {
        return $a['priority'] - $b['priority'];
    });

    return $related;
}
```

**目前實作**: 在 `bootstrap.php` 中返回空陣列 `[]`

---

#### 3. **完整的活動提示訊息** `nyb_get_activity_notice()`
**原始位置**: `new-year-bundle-active.php` 第 611-868 行

**功能說明**:
- 為每個活動的每種狀態生成提示訊息
- 支援動態連結（商品頁連結）
- 使用閉包函數動態計算訊息內容
- 完整覆蓋 Activity 1-7 的所有狀態

**缺失影響**:
- ⚠️ 部分活動提示訊息不完整
- ⚠️ `new-year-bundle-active-v2.php` 中只實現了 Activity 1（第 434-474 行）

**原始完整定義**:
```php
$notices = [
    'activity_1' => [ qualified, almost, not_qualified ],
    'activity_2' => [ qualified, almost, not_qualified ],
    'activity_3' => [ qualified, almost, not_qualified ],
    'activity_4' => [ qualified, almost, not_qualified ],
    'activity_5' => [ qualified, almost, not_qualified ],
    'activity_6' => [ qualified, almost, not_qualified ],
    'activity_7' => [ qualified, almost, not_qualified ],
];
```

**目前實作**: 在 `new-year-bundle-active-v2.php` 中只有 Activity 1 的定義

---

### 🟡 **中優先級缺失**

#### 4. **購物車內容分析的完整統計**
**原始位置**: `new-year-bundle-active.php` 第 346-417 行

**功能說明**:
- 統計 `hypnotic_pillow_count:high` (高枕數量)
- 統計 `hypnotic_pillow_count:other` (其他枕數量)
- 詳細的枕頭變體統計

**缺失影響**:
- ⚠️ 無法區分高枕和其他枕頭
- ⚠️ 可能影響未來的枕頭相關功能

**已實現部分**: 基本統計在 `CartAnalyzer::analyze()` 中
**缺失部分**: 高枕和其他枕的區分統計

---

#### 5. **產品查找輔助函數** `nyb_find_gift_product_in_cart()`
**原始位置**: `new-year-bundle-active.php` 第 1316-1334 行

**功能說明**:
- 在購物車中查找指定產品的贈品
- 支援自定義 metadata key

**目前狀態**:
- ✅ 已在 `CartAnalyzer::find_gift_in_cart()` 中實現
- ⚠️ 但在 `bootstrap.php` 中未提供向後兼容函數

---

### 🟢 **低優先級缺失**

#### 6. **靜態快取機制優化**
**原始功能**:
- 使用靜態變數快取活動狀態計算結果
- 使用購物車 hash 判斷是否需要重新計算

**目前狀態**: 未實現快取機制

**影響**: 效能略微下降（每次都重新計算）

---

#### 7. **日誌記錄的詳細程度**
**原始功能**:
- 更詳細的日誌記錄
- 包含更多上下文資訊

**目前狀態**: 基本日誌已實現，但不如原版詳細

---

## 📋 功能對比表

| 功能模組 | 原始檔案 | 重構後 | 狀態 |
|---------|---------|--------|------|
| **核心引擎** | | | |
| 活動檢測 | ✅ | ✅ | 完整 |
| 數量扣減 | ❌ | ✅ | 新增 |
| 優先級排序 | ✅ | ✅ | 改進 |
| 活動狀態計算 | ✅ | ❌ | **缺失** |
| 相關活動篩選 | ✅ | ❌ | **缺失** |
| **活動實作** | | | |
| Activity 1-7 | ✅ | ✅ | 完整 |
| **前端顯示** | | | |
| 商品頁提示 | ✅ | ⚠️ | 部分功能 |
| 購物車提示 | ✅ | ⚠️ | 部分功能 |
| 活動訊息 | ✅ | ⚠️ | 不完整 |
| **贈品管理** | | | |
| 贈品顯示 | ✅ | ✅ | 完整 |
| 贈品控制 | ✅ | ✅ | 完整 |
| **訂單系統** | | | |
| 訂單記錄 | ✅ | ✅ | 完整 |
| 後台顯示 | ✅ | ✅ | 完整 |
| **輔助功能** | | | |
| Hash Map | ✅ | ✅ | 完整 |
| 靜態快取 | ✅ | ❌ | 缺失 |
| 日誌記錄 | ✅ | ⚠️ | 簡化版 |

---

## 🔧 建議實作優先順序

### Phase 1: 核心功能補完（必須）
1. ✅ 實作 `nyb_calculate_activity_status()` - **最高優先級**
2. ✅ 實作 `nyb_get_related_activities()` - **高優先級**
3. ✅ 補完 `nyb_get_activity_notice()` 所有活動定義 - **高優先級**

### Phase 2: 效能優化（建議）
4. ⚙️ 加入靜態快取機制
5. ⚙️ 補充高枕/其他枕的統計

### Phase 3: 細節完善（可選）
6. 📝 增強日誌記錄
7. 🧪 添加單元測試

---

## 💡 實作建議

### 建議1: 在 ActivityEngine 中新增方法

```php
// engine/ActivityEngine.php

class NYB_ActivityEngine {

    /**
     * 計算活動狀態
     * @param int $product_id 商品ID（用於智慧判斷）
     * @return array
     */
    public function calculate_status( $product_id = 0 ) {
        // 靜態快取
        static $cached_status = null;
        static $cached_cart_hash = null;

        $cart = WC()->cart;
        $cart_hash = md5( serialize( $cart->get_cart_contents() ) );

        // 快取檢查
        if ( $cached_cart_hash === $cart_hash && $cached_status !== null ) {
            return $cached_status;
        }

        // 分析購物車
        $stats = NYB_CartAnalyzer::analyze();
        $results = [];

        // 為每個活動計算狀態
        foreach ( $this->activities as $activity ) {
            $activity_code = str_replace( 'bundle', 'activity_', $activity->get_code() );

            if ( $activity->is_qualified( $stats ) ) {
                $results[ $activity_code ] = [
                    'status' => 'qualified',
                    'missing' => []
                ];
            } else {
                // 計算缺少的商品
                $missing = $this->calculate_missing_items( $activity, $stats );

                $results[ $activity_code ] = [
                    'status' => empty( $missing ) ? 'not_qualified' : 'almost',
                    'missing' => $missing
                ];
            }
        }

        // 快取結果
        $cached_status = $results;
        $cached_cart_hash = $cart_hash;

        return $results;
    }

    /**
     * 獲取相關活動
     * @param int $product_id
     * @param int $variation_id
     * @return array
     */
    public function get_related_activities( $product_id, $variation_id = 0 ) {
        $all_status = $this->calculate_status( $product_id );
        $related = [];
        $maps = NYB_Constants::get_hash_maps();

        $check_id = $variation_id != 0 ? $variation_id : $product_id;

        // 根據商品類型篩選相關活動
        foreach ( $this->activities as $activity ) {
            if ( $this->is_product_related_to_activity( $check_id, $product_id, $activity, $maps ) ) {
                $activity_code = str_replace( 'bundle', 'activity_', $activity->get_code() );

                if ( isset( $all_status[ $activity_code ] ) ) {
                    $related[] = [
                        'key' => $activity_code,
                        'data' => $all_status[ $activity_code ],
                        'priority' => $activity->get_priority()
                    ];
                }
            }
        }

        // 按優先級排序
        usort( $related, function( $a, $b ) {
            return $a['priority'] - $b['priority'];
        });

        return $related;
    }
}
```

### 建議2: 在 bootstrap.php 中更新向後兼容函數

```php
// bootstrap.php

function nyb_calculate_activity_status( $product_id = 0 ) {
    global $nyb_engine;

    if ( $nyb_engine ) {
        return $nyb_engine->calculate_status( $product_id );
    }

    return [];
}

function nyb_get_related_activities( $product_id, $variation_id = 0 ) {
    global $nyb_engine;

    if ( $nyb_engine ) {
        return $nyb_engine->get_related_activities( $product_id, $variation_id );
    }

    return [];
}
```

### 建議3: 新增 Display 模組

```php
// display/NoticeManager.php

class NYB_NoticeManager {

    /**
     * 獲取活動提示訊息（完整版）
     */
    public static function get_activity_notice( $activity_key, $status, $missing = [] ) {
        // 包含所有 Activity 1-7 的完整定義
        // 從原始檔案複製過來
    }
}
```

---

## 🎯 總結

### 核心缺失（必須補充）
1. ❌ `nyb_calculate_activity_status()` - **影響商品頁和購物車提示**
2. ❌ `nyb_get_related_activities()` - **影響商品頁智慧推薦**
3. ⚠️ `nyb_get_activity_notice()` - **不完整，只有 Activity 1**

### 次要缺失（建議補充）
4. ⚙️ 靜態快取機制 - **效能優化**
5. ⚙️ 高枕/其他枕統計 - **未來擴展性**

### 已正確實作
- ✅ 所有核心活動邏輯
- ✅ 數量扣減機制（新功能）
- ✅ 贈品管理系統
- ✅ 訂單記錄系統
- ✅ 折扣管理系統

---

## 📊 完成度評估

| 模組 | 完成度 |
|------|--------|
| 核心引擎 | 70% ⚠️ |
| 活動實作 | 100% ✅ |
| 前端顯示 | 40% ❌ |
| 贈品管理 | 100% ✅ |
| 訂單系統 | 100% ✅ |
| 折扣系統 | 100% ✅ |
| **總體** | **75%** |

---

**最後更新**: 2026-01-06
**文檔版本**: 1.0

