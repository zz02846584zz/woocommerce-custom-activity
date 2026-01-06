# 新年組合活動系統 - 更新日誌

## [1.1.0] - 2025-01-05

### ✅ 重構完成

#### 核心架構改進
- **規則引擎分離**：`class-campaign-rule-engine.php` 獨立處理規則邏輯
- **購物車監聽器**：`class-cart-campaign-listener.php` 統一事件處理
- **虛擬商品管理**：`class-virtual-bedding-product.php` 專職處理天絲床包
- **除錯工具**：`class-campaign-debugger.php` 提供管理員除錯面板

#### 優惠券顯示重構
- ❌ 移除：舊的虛擬優惠券機制（`nyb_activity_1` ~ `nyb_activity_7`）
- ❌ 移除：依賴 `nyb_calculate_activity_status()` 函數
- ✅ 新增：直接從規則引擎讀取 `nyb_matched_rules` Session
- ✅ 新增：在購物車/結帳頁面總計表格中顯示活動標籤
- ✅ 改進：統一樣式，淺橙色背景 + 紅色標籤

#### 三大核心機制實現

**1. 全館折扣互斥** ✅
- 自動檢測並移除全館折扣券
- 雙重攔截：套用時提示 + 計算前強制移除
- 支援白名單 + 智能判定（百分比折扣且無商品限制）

**2. 虛擬床包價值顯示** ✅
- 根據床墊尺寸顯示原價（劃線）
- 售價固定為 $0，標註「🎁 免費贈送」
- 整合規則引擎，自動傳遞床墊 variation_id

**3. 贈品鎖定機制** ✅
- 移除按鈕替換為「🎁 活動贈品」文字
- 數量輸入框替換為純文字顯示
- 自動同步：條件不符時自動移除

#### 額外功能

**活動建議系統** 🎁
- 購物車頁面顯示「再加購XX即可享受優惠」
- 智能分析購物車狀態，最多顯示3個建議
- 黃色提示框，醒目但不干擾

**活動結束清理** ⏰
- 活動期間外自動移除所有贈品
- 顯示友善提示訊息

**除錯工具** 🔧
- 管理員專用除錯面板
- 即時顯示購物車分析、符合規則、商品類型
- 一鍵清除贈品、重新驗證功能

---

## [1.0.0] - 2025-01-05 (初始版本)

### 功能
- 7個活動規則基礎實現
- 虛擬優惠券機制
- 基礎贈品發放

### 問題
- 規則邏輯分散在多處
- 依賴未定義函數 `nyb_calculate_activity_status()`
- 無全館折扣互斥機制
- 贈品可被手動移除
- 虛擬床包價值未顯示

---

## 架構對比

### 舊架構（1.0.0）
```
new-year-bundle-active.php
├── 所有規則邏輯混雜
├── 依賴外部函數
└── 無模組化設計
```

### 新架構（1.1.0）
```
new-year-bundle-active/
├── new-year-bundle-active.php          # 配置文件
└── helpers/
    ├── class-campaign-rule-engine.php  # 規則引擎（純邏輯）
    ├── class-cart-campaign-listener.php # 事件處理
    ├── class-virtual-bedding-product.php # 虛擬商品
    ├── class-activity-coupon-display.php # 優惠顯示
    └── class-campaign-debugger.php     # 除錯工具
```

---

## 資料流動變更

### 舊流程
```
購物車更新 → 計算函數 → 虛擬優惠券 → 顯示
              ↓ (未定義)
            ❌ 錯誤
```

### 新流程
```
購物車更新 → Cart_Listener → Rule_Engine → Session
                                              ↓
                        Activity_Display ← 讀取 Session
                                              ↓
                                          顯示標籤
```

---

## API 變更

### 移除的功能
- ❌ `nyb_calculate_activity_status()` - 未定義函數
- ❌ 虛擬優惠券 `nyb_activity_1` ~ `nyb_activity_7`
- ❌ `create_virtual_coupon()` - 不再需要
- ❌ `sync_coupons()` - 舊的同步邏輯

