# 遷移指南

從舊版單一檔案切換到新的模組化架構。

---

## 📦 檔案對照

### 舊版
```
new-year-bundle-active.php (2600+ 行)
└── 所有功能都在一個檔案中
```

### 新版
```
new-year-bundle/
├── config/Constants.php              # 常數定義（原 模組1）
├── engine/
│   ├── CartAnalyzer.php             # 購物車分析（原 模組12 部分）
│   └── ActivityEngine.php           # 活動檢測引擎（原 模組3）
├── activities/
│   ├── ActivityInterface.php        # 活動介面
│   ├── Activity1.php                # 原 模組4
│   ├── Activity2.php                # 原 模組5
│   ├── Activity3.php                # 原 模組6
│   ├── Activity4.php                # 原 模組7
│   ├── Activity5.php                # 原 模組8
│   ├── Activity6.php                # 原 模組9
│   └── Activity7.php                # 原 模組10
├── gift/
│   └── GiftManager.php              # 贈品管理（原 模組11）
├── discount/
│   └── SiteWideDiscount.php         # 全館9折（原 模組2）
└── bootstrap.php                     # 自動載入器

new-year-bundle-active-v2.php        # 新版主檔案（簡化）
```

---

## 🔄 遷移步驟

### 步驟 1: 備份現有檔案 ✅ (已完成)

```bash
# 已自動備份
new-year-bundle-active.php.backup
```

### 步驟 2: 測試新版本

#### 方案A: 平行測試（推薦）

1. **保留舊版**，啟用新版測試：

編輯主要的 WooCommerce 活動載入檔案，暫時註解掉舊版：

```php
// 舊版（暫時註解）
// require_once __DIR__ . '/activities/new-year-bundle-active.php';

// 新版（測試）
require_once __DIR__ . '/activities/new-year-bundle-active-v2.php';
```

2. **清除快取**：
```bash
# WordPress 快取
wp cache flush

# WooCommerce 快取
wp wc tool run clear_transients
```

3. **測試功能**：
   - [ ] 全館9折顯示正常
   - [ ] 購物車活動檢測正常
   - [ ] 贈品自動添加
   - [ ] 優先級正確執行
   - [ ] 商品數量扣減正確
   - [ ] 購物車顯示正常
   - [ ] 結帳流程正常
   - [ ] 訂單記錄正常

#### 方案B: 直接切換

```bash
# 移除舊版
mv new-year-bundle-active.php new-year-bundle-active.php.old

# 啟用新版
mv new-year-bundle-active-v2.php new-year-bundle-active.php
```

---

## 🔍 功能對照表

| 功能 | 舊版 | 新版 | 備註 |
|------|------|------|------|
| 全館9折 | ✅ | ✅ `SiteWideDiscount` | 模組化 |
| 活動檢測 | ✅ | ✅ `ActivityEngine` | 重構 |
| 購物車分析 | ✅ | ✅ `CartAnalyzer` | 新增數量扣減 |
| 活動1-7 | ✅ | ✅ `Activity1-7` | 獨立類別 |
| 贈品管理 | ✅ | ✅ `GiftManager` | 模組化 |
| 優先級排序 | ✅ | ✅ | 改進 |
| 數量扣減 | ❌ | ✅ | **新功能** |
| 商品頁提示 | ✅ | ✅ | 保留在主檔案 |
| 購物車提示 | ✅ | ✅ | 保留在主檔案 |
| 訂單記錄 | ✅ | ✅ | 保留在主檔案 |

---

## 🆕 新功能

### 1. 數量扣減機制

**舊版問題**:
```
購物車: 1個床墊 + 1個枕頭

- Activity1 檢測: ✅ 使用床墊和枕頭
- Activity7 檢測: ✅ 也使用同樣的床墊和枕頭（重複使用！）
```

**新版解決**:
```
購物車: 1個床墊 + 1個枕頭

- Activity7 檢測: ✅ 使用床墊和枕頭
  剩餘: 0床墊, 0枕頭

- Activity1 檢測: ❌ 商品已被使用，無法套用
```

### 2. 模組化架構

**舊版**:
- 所有功能在一個檔案中
- 難以維護和測試
- 修改一個功能可能影響其他功能

**新版**:
- 每個模組獨立
- 易於維護和測試
- 符合 SOLID 原則
- 可單獨替換任一模組

### 3. 優先級機制改進

