<?php
/**
 * Plugin Name: 自訂活動管理系統
 * Description: 管理各種促銷活動（2026新年優惠、節日優惠等）
 * Version: 1.0.0
 * Author: Bryan
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Text Domain: custom-activity
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// 檢查 WooCommerce 是否存在的函數
function custom_activity_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>自訂活動管理系統</strong> 需要安裝並啟用 WooCommerce 插件。</p></div>';
        });
        return false;
    }
    return true;
}

// 定義插件常數
define( 'CUSTOM_ACTIVITY_VERSION', '1.0.0' );
define( 'CUSTOM_ACTIVITY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CUSTOM_ACTIVITY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * 加載活動模組
 */
function custom_activity_load_modules() {
    // 檢查 WooCommerce 是否存在
    if ( ! custom_activity_check_woocommerce() ) {
        return;
    }

    $modules = [
        'activities/new-year-bundle-active.php' => '2026 新年優惠活動'
    ];

    foreach ( $modules as $file => $description ) {
        $file_path = CUSTOM_ACTIVITY_PLUGIN_DIR . $file;

        if ( file_exists( $file_path ) ) {
            require_once $file_path;

            // 記錄模組加載
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( '[自訂活動] 已載入模組: %s', $description ) );
            }
        } else {
            // 模組檔案不存在時記錄錯誤
            error_log( sprintf( '[自訂活動] 模組檔案不存在: %s (%s)', $file, $description ) );
        }
    }
}

add_action( 'plugins_loaded', 'custom_activity_load_modules', 10 );

/**
 * 插件啟用時的 Hook
 */
function custom_activity_activate() {
    // 檢查 PHP 版本
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( '此插件需要 PHP 7.4 或更高版本。您目前的 PHP 版本為：' . PHP_VERSION );
    }

    // 檢查 WooCommerce
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( '此插件需要先安裝並啟用 WooCommerce。' );
    }

    // 記錄啟用日誌
    $logger = wc_get_logger();
    $context = array( 'source' => 'custom-activity' );
    $logger->info( '[自訂活動管理系統] 插件已啟用 | 版本: ' . CUSTOM_ACTIVITY_VERSION, $context );
}

register_activation_hook( __FILE__, 'custom_activity_activate' );

/**
 * 插件停用時的 Hook
 */
function custom_activity_deactivate() {
    // 清理暫存資料（如果需要）

    // 記錄停用日誌
    if ( function_exists( 'wc_get_logger' ) ) {
        $logger = wc_get_logger();
        $context = array( 'source' => 'custom-activity' );
        $logger->info( '[自訂活動管理系統] 插件已停用', $context );
    }
}

register_deactivation_hook( __FILE__, 'custom_activity_deactivate' );

/**
 * 在插件列表頁顯示活動狀態
 */
add_filter( 'plugin_row_meta', 'custom_activity_plugin_row_meta', 10, 2 );
function custom_activity_plugin_row_meta( $links, $file ) {
    if ( plugin_basename( __FILE__ ) === $file ) {
        $current_time = current_time( 'mysql' );

        // 檢查 2026 新年活動狀態
        if ( defined( 'NYB_CAMPAIGN_START' ) && defined( 'NYB_CAMPAIGN_END' ) ) {
            if ( $current_time >= NYB_CAMPAIGN_START && $current_time <= NYB_CAMPAIGN_END ) {
                $row_meta = [
                    'status' => '<span style="color: #46b450; font-weight: bold;">● 2026新年活動進行中</span>'
                ];
            } else {
                $row_meta = [
                    'status' => '<span style="color: #999;">○ 2026新年活動未啟用</span>'
                ];
            }

            $links = array_merge( $links, $row_meta );
        }
    }

    return $links;
}

/**
 * 新增管理選單（未來可擴展為活動管理後台）
 */
add_action( 'admin_menu', 'custom_activity_admin_menu', 99 );
function custom_activity_admin_menu() {
    // 確保 WooCommerce 存在
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    add_submenu_page(
        'woocommerce',
        '自訂活動管理',
        '自訂活動',
        'manage_woocommerce',
        'custom-activity-dashboard',
        'custom_activity_dashboard_page'
    );
}

/**
 * 管理後台頁面
 */
