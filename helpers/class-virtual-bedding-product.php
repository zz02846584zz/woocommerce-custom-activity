<?php
/**
 * 虛擬商品類：天絲四件組床包
 * 處理活動5和活動7的天絲四件組床包贈品
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NYB_Virtual_Bedding_Product {
    /**
     * 初始化
     */
    public static function init() {

				// 核心：讓系統承認這個不存在的 ID 是一個可購買的商品
				// add_filter( 'woocommerce_get_product', [ __CLASS__, 'inject_virtual_product' ], 10, 2 );

				// // 關鍵修正：繞過可購買性與庫存檢查
        // add_filter( 'woocommerce_is_purchasable', [ __CLASS__, 'bypass_purchasable' ], 10, 2 );
        // add_filter( 'woocommerce_product_get_stock_status', [ __CLASS__, 'bypass_stock_status' ], 10, 2 );

				// // 關鍵修正：繞過可見性與狀態檢查
				// add_filter( 'woocommerce_product_is_visible', [ __CLASS__, 'bypass_visibility' ], 10, 2 );
				// add_filter( 'woocommerce_product_get_status', [ __CLASS__, 'bypass_status' ], 10, 2 );

        // // 購物車與 Session 處理
        // add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'add_cart_item_data' ], 10, 3 );
        add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'set_virtual_product_price' ], 999 );
        // add_filter( 'woocommerce_get_cart_item_from_session', [ __CLASS__, 'get_cart_item_from_session' ], 10, 2 );
        // add_action( 'woocommerce_add_to_cart', [ __CLASS__, 'handle_virtual_product_add' ], 10, 6 );

				// // 顯示與價格
        add_filter( 'woocommerce_cart_item_name', [ __CLASS__, 'display_virtual_product_name' ], 10, 3 );
        // add_filter( 'woocommerce_cart_item_price', [ __CLASS__, 'display_virtual_product_price' ], 10, 3 );
        // add_filter( 'woocommerce_cart_item_subtotal', [ __CLASS__, 'display_virtual_product_subtotal' ], 10, 3 );

        // 創建虛擬商品對象
        // add_filter( 'woocommerce_get_product_from_item', [ __CLASS__, 'create_virtual_product' ], 10, 3 );

        // 顯示虛擬商品在購物車中
        // add_filter( 'woocommerce_cart_item_name', [ __CLASS__, 'display_virtual_product_name' ], 10, 3 );
        // add_filter( 'woocommerce_cart_item_price', [ __CLASS__, 'display_virtual_product_price' ], 10, 3 );
        // add_filter( 'woocommerce_cart_item_subtotal', [ __CLASS__, 'display_virtual_product_subtotal' ], 10, 3 );

        // 設置虛擬商品價格為0
        add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'set_virtual_product_price' ], 999 );

        // 禁用虛擬商品的數量修改
        // add_filter( 'woocommerce_cart_item_quantity', [ __CLASS__, 'disable_quantity_input' ], 10, 3 );

        // 設置虛擬商品為贈品
        // add_filter( 'woocommerce_cart_item_class', [ __CLASS__, 'add_gift_class' ], 10, 3 );

        // 在訂單中保存虛擬商品資訊
        // add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_to_order' ], 10, 4 );

        // 防止虛擬商品被手動移除
        // add_filter( 'woocommerce_cart_item_remove_link', [ __CLASS__, 'hide_remove_link' ], 10, 2 );
    }

		private static function bt_get_system_placeholder_id() {
				$slug = 'bt-system-placeholder';

				// 1. Check if the product already exists by slug
				$placeholder = get_page_by_path($slug, OBJECT, 'product');

				if ($placeholder) {
						return $placeholder->ID;
				}

				// 2. Initialize a new WooCommerce Product object
				$product = new WC_Product();

				// 3. Set Core Product Properties
				$product->set_name('天絲四件組床包');
				$product->set_slug($slug);
				$product->set_status('publish'); // Remains publishable for logic, but hidden from UI

				/**
				 * Set Catalog Visibility
				 * 'hidden' automatically sets:
				 * - taxonomy: product_visibility -> exclude-from-catalog
				 * - taxonomy: product_visibility -> exclude-from-search
				 */
				$product->set_catalog_visibility('hidden');

				// 4. Set Pricing and Type (Virtual & Zero Price)
				$product->set_regular_price(0);
				$product->set_price(0);
				$product->set_virtual(true);
				$product->set_sold_individually(false);
				$product->set_manage_stock(false);
				$product->set_stock_status('instock');

				// 5. Disable Reviews and Other UI Elements
				$product->set_reviews_allowed(false);

				// 6. Save to Database and Return ID
				$post_id = $product->save();

				return $post_id;
		}

		/**
     * 修正點 1：增強型物件注入
     */
		public static function inject_virtual_product( $product, $id ) {
				if ( (int) $id === self::VIRTUAL_PRODUCT_ID ) {
						$virtual_product = new WC_Product_Simple();
						$virtual_product->set_id( self::VIRTUAL_PRODUCT_ID );
						$virtual_product->set_name( '天絲四件組床包' );
						$virtual_product->set_virtual( true );
						$virtual_product->set_price( 0 );
						$virtual_product->set_status( 'publish' );
						return $virtual_product;
				}
				return $product;
		}

		/**
		 * 修正點 2：強制允許購買 (這是不起作用的主因)
		 */
		public static function bypass_purchasable( $purchasable, $product ) {
				if ( $product->get_id() == self::VIRTUAL_PRODUCT_ID ) {
						return true;
				}
				return $purchasable;
		}

		// 強制設定為有庫存
		public static function bypass_stock_status( $status, $product ) {
				return ( $product->get_id() == self::VIRTUAL_PRODUCT_ID ) ? 'instock' : $status;
		}

		public static function bypass_visibility( $visible, $product_id ) {
				return ( $product_id == self::VIRTUAL_PRODUCT_ID ) ? true : $visible;
		}

		public static function bypass_status( $status, $product ) {
				return ( $product->get_id() == self::VIRTUAL_PRODUCT_ID ) ? 'publish' : $status;
		}

    /**
     * 添加購物車項目數據
     */
    public static function add_cart_item_data( $cart ) {
				if (is_admin() && !defined('DOING_AJAX')) return;
				if (did_action('woocommerce_before_calculate_totals') >= 2) return;


				$system_id = self::bt_get_system_placeholder_id();

				foreach ($cart->get_cart() as $cart_item) {
						if (isset($cart_item['_nyb_bedding_value']) && isset($cart_item['_nyb_bedding_name'])) {

								// $cart_item_data['custom_regular_price'] = $_POST['_nyb_bedding_value'];
								// $cart_item_data['custom_sale_price']    = 0;
								// $cart_item_data['custom_name'] = $_POST['_nyb_bedding_name'];
								// $cart_item_data['custom_size'] = $_POST['_nyb_bedding_size'];
								// $cart_item_data['custom_unique_key'] = md5(microtime().rand());
								$cart_item['data']->set_price((float) $cart_item['_nyb_bedding_value']);
								$cart_item['data']->set_name($cart_item['_nyb_bedding_name']);
						}
				}
				// ['_nyb_bedding_value'] ), $context );
				// if ($product_id == $system_id && isset($_POST['_nyb_bedding_name']) && isset($_POST['_nyb_bedding_value'])) {
				// 		nyb_log( sprintf( "[活動5] 添加購物車項目數據 | 商品名稱: %s, 商品價值: %s", $_POST['_nyb_bedding_name'], $_POST['_nyb_bedding_value'] ), $context );
				// 		$cart_item_data['custom_data'] = [
				// 				'name'  => sanitize_text_field($_POST['_nyb_bedding_name']),
				// 				'price' => $_POST['_nyb_bedding_value'],
				// 				'unique_key' => md5(microtime().rand())
				// 		];
				// }
				return $cart_item_data;
    }

    /**
     * 從session恢復購物車項目
     */
    public static function get_cart_item_from_session( $cart_item, $values ) {
        if ( isset( $values['_nyb_virtual_bedding'] ) && $values['_nyb_virtual_bedding'] === true ) {
            // 恢復虛擬商品數據
            $cart_item['_nyb_virtual_bedding'] = $values['_nyb_virtual_bedding'];
            $cart_item['_nyb_bedding_name'] = $values['_nyb_bedding_name'] ?? '天絲四件組床包';
            $cart_item['_nyb_bedding_size'] = $values['_nyb_bedding_size'] ?? '';
            $cart_item['_nyb_bedding_value'] = $values['_nyb_bedding_value'] ?? 0;
            $cart_item['_nyb_activity_type'] = $values['_nyb_activity_type'] ?? '';
            $cart_item['_nyb_auto_gift'] = $values['_nyb_auto_gift'] ?? '';

            // 創建虛擬商品對象
            $virtual_product = new WC_Product_Simple();
            $virtual_product->set_id( self::VIRTUAL_PRODUCT_ID );
            $virtual_product->set_name( $cart_item['_nyb_bedding_name'] );
            $virtual_product->set_price( 0 );
            $virtual_product->set_regular_price( $cart_item['_nyb_bedding_value'] );
            $virtual_product->set_virtual( true );

            $cart_item['data'] = $virtual_product;
        }

        return $cart_item;
    }

    /**
     * 處理虛擬商品添加到購物車
     */
    public static function handle_virtual_product_add( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        if ( $product_id == self::VIRTUAL_PRODUCT_ID ) {
            $cart = WC()->cart;
            $cart_item = $cart->get_cart_item( $cart_item_key );

            if ( $cart_item && isset( $cart_item_data['_nyb_virtual_bedding'] ) ) {
                // 創建虛擬商品對象
                $virtual_product = new WC_Product_Simple();
                $virtual_product->set_id( self::VIRTUAL_PRODUCT_ID );
                $virtual_product->set_name( $cart_item_data['_nyb_bedding_name'] ?? '天絲四件組床包' );
                $virtual_product->set_price( 0 );
                $virtual_product->set_regular_price( $cart_item_data['_nyb_bedding_value'] ?? 0 );
                $virtual_product->set_virtual( true );

                // 更新購物車項目
                $cart->cart_contents[ $cart_item_key ]['data'] = $virtual_product;
            }
        }
    }

    /**
     * 設置虛擬商品價格為0
     */
    public static function set_virtual_product_price( $cart ) {
				if (is_admin() && !defined('DOING_AJAX')) return;
				// if (did_action('woocommerce_before_calculate_totals') >= 2) return;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
					// nyb_log( sprintf( "[活動5] 設置虛擬商品價格為0 | 商品名稱: %s, 商品價值: %s", $cart_item['_nyb_bedding_name'], $cart_item['_nyb_bedding_value'] ), $context );
            if ( isset( $cart_item['_nyb_bedding_name'] ) && isset( $cart_item['_nyb_bedding_value'] ) ) {

								// nyb_log( sprintf( "[活動5] 設置虛擬商品價格為0 | 商品名稱: %s, 商品價值: %s", $cart_item['_nyb_bedding_name'], $cart_item['_nyb_bedding_value'] ), $context );
								// $cart_item['data']->set_name($cart_item['custom_data']['name']);
								// $cart_item['data']->set_regular_price($cart_item['custom_data']['price'])
								// $cart_item['data']->set_name($cart_item['_nyb_bedding_name']);
								$cart_item['data']->set_regular_price((float) $cart_item['_nyb_bedding_value']);
								$cart_item['data']->set_sale_price(0);
								$cart_item['data']->set_price(0);

								$cart_item['data']->add_meta_data( '_is_free_gift', 'yes', true );
								$cart_item['data']->add_meta_data( '_original_price', $cart_item['_nyb_bedding_value'], true );
								// $cart_item['data']->set_sale_price(0);
								// $cart_item['data']->set_price(0);
            }
        }
    }

    /**
     * 創建虛擬商品對象（用於訂單）
     */
    public static function create_virtual_product( $product, $item, $order ) {
        $log_file = WP_CONTENT_DIR . '/newyear-bundle.log';
        $timestamp = current_time('Y-m-d H:i:s');

        error_log( sprintf( "[{$timestamp}] [虛擬商品] create_virtual_product() 開始執行 | product: %s, item: %s, order: %s",
            is_object($product) ? get_class($product) : 'null/false', json_encode($item), is_object($order) ? get_class($order) : 'null/false' ), 3, $log_file );

        if ( isset( $item['_nyb_virtual_bedding'] ) && $item['_nyb_virtual_bedding'] === true ) {
            // 創建一個虛擬簡單產品
            $virtual_product = new WC_Product_Simple();
            $virtual_product->set_id( self::VIRTUAL_PRODUCT_ID );
            $virtual_product->set_name( $item['_nyb_bedding_name'] ?? '天絲四件組床包' );
            $virtual_product->set_price( 0 );
            $virtual_product->set_regular_price( $item['_nyb_bedding_value'] ?? 0 );
            $virtual_product->set_virtual( true );
            $virtual_product->set_downloadable( false );

            return $virtual_product;
        }

        return $product;
    }

    /**
     * 顯示虛擬商品名稱
     */
    public static function display_virtual_product_name( $name, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] === true ) {
            $bedding_name = $cart_item['_nyb_bedding_name'] ?? '天絲四件組床包';
            $size_name = $cart_item['_nyb_bedding_size'] ?? '';

            if ( $size_name ) {
                return sprintf( '%s（%s）', esc_html( $bedding_name ), esc_html( $size_name ) );
            }

            return esc_html( $bedding_name );
        }

        return $name;
    }

    /**
     * 顯示虛擬商品價格
     */
    public static function display_virtual_product_price( $price, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] === true ) {
						$regular_price = wc_get_price_to_display($cart_item['data'], array('price' => $cart_item['_nyb_bedding_value']));
						$sale_price = wc_get_price_to_display($cart_item['data'], array('price' => 0));

            if ( $regular_price > 0 ) {
                return '<del>' . wc_price( $regular_price ) . '</del> <ins>' . wc_price( $sale_price ) . '</ins><br><span style="color: #df565f; font-weight: bold;">🎁 免費贈送</span>';
            }

            return wc_price( $sale_price );
        }
				return $price;
    }

    /**
     * 顯示虛擬商品小計
     */
    public static function display_virtual_product_subtotal( $subtotal, $cart_item, $cart_item_key ) {
				if ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] === true ) {
						$quantity = $cart_item['quantity'];
						$regular_price = (float) $cart_item['_nyb_bedding_value'];
						$sale_price = 0; // 假設售價固定為0

						$regular_subtotal = $regular_price * $quantity;
						$sale_subtotal = $sale_price * $quantity;

						if ( $regular_subtotal > 0 ) {
								$formatted_regular = wc_price( wc_get_price_to_display( $cart_item['data'], array( 'price' => $regular_subtotal ) ) );
								$formatted_sale = wc_price( wc_get_price_to_display( $cart_item['data'], array( 'price' => $sale_subtotal ) ) );
								return '<del>' . $formatted_regular . '</del> <ins>' . $formatted_sale . '</ins>';
						}

						return wc_price( $sale_subtotal );
				}
				return $subtotal;
    }

    /**
     * 禁用數量輸入
     */
    public static function disable_quantity_input( $product_quantity, $cart_item_key, $cart_item ) {
        if ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] === true ) {
            return '<span class="quantity" style="color: #999;">1 <small>(贈品，不可修改)</small></span>';
        }

        return $product_quantity;
    }

    /**
     * 添加贈品樣式類別
     */
    public static function add_gift_class( $class, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] === true ) {
            $class .= ' nyb-gift-item';
        }

        return $class;
    }

    /**
     * 保存到訂單
     */
    public static function save_to_order( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['_nyb_virtual_bedding'] ) && $values['_nyb_virtual_bedding'] === true ) {
            $item->add_meta_data( '贈品', '免費贈送 🎁', true );
            $item->add_meta_data( '尺寸', $values['_nyb_bedding_size'] ?? '依床墊尺寸', true );
            $item->add_meta_data( '_gift_original_price', $values['_nyb_bedding_value'] ?? 0, true );
            $item->add_meta_data( '_nyb_virtual_bedding', 'yes', true );
            $item->add_meta_data( '_nyb_activity_type', $values['_nyb_activity_type'] ?? '', true );
						$item->set_name('天絲四件組床包');
						$item->set_regular_price($values['_nyb_bedding_value'] ?? 0);
						$item->set_subtotal( $values['_nyb_bedding_value'] ?? 0 );
						$item->set_total( $values['_nyb_bedding_value'] ?? 0 );
						$item->set_price(0);
						$item->set_virtual(true);
						$item->set_downloadable(false);
        }
    }

    /**
     * 隱藏移除連結
     */
    public static function hide_remove_link( $link, $cart_item_key ) {
        $cart = WC()->cart;
        $cart_item = $cart->get_cart_item( $cart_item_key );

        if ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] === true ) {
            return '<span style="color: #999; font-size: 12px;">自動贈品</span>';
        }

        return $link;
    }

    /**
     * 添加虛擬床包商品到購物車
     *
     * @param WC_Cart $cart 購物車對象
     * @param int $mattress_var_id 床墊變體ID
     * @param string $activity_type 活動類型 (bundle5 或 bundle7)
     * @return string|false 購物車項目key或false
     */
    public static function add_to_cart( $cart, $mattress_var_id, $activity_type ) {
				$bedding_value_map = NYB_BEDDING_VALUE_MAP;
				if ( ! isset( $bedding_value_map[ $mattress_var_id ] ) ) return false;

				$bedding_value = $bedding_value_map[ $mattress_var_id ];
				$size_name = self::get_size_name( $mattress_var_id );

				foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
						if ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_activity_type'] === $activity_type ) {
								return $cart_item_key;
						}
				}

				$placeholder_id = self::bt_get_system_placeholder_id();
				$cart_item_data = [
						// 'custom_name' => '天絲四件組床包',
						// 'custom_price' => $bedding_value,
						// 'custom_size' => $size_name,
						'_nyb_virtual_bedding' => true,
						'_nyb_bedding_name'    => '天絲四件組床包',
						'_nyb_bedding_size'    => $size_name,
						'_nyb_bedding_value'   => $bedding_value,
						'_nyb_activity_type'   => $activity_type,
						'_nyb_auto_gift'       => $activity_type,
						'_is_free_gift'        => 'yes',
						'unique_key'           => md5($activity_type . $mattress_var_id) // 確保唯一性
				];

				$result = $cart->add_to_cart( $placeholder_id, 1, 0, array(), $cart_item_data );

				return $result;
		}

    /**
     * 從購物車移除虛擬床包商品
     *
     * @param WC_Cart $cart 購物車對象
     * @param string $activity_type 活動類型 (bundle5 或 bundle7)
     */
    public static function remove_from_cart( $cart, $activity_type ) {
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['_nyb_virtual_bedding'] ) &&
                 $cart_item['_nyb_activity_type'] === $activity_type ) {
                $cart->remove_cart_item( $cart_item_key );
            }
        }
    }

    /**
     * 獲取床墊尺寸名稱
     *
     * @param int $variation_id 變體ID
     * @return string 尺寸名稱
		 *
     */
    private static function get_size_name( $variation_id ) {
        $size_map = [
            2735 => '單人',
            4371 => '單人',
						3445 => '單人',
						3695 => '單人',
						4929 => '單人',
						4422 => '單人',
            2736 => '單人加大',
            4372 => '單人加大',
						3446 => '單人加大',
						3696 => '單人加大',
						4930 => '單人加大',
						4423 => '單人加大',
            2737 => '雙人',
            4373 => '雙人',
						3447 => '雙人',
						4424 => '雙人',
            2738 => '雙人加大',
            4374 => '雙人加大',
						3448 => '雙人加大',
						4425 => '雙人加大',
            2739 => '雙人特大',
            4375 => '雙人特大',
						4426 => '雙人特大',
        ];

        return isset( $size_map[ $variation_id ] ) ? $size_map[ $variation_id ] : '標準';
    }
}

// 初始化虛擬商品類
NYB_Virtual_Bedding_Product::init();