# 新年活動系統 - Clean Architecture 重構版

## 架構概覽

本專案採用 **Clean Architecture** 架構，遵循 **SOLID** 和 **YAGNI** 原則，將原本 2073 行的義大利麵代碼重構為模組化、可測試、易維護的結構。

## 目錄結構

```
NewYearBundle/
├── Domain/                    # 領域層（核心業務邏輯）
│   ├── Entity/               # 實體
│   │   ├── Activity.php      # 活動實體
│   │   └── CartItem.php      # 購物車商品實體
│   ├── ValueObject/          # 值物件
│   │   ├── ActivityStatus.php    # 活動狀態
│   │   └── ProductCategory.php   # 商品分類
│   ├── Service/              # 領域服務
│   │   ├── ActivityDetectionService.php  # 活動檢測引擎
│   │   └── LoggerInterface.php           # Logger 介面
│   └── Repository/           # 倉儲介面
│       ├── ActivityRepositoryInterface.php
│       └── CartRepositoryInterface.php
│
├── Application/              # 應用層（用例協調）
│   ├── UseCase/             # 用例
│   │   └── ApplyActivitiesUseCase.php  # 套用活動用例（含日誌）
│   └── Service/             # 應用服務
│       └── GiftManagerService.php      # 贈品管理服務
│
├── Infrastructure/           # 基礎設施層（外部依賴）
│   ├── Repository/          # 倉儲實作
│   │   └── InMemoryActivityRepository.php
│   ├── Adapter/             # 適配器
│   │   └── WooCommerceCartAdapter.php  # WooCommerce 購物車適配器
│   └── Logger/              # 日誌實作
│       ├── FileLogger.php            # 檔案日誌
│       ├── WooCommerceLogger.php     # WooCommerce 日誌
│       ├── CompositeLogger.php       # 組合日誌
│       └── NullLogger.php            # 空日誌（測試用）
│
├── Presentation/            # 展示層（使用者介面）
│   ├── Hook/               # Hook 處理器
│   │   ├── CartHookHandler.php
│   │   └── ProductPageHookHandler.php
│   └── View/               # 視圖渲染器
│       └── ActivityNoticeRenderer.php
│
├── Config/                 # 配置層
│   └── CampaignConfig.php  # 活動配置（常數集中管理）
│
├── Autoloader.php          # PSR-4 自動載入器
├── Container.php           # 依賴注入容器
├── Bootstrap.php           # 應用程式引導器
├── README.md              # 本文件
├── ARCHITECTURE.md        # 架構設計文件
└── MIGRATION_GUIDE.md     # 遷移指南
```

## 架構分層說明

### 1. Domain Layer（領域層）

**職責：** 核心業務邏輯，不依賴任何外部框架

- **Entity（實體）：** 具有唯一識別的業務物件
  - `Activity`：活動實體，包含活動規則與贈品
  - `CartItem`：購物車商品實體，支援佔用狀態追蹤

- **ValueObject（值物件）：** 不可變的值類型
  - `ActivityStatus`：活動符合狀態（qualified/almost/not_qualified）
  - `ProductCategory`：商品分類判斷邏輯

- **Service（領域服務）：** 跨實體的業務邏輯
  - `ActivityDetectionService`：活動檢測引擎，計算活動符合狀態
  - `LoggerInterface`：日誌介面定義

- **Repository Interface（倉儲介面）：** 資料存取契約
  - 定義資料存取方法，具體實作由 Infrastructure 層提供

### 2. Application Layer（應用層）

**職責：** 協調領域物件，實現具體用例

- **UseCase（用例）：** 應用程式的核心流程
  - `ApplyActivitiesUseCase`：套用活動的完整流程（含日誌記錄）

- **Service（應用服務）：** 輔助用例執行
  - `GiftManagerService`：管理贈品的加入與移除

### 3. Infrastructure Layer（基礎設施層）

**職責：** 實作外部依賴與技術細節

- **Repository（倉儲實作）：** 實作領域層定義的介面
  - `InMemoryActivityRepository`：記憶體內活動倉儲

- **Adapter（適配器）：** 橋接外部系統
  - `WooCommerceCartAdapter`：將 WooCommerce 購物車轉換為領域物件

- **Logger（日誌實作）：** 日誌記錄系統
  - `FileLogger`：寫入檔案
  - `WooCommerceLogger`：使用 WooCommerce 日誌
  - `CompositeLogger`：組合多個 Logger
  - `NullLogger`：測試用空日誌

### 4. Presentation Layer（展示層）

**職責：** 處理使用者介面與 WordPress Hook

- **Hook（Hook 處理器）：** 註冊和處理 WooCommerce Hook
  - `CartHookHandler`：購物車相關 Hook
  - `ProductPageHookHandler`：商品頁相關 Hook

- **View（視圖渲染器）：** 負責 HTML 輸出
  - `ActivityNoticeRenderer`：活動提示渲染

### 5. Config Layer（配置層）

**職責：** 集中管理所有配置與常數

- `CampaignConfig`：活動配置類別，包含所有商品 ID、價格對應等

## 設計原則

### SOLID 原則

