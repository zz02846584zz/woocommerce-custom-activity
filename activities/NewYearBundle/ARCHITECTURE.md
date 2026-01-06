# 架構設計文件

## 系統架構圖

### 整體架構（Clean Architecture）

```
┌─────────────────────────────────────────────────────────────┐
│                    Presentation Layer                        │
│  ┌──────────────────┐         ┌──────────────────┐         │
│  │  CartHookHandler │         │ProductPageHandler│         │
│  └────────┬─────────┘         └────────┬─────────┘         │
│           │                             │                    │
│           │  ┌────────────────────────┐ │                   │
│           └─▶│ActivityNoticeRenderer  │◀┘                   │
│              └────────────────────────┘                      │
└───────────────────────┬─────────────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────────────┐
│                   Application Layer                          │
│  ┌──────────────────────────┐  ┌──────────────────────┐    │
│  │ ApplyActivitiesUseCase   │  │  GiftManagerService  │    │
│  └────────┬─────────────────┘  └──────────────────────┘    │
│           │                                                  │
└───────────┼──────────────────────────────────────────────────┘
            │
┌───────────▼──────────────────────────────────────────────────┐
│                      Domain Layer                             │
│  ┌─────────────────┐  ┌──────────────────────────────┐      │
│  │     Entity      │  │      Value Object            │      │
│  │  - Activity     │  │  - ActivityStatus            │      │
│  │  - CartItem     │  │  - ProductCategory           │      │
│  └─────────────────┘  └──────────────────────────────┘      │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐    │
│  │         ActivityDetectionService                     │    │
│  │  - calculateStatus()                                 │    │
│  │  - occupyItems()                                     │    │
│  │  - canApplyActivity()                                │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐    │
│  │         Repository Interface                         │    │
│  │  - ActivityRepositoryInterface                       │    │
│  │  - CartRepositoryInterface                           │    │
│  └─────────────────────────────────────────────────────┘    │
└───────────────────────┬───────────────────────────────────────┘
                        │
┌───────────────────────▼───────────────────────────────────────┐
│                  Infrastructure Layer                          │
│  ┌────────────────────────────┐  ┌─────────────────────────┐ │
│  │ InMemoryActivityRepository │  │WooCommerceCartAdapter   │ │
│  └────────────────────────────┘  └─────────────────────────┘ │
└────────────────────────────────────────────────────────────────┘
```

## 資料流向

### 1. 購物車計算流程

```
WooCommerce Hook (woocommerce_before_calculate_totals)
    │
    ▼
CartHookHandler::handleCartCalculation()
    │
    ▼
WooCommerceCartAdapter (轉換為 Domain Entity)
    │
    ▼
ApplyActivitiesUseCase::execute()
    │
    ├─▶ ActivityRepository::getAllActivities()
    │
    ├─▶ ActivityDetectionService::canApplyActivity()
    │       │
    │       └─▶ ActivityDetectionService::calculateStatus()
    │
    ├─▶ ActivityDetectionService::occupyItems()
    │
    └─▶ GiftManagerService::addGiftsForActivity()
```

### 2. 商品頁提示流程

```
WooCommerce Hook (woocommerce_before_single_product)
    │
    ▼
ProductPageHookHandler::showSmartNotice()
    │
    ├─▶ ActivityRepository::getRelatedActivities()
    │
    ├─▶ WooCommerceCartAdapter::getItemsByCategory()
    │
    ├─▶ ActivityDetectionService::calculateStatus()
    │
    └─▶ ActivityNoticeRenderer::renderProductPageNotices()
```

## 核心類別職責

### Domain Layer

#### Entity

**Activity（活動實體）**
```php
- key: string              // 活動代碼
- name: string             // 活動名稱
- description: string      // 活動描述
- priority: int            // 優先級（數字越小優先級越高）
- requirements: array      // 活動條件 ['category' => count]
- gifts: array             // 贈品列表

+ isQualified(): bool      // 檢查是否符合條件
+ getMissingItems(): array // 取得缺少的商品
```

