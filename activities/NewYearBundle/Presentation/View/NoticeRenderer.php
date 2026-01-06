<?php
/**
 * 提示訊息渲染器
 *
 * 負責渲染活動提示訊息的 HTML
 */

namespace NewYearBundle\Presentation\View;

class NoticeRenderer
{
    /**
     * 渲染單個提示訊息
     */
    public function render(array $notice, string $status): void
    {
        if (empty($notice['message'])) {
            return;
        }

        $cssClass = $this->getWooCommerceCssClass($status);
        $bgStyle = $this->getBackgroundStyle($status);
        $borderStyle = $this->getBorderStyle($status);

        ?>
        <div class="<?php echo esc_attr($cssClass); ?>" style="margin-bottom: 15px; padding: 12px 15px; <?php echo esc_attr($bgStyle); ?> <?php echo esc_attr($borderStyle); ?>">
            <?php if (!empty($notice['title'])) : ?>
                <div style="font-weight: bold; margin-bottom: 5px;"><?php echo $notice['title']; ?></div>
            <?php endif; ?>
            <div data-missing="<?php echo esc_attr(json_encode($notice['missing'] ?? [])); ?>" style="font-size: 14px;">
                <?php echo $notice['message']; ?>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染多個提示訊息
     */
    public function renderMultiple(array $notices): void
    {
        foreach ($notices as $notice) {
            $status = $notice['type'] ?? 'info';
            $this->render($notice, $status);
        }
    }

    /**
     * 獲取 WooCommerce CSS 類別
     */
    private function getWooCommerceCssClass(string $status): string
    {
        return match($status) {
            'success' => 'woocommerce-info',
            'info' => 'woocommerce-message',
            'warning' => 'woocommerce-message',
            'error' => 'woocommerce-error',
            default => 'woocommerce-message'
        };
    }

    /**
     * 獲取背景樣式
     */
    private function getBackgroundStyle(string $status): string
    {
        return match($status) {
            'success' => 'background: #e8f5e9;',
            'info' => 'background: #fff3e0 !important;',
            'warning' => 'background: #fff3e0 !important;',
            'error' => 'background: #ffebee;',
            default => 'background: #fff3e0 !important;'
        };
    }

    /**
     * 獲取邊框樣式
     */
    private function getBorderStyle(string $status): string
    {
        return match($status) {
            'success' => 'border-left: 4px solid #4caf50 !important;',
            'info' => 'border-left: 4px solid #ff9800 !important;',
            'warning' => 'border-left: 4px solid #ff9800 !important;',
            'error' => 'border-left: 4px solid #f44336 !important;',
            default => 'border-left: 4px solid #ff9800 !important;'
        };
    }
}

