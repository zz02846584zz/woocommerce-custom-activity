<?php
/**
 * 提示訊息建構器
 *
 * 負責構建活動提示訊息
 * 重構自原 nyb_get_activity_notice() 函數
 */

namespace NewYearBundle\Application\Service;

use NewYearBundle\Domain\Service\CartAnalyzer;

class NoticeBuilder
{
    public function __construct(
        private ProductLinkGenerator $linkGenerator,
        private CartAnalyzer $cartAnalyzer
    ) {}

    /**
     * 構建活動提示訊息
     *
     * @param string $activityKey 活動代碼
     * @param string $status 狀態 (qualified/almost/not_qualified)
     * @param array $missing 缺少的商品
     * @return array ['title' => '標題', 'message' => '訊息', 'type' => 'success/info/warning', 'missing' => []]
     */
    public function build(string $activityKey, string $status, array $missing = []): array
    {
        // 獲取商品連結
        $mattressLink = $this->linkGenerator->getCategoryLink('spring_mattress');
        $hypnoticPillowLink = $this->linkGenerator->getCategoryLink('hypnotic_pillow');
        $hypnoticPillowLinkHigh = $this->linkGenerator->getCategoryLink('hypnotic_pillow_high');
        $laiMattressLink = $this->linkGenerator->getCategoryLink('lai_mattress');
        $bedFrameLink = $this->linkGenerator->getCategoryLink('bed_frame');
        $fleeceBlanketLink = $this->linkGenerator->getCategoryLink('fleece_blanket');
        $hugPillowLink = $this->linkGenerator->getCategoryLink('hug_pillow');
        $eyeMaskLink = $this->linkGenerator->getCategoryLink('eye_mask');
        $sidePillowLink = $this->linkGenerator->getCategoryLink('side_pillow');
        $pillowcaseLink = $this->linkGenerator->getCategoryLink('pillowcase');
        $beddingSetLink = $this->linkGenerator->getCategoryLink('bedding_set');

        $notices = $this->getNoticeTemplates(
            $mattressLink,
            $hypnoticPillowLink,
            $hypnoticPillowLinkHigh,
            $laiMattressLink,
            $bedFrameLink,
            $fleeceBlanketLink,
            $hugPillowLink,
            $eyeMaskLink,
            $sidePillowLink,
            $pillowcaseLink,
            $beddingSetLink,
            $missing
        );

        if (isset($notices[$activityKey][$status])) {
            $notice = $notices[$activityKey][$status];

            // 如果 message 是閉包函數，執行它
            if (is_callable($notice['message'])) {
                $notice['message'] = call_user_func($notice['message']);
            }

            return $notice;
        }

        return [
            'title' => '優惠活動',
            'missing' => $missing,
            'message' => '新年優惠活動',
            'type' => 'info'
        ];
    }

