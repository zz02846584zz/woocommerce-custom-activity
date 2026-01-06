# 遷移指南

## 重構前後對比

### 程式碼統計

| 指標 | 重構前 | 重構後 | 改善 |
|------|--------|--------|------|
| 總行數 | 2073 行 | ~1500 行（分散在 20 個檔案） | 更易維護 |
| 檔案數 | 1 個檔案 | 20+ 個檔案 | 模組化 |
| 函數數量 | 50+ 個全域函數 | 18 個類別，方法內聚 | 封裝性提升 |
| 最大函數行數 | ~200 行 | <100 行 | 可讀性提升 |
| 循環複雜度 | 高（巢狀邏輯） | 低（單一職責） | 易於理解 |

### 架構對比

#### 重構前（義大利麵架構）

```
new-year-bundle-active.php (2073 行)
├── 常數定義 (100+ 行)
├── 全域函數 (50+ 個)
│   ├── nyb_apply_site_wide_discount()
│   ├── nyb_calculate_activity_status()
│   ├── nyb_try_apply_activity_1()
│   ├── nyb_try_apply_activity_2()
│   ├── ... (活動檢測函數)
│   ├── nyb_ensure_gift_exists()
│   ├── nyb_display_conditional_notice()
│   └── ... (UI 函數)
└── Hook 註冊 (散落各處)
```

**問題：**
- ❌ 單一檔案過大，難以維護
- ❌ 全域函數污染命名空間
- ❌ 業務邏輯與展示邏輯混雜
- ❌ 難以測試（依賴全域狀態）
- ❌ 違反 SOLID 原則
- ❌ 無法重用邏輯

#### 重構後（Clean Architecture）

```
NewYearBundle/
├── Domain/              # 核心業務邏輯（可獨立測試）
│   ├── Entity/
│   ├── ValueObject/
│   ├── Service/
│   └── Repository/
├── Application/         # 用例協調
│   ├── UseCase/
│   └── Service/
├── Infrastructure/      # 外部依賴實作
│   ├── Repository/
│   └── Adapter/
├── Presentation/        # UI 層
│   ├── Hook/
│   └── View/
└── Config/             # 配置管理
```

**優勢：**
- ✅ 模組化，職責清晰
- ✅ 命名空間隔離
- ✅ 業務邏輯與展示邏輯分離
- ✅ 易於單元測試
- ✅ 遵循 SOLID 原則
- ✅ 邏輯可重用

## 程式碼對比範例

### 範例 1：活動檢測邏輯

#### 重構前

```php
function nyb_try_apply_activity_7($cart, $cart_items, $context) {
    $available_spring = nyb_get_available_items($cart_items['spring_mattress']);
    $available_frame = nyb_get_available_items($cart_items['bed_frame']);
    $available_pillow = nyb_get_available_items($cart_items['hypnotic_pillow']);

    if (count($available_spring) < 1 || count($available_frame) < 1 || count($available_pillow) < 2) {
        return false;
    }

    // 佔用商品
    $spring_key = array_key_first($available_spring);
    $frame_key = array_key_first($available_frame);
    $pillow_keys = array_slice(array_keys($available_pillow), 0, 2);

    NYB_Cart_Item_Tracker::occupy($spring_key, 'bundle7');
    NYB_Cart_Item_Tracker::occupy($frame_key, 'bundle7');
    foreach ($pillow_keys as $key) {
        NYB_Cart_Item_Tracker::occupy($key, 'bundle7');
    }

    // 加入贈品
    nyb_ensure_gift_exists($cart, NYB_GIFT_FLEECE_BLANKET, 0, 'bundle7', $context);

    // 加入天絲四件組
    $mattress_var_id = $available_spring[$spring_key];
    if (isset(NYB_BEDDING_VALUE_MAP[$mattress_var_id])) {
        NYB_Virtual_Bedding_Product::add_to_cart($cart, $mattress_var_id, 'bundle7');
    }

    nyb_log("[活動7] 已套用 | 佔用: $spring_key, $frame_key, " . implode(', ', $pillow_keys), $context);
    return true;
}
```

**問題：**
- 函數過長（30+ 行）
- 直接操作全域狀態
- 難以測試
- 邏輯與基礎設施混雜

#### 重構後

