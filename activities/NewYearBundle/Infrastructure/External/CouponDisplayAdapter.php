<?php
/**
 * 優惠券顯示適配器
 *
 * 包裝現有的 NYB_Activity_Coupon_Display 類別
 * 作為外部依賴的適配層
 */

namespace NewYearBundle\Infrastructure\External;

class CouponDisplayAdapter
{
    /**
     * 初始化優惠券顯示功能
     */
    public function init(): void
    {
        // 確保舊類別已載入
        if (!class_exists('NYB_Activity_Coupon_Display')) {
            return;
        }

        // 初始化舊類別
        \NYB_Activity_Coupon_Display::init();
    }
}