function custom_activity_dashboard_page() {
    $current_time = current_time( 'mysql' );

    ?>
    <div class="wrap">
        <h1>🎉 自訂活動管理系統</h1>

        <div class="notice notice-info">
            <p><strong>當前時間：</strong><?php echo $current_time; ?> (Asia/Taipei)</p>
        </div>

        <h2>活動模組狀態</h2>

        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th width="30%">活動名稱</th>
                    <th width="25%">活動期間</th>
                    <th width="15%">狀態</th>
                    <th width="30%">說明</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // 2026 新年活動
                if ( defined( 'NYB_CAMPAIGN_START' ) && defined( 'NYB_CAMPAIGN_END' ) ) {
                    $is_active = ( $current_time >= NYB_CAMPAIGN_START && $current_time <= NYB_CAMPAIGN_END );
                    $status_text = $is_active ? '<span style="color: #46b450; font-weight: bold;">● 進行中</span>' : '<span style="color: #999;">○ 未啟用</span>';

                    echo '<tr>';
                    echo '<td><strong>2026 新年優惠活動</strong></td>';
                    echo '<td>' . NYB_CAMPAIGN_START . '<br>至<br>' . NYB_CAMPAIGN_END . '</td>';
                    echo '<td>' . $status_text . '</td>';
                    echo '<td>全館9折 + 7大組合優惠</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>

        <h2>功能說明</h2>

        <div class="card">
            <h3>2026 新年優惠活動</h3>
            <ul>
                <li><strong>活動1：</strong>床墊+催眠枕送茸茸被</li>
                <li><strong>活動2：</strong>賴床墊送抱枕+眼罩</li>
                <li><strong>活動3：</strong>枕頭組合特價$8,888</li>
                <li><strong>活動4：</strong>枕頭買一送一，再送天絲枕套</li>
                <li><strong>活動5：</strong>彈簧床墊+催眠枕×2+賴床墊，贈天絲四件組床包</li>
                <li><strong>活動6：</strong>床墊+床架送側睡枕</li>
                <li><strong>活動7：</strong>床墊+床架+枕頭×2，贈天絲四件組床包+茸茸被</li>
            </ul>
            <p><strong>注意：</strong>活動之間可疊加，但每個活動在一筆訂單只會應用一次。</p>
        </div>

        <h2>系統資訊</h2>

        <table class="form-table">
            <tr>
                <th scope="row">插件版本</th>
                <td><?php echo CUSTOM_ACTIVITY_VERSION; ?></td>
            </tr>
            <tr>
                <th scope="row">PHP 版本</th>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <th scope="row">WooCommerce 版本</th>
                <td><?php echo defined( 'WC_VERSION' ) ? WC_VERSION : '未安裝'; ?></td>
            </tr>
            <tr>
                <th scope="row">WordPress 版本</th>
                <td><?php echo get_bloginfo( 'version' ); ?></td>
            </tr>
            <tr>
                <th scope="row">日誌位置</th>
                <td>WooCommerce → 狀態 → 日誌 → 搜尋 "custom-activity" 或 "newyear-bundle"</td>
            </tr>
        </table>

        <h2>疑難排解</h2>

        <div class="card">
            <h3>常見問題</h3>
            <dl>
                <dt><strong>Q: 活動沒有自動應用？</strong></dt>
                <dd>A: 請檢查：1) 活動期間是否正確 2) 是否使用了優惠券（優惠券與活動互斥）3) 購物車商品是否符合活動條件</dd>

                <dt><strong>Q: 9折沒有顯示？</strong></dt>
                <dd>A: 9折是在商品層級應用，如果商品已有促銷價，會優先使用促銷價。贈品商品不參與9折。</dd>

                <dt><strong>Q: 如何查看詳細日誌？</strong></dt>
                <dd>A: 前往 WooCommerce → 狀態 → 日誌，搜尋 "newyear-bundle" 查看新年活動相關日誌。</dd>

                <dt><strong>Q: 贈品無法移除？</strong></dt>
                <dd>A: 贈品由系統自動管理，當購物車內容符合活動條件時自動加入，條件不符時自動移除。無法手動修改數量或移除。</dd>
            </dl>
        </div>
    </div>

    <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin-top: 10px;
        }
        .card h3 {
            margin-top: 0;
        }
        .card ul {
            list-style: disc;
            padding-left: 20px;
        }
        .card dl {
            margin: 0;
        }
        .card dt {
            font-weight: bold;
            margin-top: 15px;
        }
        .card dd {
            margin-left: 20px;
            margin-bottom: 10px;
            color: #666;
        }
    </style>
    <?php
}

/**
 * 在 WooCommerce 系統狀態報告中加入插件資訊
 */
add_action( 'woocommerce_system_status_report', 'custom_activity_system_status_report' );
function custom_activity_system_status_report() {
    // 確保 WooCommerce 存在
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $current_time = current_time( 'mysql' );

    ?>
    <table class="wc_status_table widefat" cellspacing="0">
        <thead>
            <tr>
                <th colspan="3" data-export-label="Custom Activity">
                    <h2>自訂活動管理系統</h2>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td data-export-label="Plugin Version">插件版本：</td>
                <td class="help"></td>
                <td><?php echo CUSTOM_ACTIVITY_VERSION; ?></td>
            </tr>
            <tr>
                <td data-export-label="Current Time">當前時間：</td>
                <td class="help"></td>
                <td><?php echo $current_time; ?></td>
            </tr>
            <?php if ( defined( 'NYB_CAMPAIGN_START' ) && defined( 'NYB_CAMPAIGN_END' ) ) : ?>
            <tr>
                <td data-export-label="2026 New Year Campaign">2026新年活動：</td>
                <td class="help"></td>
                <td>
                    <?php
                    $is_active = ( $current_time >= NYB_CAMPAIGN_START && $current_time <= NYB_CAMPAIGN_END );
                    if ( $is_active ) {
                        echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> 進行中</mark>';
                        echo '<br><small>' . NYB_CAMPAIGN_START . ' ~ ' . NYB_CAMPAIGN_END . '</small>';
                    } else {
                        echo '<mark class="no"><span class="dashicons dashicons-minus"></span> 未啟用</mark>';
                        echo '<br><small>期間：' . NYB_CAMPAIGN_START . ' ~ ' . NYB_CAMPAIGN_END . '</small>';
                    }
                    ?>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