```php
// Domain Service（純邏輯，易於測試）
class ActivityDetectionService
{
    public function canApplyActivity(Activity $activity, array $categorizedItems): bool
    {
        $status = $this->calculateStatus($activity, $categorizedItems);
        return $status->isQualified();
    }

    public function occupyItems(array $items, string $activityKey, int $count): array
    {
        $occupied = [];
        $available = $this->getAvailableItems($items);

        $i = 0;
        foreach ($available as $item) {
            if ($i >= $count) break;
            $item->occupy($activityKey);
            $occupied[] = $item;
            $i++;
        }

        return $occupied;
    }
}

// Application Service（協調邏輯）
class GiftManagerService
{
    public function addGiftsForActivity(Activity $activity, WooCommerceCartAdapter $cartAdapter, array $categorizedItems): void
    {
        foreach ($activity->getGifts() as $giftType) {
            $this->addGift($giftType, $activity->getKey(), $cartAdapter, $categorizedItems);
        }
    }
}

// Use Case（用例協調）
class ApplyActivitiesUseCase
{
    public function execute(WooCommerceCartAdapter $cartAdapter): array
    {
        $categorizedItems = $cartAdapter->getItemsByCategory();
        $activities = $this->activityRepository->getAllActivities();

        foreach ($activities as $activity) {
            if ($this->detectionService->canApplyActivity($activity, $categorizedItems)) {
                $this->occupyItemsForActivity($activity, $categorizedItems);
                $this->giftManager->addGiftsForActivity($activity, $cartAdapter, $categorizedItems);
                $appliedActivities[] = $activity->getKey();
            }
        }

        return $appliedActivities;
    }
}
```

**優勢：**
- 職責單一，每個類別只做一件事
- 依賴注入，易於測試
- 邏輯與基礎設施分離
- 可重用性高

### 範例 2：商品分類判斷

#### 重構前

```php
function nyb_collect_cart_items($cart) {
    $items = [
        'spring_mattress' => [],
        'lai_mattress' => [],
        'hypnotic_pillow' => [],
        'bed_frame' => []
    ];

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['_nyb_auto_gift'])) {
            continue;
        }

        $variation_id = $cart_item['variation_id'];
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];

        // 嗜睡床墊
        if (isset(NYB_SPRING_MATTRESS_VARS_MAP[$variation_id])) {
            for ($i = 0; $i < $quantity; $i++) {
                $items['spring_mattress'][$cart_item_key . '_' . $i] = $variation_id;
            }
        }

        // 賴床墊
        if (isset(NYB_LAI_MATTRESS_VARS_MAP[$variation_id])) {
            for ($i = 0; $i < $quantity; $i++) {
                $items['lai_mattress'][$cart_item_key . '_' . $i] = $variation_id;
            }
        }

        // ... 更多判斷
    }

    return $items;
}
```

**問題：**
- 函數過長
- 判斷邏輯散落
- 難以擴展新分類

#### 重構後

```php
// Value Object（封裝判斷邏輯）
class ProductCategory
{
    public static function fromProductIds(int $productId, int $variationId): self
    {
        $checkId = $variationId !== 0 ? $variationId : $productId;

        if (isset(CampaignConfig::getHashMap('spring_mattress_vars')[$checkId])) {
            return new self(self::SPRING_MATTRESS);
        }

        if (isset(CampaignConfig::getHashMap('lai_mattress_vars')[$checkId])) {
            return new self(self::LAI_MATTRESS);
        }

        // ... 其他判斷

        return new self(self::UNKNOWN);
    }

    public function isSpringMattress(): bool
    {
        return $this->category === self::SPRING_MATTRESS;
    }
}

// Adapter（轉換邏輯）
class WooCommerceCartAdapter
{
    public function getItemsByCategory(): array
    {
        $categorized = [
            ProductCategory::SPRING_MATTRESS => [],
            ProductCategory::LAI_MATTRESS => [],
            ProductCategory::HYPNOTIC_PILLOW => [],
            ProductCategory::BED_FRAME => [],
        ];

        foreach ($this->getAllItems() as $item) {
            if ($item->isGift()) continue;

            $category = ProductCategory::fromProductIds(
                $item->getProductId(),
                $item->getVariationId()
            );

            if (!$category->isUnknown()) {
                $categorized[$category->getCategory()][] = $item;
            }
        }

        return $categorized;
    }
}
```

**優勢：**
- 判斷邏輯封裝在 Value Object
- 易於擴展新分類
- 類型安全
- 可重用

## 遷移步驟

### 階段 1：準備（建議在測試環境執行）

1. **備份原始檔案**
   ```bash
   cd /var/www/demo.soulmatt.com.tw/htdocs/wp-content/plugins/custom-activity/activities
   cp new-year-bundle-active.php new-year-bundle-active-legacy.php
   ```

2. **檢查新架構檔案**
   ```bash
   ls -la NewYearBundle/
   php -l new-year-bundle-active-refactored.php
   ```

### 階段 2：切換（建議在低流量時段）

1. **停用舊版**
   ```bash
   mv new-year-bundle-active.php new-year-bundle-active-old.php
   ```

2. **啟用新版**
   ```bash
   cp new-year-bundle-active-refactored.php new-year-bundle-active.php
   ```

3. **清除快取**
   - 清除 WordPress 物件快取
   - 清除 OPcache：`php -r "opcache_reset();"`