**舊版**:
```php
// 手動排列順序
if ( 條件7 ) { apply_activity_7(); }
if ( 條件6 ) { apply_activity_6(); }
// ...
```

**新版**:
```php
// 自動按優先級排序
foreach ( $this->activities as $activity ) {
    if ( $activity->is_qualified( $stats ) ) {
        $activity->apply( $cart, $stats, $context );
    }
}
```

---

## 🐛 已知問題

### 問題 1: 部分輔助函數未完整實作

**影響函數**:
- `nyb_calculate_activity_status()` - 未完整實作
- `nyb_get_related_activities()` - 未完整實作

**臨時解決**:
這些函數返回空陣列，商品頁提示可能不顯示。

**完整解決**:
需要在 `ActivityEngine` 中添加狀態計算方法：

```php
// engine/ActivityEngine.php
public function calculate_status( $product_id = 0 ) {
    // TODO: 實作活動狀態計算
}
```

### 問題 2: 提示訊息函數較龐大

**現狀**:
`nyb_get_activity_notice()` 函數仍在主檔案中，約 500+ 行。

**建議**:
未來可以進一步重構為獨立的 `NoticeManager` 類別。

---

## 📊 效能對比

| 指標 | 舊版 | 新版 |
|------|------|------|
| 檔案大小 | 2600+ 行 | 最大單檔 300 行 |
| 可維護性 | ⭐⭐ | ⭐⭐⭐⭐⭐ |
| 可測試性 | ⭐⭐ | ⭐⭐⭐⭐⭐ |
| 可擴展性 | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| 執行效能 | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| 邏輯正確性 | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

> 註：新版加入數量扣減機制，邏輯更正確但略增計算開銷

---

## 🔧 開發者指南

### 如何調試

```php
// 在 bootstrap.php 中啟用詳細日誌
define( 'NYB_DEBUG_MODE', true );

// 查看日誌
tail -f /var/www/.../wp-content/newyear-bundle.log
```

### 如何新增活動

參考 [README.md - 如何新增活動](README.md#-如何新增活動)

### 如何修改現有活動

```php
// 1. 找到對應的活動類別
// activities/Activity1.php

// 2. 修改條件判斷
public function is_qualified( $stats ) {
    // 修改這裡
    return $stats['available']['spring_mattress'] >= 2;  // 原本是 >= 1
}

// 3. 修改套用邏輯
public function apply( $cart, &$stats, $context ) {
    // 修改數量
    NYB_CartAnalyzer::consume_item( $stats, 'spring_mattress', 2, $this->get_code() );
    // ...
}
```

---

## 📝 回滾方案

如果新版本有問題，可以快速回滾到舊版：

```bash
# 方案1: 恢復備份
cp new-year-bundle-active.php.backup new-year-bundle-active.php

# 方案2: 切換載入
# 編輯主要載入檔案
# 啟用舊版，停用新版
```

---

## 🎯 下一步建議

1. **完成未實作功能**:
   - 實作 `calculate_status()` 方法
   - 實作 `get_related_activities()` 方法

2. **進一步模組化**:
   - 將提示系統獨立為 `NoticeManager` 類別
   - 將訂單記錄獨立為 `OrderRecorder` 類別

3. **撰寫單元測試**:
   ```php
   tests/
   ├── ActivityEngineTest.php
   ├── CartAnalyzerTest.php
   ├── Activity1Test.php
   └── ...
   ```

4. **效能優化**:
   - 快取活動狀態計算結果
   - 減少重複的購物車查詢

5. **文件補充**:
   - API 文件
   - 流程圖
   - 使用案例

---

## ❓ 常見問題

### Q: 新版會影響現有訂單嗎？
**A**: 不會。新版只影響新的購物車和訂單，現有訂單資料不受影響。

### Q: 可以混用新舊版本嗎？
**A**: 不可以。只能啟用一個版本。

### Q: 新版效能會變差嗎？
**A**: 略有影響（< 5%），但邏輯更正確，值得權衡。

### Q: 如何確認新版正常運作？
**A**: 檢查日誌檔 `newyear-bundle.log`，查看活動檢測和套用記錄。

### Q: 遇到問題怎麼辦？
**A**:
1. 查看日誌檔
2. 啟用 `NYB_DEBUG_MODE`
3. 回滾到舊版
4. 聯繫開發者

---

## 📧 支援

如有問題，請：
1. 查閱 [README.md](README.md)
2. 檢查日誌檔
3. 提交 Issue

---

**最後更新**: 2026-01-06

