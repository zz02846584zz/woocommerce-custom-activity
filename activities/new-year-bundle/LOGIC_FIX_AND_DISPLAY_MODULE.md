# 邏輯修正與 Display 模組實作報告

## 執行日期
2026-01-06

## 階段一：修正條件判斷邏輯缺陷

### 問題診斷

**原始問題**：`ActivityEngine::calculate_status()` 使用原始購物車數量判斷，未採用扣減機制，導致顯示與實際執行結果不一致。

#### 錯誤場景範例

購物車：1 嗜睡床墊 + 2 催眠枕 + 1 賴床墊

| 活動 | 優先級 | 舊版 calculate_status | 實際 execute 結果 |
|------|--------|----------------------|------------------|
| Activity5 | 3 | ✅ qualified | ✅ 套用（消耗 1 床墊 + 2 枕 + 1 賴床墊） |
| Activity1 | 7 | ✅ qualified | ❌ **無法套用**（商品已被 Activity5 消耗） |

**影響**：前端誤導使用者，顯示可獲得優惠但結賬時無法兌現。

### 解決方案

#### 核心變更

**檔案**：`new-year-bundle/engine/ActivityEngine.php`

**方法**：`calculate_status()`

**變更內容**：

1. **引入臨時 available 陣列**
   ```php
   $temp_available = $stats['available'];
   ```

2. **按優先級順序檢查**
   ```php
   foreach ( $this->activities as $activity ) {
       if ( $activity->is_qualified( $temp_stats ) ) {
           // 模擬扣減
           $this->simulate_consume( $activity, $temp_available, $stats );
       }
   }
   ```

3. **新增輔助方法**
   - `simulate_consume()` - 模擬活動消耗數量
   - `calculate_missing_items()` - 計算缺少的項目
   - `determine_status()` - 判斷 almost/not_qualified
   - `get_activity_key_by_code()` - 代碼轉鍵名

#### 修正後行為

| 功能 | 舊版 | 新版 |
|------|------|------|
| 判斷依據 | `$stats['spring_mattress_count']` | `$stats['available']['spring_mattress']` |
| 優先級考量 | ❌ 無 | ✅ 按 priority 順序檢查 |
| 數量扣減 | ❌ 不扣減 | ✅ 逐步扣減 |
| 與 execute 一致性 | ❌ 不一致 | ✅ 完全一致 |

---

## 階段二：實作 Display 模組

### 目標

將 `helpers/class-activity-coupon-display.php` 模組化到 `new-year-bundle/display/` 目錄，遵循 SOLID 原則。

### 新增檔案

#### 1. `new-year-bundle/display/CouponDisplay.php`

**職責**：
- 創建虛擬優惠券（僅用於顯示）
- 根據活動狀態自動同步優惠券
- 自訂優惠券顯示 HTML
- 禁止使用者移除活動優惠券
- 隱藏優惠券套用成功訊息
- 輸出 CSS 樣式

**依賴**：
- `NYB_ActivityEngine` - 透過建構子注入

**公開方法**：
```php
__construct( NYB_ActivityEngine $engine )
init()
create_virtual_coupon( $data, $code )
sync_coupons( $cart )
render_coupon_html( $html, $coupon, $discount_amount_html )
prevent_removal( $can_remove, $code )
hide_success_message( $message, $message_code, $coupon )
output_styles()
```

**常數定義**：
```php
const ACTIVITY_COUPON_MAP = [
    'activity_1' => 'nyb_activity_1',
    // ... activity_2 ~ 7
];

const ACTIVITY_NAMES = [
    'nyb_activity_1' => '嗜睡床墊+催眠枕，送茸茸被',
    // ... 其他活動名稱
];
```

### 更新檔案

#### 1. `new-year-bundle/bootstrap.php`

**新增載入**：
```php
require_once $base_dir . '/display/CouponDisplay.php';
```

**新增初始化**：
```php
$nyb_coupon_display = new NYB_CouponDisplay( $nyb_engine );
$nyb_coupon_display->init();
```

#### 2. `new-year-bundle-active-v2.php`

**移除引用**：
```php
// 移除
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'helpers/class-activity-coupon-display.php';
NYB_Activity_Coupon_Display::init();
```

**保留**：
```php
// 僅保留虛擬床包商品
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'helpers/class-virtual-bedding-product.php';
NYB_Virtual_Bedding_Product::init();
```

---

## 架構改進