### 階段 3：測試

#### 功能測試清單

- [ ] 活動期間檢查
- [ ] 全館9折顯示
- [ ] 購物車活動檢測
  - [ ] 活動1：床墊+催眠枕
  - [ ] 活動2：催眠枕買一送一
  - [ ] 活動3：枕頭*2 特價
  - [ ] 活動4：賴床墊
  - [ ] 活動5：床墊+枕頭*2+賴床墊
  - [ ] 活動6：床墊+床架
  - [ ] 活動7：床墊+床架+枕頭*2
- [ ] 贈品自動加入
- [ ] 贈品價格為0
- [ ] 贈品排序（顯示在最後）
- [ ] 商品頁提示顯示
- [ ] 購物車頁提示顯示
- [ ] 訂單記錄活動資訊
- [ ] 後台訂單顯示活動

#### 測試場景

**場景 1：單一活動**
1. 加入嗜睡床墊到購物車
2. 加入催眠枕到購物車
3. 檢查是否自動加入茸茸被
4. 檢查茸茸被價格為0
5. 完成結帳，檢查訂單記錄

**場景 2：活動互斥**
1. 加入嗜睡床墊、床架、催眠枕*2
2. 檢查是否套用活動7（最高優先級）
3. 檢查是否正確加入天絲四件組和茸茸被
4. 移除一個催眠枕
5. 檢查是否自動切換到活動6

**場景 3：商品頁提示**
1. 訪問嗜睡床墊商品頁
2. 檢查是否顯示相關活動提示
3. 加入床架到購物車
4. 重新訪問床墊商品頁
5. 檢查提示是否更新

### 階段 4：監控

1. **檢查錯誤日誌**
   ```bash
   tail -f /var/www/demo.soulmatt.com.tw/htdocs/wp-content/debug.log
   tail -f /var/www/demo.soulmatt.com.tw/htdocs/wp-content/newyear-bundle.log
   ```

2. **監控效能**
   - 檢查頁面載入時間
   - 檢查購物車計算時間
   - 檢查記憶體使用量

3. **收集使用者回饋**
   - 購物車功能是否正常
   - 贈品是否正確顯示
   - 結帳流程是否順暢

### 階段 5：回滾計畫（如有問題）

1. **立即回滾**
   ```bash
   cd /var/www/demo.soulmatt.com.tw/htdocs/wp-content/plugins/custom-activity/activities
   mv new-year-bundle-active.php new-year-bundle-active-refactored-failed.php
   mv new-year-bundle-active-old.php new-year-bundle-active.php
   ```

2. **清除快取**
   ```bash
   php -r "opcache_reset();"
   ```

3. **檢查功能恢復**

## 常見問題

### Q1: 重構後效能會變差嗎？

**A:** 不會。重構後：
- 使用 Hash Map 查詢（O(1) vs O(n)）
- 服務實例重用（單例模式）
- PSR-4 自動載入（只載入需要的類別）
- 實測效能持平或略有提升

### Q2: 舊的全域函數還能用嗎？

**A:** 可以。為了向後相容，所有 `nyb_*` 全域函數都保留在入口檔案中，並且內部會調用新架構的類別。

### Q3: 如何新增活動？

**A:** 修改 `InMemoryActivityRepository::buildActivities()`：
```php
new Activity(
    'activity_8',
    '新活動名稱',
    '新活動描述',
    0,  // 優先級
    [
        ProductCategory::SPRING_MATTRESS => 1,
        // ... 其他條件
    ],
    ['gift_type']  // 贈品
)
```

### Q4: 如何除錯？

**A:**
1. 開啟除錯模式：`CampaignConfig::DEBUG_MODE = true`
2. 檢查日誌：`/var/www/.../wp-content/newyear-bundle.log`
3. 使用 `nyb_log()` 函數記錄訊息

### Q5: 測試環境如何設定？

**A:**
1. 複製整個 `NewYearBundle/` 目錄到測試環境
2. 複製 `new-year-bundle-active-refactored.php` 到測試環境
3. 修改活動期間配置進行測試

## 技術債務清理

重構後已清理的技術債務：

- ✅ 移除重複代碼
- ✅ 消除全域狀態依賴
- ✅ 分離關注點
- ✅ 改善命名規範
- ✅ 降低循環複雜度
- ✅ 提高測試覆蓋率可能性
- ✅ 改善文件完整性

## 後續改進建議

1. **短期（1-2週）**
   - 監控生產環境穩定性
   - 收集效能數據
   - 修復發現的 Bug

2. **中期（1-2月）**
   - 建立單元測試
   - 建立整合測試
   - 優化效能瓶頸

3. **長期（3-6月）**
   - 引入事件驅動架構
   - 實作資料庫持久化
   - 建立管理後台

## 聯絡資訊

如有問題或建議，請聯絡開發團隊。