**CartItem（購物車商品實體）**
```php
- cartItemKey: string      // 購物車項目鍵
- productId: int           // 商品 ID
- variationId: int         // 變體 ID
- quantity: int            // 數量
- price: float             // 價格
- isGift: bool             // 是否為贈品
- occupiedBy: ?string      // 被哪個活動佔用

+ occupy(activityKey): void    // 佔用商品
+ release(): void              // 釋放商品
+ isOccupied(): bool           // 是否已被佔用
```

#### Value Object

**ActivityStatus（活動狀態）**
```php
- status: string           // qualified/almost/not_qualified
- missing: array           // 缺少的商品

+ qualified(): self        // 建立「已符合」狀態
+ almost(missing): self    // 建立「差一點」狀態
+ notQualified(missing): self  // 建立「不符合」狀態
+ isQualified(): bool
+ toArray(): array
```

**ProductCategory（商品分類）**
```php
+ fromProductIds(productId, variationId): self  // 從商品 ID 判斷分類
+ isSpringMattress(): bool
+ isLaiMattress(): bool
+ isHypnoticPillow(): bool
+ isBedFrame(): bool
```

#### Service

**ActivityDetectionService（活動檢測服務）**
```php
+ calculateStatus(activity, categorizedItems): ActivityStatus
    // 計算活動符合狀態

+ occupyItems(items, activityKey, count): CartItem[]
    // 佔用指定數量的商品

+ canApplyActivity(activity, categorizedItems): bool
    // 檢查是否可以套用活動
```

### Application Layer

**ApplyActivitiesUseCase（套用活動用例）**
```php
+ execute(cartAdapter): array
    // 執行活動檢測與套用流程
    // 回傳已套用的活動列表
```

**GiftManagerService（贈品管理服務）**
```php
+ addGiftsForActivity(activity, cartAdapter, categorizedItems): void
    // 為活動加入贈品

+ removeInvalidGifts(cartAdapter, appliedActivities): void
    // 移除不再符合條件的贈品
```

### Infrastructure Layer

**InMemoryActivityRepository（記憶體內活動倉儲）**
```php
+ getAllActivities(): Activity[]
    // 取得所有活動（依優先級排序）

+ getActivityByKey(key): ?Activity
    // 根據 key 取得活動

+ getRelatedActivities(productId, variationId): Activity[]
    // 取得與商品相關的活動
```

**WooCommerceCartAdapter（WooCommerce 購物車適配器）**
```php
+ getAllItems(): CartItem[]
    // 取得所有購物車商品（轉換為 Domain Entity）

+ getItemsByCategory(): array<string, CartItem[]>
    // 取得分類後的購物車商品

+ addItem(productId, quantity, variationId, customData): ?string
    // 加入商品到購物車
```

## 活動檢測邏輯

### 互斥模式（Mutex Mode）

活動按優先級檢測，商品一旦被某活動佔用，就不能被其他活動使用。

```
活動優先級（數字越小優先級越高）：
1. activity_7: 床墊+床架+枕頭*2 → 天絲四件組+茸茸被
2. activity_6: 床墊+床架 → 側睡枕
3. activity_5: 床墊+催眠枕*2+賴床墊 → 天絲四件組
4. activity_4: 賴床墊 → 抱枕+眼罩
5. activity_3: 枕頭*2 → $8888+天絲枕套*2
6. activity_2: 催眠枕 → 買一送一+天絲枕套
7. activity_1: 床墊+催眠枕 → 茸茸被
```

### 檢測流程

```
1. 重置所有商品的佔用狀態
2. 收集購物車商品並分類
3. 按優先級遍歷所有活動：
   a. 檢查未佔用的商品是否符合活動條件
   b. 如果符合：
      - 佔用所需商品
      - 加入贈品
      - 記錄已套用的活動
   c. 如果不符合：繼續下一個活動
4. 移除不再符合條件的贈品
```

### 範例場景

**購物車內容：**
- 嗜睡床墊 x1
- 床架 x1
- 催眠枕 x2