### SOLID 原則體現

| 原則 | 體現方式 |
|------|---------|
| **S** 單一職責 | `CouponDisplay` 僅負責優惠券顯示相關邏輯 |
| **O** 開放封閉 | 透過 `ACTIVITY_COUPON_MAP` 和 `ACTIVITY_NAMES` 常數擴展 |
| **L** 里氏替換 | - |
| **I** 介面隔離 | - |
| **D** 依賴倒置 | 依賴 `NYB_ActivityEngine` 抽象，透過建構子注入 |

### 模組化效益

| 項目 | 改進 |
|------|------|
| **可維護性** | 優惠券邏輯集中於單一類別 |
| **可測試性** | 可獨立測試 `CouponDisplay` |
| **可擴展性** | 新增活動只需更新常數定義 |
| **一致性** | 與其他模組架構保持一致 |

---

## 舊檔案處置建議

### 可移除檔案

**路徑**：`helpers/class-activity-coupon-display.php`

**原因**：功能已完全由 `new-year-bundle/display/CouponDisplay.php` 取代

**移除前檢查**：
```bash
# 確認無其他引用
grep -r "class-activity-coupon-display.php" /var/www/demo.soulmatt.com.tw/htdocs/wp-content/plugins/custom-activity/
grep -r "NYB_Activity_Coupon_Display" /var/www/demo.soulmatt.com.tw/htdocs/wp-content/plugins/custom-activity/
```

**建議步驟**：
1. 備份舊檔案
2. 在測試環境驗證功能
3. 確認無引用後移除

---

## 測試檢查清單

### 功能測試

- [ ] 購物車中符合條件時自動顯示活動優惠券
- [ ] 優惠券顯示正確的活動名稱和樣式
- [ ] 無法手動移除活動優惠券
- [ ] 套用優惠券時不顯示成功訊息
- [ ] 有外部優惠券時，活動優惠券自動移除
- [ ] 移除商品後，不符合條件的優惠券自動消失

### 邏輯一致性測試

**測試場景 1**：
- 購物車：1 嗜睡床墊 + 2 催眠枕 + 1 賴床墊
- 預期：Activity5 套用，Activity1/Activity3 不顯示為 qualified

**測試場景 2**：
- 購物車：2 嗜睡床墊 + 2 催眠枕
- 預期：可同時套用 Activity1 和其他活動（數量充足）

**測試場景 3**：
- 購物車：1 嗜睡床墊 + 1 催眠枕
- 預期：Activity1 qualified，Activity5 almost

### 效能測試

- [ ] 快取機制正常運作（購物車未變更時使用快取）
- [ ] 扣減模擬不影響實際購物車商品

---

## 語法檢查結果

```bash
✅ new-year-bundle-active-v2.php - 無語法錯誤
✅ new-year-bundle/bootstrap.php - 無語法錯誤
✅ new-year-bundle/display/CouponDisplay.php - 無語法錯誤
✅ new-year-bundle/engine/ActivityEngine.php - 無語法錯誤
```

---

## 檔案清單

### 新增檔案
- `new-year-bundle/display/CouponDisplay.php` (308 行)

### 修改檔案
- `new-year-bundle/engine/ActivityEngine.php` (新增 180+ 行)
- `new-year-bundle/bootstrap.php` (新增 4 行)
- `new-year-bundle-active-v2.php` (移除 3 行)

### 建議移除檔案
- `helpers/class-activity-coupon-display.php` (205 行)

---

## 下一步建議

1. **測試驗證**
   - 在測試環境執行功能測試
   - 驗證邏輯一致性測試場景

2. **效能監控**
   - 觀察 `calculate_status()` 執行時間
   - 確認快取機制有效性

3. **文檔更新**
   - 更新 `README.md` 包含 display 模組說明
   - 更新 `STRUCTURE.txt` 反映新架構

4. **舊檔案清理**
   - 備份 `class-activity-coupon-display.php`
   - 確認無引用後移除

---

## 總結

| 項目 | 狀態 |
|------|------|
| ✅ 邏輯缺陷修正 | 完成 |
| ✅ Display 模組實作 | 完成 |
| ✅ 語法檢查 | 通過 |
| ⏳ 功能測試 | 待執行 |
| ⏳ 舊檔案清理 | 待執行 |

**完成時間**：2026-01-06
**修改行數**：新增 ~490 行，修改 ~110 行，移除 3 行