### 新增的 API
- ✅ `NYB_Campaign_Rule_Engine::validate_cart()` - 驗證購物車
- ✅ `NYB_Cart_Campaign_Listener::on_cart_updated()` - 購物車更新事件
- ✅ `NYB_Activity_Coupon_Display::display_activity_badges()` - 顯示活動標籤
- ✅ `NYB_Campaign_Debugger::display_debug_info()` - 除錯面板

### Session 資料結構
```php
// 舊版：無統一 Session
// 新版：
WC()->session->get( 'nyb_matched_rules' ) => [
    [
        'rule_name'   => 'rule_7',
        'priority'    => 70,
        'description' => '嗜睡床墊+枕*2+賴床墊，贈天絲床包+茸茸被',
        'gifts'       => [ ... ],
    ],
    // ...
]
```

---

## 升級指南

### 從 1.0.0 升級到 1.1.0

**步驟1：備份舊文件**
```bash
cp new-year-bundle-active.php new-year-bundle-active-backup.php
```

**步驟2：更新文件**
- 替換所有 `helpers/` 目錄下的文件
- 更新 `new-year-bundle-active.php` 主文件

**步驟3：移除舊優惠券**
```bash
# 如果有手動創建 nyb_activity_* 優惠券，請刪除
# 新版不再使用虛擬優惠券
```

**步驟4：清除購物車 Session**
```php
// 在 WordPress 後台執行（工具 → 站點健康 → 資訊）
WC()->session->set( 'nyb_matched_rules', [] );
```

**步驟5：測試**
- 開啟除錯模式：`NYB_DEBUG_MODE = true`
- 以管理員身份訪問購物車
- 檢查除錯面板是否正常顯示

---

## 已知問題與限制

### 待確認問題（見 README.md）
- 問題A：規則2價格覆寫範圍
- 問題B：虛擬床包訂單處理
- 問題C：贈品庫存管理
- 問題E：多規則贈品去重
- 問題F：價格覆寫持久性
- 問題G：枕套贈送邏輯
- 問題H：床架variation處理

### 已解決問題
- ✅ 問題D：活動期間外處理（已實現自動清理）

---

## 效能改進

### 查詢優化
- 使用 Hash Map (`array_flip`) 實現 O(1) 商品判定
- Session 快取規則驗證結果，避免重複計算

### Hook 優化
- 防止 `before_calculate_totals` 重複執行（`did_action` 檢查）
- 後台請求跳過（`is_admin` 檢查）

---

## 安全性改進

- ✅ 所有輸出使用 `esc_html()` / `esc_attr()` 轉義
- ✅ 除錯工具僅管理員可見（`current_user_can('manage_options')`）
- ✅ AJAX 端點權限檢查
- ✅ Session 資料驗證

---

## 文件更新

### 新增文件
- ✅ `README.md` - 完整架構文件（446行）
- ✅ `QUICK_REFERENCE.md` - 快速參考卡
- ✅ `CHANGELOG.md` - 本文件

### 更新文件
- ✅ 所有 PHP 文件添加 DocBlock 註解
- ✅ 關鍵邏輯添加內聯註釋

---

## 下一版本計劃（1.2.0）

### 計劃功能
- [ ] 活動規則可視化編輯器（後台介面）
- [ ] 贈品庫存預警系統
- [ ] 活動效果統計報表
- [ ] 多語言支援（WPML/Polylang）
- [ ] 規則 A/B 測試功能

### 優化方向
- [ ] 前端 AJAX 即時驗證（無需重新整理）
- [ ] 規則優先級拖拽排序
- [ ] 自訂活動標籤顏色/圖示
- [ ] 匯出/匯入規則配置

---

**維護者**: 開發團隊
**最後更新**: 2025-01-05
**版本**: 1.1.0