**檢測結果：**
1. 檢測 activity_7：✅ 符合（床墊+床架+枕頭*2）
   - 佔用：床墊、床架、枕頭*2
   - 贈品：天絲四件組、茸茸被
2. 檢測 activity_6：❌ 不符合（床墊和床架已被佔用）
3. 檢測 activity_5：❌ 不符合（床墊和枕頭已被佔用）
4. 檢測 activity_4：❌ 不符合（無賴床墊）
5. 檢測 activity_3：❌ 不符合（枕頭已被佔用）
6. 檢測 activity_2：❌ 不符合（枕頭已被佔用）
7. 檢測 activity_1：❌ 不符合（床墊和枕頭已被佔用）

**最終套用：** activity_7

## 設計模式

### 1. Repository Pattern（倉儲模式）
- 抽象資料存取邏輯
- 介面定義在 Domain，實作在 Infrastructure
- 易於替換資料來源（記憶體 → 資料庫）

### 2. Adapter Pattern（適配器模式）
- `WooCommerceCartAdapter` 將 WooCommerce 資料轉換為 Domain 物件
- 隔離外部依賴，Domain 不直接依賴 WooCommerce

### 3. Service Layer Pattern（服務層模式）
- `ActivityDetectionService` 封裝複雜業務邏輯
- `GiftManagerService` 封裝贈品管理邏輯

### 4. Dependency Injection（依賴注入）
- 使用 `Container` 管理依賴
- 建構子注入，提高可測試性

### 5. Singleton Pattern（單例模式）
- `Bootstrap` 使用單例模式
- `Container` 內的服務採用單例模式

### 6. Strategy Pattern（策略模式）
- 不同活動有不同的檢測策略
- 透過 `Activity` 實體封裝策略

## 效能考量

### 1. Hash Map 查詢
```php
// O(n) 查詢
in_array($id, $array)

// O(1) 查詢
isset($hashMap[$id])
```

### 2. 物件重用
- 服務實例在 Container 中重用
- 避免重複建立物件

### 3. 延遲載入
- PSR-4 自動載入，只載入需要的類別
- 活動定義延遲建立

### 4. 快取機制
- 活動列表快取在記憶體中
- Hash Map 快取在 `CampaignConfig`

## 測試策略

### 單元測試範例

```php
class ActivityDetectionServiceTest extends TestCase
{
    public function testCalculateStatusQualified()
    {
        $service = new ActivityDetectionService();
        $activity = new Activity(
            'test',
            'Test Activity',
            'Description',
            1,
            ['spring_mattress' => 1],
            []
        );

        $items = [
            'spring_mattress' => [
                new CartItem('key_0', 1324, 2735, 1, 10000, false)
            ]
        ];

        $status = $service->calculateStatus($activity, $items);

        $this->assertTrue($status->isQualified());
    }
}
```

## 擴展點

### 1. 新增活動
修改 `InMemoryActivityRepository::buildActivities()`

### 2. 新增商品分類
1. 在 `ProductCategory` 新增常數
2. 在 `CampaignConfig` 新增商品 ID
3. 在 `ProductCategory::fromProductIds()` 新增判斷邏輯

### 3. 新增贈品類型
在 `GiftManagerService::addGift()` 新增 case

### 4. 替換資料來源
實作 `ActivityRepositoryInterface`，例如：
```php
class DatabaseActivityRepository implements ActivityRepositoryInterface
{
    public function getAllActivities(): array
    {
        // 從資料庫讀取活動
    }
}
```

## 向後相容策略

1. **保留全域函數：** 所有 `nyb_*` 函數保留
2. **保留 Hook 註冊：** UI 相關 Hook 保留在入口檔案
3. **漸進式遷移：** 核心邏輯先遷移，UI 邏輯逐步遷移
4. **雙版本並存：** 舊版和新版可同時存在，方便回滾

## 未來改進

1. **事件驅動架構：** 引入 Domain Events
2. **CQRS：** 讀寫分離
3. **快取層：** Redis 快取活動狀態
4. **非同步處理：** 使用佇列處理複雜計算
5. **監控與日誌：** 結構化日誌與效能監控