    /**
     * 獲取所有活動的提示訊息模板
     */
    private function getNoticeTemplates(
        string $mattressLink,
        string $hypnoticPillowLink,
        string $hypnoticPillowLinkHigh,
        string $laiMattressLink,
        string $bedFrameLink,
        string $fleeceBlanketLink,
        string $hugPillowLink,
        string $eyeMaskLink,
        string $sidePillowLink,
        string $pillowcaseLink,
        string $beddingSetLink,
        array $missing
    ): array {
        return [
            'activity_1' => [
                'qualified' => [
                    'title' => '🎁 已符合優惠',
                    'message' => '已購買' . $mattressLink . '和' . $hypnoticPillowLink . '，將獲贈' . $fleeceBlanketLink,
                    'type' => 'success',
                    'missing' => []
                ],
                'almost' => [
                    'title' => '',
                    'message' => function() use ($missing, $mattressLink, $hypnoticPillowLink, $fleeceBlanketLink) {
                        $links = [];
                        $hasSpringMattress = true;
                        $hasPillow = true;

                        foreach ($missing as $item) {
                            if ($item === '嗜睡床墊') {
                                $links[] = $mattressLink;
                                $hasSpringMattress = false;
                            } elseif ($item === '催眠枕') {
                                $links[] = $hypnoticPillowLink;
                                $hasPillow = false;
                            }
                        }

                        if (empty($links)) {
                            return '購買' . $mattressLink . '和' . $hypnoticPillowLink . '，即可獲得' . $fleeceBlanketLink;
                        }

                        $prefix = ($hasSpringMattress || $hasPillow) ? '再購買' : '購買';
                        return $prefix . implode('和', $links) . '，即可獲得' . $fleeceBlanketLink;
                    },
                    'type' => 'info',
                    'missing' => $missing
                ],
                'not_qualified' => [
                    'title' => '',
                    'message' => '購買' . $mattressLink . '和' . $hypnoticPillowLink . '，即可獲得' . $fleeceBlanketLink,
                    'type' => 'info',
                    'missing' => $missing
                ]
            ],
            'activity_2' => [
                'qualified' => [
                    'title' => '🎁 已符合優惠',
                    'message' => '已購買' . $laiMattressLink . '，將獲贈' . $hugPillowLink . '和' . $eyeMaskLink,
                    'type' => 'success',
                    'missing' => []
                ],
                'almost' => [
                    'title' => '',
                    'message' => '購買' . $laiMattressLink . '，即可獲得' . $hugPillowLink . '和' . $eyeMaskLink,
                    'type' => 'info',
                    'missing' => $missing
                ],
                'not_qualified' => [
                    'title' => '',
                    'message' => '購買' . $laiMattressLink . '，即可獲得' . $hugPillowLink . '和' . $eyeMaskLink,
                    'type' => 'info',
                    'missing' => $missing
                ]
            ],
            'activity_3' => [
                'qualified' => [
                    'title' => '🎁 已符合優惠',
                    'message' => '已購買2個' . $hypnoticPillowLink . '，享特價<strong>$8,888</strong>再加碼贈' . $pillowcaseLink . '×2（最高價2個枕頭組合）',
                    'type' => 'success',
                    'missing' => []
                ],
                'almost' => [
                    'title' => '',
                    'message' => function() use ($hypnoticPillowLink, $pillowcaseLink) {
                        $stats = $this->cartAnalyzer->analyze(\WC()->cart);
                        $pillowCount = $stats->hypnoticPillowCount ?? 0;

                        if ($pillowCount == 1) {
                            return '再購買1個' . $hypnoticPillowLink . '，即享特價<strong>$8,888</strong>再加碼贈' . $pillowcaseLink . '×2';
                        }

                        return '購買任意2個' . $hypnoticPillowLink . '，即享特價<strong>$8,888</strong>再加碼贈' . $pillowcaseLink . '×2';
                    },
                    'type' => 'info',
                    'missing' => $missing
                ],
                'not_qualified' => [
                    'title' => '',
                    'message' => '購買任意兩個' . $hypnoticPillowLink . '，即享特價<strong>$8,888</strong>再加碼贈' . $pillowcaseLink . '×2',
                    'type' => 'info',
                    'missing' => $missing
                ]
            ],
            'activity_4' => [
                'qualified' => [
                    'title' => '🎁 已符合優惠',
                    'message' => '已購買' . $hypnoticPillowLink . '，將獲贈' . $pillowcaseLink . '（買一送一）',
                    'type' => 'success',
                    'missing' => []
                ],
                'almost' => [
                    'title' => '',
                    'message' => '購買' . $hypnoticPillowLink . '，即可獲得' . $pillowcaseLink . '（買一送一）',
                    'type' => 'info',
                    'missing' => $missing
                ],
                'not_qualified' => [
                    'title' => '',
                    'message' => '購買' . $hypnoticPillowLink . '，即可獲得' . $pillowcaseLink . '（買一送一）',
                    'type' => 'info',
                    'missing' => $missing
                ]
            ],
            'activity_5' => [
                'qualified' => [
                    'title' => '🎁 已符合優惠',
                    'message' => '已購買' . $mattressLink . '、' . $hypnoticPillowLink . '×2和' . $laiMattressLink . '，將獲贈' . $beddingSetLink,
                    'type' => 'success',
                    'missing' => []
                ],
                'almost' => [
                    'title' => '',
                    'message' => function() use ($missing, $mattressLink, $hypnoticPillowLink, $laiMattressLink, $beddingSetLink) {
                        $links = [];
                        foreach ($missing as $item) {
                            if (strpos($item, '嗜睡床墊') !== false) {
                                $links[] = $mattressLink;
                            } elseif (strpos($item, '賴床墊') !== false) {
                                $links[] = $laiMattressLink;
                            } elseif (strpos($item, '催眠枕') !== false) {
                                $links[] = $hypnoticPillowLink . '<small>（' . $item . '）</small>';
                            }
                        }
                        $prefix = !empty($links) && count($missing) < 3 ? '再購買' : '購買';
                        return $prefix . implode('、', $links) . '，即可獲得' . $beddingSetLink;
                    },
                    'type' => 'info',
                    'missing' => $missing
                ],
                'not_qualified' => [
                    'title' => '',
                    'message' => '購買' . $mattressLink . '、' . $hypnoticPillowLink . '<small>（2個）</small>和' . $laiMattressLink . '，即可獲得' . $beddingSetLink,
                    'type' => 'info',
                    'missing' => $missing
                ]
            ],
            'activity_6' => [
                'qualified' => [
                    'title' => '🎁 已符合優惠',
                    'message' => '已購買' . $mattressLink . '和' . $bedFrameLink . '，將獲贈' . $sidePillowLink,
                    'type' => 'success',
                    'missing' => []
                ],
                'almost' => [
                    'title' => '',
                    'message' => function() use ($missing, $mattressLink, $bedFrameLink, $sidePillowLink) {
                        $links = [];
                        foreach ($missing as $item) {
                            if ($item === '嗜睡床墊') {
                                $links[] = $mattressLink;
                            } elseif ($item === '床架') {
                                $links[] = $bedFrameLink;
                            }
                        }

                        if (empty($links)) {
                            return '購買' . $mattressLink . '和' . $bedFrameLink . '，即可獲得' . $sidePillowLink;
                        }

                        $prefix = count($missing) < 2 ? '再購買' : '購買';
                        return $prefix . implode('和', $links) . '，即可獲得' . $sidePillowLink;
                    },
                    'type' => 'info',
                    'missing' => $missing
                ],
                'not_qualified' => [
                    'title' => '',
                    'message' => '購買' . $mattressLink . '和' . $bedFrameLink . '，即可獲得' . $sidePillowLink,
                    'type' => 'info',
                    'missing' => $missing
                ]
            ],
            'activity_7' => [
                'qualified' => [
                    'title' => '🎁 已符合優惠',
                    'message' => '已購買' . $mattressLink . '、' . $bedFrameLink . '和' . $hypnoticPillowLink . '×2，將獲贈' . $beddingSetLink . '和' . $fleeceBlanketLink,
                    'type' => 'success',
                    'missing' => []
                ],
                'almost' => [
                    'title' => '',
                    'message' => function() use ($missing, $mattressLink, $bedFrameLink, $hypnoticPillowLink, $beddingSetLink, $fleeceBlanketLink) {
                        $links = [];
                        foreach ($missing as $item) {
                            if ($item === '嗜睡床墊') {
                                $links[] = $mattressLink;
                            } elseif ($item === '床架') {
                                $links[] = $bedFrameLink;
                            } elseif (strpos($item, '催眠枕') !== false) {
                                $links[] = $hypnoticPillowLink . '<small>（' . $item . '）</small>';
                            }
                        }

                        if (empty($links)) {
                            return '購買' . $mattressLink . '、' . $bedFrameLink . '和' . $hypnoticPillowLink . '<small>（2個）</small>，即可獲得' . $beddingSetLink . '和' . $fleeceBlanketLink;
                        }

                        $prefix = count($missing) < 3 ? '再購買' : '購買';
                        return $prefix . implode('、', $links) . '，即可獲得' . $beddingSetLink . '和' . $fleeceBlanketLink;
                    },
                    'type' => 'info',
                    'missing' => $missing
                ],
                'not_qualified' => [
                    'title' => '',
                    'message' => '購買' . $mattressLink . '、' . $bedFrameLink . '和' . $hypnoticPillowLink . '<small>（2個）</small>，即可獲得' . $beddingSetLink . '和' . $fleeceBlanketLink,
                    'type' => 'info',
                    'missing' => $missing
                ]
            ]
        ];
    }
}