1. **Single Responsibility Principle（單一職責）**
   - 每個類別只負責一個功能
   - 例：`ActivityDetectionService` 只負責活動檢測邏輯

2. **Open/Closed Principle（開放封閉）**
   - 對擴展開放，對修改封閉
   - 例：新增活動只需修改 `InMemoryActivityRepository`

3. **Liskov Substitution Principle（里氏替換）**
   - 介面實作可互相替換
   - 例：`ActivityRepositoryInterface` 可替換為資料庫實作

4. **Interface Segregation Principle（介面隔離）**
   - 介面精簡，不強迫實作不需要的方法
   - 例：`CartRepositoryInterface` 只定義必要方法

5. **Dependency Inversion Principle（依賴反轉）**
   - 高層模組不依賴低層模組，都依賴抽象
   - 例：`ApplyActivitiesUseCase` 依賴 `ActivityRepositoryInterface` 而非具體實作

### YAGNI 原則

- 只實作當前需要的功能
- 避免過度設計與預測性編程
- 例：容器採用簡易實作，未引入複雜的 DI 框架

## 依賴流向

```
Presentation → Application → Domain ← Infrastructure
                                ↑
                              Config
```

- **依賴規則：** 內層不依賴外層
- **Domain Layer** 是核心，不依賴任何外部框架
- **Infrastructure Layer** 實作 Domain 定義的介面
- **Application Layer** 協調 Domain 與 Infrastructure
- **Presentation Layer** 依賴 Application 與 Infrastructure

## 使用方式

### 啟動應用程式

```php
require_once __DIR__ . '/NewYearBundle/Autoloader.php';

$autoloader = new \CustomActivity\NewYearBundle\Autoloader(__DIR__ . '/NewYearBundle');
$autoloader->register();

$app = \CustomActivity\NewYearBundle\Bootstrap::getInstance();
$app->boot();
```

### 取得服務實例

```php
$container = $app->getContainer();
$detectionService = $container->get(\CustomActivity\NewYearBundle\Domain\Service\ActivityDetectionService::class);
```

## 日誌記錄

### Logger 系統

系統整合了 Logger，用於活動檢測流程的日誌記錄：

- **LoggerInterface：** Domain 層定義的日誌介面
- **CompositeLogger：** 組合 FileLogger 和 WooCommerceLogger
- **日誌位置：** `/wp-content/newyear-bundle.log`

### 日誌範例

```
[2026-01-06 10:30:00] [INFO] ========== 新年活動檢測開始（互斥模式）==========
[2026-01-06 10:30:01] [DEBUG] [購物車統計] 嗜睡床墊:1, 賴床墊:0, 催眠枕:2, 床架:1
[2026-01-06 10:30:02] [INFO] [活動套用] activity_7 - 床墊+床架+催眠枕*2
[2026-01-06 10:30:03] [INFO] [已應用活動] bundle7
[2026-01-06 10:30:04] [INFO] ========== 新年活動檢測結束 ==========
```

### 開啟/關閉日誌

在 `Config/CampaignConfig.php` 中：

```php
public const DEBUG_MODE = true;  // 開啟日誌
public const DEBUG_MODE = false; // 關閉日誌（只記錄錯誤）
```

## 測試策略

### 單元測試

- **Domain Layer：** 純邏輯，易於測試
  ```php
  $status = $detectionService->calculateStatus($activity, $categorizedItems);
  $this->assertTrue($status->isQualified());
  ```

- **Application Layer：** 使用 Mock Repository
  ```php
  $mockRepo = $this->createMock(ActivityRepositoryInterface::class);
  $useCase = new ApplyActivitiesUseCase($mockRepo, ...);
  ```

### 整合測試

- 測試 Infrastructure 與 WooCommerce 的整合
- 測試 Hook 是否正確註冊

## 效能優化

1. **Hash Map 查詢：** 使用 `array_flip()` 實現 O(1) 查詢
2. **單例模式：** 服務實例重用，避免重複建立
3. **延遲載入：** 只在需要時載入類別
4. **條件日誌：** DEBUG 模式關閉時不執行日誌 I/O

## 向後相容

- 保留所有全域函數（`nyb_*`）
- 保留所有 Hook 註冊
- 舊代碼可無縫切換到新架構

## 擴展指南

### 新增活動

修改 `InMemoryActivityRepository::buildActivities()`：

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

### 新增商品分類

1. 在 `ProductCategory` 新增常數
2. 在 `CampaignConfig` 新增商品 ID
3. 在 `ProductCategory::fromProductIds()` 新增判斷邏輯

### 新增贈品類型

在 `GiftManagerService::addGift()` 新增 case

## 未來改進方向

1. **資料庫持久化：** 實作 `DatabaseActivityRepository`
2. **事件系統：** 引入領域事件（Domain Events）
3. **快取機制：** 加入 Redis 快取活動狀態
4. **測試覆蓋：** 建立完整的單元測試與整合測試
5. **日誌增強：** 完整整合 Logger 到所有服務

## 文件

- **架構設計：** [ARCHITECTURE.md](ARCHITECTURE.md)
- **遷移指南：** [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md)

## 授權

本專案為內部使用，版權歸屬於專案所有者。

