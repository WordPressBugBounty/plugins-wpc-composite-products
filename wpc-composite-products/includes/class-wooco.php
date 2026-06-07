<?php
defined( 'ABSPATH' ) || exit;


if ( ! class_exists( 'WPCleverWooco' ) && class_exists( 'WC_Product' ) ) {
    class WPCleverWooco {
        protected static string $image_size = 'woocommerce_thumbnail';
        protected static ?self $instance = null;

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function __construct() {
            WPCleverWooco_Helper::init();

            // Init
            add_action( 'init', [ $this, 'init' ] );

            // Enqueue frontend scripts
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

            // AJAX gallery
            // Note: Keep it here as it might be used on frontend product page
            add_action( 'wc_ajax_wooco_load_gallery', [ $this, 'ajax_load_gallery' ] );

            // Add to a cart form & button
            add_action( 'woocommerce_composite_add_to_cart', [ $this, 'add_to_cart_form' ] );
            add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'add_to_cart_button' ] );

            // Add to the cart
            // Ensure it runs before WPC Frequently Bought Together (priority: 10)
            add_filter( 'woocommerce_add_to_cart_sold_individually_found_in_cart', [ $this, 'found_in_cart' ], 9, 2 );
            add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'add_to_cart_validation' ], 9, 3 );
            add_action( 'woocommerce_add_to_cart', [ $this, 'add_to_cart' ], 9, 6 );
            add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 9, 2 );
            add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'get_cart_item_from_session' ], 9, 2 );

            // Undo remove
            add_action( 'woocommerce_restore_cart_item', [ $this, 'restore_cart_item' ] );

            // Cart item
            add_filter( 'woocommerce_cart_item_name', [ $this, 'cart_item_name' ], 10, 2 );
            add_filter( 'woocommerce_cart_item_quantity', [ $this, 'cart_item_quantity' ], 10, 3 );
            add_filter( 'woocommerce_cart_item_remove_link', [ $this, 'cart_item_remove_link' ], 10, 2 );
            add_filter( 'woocommerce_cart_contents_count', [ $this, 'cart_contents_count' ] );
            add_action( 'woocommerce_cart_item_removed', [ $this, 'cart_item_removed' ], 10, 2 );
            add_filter( 'woocommerce_cart_item_price', [ $this, 'cart_item_price' ], 10, 2 );
            add_filter( 'woocommerce_cart_item_subtotal', [ $this, 'cart_item_subtotal' ], 10, 2 );

            // Edit link
            add_action( 'woocommerce_after_cart_item_name', [ $this, 'cart_item_edit' ], 10, 2 );

            // Hide on cart & checkout page
            if ( WPCleverWooco_Helper::get_setting( 'hide_component', 'no' ) !== 'no' ) {
                add_filter( 'woocommerce_cart_item_visible', [ $this, 'cart_item_visible' ], 10, 2 );
                add_filter( 'woocommerce_checkout_cart_item_visible', [ $this, 'cart_item_visible' ], 10, 2 );
            }

            // Hide on a mini-cart
            if ( WPCleverWooco_Helper::get_setting( 'hide_component_mini_cart', 'no' ) === 'yes' ) {
                add_filter( 'woocommerce_widget_cart_item_visible', [ $this, 'cart_item_visible' ], 10, 2 );
            }

            // Hide on order details
            if ( WPCleverWooco_Helper::get_setting( 'hide_component_order', 'no' ) !== 'no' ) {
                add_filter( 'woocommerce_order_item_visible', [ $this, 'order_item_visible' ], 10, 2 );
            }

            // Item class
            if ( WPCleverWooco_Helper::get_setting( 'hide_component', 'no' ) !== 'yes' ) {
                add_filter( 'woocommerce_cart_item_class', [ $this, 'cart_item_class' ], 10, 2 );
                add_filter( 'woocommerce_mini_cart_item_class', [ $this, 'cart_item_class' ], 10, 2 );
                add_filter( 'woocommerce_order_item_class', [ $this, 'cart_item_class' ], 10, 2 );
            }

            // Get item data
            if ( WPCleverWooco_Helper::get_setting( 'hide_component', 'no' ) === 'yes_text' || WPCleverWooco_Helper::get_setting( 'hide_component', 'no' ) === 'yes_list' ) {
                add_filter( 'woocommerce_get_item_data', [ $this, 'cart_item_meta' ], 10, 2 );
            }

            // Hide item meta
            add_filter( 'woocommerce_order_item_get_formatted_meta_data', [
                    $this,
                    'order_item_get_formatted_meta_data'
            ] );

            // Order item
            add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 3 );
            add_filter( 'woocommerce_order_item_name', [ $this, 'cart_item_name' ], 10, 2 );
            add_filter( 'woocommerce_order_formatted_line_subtotal', [ $this, 'formatted_line_subtotal' ], 10, 2 );

            if ( WPCleverWooco_Helper::get_setting( 'hide_component_order', 'no' ) === 'yes_text' || WPCleverWooco_Helper::get_setting( 'hide_component_order', 'no' ) === 'yes_list' ) {
                add_action( 'woocommerce_order_item_meta_start', [ $this, 'order_item_meta_start' ], 10, 2 );
            }

            // Loop add-to-cart
            add_filter( 'woocommerce_loop_add_to_cart_link', [ $this, 'loop_add_to_cart_link' ], 10, 2 );

            // Calculate price
            add_action( 'woocommerce_before_mini_cart_contents', [ $this, 'before_mini_cart_contents' ], 9999 );
            add_action( 'woocommerce_before_calculate_totals', [ $this, 'before_calculate_totals' ], 9999 );

            // Shipping
            add_filter( 'woocommerce_cart_shipping_packages', [ $this, 'cart_shipping_packages' ] );

            // Price HTML
            add_filter( 'woocommerce_get_price_html', [ $this, 'get_price_html' ], 99, 2 );

            // Price class
            add_filter( 'woocommerce_product_price_class', [ $this, 'product_price_class' ] );

            // Order again
            add_filter( 'woocommerce_order_again_cart_item_data', [ $this, 'order_again_cart_item_data' ], 10, 2 );
            add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'cart_loaded_from_session' ] );

            // Coupons
            add_filter( 'woocommerce_coupon_is_valid_for_product', [ $this, 'coupon_is_valid_for_product' ], 10, 4 );

            // WPC Smart Messages
            add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );

            // Nonce check
            add_filter( 'wooco_disable_nonce_check', function ( $check, $context ) {
                return apply_filters( 'wooco_disable_security_check', $check, $context );
            }, 10, 2 );
        }

        public function ajax_load_gallery() {
            if ( ! apply_filters( 'wooco_disable_nonce_check', false, 'load_gallery' ) ) {
                if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wooco_nonce' ) ) {
                    die( 'Permissions check failed!' );
                }
            }

            if ( empty( $_POST['ids'] ) || ! is_array( $_POST['ids'] ) ) {
                wp_send_json_error();
            }

            $main_product_id = absint( $_POST['product_id'] ?? 0 );
            $key             = sanitize_text_field( $_POST['key'] ?? '' );
            $image_ids       = [];

            foreach ( $_POST['ids'] as $id ) {
                $id_arr = explode( '/', $id );
                $_id    = absint( $id_arr[0] ?? 0 );

                if ( $_id && ( $_product = wc_get_product( $_id ) ) ) {
                    if ( $_product_image = $_product->get_image_id() ) {
                        $image_ids[] = $_product_image;
                    }

                    if ( is_a( $_product, 'WC_Product_Variation' ) ) {
                        if ( apply_filters( 'wooco_gallery_include_product_images', false, $_product ) ) {
                            // get images from WPC Additional Variation Images
                            $_images = array_filter( explode( ',', get_post_meta( $_id, 'wpcvi_images', true ) ) );

                            if ( ! empty( $_images ) ) {
                                $image_ids = array_merge( $image_ids, $_images );
                            }
                        }

                        if ( apply_filters( 'wooco_gallery_include_variable_featured', false, $_product ) ) {
                            // get featured from parent variable product
                            $_parent_id = $_product->get_parent_id();

                            if ( $_parent_id && ( $_parent = wc_get_product( $_parent_id ) ) && ( $_parent_image = $_parent->get_image_id() ) ) {
                                $image_ids[] = $_parent_image;
                            }
                        }

                        if ( apply_filters( 'wooco_gallery_include_variable_images', false, $_product ) ) {
                            // get images from parent variable product
                            $_parent_id = $_product->get_parent_id();

                            if ( $_parent_id && ( $_parent = wc_get_product( $_parent_id ) ) && ( $_parent_images = $_parent->get_gallery_image_ids() ) ) {
                                if ( ! empty( $_parent_images ) && is_array( $_parent_images ) ) {
                                    $image_ids = array_merge( $image_ids, $_parent_images );
                                }
                            }
                        }
                    } else {
                        if ( apply_filters( 'wooco_gallery_include_product_images', false, $_product ) ) {
                            $_images = $_product->get_gallery_image_ids();

                            if ( ! empty( $_images ) && is_array( $_images ) ) {
                                $image_ids = array_merge( $image_ids, $_images );
                            }
                        }
                    }
                }
            }

            $include_main_images   = apply_filters( 'wooco_gallery_include_main_images', false, $main_product_id );
            $include_main_featured = apply_filters( 'wooco_gallery_include_main_featured', false, $main_product_id );

            if ( ( $include_main_images || $include_main_featured ) && ( $main_product = wc_get_product( $main_product_id ) ) ) {
                if ( $include_main_images ) {
                    $main_images = $main_product->get_gallery_image_ids();

                    if ( ! empty( $main_images ) && is_array( $main_images ) ) {
                        $image_ids = array_merge( $main_images, $image_ids );
                    }
                }

                if ( $include_main_featured ) {
                    $main_featured = $main_product->get_image_id();

                    if ( ! empty( $main_featured ) ) {
                        array_unshift( $image_ids, $main_featured );
                    }
                }
            }

            // remove duplicated images
            $image_ids = array_unique( $image_ids );

            if ( empty( $image_ids ) ) {
                wp_send_json_error();
            }

            $gallery_class = apply_filters( 'wooco_gallery_class', 'woocommerce-product-gallery woocommerce-product-gallery--wooco woocommerce-product-gallery--wooco-' . esc_attr( $key ) . ' woocommerce-product-gallery--wooco-' . absint( $main_product_id ) . ' woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-' . esc_attr( apply_filters( 'woocommerce_product_thumbnails_columns', 4 ) ) . ' images', $image_ids, $main_product_id );
            $gallery_html  = '<div class="' . esc_attr( $gallery_class ) . '" data-columns="' . esc_attr( apply_filters( 'woocommerce_product_thumbnails_columns', 4 ) ) . '" style="opacity: 0; transition: opacity .25s ease-in-out;">';
            $gallery_html  .= apply_filters( 'wooco_gallery_before', '', $image_ids, $main_product_id );
            $gallery_html  .= '<figure class="woocommerce-product-gallery__wrapper">';

            foreach ( $image_ids as $id ) {
                $gallery_html .= apply_filters( 'woocommerce_single_product_image_thumbnail_html', wc_get_gallery_image_html( $id ), $id );
            }

            $gallery_html .= '</figure>';
            $gallery_html .= apply_filters( 'wooco_gallery_after', '', $image_ids, $main_product_id );
            $gallery_html .= '</div>';

            wp_send_json( [ 'gallery' => apply_filters( 'wooco_gallery', $gallery_html, $image_ids, $main_product_id ) ] );
        }

        public function init() {

            // image size
            self::$image_size = apply_filters( 'wooco_image_size', self::$image_size );
        }


        public function enqueue_scripts() {
            if ( WPCleverWooco_Helper::get_setting( 'selector', 'ddslick' ) === 'ddslick' ) {
                wp_enqueue_script( 'ddslick', WOOCO_URI . 'assets/libs/ddslick/jquery.ddslick.min.js', [ 'jquery' ], WOOCO_VERSION, true );
            }

            if ( WPCleverWooco_Helper::get_setting( 'selector', 'ddslick' ) === 'select2' ) {
                wp_enqueue_style( 'select2' );
                wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', [ 'jquery' ], WOOCO_VERSION, true );
            }

            wp_enqueue_style( 'wooco-frontend', WOOCO_URI . 'assets/css/frontend.css', [], WOOCO_VERSION );
            wp_enqueue_script( 'wooco-frontend', WOOCO_URI . 'assets/js/frontend.js', [
                    'jquery',
                    'imagesloaded'
            ], WOOCO_VERSION, true );
            wp_localize_script(
                    'wooco-frontend',
                    'wooco_vars',
                    apply_filters( 'wooco_vars', [
                            'wc_ajax_url'              => WC_AJAX::get_endpoint( '%%endpoint%%' ),
                            'nonce'                    => wp_create_nonce( 'wooco_nonce' ),
                            'price_decimals'           => wc_get_price_decimals(),
                            'price_format'             => get_woocommerce_price_format(),
                            'price_thousand_separator' => wc_get_price_thousand_separator(),
                            'price_decimal_separator'  => wc_get_price_decimal_separator(),
                            'currency_symbol'          => get_woocommerce_currency_symbol(),
                            'trim_zeros'               => apply_filters( 'woocommerce_price_trim_zeros', false ),
                            'quickview_variation'      => apply_filters( 'wooco_quickview_variation', 'default' ),
                            'gallery_selector'         => apply_filters( 'wooco_gallery_selector', '.woocommerce-product-gallery' ),
                            'main_gallery_selector'    => apply_filters( 'wooco_main_gallery_selector', '.woocommerce-product-gallery:not(.woocommerce-product-gallery--wooco)' ),
                            'selector'                 => WPCleverWooco_Helper::get_setting( 'selector', 'ddslick' ),
                            'change_image'             => WPCleverWooco_Helper::get_setting( 'change_image', 'yes' ),
                            'change_price'             => WPCleverWooco_Helper::get_setting( 'change_price', 'yes' ),
                            'price_selector'           => WPCleverWooco_Helper::get_setting( 'change_price_custom', '' ),
                            'product_link'             => WPCleverWooco_Helper::get_setting( 'product_link', 'no' ),
                            'show_alert'               => WPCleverWooco_Helper::get_setting( 'show_alert', 'load' ),
                            'hide_component_name'      => WPCleverWooco_Helper::get_setting( 'hide_component_name', 'yes' ),
                            'total_text'               => WPCleverWooco_Helper::localization( 'total', esc_html__( 'Total price:', 'wpc-composite-products' ) ),
                            'selected_text'            => WPCleverWooco_Helper::localization( 'selected', esc_html__( 'Selected:', 'wpc-composite-products' ) ),
                            'saved_text'               => WPCleverWooco_Helper::localization( 'saved', esc_html__( '(saved [d])', 'wpc-composite-products' ) ),
                            'alert_min'                => WPCleverWooco_Helper::localization( 'alert_min', esc_html__( 'Please choose at least a total quantity of [min] products before adding this composite to the cart.', 'wpc-composite-products' ) ),
                            'alert_max'                => WPCleverWooco_Helper::localization( 'alert_max', esc_html__( 'Sorry, you can only choose at max a total quantity of [max] products before adding this composite to the cart.', 'wpc-composite-products' ) ),
                            'alert_m_min'              => WPCleverWooco_Helper::localization( 'alert_m_min', esc_html__( 'Please choose at least a total quantity of [min] products for the component [name].', 'wpc-composite-products' ) ),
                            'alert_m_max'              => WPCleverWooco_Helper::localization( 'alert_m_max', esc_html__( 'Sorry, you can only choose at max a total quantity of [max] products for the component [name].', 'wpc-composite-products' ) ),
                            'alert_same'               => WPCleverWooco_Helper::localization( 'alert_same', esc_html__( 'Please select a different product for each component.', 'wpc-composite-products' ) ),
                            'alert_selection'          => WPCleverWooco_Helper::localization( 'alert_selection', esc_html__( 'Please choose a purchasable product for the component [name] before adding this composite to the cart.', 'wpc-composite-products' ) ),
                            'alert_total_min'          => WPCleverWooco_Helper::localization( 'alert_total_min', esc_html__( 'The total must meet the minimum amount of [min].', 'wpc-composite-products' ) ),
                            'alert_total_max'          => WPCleverWooco_Helper::localization( 'alert_total_max', esc_html__( 'The total must meet the maximum amount of [max].', 'wpc-composite-products' ) )
                    ] )
            );
        }


        public function cart_contents_count( $count ) {
            $cart_contents_count = WPCleverWooco_Helper::get_setting( 'cart_contents_count', 'composite' );

            if ( $cart_contents_count !== 'both' ) {
                $cart_contents = WC()->cart->cart_contents;

                foreach ( $cart_contents as $cart_item ) {
                    if ( ( $cart_contents_count === 'component_products' ) && ! empty( $cart_item['wooco_ids'] ) ) {
                        $count -= $cart_item['quantity'];
                    }

                    if ( ( $cart_contents_count === 'composite' ) && ! empty( $cart_item['wooco_parent_id'] ) ) {
                        $count -= $cart_item['quantity'];
                    }
                }
            }

            return $count;
        }

        public function cart_item_name( $name, $item ) {
            if ( ! empty( $item['wooco_parent_id'] ) ) {
                if ( ( WPCleverWooco_Helper::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['wooco_component'] ) ) {
                    $_name = $item['wooco_component'] . ': ' . $name;
                } else {
                    $_name = $name;
                }

                if ( WPCleverWooco_Helper::get_setting( 'hide_composite_name', 'no' ) === 'no' ) {
                    if ( $parent_product = wc_get_product( $item['wooco_parent_id'] ) ) {
                        if ( str_contains( $name, '</a>' ) ) {
                            $_name = '<a href="' . get_permalink( $item['wooco_parent_id'] ) . '">' . $parent_product->get_name() . '</a>' . apply_filters( 'wooco_name_separator', ' &rarr; ' ) . $_name;
                        } else {
                            $_name = $parent_product->get_name() . apply_filters( 'wooco_name_separator', ' &rarr; ' ) . $_name;
                        }
                    }
                }

                return apply_filters( 'wooco_cart_item_name', $_name, $name, $item );
            }

            return $name;
        }

        public function formatted_line_subtotal( $subtotal, $item ) {
            if ( ! empty( $item['wooco_ids'] ) && isset( $item['wooco_price'] ) && ( $item['wooco_price'] !== '' ) ) {
                return apply_filters( 'wooco_order_item_subtotal', wc_price( (float) $item['wooco_price'] * $item['quantity'] ), $subtotal, $item );
            }

            return $subtotal;
        }

        public function cart_item_price( $price, $cart_item ) {
            if ( isset( $cart_item['wooco_ids'], $cart_item['wooco_keys'], $cart_item['wooco_price'] ) && method_exists( $cart_item['data'], 'get_pricing' ) && ( $cart_item['data']->get_pricing() !== 'only' ) ) {
                // composite
                return apply_filters( 'wooco_cart_item_price', wc_price( $cart_item['wooco_price'] ), $price, $cart_item );
            }

            if ( isset( $cart_item['wooco_parent_key'] ) ) {
                // component products
                $cart_parent_key = $cart_item['wooco_parent_key'];

                if ( isset( WC()->cart->cart_contents[ $cart_parent_key ] ) && method_exists( WC()->cart->cart_contents[ $cart_parent_key ]['data'], 'get_pricing' ) && ( WC()->cart->cart_contents[ $cart_parent_key ]['data']->get_pricing() === 'only' ) ) {
                    // return original price when pricing is only
                    $item_product = wc_get_product( $cart_item['data']->get_id() );

                    return apply_filters( 'wooco_cart_item_price', wc_price( wc_get_price_to_display( $item_product ) ), $price, $cart_item );
                }
            }

            return $price;
        }

        public function cart_item_subtotal( $subtotal, $cart_item = null ) {
            if ( isset( $cart_item['wooco_ids'], $cart_item['wooco_keys'], $cart_item['wooco_price'] ) && method_exists( $cart_item['data'], 'get_pricing' ) && ( $cart_item['data']->get_pricing() !== 'only' ) ) {
                // composite
                return apply_filters( 'wooco_cart_item_subtotal', wc_price( $cart_item['wooco_price'] * $cart_item['quantity'] ), $subtotal, $cart_item );
            }

            if ( isset( $cart_item['wooco_parent_key'] ) ) {
                // component products
                $cart_parent_key = $cart_item['wooco_parent_key'];

                if ( isset( WC()->cart->cart_contents[ $cart_parent_key ] ) && method_exists( WC()->cart->cart_contents[ $cart_parent_key ]['data'], 'get_pricing' ) && ( WC()->cart->cart_contents[ $cart_parent_key ]['data']->get_pricing() === 'only' ) ) {
                    // return the original price when pricing is only
                    $item_product = wc_get_product( $cart_item['data']->get_id() );

                    return apply_filters( 'wooco_cart_item_subtotal', wc_price( wc_get_price_to_display( $item_product, [ 'qty' => $cart_item['quantity'] ] ) ), $subtotal, $cart_item );
                }
            }

            return $subtotal;
        }

        public function cart_item_edit( $cart_item, $cart_item_key ) {
            $edit_link = WPCleverWooco_Helper::get_setting( 'edit_link', 'no' ) === 'yes';

            if ( ! $edit_link ) {
                return;
            }

            if ( ! empty( $cart_item['wooco_ids'] ) ) {
                $edit_url  = apply_filters( 'wooco_cart_item_edit_url', add_query_arg( [
                        'edit' => base64_encode( $cart_item['wooco_ids'] ),
                        'key'  => $cart_item_key
                ], $cart_item['data']->get_permalink() ), $cart_item, $cart_item_key );
                $edit_link = ' <a class="wooco-cart-item-edit" href="' . esc_url( $edit_url ) . '">' . esc_html( WPCleverWooco_Helper::localization( 'cart_item_edit', esc_html__( 'Edit', 'wpc-composite-products' ) ) ) . '</a>';

                echo apply_filters( 'wooco_cart_item_edit_link', $edit_link, $cart_item, $cart_item_key );
            }
        }

        public function cart_item_removed( $cart_item_key, $cart ) {
            $new_keys = [];

            foreach ( $cart->cart_contents as $cart_k => $cart_i ) {
                if ( ! empty( $cart_i['wooco_key'] ) ) {
                    $new_keys[ $cart_k ] = $cart_i['wooco_key'];
                }
            }

            if ( isset( $cart->removed_cart_contents[ $cart_item_key ]['wooco_keys'] ) ) {
                // remove all component products when removing the main composite
                $keys = $cart->removed_cart_contents[ $cart_item_key ]['wooco_keys'];

                foreach ( $keys as $key ) {
                    WC()->cart->remove_cart_item( $key );

                    if ( $new_key = array_search( $key, $new_keys ) ) {
                        WC()->cart->remove_cart_item( $new_key );
                    }
                }
            }

            if ( isset( $cart->removed_cart_contents[ $cart_item_key ]['wooco_parent_key'] ) ) {
                // remove the main composite when removing any component product
                $parent_key = $cart->removed_cart_contents[ $cart_item_key ]['wooco_parent_key'];

                WC()->cart->remove_cart_item( $parent_key );
            }
        }

        public function check_in_cart( $product_id ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                if ( $cart_item['product_id'] === $product_id ) {
                    return true;
                }
            }

            return false;
        }

        public function add_cart_item_data( $cart_item_data, $product_id ) {
            $_product = wc_get_product( $product_id );

            if ( $_product && $_product->is_type( 'composite' ) && $_product->get_components() ) {
                // make sure this is a composite
                $ids = '';

                if ( isset( $_REQUEST['wooco_ids'] ) ) {
                    $ids = WPCleverWooco_Helper::clean_ids( sanitize_text_field( $_REQUEST['wooco_ids'] ) );
                    unset( $_REQUEST['wooco_ids'] );
                }

                if ( ! empty( $ids ) ) {
                    $cart_item_data['wooco_ids'] = $ids;
                }
            }

            return $cart_item_data;
        }

        public function found_in_cart( $found_in_cart, $product_id ) {
            if ( apply_filters( 'wooco_sold_individually_found_in_cart', true ) && self::check_in_cart( $product_id ) ) {
                return true;
            }

            return $found_in_cart;
        }

        public function add_to_cart_validation( $passed, $product_id, $qty ) {
            if ( ( $_product = wc_get_product( $product_id ) ) && $_product->is_type( 'composite' ) && ( $components = $_product->get_components() ) ) {
                $ids   = '';
                $items = [];

                if ( isset( $_REQUEST['wooco_ids'] ) ) {
                    $ids = WPCleverWooco_Helper::clean_ids( sanitize_text_field( $_REQUEST['wooco_ids'] ) );
                }

                if ( ! empty( $ids ) && ( $items = WPCleverWooco_Helper::get_items( $ids ) ) ) {
                    foreach ( $items as $item ) {
                        $item_id      = $item['id'];
                        $item_key     = $item['key'];
                        $item_qty     = $item['qty'];
                        $item_product = wc_get_product( $item_id );

                        if ( ! isset( $components[ $item_key ] ) ) {
                            wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                            return false;
                        }

                        if ( ! $item_product ) {
                            wc_add_notice( esc_html__( 'One of the component products is unavailable.', 'wpc-composite-products' ), 'error' );
                            wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                            return false;
                        }

                        if ( $item_product->is_type( 'woosb' ) ) {
                            $bundle_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $item_id, $qty * $item_qty );

                            if ( ! $bundle_validation ) {
                                wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" is un-purchasable.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
                                wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                                return false;
                            }
                        }

                        if ( $item_product->is_type( 'variation' ) ) {
                            $attributes = $item_product->get_variation_attributes();

                            foreach ( $attributes as $attribute ) {
                                if ( empty( $attribute ) ) {
                                    wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" is un-purchasable.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
                                    wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                                    return false;
                                }
                            }
                        }

                        if ( $item_product->is_type( 'variable' ) || ( $item_product->is_type( 'composite' ) && ! apply_filters( 'wooco_allow_composite_product', false ) ) ) {
                            wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" is un-purchasable.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
                            wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                            return false;
                        }

                        if ( ! $item_product->is_in_stock() || ! $item_product->is_purchasable() ) {
                            wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" is un-purchasable.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
                            wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                            return false;
                        }

                        if ( isset( $components[ $item_key ]['custom_qty'] ) && ( $components[ $item_key ]['custom_qty'] === 'yes' ) ) {
                            // custom qty
                            if ( ! empty( $components[ $item_key ]['min'] ) && ( (float) $item['qty'] < (float) $components[ $item_key ]['min'] ) ) {
                                wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                                return false;
                            }

                            if ( ! empty( $components[ $item_key ]['max'] ) && ( (float) $item['qty'] > (float) $components[ $item_key ]['max'] ) ) {
                                wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                                return false;
                            }
                        } else {
                            // fixed qty
                            if ( isset( $components[ $item_key ]['qty'] ) && ( $components[ $item_key ]['qty'] != $item['qty'] ) ) {
                                wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                                return false;
                            }
                        }

                        if ( $item_product->is_sold_individually() && apply_filters( 'wooco_sold_individually_found_in_cart', true ) && self::check_in_cart( $item['id'] ) ) {
                            wc_add_notice( sprintf( /* translators: product name */ esc_html__( 'You cannot add another "%s" to your cart.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
                            wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                            return false;
                        }

                        if ( $item_product->managing_stock() ) {
                            $qty_in_cart  = ( $quantities = WC()->cart->get_cart_item_quantities() ) && isset( $quantities[ $item_product->get_stock_managed_by_id() ] ) ? $quantities[ $item_product->get_stock_managed_by_id() ] : 0;
                            $qty_to_check = 0;
                            $_items       = $items; // reuse $items already parsed above, avoid re-parsing

                            foreach ( $_items as $_item ) {
                                if ( $_item['id'] == $item_id ) {
                                    $qty_to_check += $_item['qty'];
                                }
                            }

                            if ( ! $item_product->has_enough_stock( $qty_in_cart + $qty_to_check * $qty ) ) {
                                wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" has not enough stock.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
                                wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                                return false;
                            }
                        }

                        if ( post_password_required( $item['id'] ) ) {
                            wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" is protected and cannot be purchased.', 'wpc-composite-products' ), esc_html( $item_product->get_name() ) ), 'error' );
                            wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                            return false;
                        }
                    }
                }

                // check required
                foreach ( $components as $ck => $component ) {
                    if ( isset( $component['optional'] ) && ( $component['optional'] === 'no' ) ) {
                        if ( empty( $items ) || ! in_array( $ck, array_column( $items, 'key' ) ) ) {
                            wc_add_notice( esc_html__( 'Missing a required component product.', 'wpc-composite-products' ), 'error' );
                            wc_add_notice( esc_html__( 'You cannot add this composite products to the cart.', 'wpc-composite-products' ), 'error' );

                            return false;
                        }
                    }
                }
            }

            return $passed;
        }

        public function add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
            $edit_link = WPCleverWooco_Helper::get_setting( 'edit_link', 'no' ) === 'yes';

            if ( $edit_link && ! empty( $_REQUEST['wooco_update'] ) ) {
                $edit_key = sanitize_key( wp_unslash( $_REQUEST['wooco_update'] ) );

                if ( WC()->cart->get_cart_item( $edit_key ) ) {
                    WC()->cart->remove_cart_item( $edit_key );
                }
            }

            if ( ! empty( $cart_item_data['wooco_ids'] ) && ( $items = WPCleverWooco_Helper::get_items( $cart_item_data['wooco_ids'] ) ) ) {
                self::add_to_cart_items( $items, $cart_item_key, $product_id, $quantity );
            }
        }

        public function restore_cart_item( $cart_item_key ) {
            if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['wooco_ids'] ) ) {
                unset( WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'] );

                $product_id = WC()->cart->cart_contents[ $cart_item_key ]['product_id'];
                $quantity   = WC()->cart->cart_contents[ $cart_item_key ]['quantity'];

                if ( $items = WPCleverWooco_Helper::get_items( WC()->cart->cart_contents[ $cart_item_key ]['wooco_ids'] ) ) {
                    self::add_to_cart_items( $items, $cart_item_key, $product_id, $quantity );
                }
            }
        }

        public function add_to_cart_items( $items, $cart_item_key, $product_id, $quantity ) {
            if ( apply_filters( 'wooco_exclude_components', false ) ) {
                return;
            }

            $separately = apply_filters( 'wooco_add_to_cart_separately', false );

            if ( ( $_product = wc_get_product( $product_id ) ) && $_product->is_type( 'composite' ) && ( $components = $_product->get_components() ) ) {
                // save the current key associated with wooco_parent_key
                WC()->cart->cart_contents[ $cart_item_key ]['wooco_key'] = $cart_item_key;

                // add child products
                $count = 0; // for the same component product

                foreach ( $items as $item ) {
                    $count ++;
                    $item_id  = $item['id'];
                    $item_qty = $item['qty'];
                    $item_key = $item['key'];

                    if ( isset( $components[ $item_key ] ) && ( $item_id > 0 ) && ( $item_qty > 0 ) && ( $item_product = wc_get_product( $item_id ) ) && ( 'trash' !== $item_product->get_status() ) ) {
                        $item_variation_id = 0;
                        $item_variation    = [];

                        if ( $item_product instanceof WC_Product_Variation ) {
                            // ensure we don't add a variation to the cart directly by variation ID
                            $item_variation_id = $item_id;
                            $item_id           = $item_product->get_parent_id();
                            $item_variation    = $item_product->get_variation_attributes();
                        }

                        $item_price = WPCleverWooco_Helper::format_price( $components[ $item_key ]['price'] ?? '100%' );

                        if ( $item_price === 'd' && ! empty( $components[ $item_key ]['default'] ) && ( $default_product = wc_get_product( WPCleverWooco_Helper::get_product_id( $components[ $item_key ]['default'] ) ) ) ) {
                            if ( $default_product->get_id() == $item_id ) {
                                $item_price = 0;
                            } else {
                                $product_price = $item_product->get_price();
                                $default_price = $default_product->get_price();
                                $item_price    = abs( $product_price - $default_price );
                            }
                        }

                        // add to cart
                        if ( ! $separately ) {
                            $item_data = [
                                    'wooco_pos'        => $count,
                                    'wooco_qty'        => $item_qty,
                                    'wooco_price'      => $item_price,
                                    'wooco_component'  => $components[ $item_key ]['name'] ?? '',
                                    'wooco_parent_id'  => $product_id,
                                    'wooco_parent_key' => $cart_item_key
                            ];

                            $item_key = WC()->cart->add_to_cart( $item_id, $item_qty * $quantity, $item_variation_id, $item_variation, $item_data );

                            if ( empty( $item_key ) ) {
                                // can't add the composite product
                                if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'] ) ) {
                                    $keys = WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'];

                                    foreach ( $keys as $key ) {
                                        // remove all components
                                        WC()->cart->remove_cart_item( $key );
                                    }

                                    // remove the composite
                                    WC()->cart->remove_cart_item( $cart_item_key );

                                    // break out of the loop
                                    break;
                                }
                            } elseif ( ! isset( WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'] ) || ! in_array( $item_key, WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'], true ) ) {
                                // save current key
                                WC()->cart->cart_contents[ $item_key ]['wooco_key'] = $item_key;
                                // add keys
                                WC()->cart->cart_contents[ $cart_item_key ]['wooco_keys'][] = $item_key;
                            }
                        } else {
                            // add to cart separately
                            WC()->cart->add_to_cart( $item_id, $item_qty * $quantity, $item_variation_id, $item_variation );
                            // remove main product
                            WC()->cart->remove_cart_item( $cart_item_key );
                        }
                    }
                }
            }
        }

        public function before_mini_cart_contents() {
            WC()->cart->calculate_totals();
        }

        public function before_calculate_totals( $cart_object ) {
            if ( ! defined( 'DOING_AJAX' ) && is_admin() ) {
                // This is necessary for WC 3.0+
                return;
            }

            $cart_contents = $cart_object->cart_contents;
            $new_keys      = [];

            foreach ( $cart_contents as $cart_k => $cart_i ) {
                if ( ! empty( $cart_i['wooco_key'] ) ) {
                    $new_keys[ $cart_k ] = $cart_i['wooco_key'];
                }
            }

            foreach ( $cart_contents as $cart_item_key => $cart_item ) {
                // child product qty
                if ( ! empty( $cart_item['wooco_parent_key'] ) ) {
                    $parent_new_key = array_search( $cart_item['wooco_parent_key'], $new_keys );

                    // remove orphaned components
                    if ( ! $parent_new_key || ! isset( $cart_contents[ $parent_new_key ] ) || ( isset( $cart_contents[ $parent_new_key ]['wooco_keys'] ) && ! in_array( $cart_item_key, $cart_contents[ $parent_new_key ]['wooco_keys'] ) ) ) {
                        unset( $cart_contents[ $cart_item_key ] );
                        continue;
                    }

                    // sync quantity
                    if ( ! empty( $cart_item['wooco_qty'] ) ) {
                        WC()->cart->cart_contents[ $cart_item_key ]['quantity'] = $cart_item['wooco_qty'] * $cart_contents[ $parent_new_key ]['quantity'];
                    }
                }

                // child product price
                if ( ! empty( $cart_item['wooco_parent_id'] ) ) {
                    $_pid = $cart_item['wooco_parent_id'];

                    if ( ! isset( $parent_product_cache[ $_pid ] ) ) {
                        $parent_product_cache[ $_pid ] = wc_get_product( $_pid );
                    }

                    $parent_product = $parent_product_cache[ $_pid ];

                    if ( $parent_product && $parent_product->is_type( 'composite' ) && method_exists( $parent_product, 'get_pricing' ) ) {
                        if ( $parent_product->get_pricing() === 'only' ) {
                            $cart_item['data']->set_price( 0 );
                        } else {
                            $new_price = false;
                            $_product  = apply_filters( 'wooco_product_original', wc_get_product( $cart_item['variation_id'] ?: $cart_item['product_id'] ), $cart_item );
                            $_price    = apply_filters( 'wooco_product_original_price', ( WPCleverWooco_Helper::get_setting( 'product_price', 'sale_price' ) === 'regular_price' ) ? $_product->get_regular_price( 'edit' ) : $_product->get_price( 'edit' ), $cart_item['data'] );

                            if ( isset( $cart_item['wooco_price'] ) && ( $cart_item['wooco_price'] !== '' ) ) {
                                $new_price = true;
                                $_price    = WPCleverWooco_Helper::get_new_price( $_price, $cart_item['wooco_price'] );
                            }

                            if ( $discount = $parent_product->get_discount() ) {
                                $new_price = true;
                                $_price    = $_price * ( 100 - $discount ) / 100;
                            }

                            if ( $new_price ) {
                                // set new price for child product
                                $cart_item['data']->set_price( (float) apply_filters( 'wooco_component_product_price', $_price, $cart_item ) );
                            }
                        }
                    }
                }

                // main product price
                if ( ! empty( $cart_item['wooco_ids'] ) && $cart_item['data']->is_type( 'composite' ) && method_exists( $cart_item['data'], 'get_pricing' ) && ( $cart_item['data']->get_pricing() !== 'only' ) ) {
                    $price = $cart_item['data']->get_pricing() === 'include' ? wc_get_price_to_display( $cart_item['data'], [ 'price' => $cart_item['data']->get_price( 'edit' ) ] ) : 0;

                    if ( ! empty( $cart_item['wooco_keys'] ) ) {
                        foreach ( $cart_item['wooco_keys'] as $key ) {
                            if ( isset( $cart_contents[ $key ] ) ) {
                                $_product = apply_filters( 'wooco_product_original', wc_get_product( $cart_contents[ $key ]['variation_id'] ?: $cart_contents[ $key ]['product_id'] ), $cart_contents[ $key ] );
                                $_price   = apply_filters( 'wooco_product_original_price', ( WPCleverWooco_Helper::get_setting( 'product_price', 'sale_price' ) === 'regular_price' ) ? $_product->get_regular_price( 'edit' ) : $_product->get_price( 'edit' ), $cart_contents[ $key ]['data'] );

                                if ( isset( $cart_contents[ $key ]['wooco_price'] ) && ( $cart_contents[ $key ]['wooco_price'] !== '' ) ) {
                                    $_price = WPCleverWooco_Helper::get_new_price( $_price, $cart_contents[ $key ]['wooco_price'] );
                                }

                                if ( $discount = $cart_item['data']->get_discount() ) {
                                    $_price = $_price * ( 100 - $discount ) / 100;
                                }

                                $price += wc_get_price_to_display( $cart_contents[ $key ]['data'], [
                                        'price' => (float) apply_filters( 'wooco_component_product_price', $_price, $cart_contents[ $key ] ),
                                        'qty'   => $cart_contents[ $key ]['wooco_qty']
                                ] );
                            }
                        }
                    }

                    WC()->cart->cart_contents[ $cart_item_key ]['wooco_price'] = (float) apply_filters( 'wooco_composite_product_price', $price, $cart_item );

                    if ( $cart_item['data']->get_pricing() === 'exclude' ) {
                        $cart_item['data']->set_price( 0 );
                    }
                }
            }
        }

        public function cart_item_visible( $visible, $item ) {
            if ( isset( $item['wooco_parent_id'] ) ) {
                return false;
            }

            return $visible;
        }

        public function order_item_visible( $visible, $order_item ) {
            if ( $order_item->get_meta( 'wooco_parent_id' ) || $order_item->get_meta( '_wooco_parent_id' ) ) {
                return false;
            }

            return $visible;
        }

        public function cart_item_class( $class, $item ) {
            if ( isset( $item['wooco_parent_id'] ) ) {
                $class .= ' wooco-cart-item wooco-cart-child wooco-item-child';
            } elseif ( isset( $item['wooco_ids'] ) ) {
                $class .= ' wooco-cart-item wooco-cart-parent wooco-item-parent';

                if ( WPCleverWooco_Helper::get_setting( 'hide_component', 'no' ) !== 'no' ) {
                    $class .= ' wooco-hide-component';
                }
            }

            return $class;
        }

        public function cart_item_meta( $item_data, $cart_item ) {
            if ( empty( $cart_item['wooco_ids'] ) ) {
                return $item_data;
            }

            if ( WPCleverWooco_Helper::get_setting( 'hide_component', 'no' ) === 'yes_list' ) {
                $items_str = [];

                if ( $items = WPCleverWooco_Helper::get_items( $cart_item['wooco_ids'] ) ) {
                    // Prime WP post cache for all IDs in one query, avoid N database hits
                    _prime_post_caches( array_column( $items, 'id' ), false, true );

                    foreach ( $items as $item ) {
                        if ( $item_product = wc_get_product( $item['id'] ) ) {
                            if ( ( WPCleverWooco_Helper::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['component'] ) ) {
                                $items_str[] = apply_filters( 'wooco_order_component_product_name', '<li>' . $item['component'] . ': ' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item, $cart_item );
                            } else {
                                $items_str[] = apply_filters( 'wooco_order_component_product_name', '<li>' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item, $cart_item );
                            }
                        }
                    }
                }

                if ( ! empty( $items_str ) ) {
                    $item_data[] = [
                            'key'     => WPCleverWooco_Helper::localization( 'cart_components', esc_html__( 'Components', 'wpc-composite-products' ) ),
                            'value'   => esc_html( $cart_item['wooco_ids'] ),
                            'display' => apply_filters( 'wooco_order_component_product_names', '<ul>' . implode( '', $items_str ) . '</ul>', $items, $cart_item ),
                    ];
                }
            } else {
                $items_str = [];

                if ( $items = WPCleverWooco_Helper::get_items( $cart_item['wooco_ids'] ) ) {
                    // Prime WP post cache for all IDs in one query, avoid N database hits
                    _prime_post_caches( array_column( $items, 'id' ), false, true );

                    foreach ( $items as $item ) {
                        if ( $item_product = wc_get_product( $item['id'] ) ) {
                            if ( ( WPCleverWooco_Helper::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['component'] ) ) {
                                $items_str[] = apply_filters( 'wooco_order_component_product_name', $item['component'] . ': ' . $item['qty'] . ' × ' . $item_product->get_name(), $item, $cart_item );
                            } else {
                                $items_str[] = apply_filters( 'wooco_order_component_product_name', $item['qty'] . ' × ' . $item_product->get_name(), $item, $cart_item );
                            }
                        }
                    }
                }

                if ( ! empty( $items_str ) ) {
                    $item_data[] = [
                            'key'     => WPCleverWooco_Helper::localization( 'cart_components', esc_html__( 'Components', 'wpc-composite-products' ) ),
                            'value'   => esc_html( $cart_item['wooco_ids'] ),
                            'display' => apply_filters( 'wooco_order_component_product_names', implode( '; ', $items_str ), $items, $cart_item ),
                    ];
                }
            }

            return $item_data;
        }

        public function order_item_get_formatted_meta_data( $formatted_meta ) {
            foreach ( $formatted_meta as $key => $meta ) {
                if ( ( $meta->key === 'wooco_ids' ) || ( $meta->key === 'wooco_parent_id' ) || ( $meta->key === 'wooco_qty' ) || ( $meta->key === 'wooco_price' ) || ( $meta->key === 'wooco_component' ) ) {
                    unset( $formatted_meta[ $key ] );
                }
            }

            return $formatted_meta;
        }

        public function add_order_item_meta( $item, $cart_item_key, $values ) {
            if ( isset( $values['wooco_parent_id'] ) ) {
                $item->update_meta_data( 'wooco_parent_id', $values['wooco_parent_id'] );
            }

            if ( isset( $values['wooco_qty'] ) ) {
                $item->update_meta_data( 'wooco_qty', $values['wooco_qty'] );
            }

            if ( isset( $values['wooco_ids'] ) ) {
                $item->update_meta_data( 'wooco_ids', $values['wooco_ids'] );
            }

            if ( isset( $values['wooco_price'] ) ) {
                $item->update_meta_data( 'wooco_price', $values['wooco_price'] );
            }

            if ( isset( $values['wooco_component'] ) ) {
                $item->update_meta_data( 'wooco_component', $values['wooco_component'] );
            }
        }


        public function order_item_meta_start( $order_item_id, $order_item ) {
            if ( $ids = $order_item->get_meta( 'wooco_ids' ) ) {
                if ( $items = WPCleverWooco_Helper::get_items( $ids ) ) {
                    // Prime WP post cache for composite items about to be processed in one query
                    _prime_post_caches( array_column( $items, 'id' ), false, true );

                    if ( WPCleverWooco_Helper::get_setting( 'hide_component_order', 'no' ) === 'yes_list' ) {
                        $items_str = [];

                        foreach ( $items as $item ) {
                            if ( $item_product = wc_get_product( $item['id'] ) ) {
                                if ( ( WPCleverWooco_Helper::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['component'] ) ) {
                                    $items_str[] = apply_filters( 'wooco_order_component_product_name', '<li>' . $item['component'] . ': ' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item );
                                } else {
                                    $items_str[] = apply_filters( 'wooco_order_component_product_name', '<li>' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item );
                                }
                            }
                        }

                        $items_str = apply_filters( 'wooco_order_component_product_names', '<ul>' . implode( '', $items_str ) . '</ul>', $items );
                    } else {
                        $items_str = [];

                        foreach ( $items as $item ) {
                            if ( $item_product = wc_get_product( $item['id'] ) ) {
                                if ( ( WPCleverWooco_Helper::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['component'] ) ) {
                                    $items_str[] = apply_filters( 'wooco_order_component_product_name', $item['component'] . ': ' . $item['qty'] . ' × ' . $item_product->get_name(), $item );
                                } else {
                                    $items_str[] = apply_filters( 'wooco_order_component_product_name', $item['qty'] . ' × ' . $item_product->get_name(), $item );
                                }
                            }
                        }

                        $items_str = apply_filters( 'wooco_order_component_product_names', implode( '; ', $items_str ), $items );
                    }

                    echo wp_kses_post( apply_filters( 'wooco_before_order_itemmeta_composite', '<div class="wooco-itemmeta-composite">' . sprintf( WPCleverWooco_Helper::localization( 'cart_components_s', /* translators: components */ esc_html__( 'Components: %s', 'wpc-composite-products' ) ), $items_str ) . '</div>', $order_item_id, $order_item ) );
                }
            }

            if ( ( $parent_id = $order_item->get_meta( 'wooco_parent_id' ) ) && ( $parent_product = wc_get_product( $parent_id ) ) ) {
                if ( ( $component = $order_item->get_meta( 'wooco_component' ) ) && ! empty( $component ) ) {
                    echo wp_kses_post( apply_filters( 'wooco_before_order_itemmeta_component', '<div class="wooco-itemmeta-component">' . sprintf( WPCleverWooco_Helper::localization( 'cart_composite_s', /* translators: composite */ esc_html__( 'Composite: %s', 'wpc-composite-products' ) ), $parent_product->get_name() . apply_filters( 'wooco_name_separator', ' &rarr; ' ) . $component ) . '</div>', $order_item_id, $order_item ) );
                } else {
                    echo wp_kses_post( apply_filters( 'wooco_before_order_itemmeta_component', '<div class="wooco-itemmeta-component">' . sprintf( WPCleverWooco_Helper::localization( 'cart_composite_s', /* translators: composite */ esc_html__( 'Composite: %s', 'wpc-composite-products' ) ), $parent_product->get_name() ) . '</div>', $order_item_id, $order_item ) );
                }
            }
        }


        public function get_cart_item_from_session( $cart_item, $item_session_values ) {
            if ( ! empty( $item_session_values['wooco_ids'] ) ) {
                $cart_item['wooco_ids']   = $item_session_values['wooco_ids'];
                $cart_item['wooco_price'] = $item_session_values['wooco_price'] ?? '';
            }

            if ( ! empty( $item_session_values['wooco_parent_id'] ) ) {
                $cart_item['wooco_parent_id']  = $item_session_values['wooco_parent_id'];
                $cart_item['wooco_pos']        = $item_session_values['wooco_pos'] ?? '';
                $cart_item['wooco_qty']        = $item_session_values['wooco_qty'] ?? '';
                $cart_item['wooco_price']      = $item_session_values['wooco_price'] ?? '';
                $cart_item['wooco_component']  = $item_session_values['wooco_component'] ?? '';
                $cart_item['wooco_parent_key'] = $item_session_values['wooco_parent_key'] ?? '';
            }

            return $cart_item;
        }


        public function cart_item_remove_link( $link, $cart_item_key ) {
            if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['wooco_parent_key'] ) ) {
                $parent_key = WC()->cart->cart_contents[ $cart_item_key ]['wooco_parent_key'];

                if ( isset( WC()->cart->cart_contents[ $parent_key ] ) || array_search( $parent_key, array_column( WC()->cart->cart_contents, 'wooco_key', 'key' ) ) ) {
                    return '';
                }
            }

            return $link;
        }

        public function cart_item_quantity( $quantity, $cart_item_key, $cart_item ) {
            // add qty as text - not input
            if ( isset( $cart_item['wooco_parent_id'] ) ) {
                return $cart_item['quantity'];
            }

            return $quantity;
        }


        public function add_to_cart_form() {
            self::show_items();

            $edit_link = WPCleverWooco_Helper::get_setting( 'edit_link', 'no' ) === 'yes';
            $edit_ids  = isset( $_GET['edit'] ) ? explode( ',', base64_decode( sanitize_text_field( wp_unslash( $_GET['edit'] ) ) ) ) : [];
            $edit_key  = sanitize_key( wp_unslash( $_GET['key'] ?? '' ) );

            if ( $edit_link && ! empty( $edit_ids ) && ! empty( $edit_key ) ) {
                // edit cart item
                global $product;

                if ( is_a( $product, 'WC_Product' ) && $product->is_type( 'composite' ) ) {
                    $product_id = $product->get_id();
                    $quantity   = ( $cart_item = WC()->cart->get_cart_item( $edit_key ) ) ? $cart_item['quantity'] : 1;
                    echo '<form class="cart" action="' . esc_url( wc_get_cart_url() ) . '" method="post" enctype="multipart/form-data">';
                    echo '<input type="hidden" name="wooco_ids" class="wooco-ids wooco-ids-' . esc_attr( $product_id ) . '" value=""/>';
                    echo '<input type="hidden" name="wooco_update" value="' . esc_attr( $edit_key ) . '"/>';
                    echo '<input type="hidden" name="quantity" value="' . esc_attr( $quantity ) . '"/>';
                    echo '<button type="submit" name="add-to-cart" value="' . esc_attr( $product_id ) . '" class="single_add_to_cart_button button alt">' . esc_html( WPCleverWooco_Helper::localization( 'cart_item_update', esc_html__( 'Update', 'wpc-composite-products' ) ) ) . '</button>';
                    echo '</form>';
                }
            } else {
                // add-to-cart
                wc_get_template( 'single-product/add-to-cart/simple.php' );
            }
        }

        public function add_to_cart_button() {
            global $product;

            if ( $product && $product->is_type( 'composite' ) ) {
                echo '<input type="hidden" name="wooco_ids" class="wooco-ids wooco-ids-' . esc_attr( $product->get_id() ) . '" value=""/>';
            }
        }

        public function loop_add_to_cart_link( $link, $product ) {
            if ( $product->is_type( 'composite' ) ) {
                $link = str_replace( 'ajax_add_to_cart', '', $link );
            }

            return $link;
        }

        public function cart_shipping_packages( $packages ) {
            if ( ! empty( $packages ) ) {
                $shipping_fee_cache = []; // memoize get_post_meta 'wooco_shipping_fee' by product ID

                foreach ( $packages as $package_key => $package ) {
                    if ( ! empty( $package['contents'] ) ) {
                        foreach ( $package['contents'] as $cart_item_key => $cart_item ) {
                            if ( ! empty( $cart_item['wooco_parent_id'] ) ) {
                                $pid = $cart_item['wooco_parent_id'];

                                if ( ! array_key_exists( $pid, $shipping_fee_cache ) ) {
                                    $shipping_fee_cache[ $pid ] = get_post_meta( $pid, 'wooco_shipping_fee', true );
                                }

                                if ( $shipping_fee_cache[ $pid ] === 'whole' ) {
                                    unset( $packages[ $package_key ]['contents'][ $cart_item_key ] );
                                }
                            }

                            if ( ! empty( $cart_item['wooco_ids'] ) ) {
                                $pid = $cart_item['data']->get_id();

                                if ( ! array_key_exists( $pid, $shipping_fee_cache ) ) {
                                    $shipping_fee_cache[ $pid ] = get_post_meta( $pid, 'wooco_shipping_fee', true );
                                }

                                if ( $shipping_fee_cache[ $pid ] === 'each' ) {
                                    unset( $packages[ $package_key ]['contents'][ $cart_item_key ] );
                                }
                            }
                        }
                    }
                }
            }

            return $packages;
        }

        public function get_price_html( $price, $product ) {
            if ( is_admin() ) {
                return $price;
            }

            if ( $product->is_type( 'composite' ) ) {
                $product_id = $product->get_id();

                // wp_cache to avoid get_post_meta on hot path (shop loop, mini-cart, ...)
                $cache_key    = 'wooco_custom_price_' . $product_id;
                $custom_price = wp_cache_get( $cache_key, 'wooco' );

                if ( $custom_price === false ) {
                    $custom_price = stripslashes( (string) get_post_meta( $product_id, 'wooco_custom_price', true ) );
                    wp_cache_set( $cache_key, $custom_price, 'wooco' );
                }

                if ( ! empty( $custom_price ) ) {
                    return $custom_price;
                }

                if ( $product->get_pricing() !== 'only' ) {
                    switch ( WPCleverWooco_Helper::get_setting( 'price_format', 'from_regular' ) ) {
                        case 'from_regular':
                            return esc_html__( 'From', 'wpc-composite-products' ) . ' ' . wc_price( wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ) );
                        case 'from_sale':
                            return esc_html__( 'From', 'wpc-composite-products' ) . ' ' . wc_price( wc_get_price_to_display( $product, [ 'price' => $product->get_price() ] ) );
                    }
                }
            }

            return $price;
        }

        public function product_price_class( $class ) {
            global $product;

            if ( $product && $product->is_type( 'composite' ) ) {
                $class .= ' wooco-price-' . $product->get_id();
            }

            return $class;
        }

        public function order_again_cart_item_data( $item_data, $item ) {
            if ( isset( $item['wooco_ids'] ) ) {
                $item_data['wooco_ids']         = $item['wooco_ids'];
                $item_data['wooco_order_again'] = 'yes';
            }

            if ( isset( $item['wooco_parent_id'] ) ) {
                $item_data['wooco_order_again'] = 'yes';
                $item_data['wooco_parent_id']   = $item['wooco_parent_id'];
            }

            return $item_data;
        }

        public function cart_loaded_from_session() {
            foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
                if ( isset( $cart_item['wooco_order_again'], $cart_item['wooco_parent_id'] ) ) {
                    WC()->cart->remove_cart_item( $cart_item_key );
                }

                if ( isset( $cart_item['wooco_order_again'], $cart_item['wooco_ids'] ) ) {
                    if ( $items = WPCleverWooco_Helper::get_items( $cart_item['wooco_ids'] ) ) {
                        self::add_to_cart_items( $items, $cart_item_key, $cart_item['product_id'], $cart_item['quantity'] );
                    }
                }
            }
        }

        public function coupon_is_valid_for_product( $valid, $product, $coupon, $item ) {
            if ( ( WPCleverWooco_Helper::get_setting( 'coupon_restrictions', 'no' ) === 'both' ) && ( isset( $item['wooco_parent_id'] ) || isset( $item['wooco_ids'] ) ) ) {
                // exclude both composite and component products
                return false;
            }

            if ( ( WPCleverWooco_Helper::get_setting( 'coupon_restrictions', 'no' ) === 'composite' ) && isset( $item['wooco_ids'] ) ) {
                // exclude composite
                return false;
            }

            if ( ( WPCleverWooco_Helper::get_setting( 'coupon_restrictions', 'no' ) === 'component' ) && isset( $item['wooco_parent_id'] ) ) {
                // exclude component products
                return false;
            }

            return $valid;
        }

        public function show_items( $product = null ) {
            if ( ! $product ) {
                global $product;
            }

            if ( ! $product || ! $product->is_type( 'composite' ) ) {
                return;
            }

            $order      = 1;
            $product_id = $product->get_id();
            $df_ids     = isset( $_GET['df'] ) ? explode( ',', sanitize_text_field( $_GET['df'] ) ) : [];
            $edit_ids   = isset( $_GET['edit'] ) ? explode( ',', base64_decode( sanitize_text_field( $_GET['edit'] ) ) ) : [];

            if ( ! empty( $edit_ids ) ) {
                // ignore $df_ids
                $df_ids = [];
            }

            do_action( 'wooco_before_wrap', $product );

            if ( ! WPCleverWooco_Helper::enable_cache( 'show_items' ) || ( false === ( $show_items = get_transient( 'wooco_show_items_' . $product_id ) ) ) ) {
                ob_start();

                // Batch load all post meta of this product into WP object cache
                // so subsequent get_post_meta calls don't need individual database hits
                update_postmeta_cache( [ $product_id ] );

                if ( $components = $product->get_components() ) {
                    // get settings
                    $selector             = apply_filters( 'wooco_selector', WPCleverWooco_Helper::get_setting( 'selector', 'ddslick' ) );
                    $show_price           = WPCleverWooco_Helper::get_setting( 'show_price', 'yes' ) === 'yes';
                    $show_availability    = WPCleverWooco_Helper::get_setting( 'show_availability', 'yes' ) === 'yes';
                    $show_image           = WPCleverWooco_Helper::get_setting( 'show_image', 'yes' ) === 'yes';
                    $plus_minus           = WPCleverWooco_Helper::get_setting( 'show_plus_minus', 'yes' ) === 'yes';
                    $checked              = WPCleverWooco_Helper::get_setting( 'checked', 'yes' ) === 'yes';
                    $checkbox             = WPCleverWooco_Helper::get_setting( 'checkbox', 'no' ) === 'yes';
                    $product_link         = WPCleverWooco_Helper::get_setting( 'product_link', 'no' );
                    $option_none_required = WPCleverWooco_Helper::get_setting( 'option_none_required', 'no' ) === 'yes';
                    $total_limit          = get_post_meta( $product_id, 'wooco_total_limits', true ) === 'on';
                    $total_limit_min      = get_post_meta( $product_id, 'wooco_total_limits_min', true );
                    $total_limit_max      = get_post_meta( $product_id, 'wooco_total_limits_max', true );

                    // option none image
                    $option_none_image = $option_none_image_full = WPCleverWooco_Helper::get_setting( 'option_none_image', 'placeholder' ) !== 'none' ? wc_placeholder_img_src() : '';

                    if ( ( WPCleverWooco_Helper::get_setting( 'option_none_image', 'placeholder' ) === 'product' ) && ( $product_image_id = $product->get_image_id() ) ) {
                        $product_image          = wp_get_attachment_image_src( $product_image_id, self::$image_size );
                        $product_image_full     = wp_get_attachment_image_src( $product_image_id, 'full' );
                        $option_none_image      = $product_image[0] ?? '';
                        $option_none_image_full = $product_image_full[0] ?? '';
                    }

                    if ( ( WPCleverWooco_Helper::get_setting( 'option_none_image', 'placeholder' ) === 'custom' ) && ( $option_none_image_id = WPCleverWooco_Helper::get_setting( 'option_none_image_id' ) ) ) {
                        $custom_image           = wp_get_attachment_image_src( $option_none_image_id, self::$image_size );
                        $custom_image_full      = wp_get_attachment_image_src( $option_none_image_id, 'full' );
                        $option_none_image      = $custom_image[0] ?? '';
                        $option_none_image_full = $custom_image_full[0] ?? '';
                    }

                    echo '<div class="' . esc_attr( apply_filters( 'wooco_wrap_class', 'wooco_wrap wooco-wrap wooco-wrap-' . $product_id, $product ) ) . '" data-id="' . esc_attr( $product_id ) . '">';

                    if ( $before_text = apply_filters( 'wooco_before_text', get_post_meta( $product_id, 'wooco_before_text', true ), $product_id ) ) {
                        echo '<div class="wooco_before_text wooco-before-text wooco-text">' . wp_kses_post( do_shortcode( $before_text ) ) . '</div>';
                    }

                    do_action( 'wooco_before_components', $product );

                    $components_attrs = apply_filters( 'wooco_components_data_attributes', [
                            'percent'       => $product->get_discount(),
                            'min'           => get_post_meta( $product_id, 'wooco_qty_min', true ),
                            'max'           => get_post_meta( $product_id, 'wooco_qty_max', true ),
                            'price'         => wc_get_price_to_display( $product ),
                            'regular-price' => wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ),
                            'pricing'       => $product->get_pricing(),
                            'same'          => get_post_meta( $product_id, 'wooco_same_products', true ) === 'do_not_allow' ? 'no' : 'yes',
                            'checkbox'      => $checkbox ? 'yes' : 'no',
                            'total-min'     => $total_limit && $total_limit_min ? $total_limit_min : 0,
                            'total-max'     => $total_limit && $total_limit_max ? $total_limit_max : '-1'
                    ], $product );

                    echo '<div class="' . esc_attr( apply_filters( 'wooco_components_class', 'wooco_components wooco-components', $product ) ) . '" ' . WPCleverWooco_Helper::data_attributes( $components_attrs ) . '>';

                    foreach ( $components as $key => $component ) {
                        $component_type = $component['type'] ?? '';

                        if ( $component_type === 'products' ) {
                            $component_val = $component['products'] ?? [];
                        } else {
                            $component_val = $component['other'] ?? [];
                        }

                        $component_default    = $component['default'] ?? 0;
                        $component_default_id = WPCleverWooco_Helper::get_product_id( $component_default );
                        $component_default    = absint( $df_ids[ $order - 1 ] ?? $component_default_id );
                        $component_default    = apply_filters( 'wooco_component_default', $component_default, $component );
                        $component['default'] = $component_default;
                        $component_required   = isset( $component['optional'] ) && ( $component['optional'] === 'no' );
                        $component_multiple   = isset( $component['multiple'] ) && ( $component['multiple'] === 'yes' );
                        $component_qty        = (float) ( $component['qty'] ?? 1 );
                        $component_custom_qty = isset( $component['custom_qty'] ) && $component['custom_qty'] === 'yes';
                        $component_exclude    = $component['exclude'] ?? [];
                        $component_orderby    = (string) ( $component['orderby'] ?? 'default' );
                        $component_order      = (string) ( $component['order'] ?? 'default' );
                        $component_price      = isset( $component['price'] ) ? WPCleverWooco_Helper::format_price( $component['price'] ) : '';
                        $component_products   = self::get_products( $component_type, $component_val, $component_orderby, $component_order, $component_exclude, $component_default, $component_qty, $component_price, $component_custom_qty );
                        $component_selector   = isset( $component['selector'] ) && $component['selector'] !== 'default' ? $component['selector'] : $selector;

                        // force set 'grid_3' if enable multiple
                        if (
                                $component_multiple && ! in_array( $component_selector, [
                                        'list',
                                        'grid_2',
                                        'grid_3',
                                        'grid_4'
                                ] )
                        ) {
                            $component_selector = 'grid_3';
                        }

                        $component_selector = apply_filters( 'wooco_component_selector', $component_selector, $component );
                        $component_dropdown = ! in_array( $component_selector, [
                                        'list',
                                        'grid_2',
                                        'grid_3',
                                        'grid_4'
                                ] ) && ! $component_multiple;
                        $component_class    = 'wooco_component wooco_component_' . $order . ' wooco_component_type_' . $component_type . ' wooco_component_has_' . ( $component_products ? count( $component_products ) : '0' ) . ' wooco_component_layout_' . $component_selector;

                        if ( $component_required ) {
                            $component_class .= ' wooco_component_required';
                        }

                        if ( $option_none_required ) {
                            $component_class .= ' wooco_component_option_none_required';
                        }

                        if ( $component_multiple ) {
                            $component_class .= ' wooco_component_multiple';
                        }

                        if ( $component_custom_qty ) {
                            $component_class .= ' wooco_component_custom_qty';
                        }

                        if ( ! $component_products && ! $component_required ) {
                            // have no products and isn't required, hide it
                            continue;
                        }

                        echo '<div class="' . esc_attr( apply_filters( 'wooco_component_class', $component_class, $component, $order ) ) . '">';
                        do_action( 'wooco_before_component', $component, $order );

                        if ( ! empty( $component['name'] ) ) {
                            echo '<div class="wooco_component_name">' . esc_html( $component['name'] ) . '</div>';
                        }

                        if ( ! empty( $component['desc'] ) ) {
                            echo '<div class="wooco_component_desc">' . wp_kses_post( $component['desc'] ) . '</div>';
                        }

                        if ( ! $component_products ) {
                            if ( $component_required ) {
                                // have no product and required
                                ?>
                                <div class="wooco_component_product wooco_component_product_none"
                                     data-key="<?php echo esc_attr( $key ); ?>"
                                     data-name="<?php echo esc_attr( $component['name'] ); ?>" data-id="0"
                                     data-qty="<?php echo esc_attr( $component_qty ); ?>"
                                     data-m-min="<?php echo esc_attr( ! empty( $component['m_min'] ) ? (float) $component['m_min'] : '0' ); ?>"
                                     data-m-max="<?php echo esc_attr( ! empty( $component['m_max'] ) ? (float) $component['m_max'] : '10000' ); ?>"
                                     data-price="0" data-regular-price="0" data-new-price="0" data-required="yes"
                                     data-custom-qty="<?php echo esc_attr( $component_custom_qty ? 'yes' : 'no' ); ?>"
                                     data-multiple="<?php echo esc_attr( $component_multiple ? 'yes' : 'no' ); ?>"></div>
                                <?php
                            }
                        } else {
                            if ( ( count( $component_products ) === 1 ) && $component_required ) {
                                // have one product and required
                                $one_required = true;
                            } else {
                                $one_required = false;
                            }

                            $option_none_image       = apply_filters( 'wooco_option_none_img_src', $option_none_image, $component, $product );
                            $option_none_image_full  = apply_filters( 'wooco_option_none_img_full', $option_none_image_full, $component, $product );
                            $option_none             = $component_required ? WPCleverWooco_Helper::localization( 'option_none_required', esc_html__( 'Please make your choice here', 'wpc-composite-products' ) ) : WPCleverWooco_Helper::localization( 'option_none', esc_html__( 'No, thanks. I don\'t need this', 'wpc-composite-products' ) );
                            $option_none_label       = apply_filters( 'wooco_option_none', $option_none, $component, $product );
                            $option_none_description = apply_filters( 'wooco_option_none_description', wc_price( 0 ), $component, $product );
                            $option_none_data        = apply_filters( 'wooco_option_none_data', [
                                    'id'            => '-1',
                                    'pid'           => '-1',
                                    'qty'           => '0',
                                    'price'         => '',
                                    'regular-price' => '',
                                    'link'          => '',
                                    'price-html'    => '',
                                    'imagesrc'      => esc_url( $option_none_image ),
                                    'imagefull'     => esc_url( $option_none_image_full ),
                                    'availability'  => '',
                                    'description'   => htmlentities( $option_none_description ),
                            ], $component, $product );
                            $component_product_attrs = apply_filters( 'wooco_component_product_data_attributes', [
                                    'key'           => $key,
                                    'name'          => $component['name'],
                                    'id'            => '-1',
                                    'qty'           => $component['qty'],
                                    'm-min'         => ! empty( $component['m_min'] ) ? (float) $component['m_min'] : '0',
                                    'm-max'         => ! empty( $component['m_max'] ) ? (float) $component['m_max'] : '10000',
                                    'price'         => '0',
                                    'regular-price' => '0',
                                    'price-html'    => '',
                                    'new-price'     => $component_price,
                                    'required'      => $component_required ? 'yes' : 'no',
                                    'custom-qty'    => $component_custom_qty ? 'yes' : 'no',
                                    'multiple'      => $component_multiple ? 'yes' : 'no',
                                    'count'         => count( $component_products )
                            ], $component, $product );

                            echo '<div class="wooco_component_product" ' . WPCleverWooco_Helper::data_attributes( $component_product_attrs ) . '>';

                            if ( $checkbox && $component_dropdown ) {
                                $components_checked = $checked || $component_required;

                                if ( ! empty( $edit_ids ) ) {
                                    foreach ( $edit_ids as $edit_id ) {
                                        if ( str_contains( $edit_id, '/' . $key ) ) {
                                            $components_checked = true;
                                        }
                                    }
                                }
                                ?>
                                <div class="wooco_component_product_checkbox">
                                    <label>
                                        <input class="wooco-checkbox"
                                               type="checkbox" <?php echo( apply_filters( 'wooco_component_checkbox_checked', $components_checked, $component ) ? 'checked="checked"' : '' ); ?>                                 <?php echo( apply_filters( 'wooco_component_checkbox_disabled', $component_required, $component ) ? 'disabled' : '' ); ?> />
                                    </label>
                                </div>
                            <?php } ?>

                            <?php if ( ( $component_selector === 'select' ) && $show_image ) { ?>
                                <div class="wooco_component_product_image">
                                    <?php echo '<img src="' . esc_url( $option_none_image ) . '"/>'; ?>
                                </div>
                            <?php } ?>

                            <div class="wooco_component_product_selection">
                                <?php if ( ! $component_dropdown ) {
                                    // list or grid
                                    $_order = 1;

                                    if ( $component_selector === 'list' ) {
                                        echo '<div class="wooco_component_product_selection_list">';

                                        foreach ( $component_products as $component_product ) {
                                            if ( $component_product_obj = wc_get_product( $component_product['id'] ) ) {
                                                $item_selected = apply_filters( 'wooco_component_product_selected', isset( $component['default'] ) && ( $component['default'] == $component_product['id'] ), $component_product, $component );

                                                if ( ! empty( $edit_ids ) ) {
                                                    foreach ( $edit_ids as $edit_id ) {
                                                        if ( str_contains( $edit_id, '/' . $key ) ) {
                                                            $edit_id_arr  = explode( '/', $edit_id );
                                                            $edit_product = absint( $edit_id_arr[0] ?? 0 );
                                                            $edit_qty     = (float) ( $edit_id_arr[1] ?? 1 );

                                                            if ( $edit_product === $component_product['id'] ) {
                                                                $item_selected            = true;
                                                                $component_product['qty'] = $edit_qty;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }

                                                echo '<div class="wooco_component_product_selection_list_item wooco_component_product_selection_item ' . ( $item_selected || $one_required ? 'wooco_item_selected' : '' ) . '" ' . WPCleverWooco_Helper::data_attributes( $component_product ) . '>';
                                                echo '<div class="wooco_component_product_selection_list_item_choose"><span></span></div>';
                                                echo '<div class="wooco_component_product_selection_list_item_image">' . wp_kses_post( apply_filters( 'wooco_component_product_image', $component_product_obj->get_image(), $component_product_obj ) ) . '</div>';
                                                echo '<div class="wooco_component_product_selection_list_item_info">';
                                                echo '<div class="wooco_component_product_selection_list_item_name">' . wp_kses_post( $component_product['name'] ) . '</div>';
                                                echo '<div class="wooco_component_product_selection_list_item_desc">' . wp_kses_post( html_entity_decode( $component_product['description'] ) ) . '</div>';
                                                echo '</div>';

                                                if ( $component_custom_qty ) {
                                                    $min = 0;
                                                    $max = 1000;
                                                    $qty = $component_product['qty'];

                                                    if ( ! empty( $component['min'] ) ) {
                                                        $min = $component['min'];
                                                    }

                                                    if ( ! empty( $component['max'] ) ) {
                                                        $max = $component['max'];
                                                    }

                                                    if ( ! class_exists( 'WPCleverWoopq' ) || ( WPCleverWoopq::get_setting( 'decimal', 'no' ) !== 'yes' ) ) {
                                                        $qty = (int) $qty;
                                                        $min = (int) $min;
                                                        $max = (int) $max;
                                                    }

                                                    if ( ( $max_purchase = $component_product_obj->get_max_purchase_quantity() ) && ( $max_purchase > 0 ) && ( $max_purchase < $max ) ) {
                                                        // get_max_purchase_quantity can return -1
                                                        $max = $max_purchase;
                                                    }

                                                    echo '<div class="wooco_component_product_selection_list_item_qty wooco_component_product_selection_item_qty wooco-qty-wrap">';
                                                    echo '<span class="wooco-qty-input">';
                                                    echo $plus_minus ? '<span class="wooco_component_product_qty_btn wooco_component_product_qty_minus wooco-minus">-</span>' : '';
                                                    echo woocommerce_quantity_input( [
                                                            'input_value' => $qty,
                                                            'min_value'   => $min,
                                                            'max_value'   => $max,
                                                            'wooco_qty'   => [
                                                                    'input_value' => $qty,
                                                                    'min_value'   => $min,
                                                                    'max_value'   => $max
                                                            ],
                                                            'classes'     => apply_filters( 'wooco_qty_classes', [
                                                                    'input-text',
                                                                    'wooco_component_product_qty_input',
                                                                    'wooco_qty',
                                                                    'qty',
                                                                    'text'
                                                            ], $component ),
                                                            'input_name'  => 'wooco_qty_' . $order . '_' . $_order
                                                        // compatible with WPC Product Quantity
                                                    ], $component_product_obj, false );
                                                    echo $plus_minus ? '<span class="wooco_component_product_qty_btn wooco_component_product_qty_plus wooco-plus">+</span>' : '';
                                                    echo '</span>';
                                                    echo '</div>';
                                                }

                                                if ( $product_link !== 'no' ) {
                                                    if ( $component_product_obj->is_visible() || apply_filters( 'wooco_hidden_product_link', false ) ) {
                                                        $quickview_id = is_a( $component_product_obj, 'WC_Product_Variation' ) && ( apply_filters( 'wooco_quickview_variation', 'default' ) === 'parent' ) ? $component_product_obj->get_parent_id() : $component_product['id'];
                                                        echo '<div class="wooco_component_product_selection_list_item_link"><a ' . ( $product_link === 'yes_popup' ? 'class="woosq-link" data-id="' . esc_attr( $quickview_id ) . '" data-context="wooco"' : 'class="wooco_component_product_selection_list_item_link"' ) . ' href="' . esc_url( $component_product_obj->get_permalink() ) . '" ' . ( $product_link === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . esc_html( $component_product_obj->get_name() ) . '</a></div>';
                                                    }
                                                }

                                                echo '</div>';
                                            }

                                            $_order ++;
                                        }

                                        echo '</div>';
                                    } else {
                                        echo '<div class="wooco_component_product_selection_grid">';

                                        foreach ( $component_products as $component_product ) {
                                            if ( $component_product_obj = wc_get_product( $component_product['id'] ) ) {
                                                $item_selected = apply_filters( 'wooco_component_product_selected', isset( $component['default'] ) && ( $component['default'] == $component_product['id'] ), $component_product, $component );

                                                if ( ! empty( $edit_ids ) ) {
                                                    foreach ( $edit_ids as $edit_id ) {
                                                        if ( str_contains( $edit_id, '/' . $key ) ) {
                                                            $edit_id_arr  = explode( '/', $edit_id );
                                                            $edit_product = absint( $edit_id_arr[0] ?? 0 );
                                                            $edit_qty     = (float) ( $edit_id_arr[1] ?? 1 );

                                                            if ( $edit_product === $component_product['id'] ) {
                                                                $item_selected            = true;
                                                                $component_product['qty'] = $edit_qty;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }

                                                echo '<div class="wooco_component_product_selection_grid_item wooco_component_product_selection_item ' . ( $item_selected || $one_required ? 'wooco_item_selected' : '' ) . '" ' . WPCleverWooco_Helper::data_attributes( $component_product ) . '>';
                                                echo '<div class="wooco_component_product_selection_grid_item_image">' . wp_kses_post( apply_filters( 'wooco_component_product_image', $component_product_obj->get_image(), $component_product_obj ) ) . '</div>';
                                                echo '<div class="wooco_component_product_selection_grid_item_info">';
                                                echo '<div class="wooco_component_product_selection_grid_item_name">' . wp_kses_post( $component_product['name'] ) . '</div>';
                                                echo '<div class="wooco_component_product_selection_grid_item_desc">' . wp_kses_post( html_entity_decode( $component_product['description'] ) ) . '</div>';

                                                if ( $component_custom_qty ) {
                                                    $min = 0;
                                                    $max = 1000;
                                                    $qty = $component_product['qty'];

                                                    if ( ! empty( $component['min'] ) ) {
                                                        $min = $component['min'];
                                                    }

                                                    if ( ! empty( $component['max'] ) ) {
                                                        $max = $component['max'];
                                                    }

                                                    if ( ! class_exists( 'WPCleverWoopq' ) || ( WPCleverWoopq::get_setting( 'decimal', 'no' ) !== 'yes' ) ) {
                                                        $qty = (int) $qty;
                                                        $min = (int) $min;
                                                        $max = (int) $max;
                                                    }

                                                    if ( ( $max_purchase = $component_product_obj->get_max_purchase_quantity() ) && ( $max_purchase > 0 ) && ( $max_purchase < $max ) ) {
                                                        // get_max_purchase_quantity can return -1
                                                        $max = $max_purchase;
                                                    }

                                                    echo '<div class="wooco_component_product_selection_grid_item_qty wooco_component_product_selection_item_qty wooco-qty-wrap">';
                                                    echo '<span class="wooco-qty-input">';
                                                    echo $plus_minus ? '<span class="wooco_component_product_qty_btn wooco_component_product_qty_minus wooco-minus">-</span>' : '';
                                                    echo woocommerce_quantity_input( [
                                                            'input_value' => $qty,
                                                            'min_value'   => $min,
                                                            'max_value'   => $max,
                                                            'wooco_qty'   => [
                                                                    'input_value' => $qty,
                                                                    'min_value'   => $min,
                                                                    'max_value'   => $max
                                                            ],
                                                            'classes'     => apply_filters( 'wooco_qty_classes', [
                                                                    'input-text',
                                                                    'wooco_component_product_qty_input',
                                                                    'wooco_qty',
                                                                    'qty',
                                                                    'text'
                                                            ], $component ),
                                                            'input_name'  => 'wooco_qty_' . $order . '_' . $_order
                                                        // compatible with WPC Product Quantity
                                                    ], $component_product_obj, false );
                                                    echo $plus_minus ? '<span class="wooco_component_product_qty_btn wooco_component_product_qty_plus wooco-plus">+</span>' : '';
                                                    echo '</span>';
                                                    echo '</div>';
                                                }

                                                echo '</div>';

                                                if ( $product_link !== 'no' ) {
                                                    if ( $component_product_obj->is_visible() || apply_filters( 'wooco_hidden_product_link', false ) ) {
                                                        $quickview_id = is_a( $component_product_obj, 'WC_Product_Variation' ) && ( apply_filters( 'wooco_quickview_variation', 'default' ) === 'parent' ) ? $component_product_obj->get_parent_id() : $component_product['id'];
                                                        echo '<a ' . ( $product_link === 'yes_popup' ? 'class="wooco_component_product_selection_grid_item_link woosq-link" data-id="' . esc_attr( $quickview_id ) . '" data-context="wooco"' : 'class="wooco_component_product_selection_grid_item_link"' ) . ' href="' . esc_url( $component_product_obj->get_permalink() ) . '" ' . ( $product_link === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . esc_html( $component_product_obj->get_name() ) . '</a>';
                                                    }
                                                }

                                                echo '</div>';
                                            }

                                            $_order ++;
                                        }

                                        echo '</div>';
                                    }
                                } else { ?>
                                    <label for="<?php echo esc_attr( 'wooco_component_product_select_' . $order ); ?>"></label>
                                    <select class="wooco_component_product_select"
                                            id="<?php echo esc_attr( 'wooco_component_product_select_' . $order ); ?>">
                                        <?php
                                        if ( ! $checkbox && ( ! $component_required || $option_none_required ) && ! $one_required ) {
                                            echo '<option value="-1" ' . WPCleverWooco_Helper::data_attributes( $option_none_data ) . '>' . esc_html( $option_none_label ) . '</option>';
                                        }

                                        foreach ( $component_products as $component_product ) {
                                            $item_selected = apply_filters( 'wooco_component_product_selected', isset( $component['default'] ) && ( $component['default'] == $component_product['id'] ), $component_product, $component );

                                            if ( ! empty( $edit_ids ) ) {
                                                foreach ( $edit_ids as $edit_id ) {
                                                    if ( str_contains( $edit_id, '/' . $key ) ) {
                                                        $edit_id_arr  = explode( '/', $edit_id );
                                                        $edit_product = absint( $edit_id_arr[0] ?? 0 );
                                                        $edit_qty     = (float) ( $edit_id_arr[1] ?? 1 );

                                                        if ( $edit_product === $component_product['id'] ) {
                                                            $item_selected         = true;
                                                            $component['edit_qty'] = $edit_qty;
                                                            break;
                                                        }
                                                    }
                                                }
                                            }

                                            echo '<option value="' . esc_attr( $component_product['purchasable'] === 'yes' ? $component_product['id'] : 0 ) . '" ' . WPCleverWooco_Helper::data_attributes( $component_product ) . ' ' . esc_attr( $component_product['purchasable'] !== 'yes' ? 'disabled' : '' ) . ' ' . esc_attr( $item_selected ? 'selected' : '' ) . '>' . esc_html( $component_product['name'] ) . '</option>';
                                        }
                                        ?>
                                    </select>
                                <?php } ?>
                            </div>

                            <?php
                            if ( ( $component_selector === 'select' ) && $show_availability ) {
                                echo '<div class="wooco_component_product_availability"></div>';
                            }

                            if ( ( $component_selector === 'select' ) && $show_price ) {
                                echo '<div class="wooco_component_product_price"></div>';
                            }

                            if ( $component_custom_qty && $component_dropdown ) {
                                $min = 0;
                                $max = 1000;
                                $qty = $component['edit_qty'] ?? $component['qty'];

                                if ( ! empty( $component['min'] ) ) {
                                    $min = $component['min'];
                                }

                                if ( ! empty( $component['max'] ) ) {
                                    $max = $component['max'];
                                }

                                if ( class_exists( 'WPCleverWoopq' ) && ( WPCleverWoopq::get_setting( 'decimal', 'no' ) === 'yes' ) ) {
                                    $step = WPCleverWoopq::get_setting( 'step' ) ?: '1';
                                } else {
                                    $step = '1';
                                    $qty  = (int) $qty;
                                    $min  = (int) $min;
                                    $max  = (int) $max;
                                }

                                echo '<div class="wooco_component_product_qty wooco-qty-wrap">';
                                echo '<span class="wooco-qty-input">';
                                echo $plus_minus ? '<span class="wooco_component_product_qty_btn wooco_component_product_qty_minus wooco-minus">-</span>' : '';
                                echo '<input class="wooco_component_product_qty_input wooco_qty input-text text qty" type="number" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $qty ) . '"/>';
                                echo $plus_minus ? '<span class="wooco_component_product_qty_btn wooco_component_product_qty_plus wooco-plus">+</span>' : '';
                                echo '</span>';
                                echo '</div>';
                            }

                            echo '</div><!-- /.wooco_component_product -->';
                        }

                        do_action( 'wooco_after_component', $component, $order );
                        echo '</div>';
                        $order ++;
                    }

                    echo '</div><!-- /.wooco-components -->';

                    echo '<div class="wooco_summary wooco-summary wooco-text"><div class="wooco_total wooco-total"></div><div class="wooco_count wooco-count"></div></div>';

                    if ( WPCleverWooco_Helper::get_setting( 'show_alert', 'load' ) !== 'no' ) {
                        echo '<div class="wooco_alert wooco-alert wooco-text" style="display: none"></div>';
                    }

                    do_action( 'wooco_after_components', $product );

                    if ( $after_text = apply_filters( 'wooco_after_text', get_post_meta( $product_id, 'wooco_after_text', true ), $product_id ) ) {
                        echo '<div class="wooco_after_text wooco-after-text wooco-text">' . wp_kses_post( do_shortcode( $after_text ) ) . '</div>';
                    }

                    echo '</div>';
                }

                $show_items = ob_get_clean();

                // Prime WP post cache for all products in selection - optimization for shop loop/mini-cart
                if ( WPCleverWooco_Helper::enable_cache( 'show_items' ) ) {
                    set_transient( 'wooco_show_items_' . $product_id, $show_items, 24 * HOUR_IN_SECONDS );
                }
            }

            echo $show_items;

            do_action( 'wooco_after_wrap', $product );
        }


        public function get_products( $type, $val, $orderby, $order, $exclude = [], $default = 0, $qty = 1, $price = '', $custom_qty = false ) {
            $has_default           = false;
            $products              = $_products = [];
            $val_arr               = array_unique( ! is_array( $val ) ? array_map( 'trim', explode( ',', $val ) ) : $val );
            $exclude_ids           = $type != 'products' ? ( ! is_array( $exclude ) ? explode( ',', $exclude ) : $exclude ) : [];
            $exclude_ids           = array_map( [ 'WPCleverWooco_Helper', 'get_product_id' ], $exclude_ids );
            $limit                 = apply_filters( 'wooco_limit', - 1 );
            $exclude_hidden        = apply_filters( 'wooco_exclude_hidden', WPCleverWooco_Helper::get_setting( 'exclude_hidden', 'no' ) === 'yes' );
            $exclude_unpurchasable = apply_filters( 'wooco_exclude_unpurchasable', WPCleverWooco_Helper::get_setting( 'exclude_unpurchasable', 'yes' ) === 'yes' );
            $default_id            = WPCleverWooco_Helper::get_product_id( $default );

            if ( $orderby === 'name' ) {
                $orderby = 'title';
            }

            if ( $orderby === 'menu_order' ) {
                $orderby = 'menu_order title';
            }

            if ( apply_filters( 'wooco_use_wc_get_products', true ) ) {
                // query args
                if ( $type === 'products' ) {
                    if ( ( $orderby === 'default' ) && ( $order === 'DESC' ) ) {
                        $val_arr = array_reverse( $val_arr );
                    }

                    $val_arr = array_map( [ 'WPCleverWooco_Helper', 'get_product_id' ], $val_arr );

                    if ( $orderby === 'default' ) {
                        $orderby = 'post__in';
                    }

                    $args = [
                            'is_wooco' => true,
                            'type'     => array_merge( [ 'variation' ], array_keys( wc_get_product_types() ) ),
                            'include'  => $val_arr,
                            'orderby'  => $orderby,
                            'order'    => $order,
                            'limit'    => $limit
                    ];
                }

                // order by price
                if ( $orderby === 'price' ) {
                    $args['orderby']  = 'meta_value_num';
                    $args['meta_key'] = '_price';
                }

                $args['status'] = [ 'publish' ];

                // filter
                $args = apply_filters( 'wooco_wc_get_products_args', $args );

                // query products
                $_products = apply_filters( 'wooco_wc_get_products', wc_get_products( $args ), $type, $val_arr, $orderby, $order, $limit );
            }

            if ( empty( $_products ) && apply_filters( 'wooco_use_wp_get_posts', true ) ) {
                // try get_posts in some cases get_products does not work
                if ( $type === 'products' ) {
                    $args = apply_filters( 'wooco_wp_get_posts_args', [
                            'is_wooco'       => true,
                            'fields'         => 'ids',
                            'post_type'      => [ 'product', 'product_variation' ],
                            'post_status'    => [ 'publish' ],
                            'include'        => $val_arr,
                            'orderby'        => $orderby,
                            'order'          => $order,
                            'posts_per_page' => $limit
                    ] );
                }

                $_posts = apply_filters( 'wooco_wp_get_posts', get_posts( $args ), $type, $val_arr, $orderby, $order, $limit );

                if ( ! empty( $_posts ) && is_array( $_posts ) ) {
                    $_products = array_map( 'wc_get_product', $_posts );
                }

                $_products = apply_filters( 'wooco_pre_get_products', $_products, $type, $val_arr, $orderby, $order, $limit );
            }

            if ( is_array( $_products ) && ! empty( $_products ) ) {
                foreach ( $_products as $_product ) {
                    if ( $_product->is_type( 'composite' ) && ! apply_filters( 'wooco_allow_composite_product', false ) ) {
                        continue;
                    }

                    $_product_id = $_product->get_id();

                    if ( ( $type === 'products' ) && ! in_array( $_product_id, $val_arr ) && ( $_product_id != $default_id ) ) {
                        continue;
                    }

                    if ( in_array( $_product_id, $exclude_ids ) ) {
                        continue;
                    }

                    if ( ! apply_filters( 'wooco_product_visible', true, $_product ) || ( ! $_product->is_visible() && $exclude_hidden ) ) {
                        continue;
                    }

                    if ( $_product->is_type( 'variable' ) ) {
                        $children = $_product->get_children();

                        if ( ! empty( $children ) ) {
                            foreach ( $children as $child ) {
                                if ( in_array( $child, $exclude_ids ) ) {
                                    continue;
                                }

                                $child_product = wc_get_product( $child );

                                if ( ! $child_product || ( ! $child_product->variation_is_visible() && $exclude_hidden ) || ( $exclude_unpurchasable && ! self::is_purchasable( $child_product, $qty ) ) ) {
                                    continue;
                                }

                                if ( apply_filters( 'wooco_check_variation_tag', false ) && ( $type === 'product_tag' ) ) {
                                    // check variation tag
                                    if ( ! has_term( $val_arr, 'product_tag', $child ) ) {
                                        continue;
                                    }
                                }

                                if ( apply_filters( 'wooco_check_variation_attribute', true ) && ( str_starts_with( $type, 'pa_' ) ) ) {
                                    // check variation attribute
                                    $attrs = $child_product->get_attributes();

                                    if ( ! isset( $attrs[ $type ] ) || ( ! in_array( $attrs[ $type ], $val_arr ) ) ) {
                                        continue;
                                    }
                                }

                                $products[ 'pid_' . $child ] = self::get_product_data( $child_product, $qty, $price, $custom_qty, $default_id );

                                if ( $child == $default_id ) {
                                    $has_default = true;
                                }
                            }
                        }
                    } else {
                        if ( $exclude_unpurchasable && ! self::is_purchasable( $_product, $qty ) ) {
                            continue;
                        }

                        $products[ 'pid_' . $_product_id ] = self::get_product_data( $_product, $qty, $price, $custom_qty, $default_id );

                        if ( $_product_id == $default_id ) {
                            $has_default = true;
                        }
                    }
                }

                if ( ! $has_default && ( $product_default = wc_get_product( $default_id ) ) ) {
                    // add default product
                    if ( $product_default->is_type( 'variable' ) ) {
                        // select a available variation
                        $available_variations = $product_default->get_available_variations();

                        if ( count( $available_variations ) > 0 ) {
                            $sort_default_variations = apply_filters( 'wooco_sort_default_variations', 'default' );

                            if ( $sort_default_variations === 'price_asc' ) {
                                $display_price = array_column( $available_variations, 'display_price' );

                                array_multisort( $display_price, SORT_ASC, $available_variations );
                            }

                            if ( $sort_default_variations === 'price_desc' ) {
                                $display_price = array_column( $available_variations, 'display_price' );

                                array_multisort( $display_price, SORT_DESC, $available_variations );
                            }

                            foreach ( array_reverse( $available_variations ) as $available_variation ) {
                                $available_variation_id      = $available_variation['variation_id'];
                                $available_variation_product = wc_get_product( $available_variation_id );

                                if ( ! $available_variation_product || ( ! $available_variation_product->variation_is_visible() && $exclude_hidden ) || ( $exclude_unpurchasable && ! self::is_purchasable( $available_variation_product, $qty ) ) ) {
                                    continue;
                                }

                                // add variation
                                $products = [ 'pid_' . $available_variation_id => self::get_product_data( $available_variation_product, $qty, $price, $custom_qty, $default_id ) ] + $products;
                            }

                            foreach ( $available_variations as $available_variation ) {
                                $available_variation_id      = $available_variation['variation_id'];
                                $available_variation_product = wc_get_product( $available_variation_id );

                                if ( $available_variation_product && self::is_purchasable( $available_variation_product, $qty ) ) {
                                    // select default variation
                                    $products = [ 'pid_' . $available_variation_id => self::get_product_data( $available_variation_product, $qty, $price, $custom_qty, $default_id ) ] + $products;
                                    break;
                                }
                            }
                        }
                    } else {
                        if ( self::is_purchasable( $product_default, $qty ) || ! $exclude_unpurchasable ) {
                            $products = [ 'pid_' . $default_id => self::get_product_data( $product_default, $qty, $price, $custom_qty, $default_id ) ] + $products;
                        }
                    }
                }
            }

            return apply_filters( 'wooco_get_products', $products, $type, $val, $orderby, $order, $exclude, $default, $qty, $price, $custom_qty );
        }

        public function is_purchasable( $product, $qty ) {
            return $product->is_purchasable() && $product->is_in_stock() && $product->has_enough_stock( $qty ) && ( 'trash' !== $product->get_status() );
        }

        public function get_product_data( $product, $qty = 1, $price = '', $custom_qty = false, $default_id = 0 ) {
            // settings
            $show_price             = WPCleverWooco_Helper::get_setting( 'show_price', 'yes' ) === 'yes';
            $show_availability      = WPCleverWooco_Helper::get_setting( 'show_availability', 'yes' ) === 'yes';
            $show_short_description = WPCleverWooco_Helper::get_setting( 'show_short_description', 'no' ) === 'yes';
            $show_image             = WPCleverWooco_Helper::get_setting( 'show_image', 'yes' ) === 'yes';
            $show_qty               = WPCleverWooco_Helper::get_setting( 'show_qty', 'yes' ) === 'yes';

            if ( $show_image ) {
                if ( $product_image_id = $product->get_image_id() ) {
                    $img             = wp_get_attachment_image_src( $product_image_id, self::$image_size );
                    $img_full        = wp_get_attachment_image_src( $product_image_id, 'full' );
                    $img_gallery     = wp_get_attachment_image_src( $product_image_id, 'woocommerce_gallery_thumbnail' );
                    $img_src         = $img[0] ?? wc_placeholder_img_src();
                    $img_full_src    = $img_full[0] ?? wc_placeholder_img_src();
                    $img_gallery_src = $img_gallery[0] ?? wc_placeholder_img_src();
                } else {
                    $img_src = $img_full_src = $img_gallery_src = wc_placeholder_img_src();
                }
            } else {
                $img_src = $img_full_src = $img_gallery_src = '';
            }

            $price_ori     = apply_filters( 'wooco_product_original_price', ( WPCleverWooco_Helper::get_setting( 'product_price', 'sale_price' ) === 'regular_price' ) ? $product->get_regular_price() : $product->get_price(), $product );
            $price_display = wc_get_price_to_display( $product, [ 'price' => $price_ori ] );
            $price_html    = apply_filters( 'wooco_product_original_price_html', $product->get_price_html(), $product );

            if ( $price !== '' ) {
                // new price — use static cache to avoid calling wc_get_product($default_id) repeatedly in loop
                static $default_product_cache = [];

                if ( ( $price === 'd' ) && $default_id ) {
                    if ( ! isset( $default_product_cache[ $default_id ] ) ) {
                        $default_product_cache[ $default_id ] = wc_get_product( $default_id );
                    }

                    $_default_product = $default_product_cache[ $default_id ];

                    if ( $_default_product ) {
                        if ( $default_id == $product->get_id() ) {
                            $new_price = 0;
                        } else {
                            $new_price = abs( $price_ori - $_default_product->get_price() );
                        }
                    } else {
                        $new_price = WPCleverWooco_Helper::get_new_price( (float) $price_ori, $price );
                    }
                } else {
                    $new_price = WPCleverWooco_Helper::get_new_price( (float) $price_ori, $price );
                }

                if ( $new_price !== (float) $price_ori ) {
                    $price_display = wc_get_price_to_display( $product, [ 'price' => $new_price ] );
                    $price_html    = wc_format_sale_price( wc_get_price_to_display( $product, [ 'price' => $price_ori ] ), $price_display );
                }
            }

            if ( ! $custom_qty && $show_qty ) {
                $name = $qty . ' &times; ' . $product->get_name();
            } else {
                $name = $product->get_name();
            }

            $desc = '';

            if ( $show_price ) {
                $desc .= '<span>' . $price_html . '</span>';
            }

            if ( $show_availability ) {
                $desc .= '<span>' . wc_get_stock_html( $product ) . '</span>';
            }

            if ( $show_short_description ) {
                if ( $product->is_type( 'variation' ) ) {
                    $desc .= '<div class="wooco_component_product_short_description wooco_component_variation_short_description">' . $product->get_description() . '</div>';
                } else {
                    $desc .= '<div class="wooco_component_product_short_description">' . $product->get_short_description() . '</div>';
                }
            }

            return apply_filters( 'wooco_product_data', [
                    'id'            => $product->get_id(),
                    'qty'           => $qty,
                    'pid'           => $product->is_type( 'variation' ) && $product->get_parent_id() ? $product->get_parent_id() : 0,
                    'purchasable'   => apply_filters( 'wooco_product_purchasable', ( self::is_purchasable( $product, $qty ) ? 'yes' : 'no' ), $product, $qty, $price ),
                    'link'          => apply_filters( 'wooco_product_link', ( ! $product->is_visible() && ! apply_filters( 'wooco_hidden_product_link', false ) ? '' : $product->get_permalink() ), $product, $qty, $price ),
                    'name'          => apply_filters( 'wooco_product_name', $name, $product, $qty, $price ),
                    'price'         => apply_filters( 'wooco_product_price', $price_display, $product, $qty, $price ),
                    'new-price'     => apply_filters( 'wooco_product_new_price', $price, $product, $qty, $price ),
                    'regular-price' => apply_filters( 'wooco_product_regular_price', wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ), $product, $qty, $price ),
                    'regular_price' => apply_filters( 'wooco_product_regular_price', wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ), $product, $qty, $price ),
                    'price-html'    => apply_filters( 'wooco_product_price_html', htmlentities( $price_html ), $product, $qty, $price ),
                    'price_html'    => apply_filters( 'wooco_product_price_html', htmlentities( $price_html ), $product, $qty, $price ),
                    'description'   => apply_filters( 'wooco_product_description', htmlentities( $desc ), $product, $qty, $price ),
                    'availability'  => apply_filters( 'wooco_product_availability', htmlentities( wc_get_stock_html( $product ) ), $product, $qty, $price ),
                    'image'         => apply_filters( 'wooco_product_image', $img_src, $product, $qty, $price ),
                    'imagesrc'      => apply_filters( 'wooco_product_image', $img_src, $product, $qty, $price ),
                    'imagefull'     => apply_filters( 'wooco_product_image_full', $img_full_src, $product, $qty, $price ),
                    'image_full'    => apply_filters( 'wooco_product_image_full', $img_full_src, $product, $qty, $price ),
                    'image_gallery' => apply_filters( 'wooco_product_image_gallery', $img_gallery_src, $product, $qty, $price ),
            ], $product, $qty, $price );
        }


        public function wpcsm_locations( $locations ) {
            $locations['WPC Composite Products'] = [
                    'wooco_before_wrap'       => esc_html__( 'Before container', 'wpc-composite-products' ),
                    'wooco_after_wrap'        => esc_html__( 'After container', 'wpc-composite-products' ),
                    'wooco_before_components' => esc_html__( 'Before component list', 'wpc-composite-products' ),
                    'wooco_after_components'  => esc_html__( 'After component list', 'wpc-composite-products' ),
                    'wooco_before_component'  => esc_html__( 'Before component', 'wpc-composite-products' ),
                    'wooco_after_component'   => esc_html__( 'After component', 'wpc-composite-products' ),
            ];

            return $locations;
        }
    }

    function WPCleverWooco() {
        return WPCleverWooco::instance();
    }
}
