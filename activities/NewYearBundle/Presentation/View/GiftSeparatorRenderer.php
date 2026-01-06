<?php
/**
 * 贈品分隔線渲染器
 *
 * 負責渲染購物車中的贈品分隔線
 */

namespace NewYearBundle\Presentation\View;

class GiftSeparatorRenderer
{
    /**
     * 渲染贈品分隔線JavaScript
     */
    public function renderScript(): void
    {
        static $scriptAdded = false;

        if ($scriptAdded) {
            return;
        }
        $scriptAdded = true;

        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function addGiftSeparator() {
                $('.nyb-gift-separator-row').remove();

                var firstGiftCart = $('.woocommerce-cart-form__cart-item.nyb-gift-item').first();
                if (firstGiftCart.length > 0) {
                    var separator = '<tr class="nyb-gift-separator-row">' +
                        '<td colspan="6" class="nyb-gift-separator" style="padding: 20px 0 15px 0; border-top: 2px dashed #ddd; border-bottom: none;">' +
                        '<div style="text-align: center; position: relative; margin-top: -10px;">' +
                        '<span style="background: #fff; padding: 5px 20px; color: #df565f; font-weight: bold; font-size: 14px; display: inline-block; border: 2px solid #df565f; border-radius: 20px;">' +
                        '🎁 以下為活動贈品' +
                        '</span>' +
                        '</div>' +
                        '</td>' +
                        '</tr>';

                    firstGiftCart.before(separator);
                }

                var firstGiftCheckout = $('.woocommerce-checkout-review-order-table .nyb-gift-item').first();
                if (firstGiftCheckout.length > 0) {
                    var checkoutSeparator = '<tr class="nyb-gift-separator-row">' +
                        '<td colspan="3" class="nyb-gift-separator" style="padding: 15px 0 10px 0; border-top: 2px dashed #ddd; border-bottom: none;">' +
                        '<div style="text-align: center;">' +
                        '<span style="background: #fff; padding: 4px 15px; color: #df565f; font-weight: bold; font-size: 13px; display: inline-block; border: 2px solid #df565f; border-radius: 15px;">' +
                        '🎁 活動贈品' +
                        '</span>' +
                        '</div>' +
                        '</td>' +
                        '</tr>';

                    firstGiftCheckout.before(checkoutSeparator);
                }
            }

            addGiftSeparator();

            $(document.body).on('updated_cart_totals updated_checkout', function() {
                addGiftSeparator();
            });
        });
        </script>
        <?php
    }

    /**
     * 渲染贈品樣式CSS
     */
    public function renderStyles(): void
    {
        if (!is_cart() && !is_checkout()) {
            return;
        }

        ?>
        <style type="text/css">
            .nyb-gift-item .product-thumbnail {
                position: relative;
            }

            .nyb-gift-item .product-thumbnail::after {
                content: '🎁';
                position: absolute;
                top: 5px;
                right: 5px;
                background: #df565f;
                color: white;
                border-radius: 3px;
                font-size: 12px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                width: 28px;
                height: 28px;
                display: flex;
                align-items: center;
                justify-content: center;
                text-shadow: 1px 1px 10px #ff9f9f;
            }

            @media (max-width: 768px) {
                .nyb-gift-separator {
                    padding: 15px 0 10px 0 !important;
                }

                .nyb-gift-separator span {
                    font-size: 12px !important;
                    padding: 4px 15px !important;
                }

                .nyb-gift-item {
                    border-left-width: 2px !important;
                }
            }
        </style>
        <?php
    }
}

