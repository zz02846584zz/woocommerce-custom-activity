<?php
/**
 * WooCommerce Cart History Tracker
 * 維護一個基於時間序的購物車歷史，解決 Item Merging 導致的順序丟失問題。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NYB_Cart_History_Tracker {

    private $session_key = 'nyb_cart_add_history';
    private static $processed_updates = []; // 用於防止 Hook 重複觸發的暫存旗標

    public function __construct() {
        // 1. 監聽加入購物車動作
        add_action( 'woocommerce_add_to_cart', [ $this, 'handle_add_to_cart' ], 10, 6 );

        // 2. 監聽數量更新動作 (購物車頁面改數量 / 重複加入商品)
        add_action( 'woocommerce_after_cart_item_quantity_update', [ $this, 'handle_quantity_update' ], 10, 4 );

        // 3. 監聽移除動作
        add_action( 'woocommerce_cart_item_removed', [ $this, 'handle_item_removed' ], 10, 2 );

        // 4. 監聽撤銷移除 (Undo)
        add_action( 'woocommerce_cart_item_restored', [ $this, 'handle_item_restored' ], 10, 2 );

        // 5. 監聽清空購物車
        add_action( 'woocommerce_cart_emptied', [ $this, 'clear_history' ] );

        // 6. 安全網：若 Session 空了但購物車有東西，進行重建
        add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'validate_and_rehydrate' ] );
    }

    /**
     * 獲取當前的歷史紀錄
     */
    public function get_history() {
        return WC()->session->get( $this->session_key, [] );
    }

    /**
     * 儲存歷史紀錄
     */
    private function save_history( $history ) {
        WC()->session->set( $this->session_key, array_values( $history ) ); // array_values 確保索引重排
    }

    /**
     * 處理：新商品加入 (New Item)
     * 注意：若是對"已存在商品"再次點擊加入，WC 會先觸發 quantity_update，再觸發 add_to_cart。
     * 我們利用 $processed_updates 避免重複計算。
     */
    public function handle_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        // 如果這個 Key 剛剛已經在 update_quantity 被處理過了，這裡就跳過
        if ( isset( self::$processed_updates[ $cart_item_key ] ) ) {
            return;
        }

				$cart_item = WC()->cart->get_cart_item( $cart_item_key );

				$is_gift = isset( $cart_item[ '_nyb_auto_gift' ] ) && $cart_item[ '_nyb_auto_gift' ];

				$this->log_history( $cart_item, '[handle_add_to_cart] 商品資訊' );

				$this->log_history( $is_gift, '[handle_add_to_cart] 是否為贈品' );

				// 如果是贈品，則不記錄
				if ( $is_gift ) {
					return;
				}

        $this->append_to_history( $product_id, $variation_id, $quantity );
    }

    /**
     * 處理：數量更新 (Update Quantity)
     * 包含：購物車頁面改數字、或對已存在商品再次點擊 Add to cart
     */
    public function handle_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
        // 標記此 Key 本次請求已處理
        self::$processed_updates[ $cart_item_key ] = true;

        $item = $cart->get_cart_item( $cart_item_key );
        $product_id = $item['product_id'];
        $variation_id = $item['variation_id'] ? $item['variation_id'] : 0;

        if ( $quantity > $old_quantity ) {
            // 變多：視為新加入
            $add_qty = $quantity - $old_quantity;
            $this->append_to_history( $product_id, $variation_id, $add_qty );
        } elseif ( $quantity < $old_quantity ) {
            // 變少：執行 LIFO 移除 (從後面刪，保留最早的)
            $remove_qty = $old_quantity - $quantity;
            $this->remove_from_history_lifo( $product_id, $variation_id, $remove_qty );
        }
    }

    /**
     * 處理：移除項目 (Remove)
     * 從 WC 3.x 開始，Removed Action 觸發時，Item 已經不在 Cart Object 裡，
     * 但存在於 $cart->removed_cart_contents 中。
     */
    public function handle_item_removed( $cart_item_key, $cart ) {
        if ( isset( $cart->removed_cart_contents[ $cart_item_key ] ) ) {
            $item = $cart->removed_cart_contents[ $cart_item_key ];
            $product_id = $item['product_id'];
            $variation_id = $item['variation_id'] ? $item['variation_id'] : 0;

            // 移除該商品的所有歷史紀錄 (因為是整行刪除)
            // 這裡傳入數量 999999 確保刪光，或者寫一個專門的 purge 方法
            $this->remove_from_history_lifo( $product_id, $variation_id, 999999 );
        }
    }

    /**
     * 處理：撤銷移除 (Restore)
     * 當用戶點擊 "Undo" 時，視為重新加入 (時間戳記更新為當下)
     */
    public function handle_item_restored( $cart_item_key, $cart ) {
        $item = $cart->get_cart_item( $cart_item_key );
        $product_id = $item['product_id'];
        $variation_id = $item['variation_id'] ? $item['variation_id'] : 0;
        $quantity = $item['quantity'];

        $this->append_to_history( $product_id, $variation_id, $quantity );
    }

    /**
     * 處理：清空購物車
     */
    public function clear_history() {
        if ( WC()->session ) {
            WC()->session->set( $this->session_key, [] );
        }
    }

    /**
     * 核心邏輯：追加紀錄
     */
    private function append_to_history( $product_id, $variation_id, $qty ) {
        $history = $this->get_history();

        for ( $i = 0; $i < $qty; $i++ ) {
            $history[] = [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'timestamp'    => microtime( true ), // 精確到微秒
                'date_str'     => current_time( 'Y-m-d H:i:s' ) // 方便除錯看
            ];
        }

        $this->save_history( $history );
				$this->log_history( $history, '[append_to_history] 新增紀錄' );
    }

		/**
		 *
		 */

		private function log_history( $history, $message ) {
			if ( ! NYB_DEBUG_MODE && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
					return;
			}

			$log_file = WP_CONTENT_DIR . '/cart-history.log';
			$timestamp = current_time('Y-m-d H:i:s');
			error_log("[{$timestamp}] {$message} " . json_encode($history, JSON_UNESCAPED_UNICODE, JSON_PRETTY_PRINT) . "\n", 3, $log_file);
		}

    /**
     * 核心邏輯：LIFO 移除 (Last-In, First-Out)
     * 這是為了保護"先加入者"的權益。當數量減少時，我們刪除"最新"加入的紀錄。
     */
    private function remove_from_history_lifo( $product_id, $variation_id, $qty_to_remove ) {
        $history = $this->get_history();
        $removed_count = 0;

        // 倒序遍歷 (從最後面開始找)
        for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
            if ( $removed_count >= $qty_to_remove ) break;

            $h_pid = $history[$i]['product_id'];
            $h_vid = $history[$i]['variation_id'];

            // 比對 Product ID 和 Variation ID
            if ( $h_pid == $product_id && $h_vid == $variation_id ) {
                unset( $history[$i] ); // 移除此紀錄
                $removed_count++;
            }
        }

        $this->save_history( $history );
    }

    /**
     * 安全網：狀態同步檢查
     * 防止 Session 過期但 Cart 還在，導致計算崩潰。
     */
    public function validate_and_rehydrate() {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        if ( ! WC()->session ) return;

        $cart_contents = WC()->cart->get_cart();
        $history = $this->get_history();

        // 如果購物車有東西，但歷史是空的 (例如換裝置、Session過期)
        if ( ! empty( $cart_contents ) && empty( $history ) ) {
            $new_history = [];
            $fake_time = microtime(true);

            foreach ( $cart_contents as $key => $item ) {
                $p_id = $item['product_id'];
                $v_id = $item['variation_id'] ? $item['variation_id'] : 0;
                $qty  = $item['quantity'];

                for( $i=0; $i < $qty; $i++ ) {
                    $new_history[] = [
                        'product_id'   => $p_id,
                        'variation_id' => $v_id,
                        'timestamp'    => $fake_time + ($i * 0.01), // 偽造微小時間差
                        'rehydrated'   => true // 標記為重建數據
                    ];
                }
            }
            $this->save_history( $new_history );
        }
    }
}

new NYB_Cart_History_Tracker();