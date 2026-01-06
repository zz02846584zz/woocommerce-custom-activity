<?php
/**
 * 虛擬商品適配器
 *
 * 包裝現有的 NYB_Virtual_Bedding_Product 類別
 * 提供領域層需要的虛擬床包商品操作介面
 */

namespace NewYearBundle\Infrastructure\External;

class VirtualProductAdapter
{
    /**
     * 初始化虛擬商品功能
     */
    public function init(): void
    {
        // 確保舊類別已載入
        if (!class_exists('NYB_Virtual_Bedding_Product')) {
            return;
        }

        // 初始化舊類別
        \NYB_Virtual_Bedding_Product::init();
    }

    /**
     * 添加虛擬床包商品到購物車
     *
     * @param \WC_Cart $cart 購物車對象
     * @param int $mattressVarId 床墊變體ID
     * @param string $activityType 活動類型
     * @return string|false 購物車項目key或false
     */
    public function addToCart(\WC_Cart $cart, int $mattressVarId, string $activityType)
    {
        if (!class_exists('NYB_Virtual_Bedding_Product')) {
            return false;
        }

        return \NYB_Virtual_Bedding_Product::add_to_cart($cart, $mattressVarId, $activityType);
    }

    /**
     * 從購物車移除虛擬床包商品
     *
     * @param \WC_Cart $cart 購物車對象
     * @param string $activityType 活動類型
     */
    public function removeFromCart(\WC_Cart $cart, string $activityType): void
    {
        if (!class_exists('NYB_Virtual_Bedding_Product')) {
            return;
        }

        \NYB_Virtual_Bedding_Product::remove_from_cart($cart, $activityType);
    }
}

