<?php
/**
 * 活動4選擇器視圖
 *
 * 負責渲染活動4枕套選擇介面的HTML（顯示不同枕頭對應的枕套）
 */

namespace NewYearBundle\Presentation\View;

use NewYearBundle\Config;

class Activity4SelectorView
{
    /**
     * 渲染選擇器HTML
     */
    public function render(array $availablePillows, ?int $selectedPillow): void
    {
        $pillowcaseMap = Config::getPillowcaseMap();

        ?>
        <div class="nyb-activity4-selector">
            <div class="nyb-selector-header">
                <h3>🎁 買一送一活動 - 請選擇贈品</h3>
                <p>您購買了多種催眠枕，本活動只贈送1個配對天絲枕套，請選擇您要的枕套款式：</p>
            </div>

            <div class="nyb-selector-form">
                <div class="nyb-pillow-grid">
                    <?php foreach ($availablePillows as $varId => $pillowData) :
                        $pillowcaseName = '';
                        if (isset($pillowcaseMap[$varId])) {
                            $pillowcaseProduct = wc_get_product($pillowcaseMap[$varId]);
                            if ($pillowcaseProduct) {
                                $pillowcaseName = $pillowcaseProduct->get_name();
                            }
                        }
                        $isSelected = ($selectedPillow == $varId);
                        $pillowName = preg_replace('/,.*$/', '', $pillowData['name']);
                    ?>
                        <label class="nyb-pillow-card <?php echo $isSelected ? 'selected' : ''; ?>">
                            <input type="radio" name="nyb_pillow_selection" value="<?php echo esc_attr($varId); ?>" <?php checked($selectedPillow, $varId); ?>>
                            <div class="nyb-card-content">
                                <div class="nyb-check-icon">✓</div>
                                <div class="nyb-item-group">
                                    <span class="nyb-item-name pillow"><?php echo esc_html($pillowName); ?> + 枕套</span>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="nyb-action-row">
                    <button type="button" id="nyb-update-selection" class="button">
                        確認選擇
                    </button>
                    <span id="nyb-selection-message">
                        ✓ 已更新
                    </span>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var pillowcaseMap = <?php echo json_encode($pillowcaseMap); ?>;

            function initNybSelector() {
                $(document.body).off('change', '.nyb-pillow-card input[type="radio"]').on('change', '.nyb-pillow-card input[type="radio"]', function() {
                    $('.nyb-pillow-card').removeClass('selected');
                    if ($(this).is(':checked')) {
                        $(this).closest('.nyb-pillow-card').addClass('selected');
                    }
                });

                $(document.body).off('click', '#nyb-update-selection').on('click', '#nyb-update-selection', function() {
                    var button = $(this);
                    var message = $('#nyb-selection-message');
                    var selectedPillow = $('input[name="nyb_pillow_selection"]:checked').val();

                    if (!selectedPillow) {
                        alert('請先選擇一個組合');
                        return;
                    }

                    var selectedPillowcase = pillowcaseMap[selectedPillow] || '';

                    button.prop('disabled', true).text('更新中...');

                    $.ajax({
                        url: wc_cart_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'nyb_update_activity4_selection',
                            nonce: '<?php echo wp_create_nonce('nyb_activity4_selection'); ?>',
                            pillow: selectedPillow,
                            pillowcase: selectedPillowcase
                        },
                        success: function(response) {
                            if (response.success) {
                                message.fadeIn().delay(2000).fadeOut();
                                button.prop('disabled', false).text('確認選擇');
                                $(document.body).trigger('wc_update_cart');
                            } else {
                                alert('更新失敗，請重試');
                                button.prop('disabled', false).text('確認選擇');
                            }
                        },
                        error: function() {
                            alert('發生錯誤，請重試');
                            button.prop('disabled', false).text('確認選擇');
                        }
                    });
                });
            }

            initNybSelector();

            $(document.body).on('updated_cart_totals', function() {
                initNybSelector();
            });
        });
        </script>

        <style>
            .nyb-activity4-selector {
                margin: 20px 0;
                padding: 25px;
                background: #fff;
                border: 2px solid #df565f;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(223, 86, 95, 0.08);
            }
            .nyb-selector-header h3 {
                margin: 0 0 10px 0;
                color: #df565f;
                font-size: 18px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .nyb-selector-header p {
                margin: 0 0 20px 0;
                color: #666;
                font-size: 14px;
                line-height: 1.5;
            }
            .nyb-pillow-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 15px;
                margin-bottom: 25px;
            }
            .nyb-pillow-card {
                position: relative;
                display: block;
                padding: 15px;
                border: 2px solid #eee;
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.2s ease;
                background: #fff;
            }
            .nyb-pillow-card:hover {
                border-color: #df565f;
                background: #fff9f0;
            }
            .nyb-pillow-card.selected {
                border-color: #df565f;
                background: #fff9f0;
                box-shadow: 0 0 0 1px #df565f;
            }
            .nyb-pillow-card input[type="radio"] {
                position: absolute;
                opacity: 0;
                width: 0;
                height: 0;
            }
            .nyb-card-content {
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }
            .nyb-check-icon {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                border: 2px solid #ddd;
                display: flex;
                align-items: center;
                justify-content: center;
                color: transparent;
                font-weight: bold;
                flex-shrink: 0;
                transition: all 0.2s;
                background: #fff;
            }
            .nyb-pillow-card.selected .nyb-check-icon {
                background: #df565f;
                border-color: #df565f;
                color: white;
            }
            .nyb-item-group {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .nyb-item-name {
                font-size: 15px;
                color: #333;
                font-weight: 500;
                line-height: 1.4;
            }
            .nyb-item-name.pillow {
                color: #df565f;
                font-weight: bold;
            }
            .nyb-action-row {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            #nyb-update-selection {
                background: #df565f;
                color: white;
                padding: 12px 30px;
                border: none;
                border-radius: 6px;
                font-size: 15px;
                cursor: pointer;
                font-weight: bold;
                transition: background 0.2s;
            }
            #nyb-update-selection:hover {
                background: #c94a53;
            }
            #nyb-selection-message {
                display: none;
                color: #4caf50;
                font-weight: bold;
                font-size: 14px;
            }
            @media (max-width: 768px) {
                .nyb-activity4-selector {
                    padding: 15px;
                }
                .nyb-pillow-grid {
                    grid-template-columns: 1fr;
                }
                .nyb-pillow-card {
                    padding: 12px;
                }
                #nyb-update-selection {
                    width: 100%;
                }
                .nyb-action-row {
                    flex-direction: column;
                    gap: 10px;
                }
            }
        </style>
        <?php
    }
}

