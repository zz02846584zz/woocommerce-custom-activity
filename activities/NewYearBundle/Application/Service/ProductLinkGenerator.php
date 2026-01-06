<?php
/**
 * 商品連結生成器
 *
 * 負責生成商品頁面的連結 HTML
 */

namespace NewYearBundle\Application\Service;

use NewYearBundle\Config;

class ProductLinkGenerator
{
    /**
     * 生成商品連結
     */
    public function generate(int $productId, string $text): string
    {
        if (!$productId) {
            return $text;
        }

        $url = get_permalink($productId);
        if (!$url) {
            return $text;
        }

        return '<a href="' . esc_url($url) . '" style="color: inherit; text-decoration: underline; font-weight: bold;" target="_blank">' . esc_html($text) . '</a>';
    }

    /**
     * 獲取商品類別的連結 HTML
     */
    public function getCategoryLink(string $category): string
    {
        $links = [
            'mattress' => $this->generate(1324, '嗜睡床墊'),
            'spring_mattress' => $this->generate(1324, '嗜睡床墊'),
            'hypnotic_pillow' => $this->generate(Config::getHypnoticPillowParent(), '催眠枕'),
            'hypnotic_pillow_high' => $this->generate(2984, '高枕'),
            'lai_mattress' => $this->generate(3444, '賴床墊'),
            'bed_frame' => $this->generate(4930, '床架'),
            'fleece_blanket' => $this->generate(Config::getGiftFleeceBlanket(), '茸茸被'),
            'hug_pillow' => $this->generate(Config::getGiftHugPillow(), '抱枕'),
            'eye_mask' => $this->generate(Config::getGiftEyeMask(), '眼罩'),
            'side_pillow' => $this->generate(Config::getHypnoticPillowParent(), '側睡枕'),
            'pillowcase' => $this->generate(Config::getHypnoticPillowParent(), '天絲枕套'),
            'bedding_set' => '<strong>天絲四件組床包</strong>'
        ];

        return $links[$category] ?? $category;
    }
}

