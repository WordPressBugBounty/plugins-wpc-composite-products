<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWooco_Backend' ) ) {
    class WPCleverWooco_Backend {
        protected static ?self $instance = null;

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function __construct() {
            // Settings
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_filter( 'pre_update_option', [ $this, 'last_saved' ], 10, 2 );
            add_action( 'admin_menu', [ $this, 'admin_menu' ] );

            // Enqueue backend scripts
            add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

            // AJAX
            add_action( 'wp_ajax_wooco_add_component', [ $this, 'ajax_add_component' ] );
            add_action( 'wp_ajax_wooco_save_components', [ $this, 'ajax_save_components' ] );
            add_action( 'wp_ajax_wooco_export_components', [ $this, 'ajax_export_components' ] );
            add_action( 'wp_ajax_wooco_search_term', [ $this, 'ajax_search_term' ] );
            add_action( 'wp_ajax_wooco_search_product', [ $this, 'ajax_search_product' ] );

            // AJAX gallery

            // Add to selector
            add_filter( 'product_type_selector', [ $this, 'product_type_selector' ] );

            // Product data tabs
            add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );

            // Product data panels
            add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
            add_action( 'woocommerce_process_product_meta_composite', [ $this, 'process_meta_composite' ] );

            // Post states
            add_filter( 'display_post_states', [ $this, 'display_post_states' ], 10, 2 );

            // Admin order
            add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_order_itemmeta' ] );
            add_action( 'woocommerce_before_order_itemmeta', [ $this, 'before_order_itemmeta' ], 10, 2 );

            // Add settings link
            add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
            add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

            // Export
            add_filter( 'woocommerce_product_export_meta_value', [ $this, 'export_process' ], 10, 3 );

            // Import
            add_filter( 'woocommerce_product_import_pre_insert_product_object', [ $this, 'import_process' ], 10, 2 );
        }

        public function component( $active = false, $component = [], $key = null ) {
            if ( ! $key ) {
                $key = WPCleverWooco_Helper::generate_key();
            }

            $component_default = [
                    'name'       => '',
                    'desc'       => '',
                    'type'       => '',
                    'products'   => [],
                    'other'      => [],
                    'orderby'    => 'default',
                    'order'      => 'default',
                    'exclude'    => [],
                    'default'    => '',
                    'optional'   => 'yes',
                    'multiple'   => 'no',
                    'qty'        => 1,
                    'custom_qty' => 'no',
                    'price'      => '',
                    'min'        => 0,
                    'max'        => 1000,
                    'm_min'      => 0,
                    'm_max'      => 1000,
                    'selector'   => 'default',
            ];

            if ( ! empty( $component ) ) {
                $component = array_merge( $component_default, $component );
            } else {
                $component = $component_default;
            }

            if ( class_exists( 'WPCleverWoopq' ) && ( WPCleverWoopq::get_setting( 'decimal', 'no' ) === 'yes' ) ) {
                $step = '0.000001';
            } else {
                $step             = '1';
                $component['qty'] = (int) $component['qty'];
                $component['min'] = (int) $component['min'];
                $component['max'] = (int) $component['max'];
            }
            ?>
            <tr class="wooco_component">
                <td>
                    <div class="wooco_component_inner <?php echo esc_attr( $active ? 'active' : '' ); ?>">
                        <div class="wooco_component_heading">
                            <span class="wooco_move_component"></span>
                            <span class="wooco_component_name"><?php echo esc_html( wp_strip_all_tags( $component['name'] ) ); ?></span>
                            <a class="wooco_duplicate_component"
                               href="#"><?php esc_html_e( 'duplicate', 'wpc-composite-products' ); ?></a>
                            <a class="wooco_remove_component"
                               href="#"><?php esc_html_e( 'remove', 'wpc-composite-products' ); ?></a>
                        </div>
                        <div class="wooco_component_content">
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Name', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][name]' ); ?>"
                                               type="text"
                                               class="wooco_component_name_val"
                                               id="<?php echo esc_attr( 'wooco_component_name_val_' . $key ); ?>"
                                               value="<?php echo esc_attr( $component['name'] ); ?>"
                                               placeholder="<?php esc_attr_e( 'Name', 'wpc-composite-products' ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Description', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <textarea class="wooco_component_desc_val"
                                                  name="<?php echo esc_attr( 'wooco_components[' . $key . '][desc]' ); ?>"
                                                  placeholder="<?php esc_attr_e( 'Description', 'wpc-composite-products' ); ?>"><?php echo esc_textarea( $component['desc'] ); ?></textarea>
                                    </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Source', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][type]' ); ?>"
                                                class="wooco_component_type wooco_component_type_val"
                                                id="<?php echo esc_attr( 'wooco_component_type_val_' . $key ); ?>">
                                            <option value=""><?php esc_html_e( 'Select source', 'wpc-composite-products' ); ?>
                                            </option>
                                            <option value="products" <?php selected( $component['type'], 'products' ); ?>>
                                                <?php esc_html_e( 'Selected products', 'wpc-composite-products' ); ?>
                                            </option>
                                            <?php
                                            $taxonomies = get_object_taxonomies( 'product', 'objects' );

                                            foreach ( $taxonomies as $taxonomy ) {
                                                echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . selected( $component['type'], $taxonomy->name, false ) . ' disabled>' . esc_html( $taxonomy->label ) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </label>
                                    <span><?php esc_html_e( 'Order by', 'wpc-composite-products' ); ?> <label>
                                            <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][orderby]' ); ?>"
                                                    class="wooco_component_orderby_val"
                                                    id="<?php echo esc_attr( 'wooco_component_orderby_val_' . $key ); ?>">
                                                <option value="default" <?php selected( $component['orderby'], 'default' ); ?>>
                                                    <?php esc_html_e( 'Default', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="none" <?php selected( $component['orderby'], 'none' ); ?>>
                                                    <?php esc_html_e( 'None', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="ID" <?php selected( $component['orderby'], 'ID' ); ?>>
                                                    <?php esc_html_e( 'ID', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="title" <?php selected( $component['orderby'], 'title' ); ?>>
                                                    <?php esc_html_e( 'Name', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="type" <?php selected( $component['orderby'], 'type' ); ?>>
                                                    <?php esc_html_e( 'Type', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="rand" <?php selected( $component['orderby'], 'rand' ); ?>>
                                                    <?php esc_html_e( 'Rand', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="date" <?php selected( $component['orderby'], 'date' ); ?>>
                                                    <?php esc_html_e( 'Date', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="price" <?php selected( $component['orderby'], 'price' ); ?>>
                                                    <?php esc_html_e( 'Price', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="modified" <?php selected( $component['orderby'], 'modified' ); ?>>
                                                    <?php esc_html_e( 'Modified', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="menu_order" <?php selected( $component['orderby'], 'menu_order' ); ?>>
                                                    <?php esc_html_e( 'Menu order', 'wpc-composite-products' ); ?></option>
                                            </select>
                                        </label></span> &nbsp;
                                    <span><?php esc_html_e( 'Order', 'wpc-composite-products' ); ?> <label>
                                            <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][order]' ); ?>"
                                                    class="wooco_component_order_val"
                                                    id="<?php echo esc_attr( 'wooco_component_order_val_' . $key ); ?>">
                                                <option value="default" <?php selected( $component['order'], 'default' ); ?>>
                                                    <?php esc_html_e( 'Default', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="DESC" <?php selected( $component['order'], 'DESC' ); ?>>
                                                    <?php esc_html_e( 'DESC', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="ASC" <?php selected( $component['order'], 'ASC' ); ?>>
                                                    <?php esc_html_e( 'ASC', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select>
                                        </label></span>
                                </div>
                            </div>
                            <div class="wooco_component_content_line wooco_hide wooco_show_if_other">
                                <div class="wooco_component_content_line_label wooco_component_type_label">
                                    <?php esc_html_e( 'Terms', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <?php
                                    if ( ! is_array( $component['other'] ) ) {
                                        // old versions before 6.0
                                        $other = array_map( 'trim', explode( ',', $component['other'] ) );
                                    } else {
                                        $other = $component['other'];
                                    }
                                    ?>
                                    <label>
                                        <select class="wooco_terms wooco_component_other_val" multiple="multiple"
                                                id="<?php echo esc_attr( 'wooco_component_other_val_' . $key ); ?>"
                                                name="<?php echo esc_attr( 'wooco_components[' . $key . '][other][]' ); ?>"
                                                data-<?php echo esc_attr( $component['type'] ); ?>="<?php echo esc_attr( implode( ',', $other ) ); ?>">
                                            <?php
                                            if ( ! empty( $other ) ) {
                                                foreach ( $other as $t ) {
                                                    if ( $term = get_term_by( 'slug', $t, $component['type'] ) ) {
                                                        echo '<option value="' . esc_attr( $t ) . '" selected>' . esc_html( $term->name ) . '</option>';
                                                    }
                                                }
                                            }
                                            ?>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line wooco_hide wooco_show_if_products">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Products', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select class="wooco_products wooco_component_products_val"
                                                data-allow_clear="false"
                                                style="width: 100%;" data-sortable="1" multiple="multiple"
                                                id="<?php echo esc_attr( 'wooco_component_products_val_' . $key ); ?>"
                                                name="<?php echo esc_attr( 'wooco_components[' . $key . '][products][]' ); ?>"
                                                data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-composite-products' ); ?>">
                                            <?php
                                            if ( ! is_array( $component['products'] ) ) {
                                                // old versions before 6.0
                                                $_product_ids = explode( ',', $component['products'] );
                                            } else {
                                                $_product_ids = $component['products'];
                                            }

                                            if ( ! empty( $_product_ids ) ) {
                                                foreach ( $_product_ids as $_product_id ) {
                                                    if ( ! empty( $_product_id ) ) {
                                                        $_product_id = WPCleverWooco_Helper::get_product_id( $_product_id );

                                                        if ( $_product = wc_get_product( $_product_id ) ) {
                                                            echo '<option value="' . esc_attr( WPCleverWooco_Helper::get_product_sku_or_id( $_product ) ) . '" selected="selected">' . wp_kses_post( $_product->get_formatted_name() ) . '</option>';
                                                        }
                                                    }
                                                }
                                            }
                                            ?>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line wooco_show wooco_hide_if_products">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Exclude', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select class="wooco_products wooco_component_exclude_val" multiple="multiple"
                                                data-allow_clear="false" style="width: 100%;" data-sortable="1"
                                                id="<?php echo esc_attr( 'wooco_component_exclude_val_' . $key ); ?>"
                                                name="<?php echo esc_attr( 'wooco_components[' . $key . '][exclude][]' ); ?>"
                                                data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-composite-products' ); ?>">
                                            <?php
                                            if ( ! is_array( $component['exclude'] ) ) {
                                                // old versions before 6.0
                                                $_product_ids = explode( ',', $component['exclude'] );
                                            } else {
                                                $_product_ids = $component['exclude'];
                                            }

                                            if ( ! empty( $_product_ids ) ) {
                                                foreach ( $_product_ids as $_product_id ) {
                                                    if ( ! empty( $_product_id ) ) {
                                                        $_product_id = WPCleverWooco_Helper::get_product_id( $_product_id );

                                                        if ( $_product = wc_get_product( $_product_id ) ) {
                                                            echo '<option value="' . esc_attr( WPCleverWooco_Helper::get_product_sku_or_id( $_product ) ) . '" selected="selected">' . wp_kses_post( $_product->get_formatted_name() ) . '</option>';
                                                        }
                                                    }
                                                }
                                            }
                                            ?>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Default option', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select class="wooco_products" style="width: 100%;" data-allow_clear="true"
                                                id="<?php echo esc_attr( 'wooco_component_default_val_' . $key ); ?>"
                                                name="<?php echo esc_attr( 'wooco_components[' . $key . '][default]' ); ?>"
                                                data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-composite-products' ); ?>">
                                            <?php
                                            if ( ! empty( $component['default'] ) ) {
                                                $default_id = WPCleverWooco_Helper::get_product_id( $component['default'] );

                                                if ( $default_product = wc_get_product( $default_id ) ) {
                                                    echo '<option value="' . esc_attr( WPCleverWooco_Helper::get_product_sku_or_id( $default_product ) ) . '" selected="selected">' . wp_kses_post( $default_product->get_formatted_name() ) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Required', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][optional]' ); ?>"
                                                class="wooco_component_optional_val"
                                                id="<?php echo esc_attr( 'wooco_component_optional_val_' . $key ); ?>">
                                            <option value="no" <?php selected( $component['optional'], 'no' ); ?>>
                                                <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                            </option>
                                            <option value="yes" <?php selected( $component['optional'], 'yes' ); ?>>
                                                <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                            </option>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'New price', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][price]' ); ?>"
                                               class="wooco_component_price_val" type="text"
                                               style="width: 60px; display: inline-block"
                                               id="<?php echo esc_attr( 'wooco_component_price_val_' . $key ); ?>"
                                               value="<?php echo esc_attr( WPCleverWooco_Helper::format_price( $component['price'] ) ); ?>"/>
                                    </label>
                                    <span class="woocommerce-help-tip"
                                          data-tip="<?php esc_html_e( 'Set a new price using a number (eg. "49" for $49) or a percentage (eg. "90%" of the original price).', 'wpc-composite-products' ); ?>"></span>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Quantity', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][qty]' ); ?>"
                                               class="wooco_component_qty_val" type="number" min="0"
                                               step="<?php echo esc_attr( $step ); ?>"
                                               value="<?php echo esc_attr( $component['qty'] ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Custom quantity', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][custom_qty]' ); ?>"
                                                class="wooco_component_custom_qty_val"
                                                id="<?php echo esc_attr( 'wooco_component_custom_qty_val_' . $key ); ?>">
                                            <option value="no" <?php selected( $component['custom_qty'], 'no' ); ?>>
                                                <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                            </option>
                                            <option value="yes" <?php selected( $component['custom_qty'], 'yes' ); ?>>
                                                <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                            </option>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line wooco_show_if_custom_qty">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Each item\'s quantity limit', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <?php esc_html_e( 'Min', 'wpc-composite-products' ); ?>
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][min]' ); ?>"
                                               class="wooco_component_min_val" type="number" min="0"
                                               step="<?php echo esc_attr( $step ); ?>"
                                               value="<?php echo esc_attr( $component['min'] ); ?>"/>
                                    </label>
                                    <?php esc_html_e( 'Max', 'wpc-composite-products' ); ?>
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][max]' ); ?>"
                                               class="wooco_component_max_val" type="number" min="0"
                                               step="<?php echo esc_attr( $step ); ?>"
                                               value="<?php echo esc_attr( $component['max'] ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line wooco_show_if_custom_qty">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Whole component\'s quantity limit', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <?php esc_html_e( 'Min', 'wpc-composite-products' ); ?>
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][m_min]' ); ?>"
                                               class="wooco_component_m_min_val" type="number" min="0"
                                               step="<?php echo esc_attr( $step ); ?>"
                                               value="<?php echo esc_attr( $component['m_min'] ); ?>"/>
                                    </label>
                                    <?php esc_html_e( 'Max', 'wpc-composite-products' ); ?>
                                    <label>
                                        <input name="<?php echo esc_attr( 'wooco_components[' . $key . '][m_max]' ); ?>"
                                               class="wooco_component_m_max_val" type="number" min="0"
                                               step="<?php echo esc_attr( $step ); ?>"
                                               value="<?php echo esc_attr( $component['m_max'] ); ?>"/>
                                    </label>
                                    <span class="woocommerce-help-tip"
                                          data-tip="<?php esc_html_e( 'For multiple selection only.', 'wpc-composite-products' ); ?>"></span>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Multiple selection', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][multiple]' ); ?>"
                                                class="wooco_component_multiple_val"
                                                id="<?php echo esc_attr( 'wooco_component_multiple_val_' . $key ); ?>">
                                            <option value="no" <?php selected( $component['multiple'], 'no' ); ?>>
                                                <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                            </option>
                                            <option value="yes" <?php selected( $component['multiple'], 'yes' ); ?>>
                                                <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                            </option>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wooco_component_content_line">
                                <div class="wooco_component_content_line_label">
                                    <?php esc_html_e( 'Selector interface', 'wpc-composite-products' ); ?>
                                </div>
                                <div class="wooco_component_content_line_value">
                                    <label>
                                        <select name="<?php echo esc_attr( 'wooco_components[' . $key . '][selector]' ); ?>"
                                                class="wooco_component_selector_val"
                                                id="<?php echo esc_attr( 'wooco_component_selector_val_' . $key ); ?>">
                                            <option value="default" <?php selected( $component['selector'], 'default' ); ?>>
                                                <?php esc_html_e( 'Default', 'wpc-composite-products' ); ?>
                                            </option>
                                            <option value="list" <?php selected( $component['selector'], 'list' ); ?>>
                                                <?php esc_html_e( 'List', 'wpc-composite-products' ); ?>
                                            </option>
                                            <option value="grid_2" <?php selected( $component['selector'], 'grid_2' ); ?>>
                                                <?php esc_html_e( 'Grid - 2 columns', 'wpc-composite-products' ); ?>
                                            </option>
                                            <option value="grid_3" <?php selected( $component['selector'], 'grid_3' ); ?>>
                                                <?php esc_html_e( 'Grid - 3 columns', 'wpc-composite-products' ); ?>
                                            </option>
                                            <option value="grid_4" <?php selected( $component['selector'], 'grid_4' ); ?>>
                                                <?php esc_html_e( 'Grid - 4 columns', 'wpc-composite-products' ); ?>
                                            </option>
                                        </select> </label>
                                    <span class="woocommerce-help-tip"
                                          data-tip="<?php esc_html_e( 'Specify selector interface for this component. If not, the default selector interface will be used.', 'wpc-composite-products' ); ?>"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        <?php }

        public function ajax_add_component() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wooco_nonce' ) ) {
                die( 'Permissions check failed!' );
            }

            $component = [];
            $form_data = isset( $_POST['form_data'] ) ? sanitize_post( $_POST['form_data'] ) : '';

            if ( ! empty( $form_data ) ) {
                $components = [];
                parse_str( $form_data, $components );

                if ( isset( $components['wooco_components'] ) && is_array( $components['wooco_components'] ) ) {
                    $component = reset( $components['wooco_components'] );
                }
            }

            $this->component( true, $component );
            wp_die();
        }

        public function ajax_save_components() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wooco_nonce' ) ) {
                die( 'Permissions check failed!' );
            }

            if ( ! isset( $_POST['pid'] ) || ! current_user_can( 'edit_post', absint( sanitize_text_field( $_POST['pid'] ) ) ) ) {
                die( 'Permissions check failed!' );
            }

            $pid       = absint( sanitize_text_field( $_POST['pid'] ) );
            $form_data = isset( $_POST['form_data'] ) ? sanitize_post( $_POST['form_data'] ) : '';

            if ( $pid && $form_data ) {
                $components = [];
                parse_str( $form_data, $components );

                if ( isset( $components['wooco_components'] ) ) {
                    update_post_meta( $pid, 'wooco_components', WPCleverWooco_Helper::sanitize_array( $components['wooco_components'] ) );
                }

                // delete cache
                delete_transient( 'wooco_show_items_' . $pid );
            }

            wp_die();
        }

        public function ajax_export_components() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wooco_nonce' ) ) {
                die( 'Permissions check failed!' );
            }

            $product_id = absint( $_POST['pid'] ?? 0 );
            $components = get_post_meta( $product_id, 'wooco_components', true );
            echo '<textarea style="width: 100%; height: 200px">' . esc_textarea( ! empty( $components ) ? serialize( $components ) : '' ) . '</textarea>';
            echo '<div>' . esc_html__( 'You can copy this field and use it for a CSV import file.', 'wpc-composite-products' ) . '</div>';

            wp_die();
        }

        public function ajax_search_term() {
            if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wooco_nonce' ) ) {
                die( 'Permissions check failed!' );
            }

            $return = [];

            $args = [
                    'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
                    'orderby'    => 'id',
                    'order'      => 'ASC',
                    'hide_empty' => false,
                    'fields'     => 'all',
                    'name__like' => sanitize_text_field( $_REQUEST['term'] ),
            ];

            $terms = get_terms( $args );

            if ( is_array( $terms ) && count( $terms ) ) {
                foreach ( $terms as $term ) {
                    $return[] = [ $term->slug, $term->name ];
                }
            }

            wp_send_json( $return );
        }


        public function ajax_search_product() {
            if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wooco_nonce' ) ) {
                die( 'Permissions check failed!' );
            }

            if ( isset( $_REQUEST['term'] ) ) {
                $term = (string) wc_clean( wp_unslash( $_REQUEST['term'] ) );
            }

            if ( empty( $term ) ) {
                wp_die();
            }

            $products   = [];
            $limit      = absint( apply_filters( 'wooco_json_search_limit', 30 ) );
            $data_store = WC_Data_Store::load( 'product' );
            $ids        = $data_store->search_products( $term, '', true, false, $limit );

            foreach ( $ids as $id ) {
                $product_object = wc_get_product( $id );

                if ( ! wc_products_array_filter_readable( $product_object ) ) {
                    continue;
                }

                $products[] = [
                        WPCleverWooco_Helper::get_product_sku_or_id( $product_object ),
                        rawurldecode( wp_strip_all_tags( $product_object->get_formatted_name() ) )
                ];
            }

            wp_send_json( apply_filters( 'wooco_json_search_found_products', $products ) );
        }

        public function register_settings() {
            // settings
            register_setting( 'wooco_settings', 'wooco_settings', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'WPCleverWooco_Helper', 'sanitize_array' ],
            ] );

            // localization
            register_setting( 'wooco_localization', 'wooco_localization', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'WPCleverWooco_Helper', 'sanitize_array' ],
            ] );
        }

        public function last_saved( $value, $option ) {
            if ( $option == 'wooco_settings' || $option == 'wooco_localization' ) {
                $value['_last_saved']    = current_time( 'timestamp' );
                $value['_last_saved_by'] = get_current_user_id();
            }

            return $value;
        }

        public function admin_menu() {
            add_submenu_page( 'wpclever', esc_html__( 'WPC Composite Products', 'wpc-composite-products' ), esc_html__( 'Composite Products', 'wpc-composite-products' ), 'manage_options', 'wpclever-wooco', [
                    $this,
                    'admin_menu_content'
            ] );
        }

        public function admin_menu_content() {
            add_thickbox();
            $active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
            ?>
            <div class="wpclever_settings_page wrap">
                <div class="wpclever_settings_page_header">
                    <a class="wpclever_settings_page_header_logo" href="https://wpclever.net/" target="_blank"
                       title="Visit wpclever.net"></a>
                    <div class="wpclever_settings_page_header_text">
                        <div class="wpclever_settings_page_title">
                            <?php echo esc_html__( 'WPC Composite Products', 'wpc-composite-products' ) . ' ' . esc_html( WOOCO_VERSION ) . ' ' . ( defined( 'WOOCO_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-composite-products' ) . '</span>' : '' ); ?>
                        </div>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
                                <?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-composite-products' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOCO_REVIEWS ); ?>"
                                   target="_blank"><?php esc_html_e( 'Reviews', 'wpc-composite-products' ); ?></a> |
                                <a href="<?php echo esc_url( WOOCO_CHANGELOG ); ?>"
                                   target="_blank"><?php esc_html_e( 'Changelog', 'wpc-composite-products' ); ?></a> |
                                <a href="<?php echo esc_url( WOOCO_DISCUSSION ); ?>"
                                   target="_blank"><?php esc_html_e( 'Discussion', 'wpc-composite-products' ); ?></a>
                            </p>
                        </div>
                    </div>
                </div>
                <h2></h2>
                <?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Settings updated.', 'wpc-composite-products' ); ?></p>
                    </div>
                <?php } ?>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=how' ) ); ?>"
                           class="<?php echo $active_tab === 'how' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
                            <?php esc_html_e( 'How to use?', 'wpc-composite-products' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=settings' ) ); ?>"
                           class="<?php echo $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
                            <?php esc_html_e( 'Settings', 'wpc-composite-products' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=localization' ) ); ?>"
                           class="<?php echo $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
                            <?php esc_html_e( 'Localization', 'wpc-composite-products' ); ?>
                        </a> <a href="<?php echo esc_url( WOOCO_DOCS ); ?>" class="nav-tab" target="_blank">
                            <?php esc_html_e( 'Docs', 'wpc-composite-products' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=premium' ) ); ?>"
                           class="<?php echo $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>"
                           style="color: #c9356e">
                            <?php esc_html_e( 'Premium Version', 'wpc-composite-products' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
                            <?php esc_html_e( 'Essential Kit', 'wpc-composite-products' ); ?>
                        </a>
                    </h2>
                </div>
                <div class="wpclever_settings_page_content">
                    <?php if ( $active_tab === 'how' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>
                                <?php esc_html_e( 'When creating the product, please choose product data is "Smart composite" then you can see the search field to start search and add component products.', 'wpc-composite-products' ); ?>
                            </p>
                            <p>
                                <img src="<?php echo esc_url( WOOCO_URI . 'assets/images/how-01.jpg' ); ?>" alt=""/>
                            </p>
                        </div>
                        <?php
                    } elseif ( $active_tab === 'settings' ) {
                        $price_format             = WPCleverWooco_Helper::get_setting( 'price_format', 'from_regular' );
                        $product_price            = WPCleverWooco_Helper::get_setting( 'product_price', 'sale_price' );
                        $selector                 = WPCleverWooco_Helper::get_setting( 'selector', 'ddslick' );
                        $exclude_hidden           = WPCleverWooco_Helper::get_setting( 'exclude_hidden', 'no' );
                        $exclude_unpurchasable    = WPCleverWooco_Helper::get_setting( 'exclude_unpurchasable', 'yes' );
                        $show_alert               = WPCleverWooco_Helper::get_setting( 'show_alert', 'load' );
                        $show_qty                 = WPCleverWooco_Helper::get_setting( 'show_qty', 'yes' );
                        $show_plus_minus          = WPCleverWooco_Helper::get_setting( 'show_plus_minus', 'yes' );
                        $show_image               = WPCleverWooco_Helper::get_setting( 'show_image', 'yes' );
                        $show_price               = WPCleverWooco_Helper::get_setting( 'show_price', 'yes' );
                        $show_availability        = WPCleverWooco_Helper::get_setting( 'show_availability', 'yes' );
                        $show_short_description   = WPCleverWooco_Helper::get_setting( 'show_short_description', 'no' );
                        $option_none_image        = WPCleverWooco_Helper::get_setting( 'option_none_image', 'placeholder' );
                        $option_none_image_id     = WPCleverWooco_Helper::get_setting( 'option_none_image_id', '' );
                        $option_none_required     = WPCleverWooco_Helper::get_setting( 'option_none_required', 'no' );
                        $checkbox                 = WPCleverWooco_Helper::get_setting( 'checkbox', 'no' );
                        $checked                  = WPCleverWooco_Helper::get_setting( 'checked', 'yes' );
                        $change_image             = WPCleverWooco_Helper::get_setting( 'change_image', 'yes' );
                        $change_price             = WPCleverWooco_Helper::get_setting( 'change_price', 'yes' );
                        $change_price_custom      = WPCleverWooco_Helper::get_setting( 'change_price_custom', '.summary > .price' );
                        $product_link             = WPCleverWooco_Helper::get_setting( 'product_link', 'no' );
                        $coupon_restrictions      = WPCleverWooco_Helper::get_setting( 'coupon_restrictions', 'no' );
                        $cart_contents_count      = WPCleverWooco_Helper::get_setting( 'cart_contents_count', 'composite' );
                        $hide_composite_name      = WPCleverWooco_Helper::get_setting( 'hide_composite_name', 'no' );
                        $hide_component_name      = WPCleverWooco_Helper::get_setting( 'hide_component_name', 'yes' );
                        $hide_component           = WPCleverWooco_Helper::get_setting( 'hide_component', 'no' );
                        $hide_component_mini_cart = WPCleverWooco_Helper::get_setting( 'hide_component_mini_cart', 'no' );
                        $hide_component_order     = WPCleverWooco_Helper::get_setting( 'hide_component_order', 'no' );
                        $edit_link                = WPCleverWooco_Helper::get_setting( 'edit_link', 'no' );
                        ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( 'General', 'wpc-composite-products' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Price format', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[price_format]">
                                                <option value="from_regular" <?php selected( $price_format, 'from_regular' ); ?>>
                                                    <?php esc_html_e( 'From regular price', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="from_sale" <?php selected( $price_format, 'from_sale' ); ?>>
                                                    <?php esc_html_e( 'From sale price', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="normal" <?php selected( $price_format, 'normal' ); ?>>
                                                    <?php esc_html_e( 'Regular and sale price', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Choose a price format for composites on the archive page.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Calculate product prices', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[product_price]">
                                                <option value="sale_price" <?php selected( $product_price, 'sale_price' ); ?>>
                                                    <?php esc_html_e( 'from Sale price', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="regular_price" <?php selected( $product_price, 'regular_price' ); ?>>
                                                    <?php esc_html_e( 'from Regular price', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Component product pricing methods: from Sale price (default) or Regular price.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Selector interface', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[selector]">
                                                <option value="list" <?php selected( $selector, 'list' ); ?>>
                                                    <?php esc_html_e( 'List', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="grid_2" <?php selected( $selector, 'grid_2' ); ?>>
                                                    <?php esc_html_e( 'Grid - 2 columns', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="grid_3" <?php selected( $selector, 'grid_3' ); ?>>
                                                    <?php esc_html_e( 'Grid - 3 columns', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="grid_4" <?php selected( $selector, 'grid_4' ); ?>>
                                                    <?php esc_html_e( 'Grid - 4 columns', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="ddslick" <?php selected( $selector, 'ddslick' ); ?>>
                                                    <?php esc_html_e( 'Dropdown - ddSlick', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="select2" <?php selected( $selector, 'select2' ); ?>>
                                                    <?php esc_html_e( 'Dropdown - Select2', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="select" <?php selected( $selector, 'select' ); ?>>
                                                    <?php esc_html_e( 'Dropdown - HTML select tag', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span class="description">Read more about <a
                                                    href="https://designwithpc.com/Plugins/ddSlick"
                                                    target="_blank">ddSlick</a>, <a href="https://select2.org/"
                                                                                    target="_blank">Select2</a>
                                            and <a href="https://www.w3schools.com/tags/tag_select.asp" target="_blank">HTML select
                                                tag</a></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Exclude hidden', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[exclude_hidden]">
                                                <option value="yes" <?php selected( $exclude_hidden, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $exclude_hidden, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Exclude hidden products from the list.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Exclude unpurchasable', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[exclude_unpurchasable]">
                                                <option value="yes" <?php selected( $exclude_unpurchasable, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $exclude_unpurchasable, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Exclude unpurchasable products from the list.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show alert', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_alert]">
                                                <option value="load" <?php selected( $show_alert, 'load' ); ?>>
                                                    <?php esc_html_e( 'On composite loaded', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="change" <?php selected( $show_alert, 'change' ); ?>>
                                                    <?php esc_html_e( 'On composite changing', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $show_alert, 'no' ); ?>>
                                                    <?php esc_html_e( 'No, always hide the alert', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Show the inline alert under the components.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show quantity', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_qty]">
                                                <option value="yes" <?php selected( $show_qty, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $show_qty, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Show the quantity number before component product name.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show plus/minus button', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_plus_minus]">
                                                <option value="yes" <?php selected( $show_plus_minus, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $show_plus_minus, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Show the plus/minus button for the quantity input.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show image', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_image]">
                                                <option value="yes" <?php selected( $show_image, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $show_image, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show price', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_price]">
                                                <option value="yes" <?php selected( $show_price, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $show_price, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show availability', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_availability]">
                                                <option value="yes" <?php selected( $show_availability, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $show_availability, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show short description', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[show_short_description]">
                                                <option value="yes" <?php selected( $show_short_description, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $show_short_description, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Component selector', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[checkbox]" class="wooco_settings_checkbox">
                                                <option value="yes" <?php selected( $checkbox, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Checkbox', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $checkbox, 'no' ); ?>>
                                                    <?php esc_html_e( 'Option none', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Use checkbox or Option none for the dropdown selector.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="wooco_settings_checkbox_hide wooco_settings_checkbox_show_yes">
                                    <th><?php esc_html_e( 'Checked by default', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[checked]">
                                                <option value="yes" <?php selected( $checked, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $checked, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Mark checkboxes as checked by default.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="wooco_settings_checkbox_hide wooco_settings_checkbox_show_no">
                                    <th><?php esc_html_e( 'Show "Option none" for required component', 'wpc-composite-products' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="wooco_settings[option_none_required]">
                                                <option value="yes" <?php selected( $option_none_required, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $option_none_required, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="wooco_settings_checkbox_hide wooco_settings_checkbox_show_no">
                                    <th><?php esc_html_e( '"Option none" image', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <select name="wooco_settings[option_none_image]"
                                                    class="wooco_option_none_image">
                                                <option value="placeholder" <?php selected( $option_none_image, 'placeholder' ); ?>>
                                                    <?php esc_html_e( 'Placeholder image', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="product" <?php selected( $option_none_image, 'product' ); ?>>
                                                    <?php esc_html_e( 'Main product\'s image', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="custom" <?php selected( $option_none_image, 'custom' ); ?>>
                                                    <?php esc_html_e( 'Custom image', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="none" <?php selected( $option_none_image, 'none' ); ?>>
                                                    <?php esc_html_e( 'None (hide it)', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'If you choose "Placeholder image", you can change it in WooCommerce > Settings > Products > Placeholder image.', 'wpc-composite-products' ); ?></span>
                                        <div class="wooco_option_none_image_custom" style="display: none">
                                            <?php wp_enqueue_media(); ?>
                                            <span class="wooco_option_none_image_preview"
                                                  id="wooco_option_none_image_preview">
                                                <?php if ( $option_none_image_id ) {
                                                    echo '<img src="' . esc_url( wp_get_attachment_url( $option_none_image_id ) ) . '"/>';
                                                } ?>
                                            </span>
                                            <input id="wooco_option_none_image_upload" type="button" class="button"
                                                   value="<?php esc_attr_e( 'Upload image', 'wpc-composite-products' ); ?>"/>
                                            <input type="hidden" name="wooco_settings[option_none_image_id]"
                                                   id="wooco_option_none_image_id"
                                                   value="<?php echo esc_attr( $option_none_image_id ); ?>"/>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Change image gallery', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[change_image]">
                                                <option value="yes" <?php selected( $change_image, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $change_image, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Change the main product’s image gallery based on selected products.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Change price', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[change_price]" class="wooco_change_price">
                                                <option value="yes" <?php selected( $change_price, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="yes_custom" <?php selected( $change_price, 'yes_custom' ); ?>>
                                                    <?php esc_html_e( 'Yes, custom selector', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $change_price, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label> <label>
                                            <input type="text" name="wooco_settings[change_price_custom]"
                                                   value="<?php echo esc_attr( $change_price_custom ); ?>"
                                                   placeholder=".summary > .price"
                                                   class="wooco_change_price_custom"/>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e( 'Change the main product’s price based on the changes in prices of selected variations in a grouped products. This uses Javascript to change the main product’s price to it depends heavily on theme’s HTML. If the price doesn\'t change when this option is enabled, please contact us and we can help you adjust the JS file.', 'wpc-composite-products' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Link to individual product', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[product_link]">
                                                <option value="yes" <?php selected( $product_link, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes, open on the same tab', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="yes_blank" <?php selected( $product_link, 'yes_blank' ); ?>>
                                                    <?php esc_html_e( 'Yes, open on a new tab', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="yes_popup" <?php selected( $product_link, 'yes_popup' ); ?>>
                                                    <?php esc_html_e( 'Yes, open quick view popup', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $product_link, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <p class="description">
                                            <?php esc_html_e( 'Add a link to the target individual product below this selection.', 'wpc-composite-products' ); ?>
                                            If you choose "Open quick view popup", please install
                                            <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>"
                                               class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to
                                            make it work.
                                        </p>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Cart & Checkout', 'wpc-composite-products' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Coupon restrictions', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[coupon_restrictions]">
                                                <option value="no" <?php selected( $coupon_restrictions, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="composite" <?php selected( $coupon_restrictions, 'composite' ); ?>>
                                                    <?php esc_html_e( 'Exclude composite', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="component" <?php selected( $coupon_restrictions, 'component' ); ?>>
                                                    <?php esc_html_e( 'Exclude component products', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="both" <?php selected( $coupon_restrictions, 'both' ); ?>>
                                                    <?php esc_html_e( 'Exclude both composite and component products', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Choose products you want to exclude from coupons.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Cart content count', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[cart_contents_count]">
                                                <option value="composite" <?php selected( $cart_contents_count, 'composite' ); ?>>
                                                    <?php esc_html_e( 'Composite only', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="component_products" <?php selected( $cart_contents_count, 'component_products' ); ?>>
                                                    <?php esc_html_e( 'Component products only', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="both" <?php selected( $cart_contents_count, 'both' ); ?>>
                                                    <?php esc_html_e( 'Both composite and component products', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide composite name before component products', 'wpc-composite-products' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="wooco_settings[hide_composite_name]">
                                                <option value="yes" <?php selected( $hide_composite_name, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $hide_composite_name, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide component name before component products', 'wpc-composite-products' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="wooco_settings[hide_component_name]">
                                                <option value="yes" <?php selected( $hide_component_name, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $hide_component_name, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide component products on mini-cart', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[hide_component_mini_cart]">
                                                <option value="yes" <?php selected( $hide_component_mini_cart, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $hide_component_mini_cart, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Hide component products, just show the main composite on mini-cart.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide component products on cart & checkout page', 'wpc-composite-products' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="wooco_settings[hide_component]">
                                                <option value="yes" <?php selected( $hide_component, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes, just show the main composite', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="yes_text" <?php selected( $hide_component, 'yes_text' ); ?>>
                                                    <?php esc_html_e( 'Yes, but shortly list component sub-product names under the main composite in one line', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="yes_list" <?php selected( $hide_component, 'yes_list' ); ?>>
                                                    <?php esc_html_e( 'Yes, but list component sub-product names under the main composite in separate lines', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $hide_component, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide component products on order details', 'wpc-composite-products' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="wooco_settings[hide_component_order]">
                                                <option value="yes" <?php selected( $hide_component_order, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="yes_text" <?php selected( $hide_component_order, 'yes_text' ); ?>>
                                                    <?php esc_html_e( 'Yes, but shortly list component sub-product names under the main composite in one line', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="yes_list" <?php selected( $hide_component_order, 'yes_list' ); ?>>
                                                    <?php esc_html_e( 'Yes, but list component sub-product names under the main composite in separate lines', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $hide_component_order, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label>
                                        <p class="description">
                                            <?php esc_html_e( 'Hide component products, just show the main composite on order details (order confirmation or emails).', 'wpc-composite-products' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Edit link (Beta)', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label> <select name="wooco_settings[edit_link]">
                                                <option value="yes" <?php selected( $edit_link, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'wpc-composite-products' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $edit_link, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'wpc-composite-products' ); ?>
                                                </option>
                                            </select> </label> <span
                                                class="description"><?php esc_html_e( 'Enable the edit link for composite products on the cart page.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
                                        <div class="wpclever_submit">
                                            <?php
                                            settings_fields( 'wooco_settings' );
                                            submit_button( '', 'primary', 'submit', false );

                                            if ( function_exists( 'wpc_last_saved' ) ) {
                                                wpc_last_saved( WPCleverWooco_Helper::get_settings() );
                                            }
                                            ?>
                                        </div>
                                        <a style="display: none;" class="wpclever_export" data-key="wooco_settings"
                                           data-name="settings"
                                           href="#"><?php esc_html_e( 'import / export', 'wpc-composite-products' ); ?></a>
                                    </th>
                                </tr>
                            </table>
                        </form>
                    <?php } elseif ( $active_tab === 'localization' ) { ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th scope="row"><?php esc_html_e( 'General', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-composite-products' ); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Option none (optional component)', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="wooco_localization[option_none]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'option_none' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'No, thanks. I don\'t need this', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Text to display for showing a "Don\'t choose any product" option.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Option none (required component)', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="wooco_localization[option_none_required]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'option_none_required' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Please make your choice here', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Text to display for showing a "Don\'t choose any product" option.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total text', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wooco_localization[total]" class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'total' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Total price:', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Selected text', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wooco_localization[selected]" class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'selected' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Selected:', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Saved text', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wooco_localization[saved]" class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'saved' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( '(saved [d])', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Use [d] to show the saved percentage.', 'wpc-composite-products' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( '"Add to cart" button labels', 'wpc-composite-products' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Shop/archive page', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <div style="margin-bottom: 5px">
                                            <label>
                                                <input type="text" class="regular-text"
                                                       name="wooco_localization[button_select]"
                                                       value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'button_select' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Select options', 'wpc-composite-products' ); ?>"/>
                                            </label>
                                            <span
                                                    class="description"><?php esc_html_e( 'For purchasable composites.', 'wpc-composite-products' ); ?></span>
                                        </div>
                                        <div>
                                            <label>
                                                <input type="text" class="regular-text"
                                                       name="wooco_localization[button_read]"
                                                       value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'button_read' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Read more', 'wpc-composite-products' ); ?>"/>
                                            </label>
                                            <span
                                                    class="description"><?php esc_html_e( 'For unpurchasable composites.', 'wpc-composite-products' ); ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Single product page', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="wooco_localization[button_single]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'button_single' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Add to cart', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Cart & Checkout', 'wpc-composite-products' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Components', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="wooco_localization[cart_components]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'cart_components' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Components', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php /* translators: components */
                                        esc_html_e( 'Components: %s', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="wooco_localization[cart_components_s]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'cart_components_s' ) ); ?>"
                                                   placeholder="<?php /* translators: components */
                                                   esc_attr_e( 'Components: %s', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php /* translators: composite */
                                        esc_html_e( 'Composite: %s', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="wooco_localization[cart_composite_s]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'cart_composite_s' ) ); ?>"
                                                   placeholder="<?php /* translators: composite */
                                                   esc_attr_e( 'Composite: %s', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Edit', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="wooco_localization[cart_item_edit]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'cart_item_edit' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Edit', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Update', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="wooco_localization[cart_item_update]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'cart_item_update' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Update', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Alert', 'wpc-composite-products' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Require selection', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text"
                                                   name="wooco_localization[alert_selection]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'alert_selection' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Please choose a purchasable product for the component [name] before adding this composite to the cart.', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Different selection', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_same]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'alert_same' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Please select a different product for each component.', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Whole component\'s quantity minimum required', 'wpc-composite-products' ); ?>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_m_min]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'alert_m_min' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Please choose at least a total quantity of [min] products for the component [name].', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Whole component\'s quantity maximum reached', 'wpc-composite-products' ); ?>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_m_max]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'alert_m_max' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Sorry, you can only choose at max a total quantity of [max] products for the component [name].', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Minimum required', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_min]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'alert_min' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Please choose at least a total quantity of [min] products before adding this composite to the cart.', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Maximum reached', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text" name="wooco_localization[alert_max]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'alert_max' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Sorry, you can only choose at max a total quantity of [max] products before adding this composite to the cart.', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total minimum required', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text"
                                                   name="wooco_localization[alert_total_min]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'alert_total_min' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'The total must meet the minimum amount of [min].', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total maximum required', 'wpc-composite-products' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="large-text"
                                                   name="wooco_localization[alert_total_max]"
                                                   value="<?php echo esc_attr( WPCleverWooco_Helper::localization( 'alert_total_max' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'The total must meet the maximum amount of [max].', 'wpc-composite-products' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
                                        <div class="wpclever_submit">
                                            <?php
                                            settings_fields( 'wooco_localization' );
                                            submit_button( '', 'primary', 'submit', false );

                                            if ( function_exists( 'wpc_last_saved' ) ) {
                                                wpc_last_saved( get_option( 'wooco_localization', [] ) );
                                            }
                                            ?>
                                        </div>
                                        <a style="display: none;" class="wpclever_export" data-key="wooco_localization"
                                           data-name="settings"
                                           href="#"><?php esc_html_e( 'import / export', 'wpc-composite-products' ); ?></a>
                                    </th>
                                </tr>
                            </table>
                        </form>
                    <?php } elseif ( $active_tab == 'tools' ) { ?>
                        <table class="form-table">
                            <tr class="heading">
                                <th scope="row"><?php esc_html_e( 'Data Migration', 'wpc-composite-products' ); ?></th>
                                <td>
                                    <?php esc_html_e( 'If selected products don\'t appear on the current version. Please try running Migrate tool.', 'wpc-composite-products' ); ?>

                                    <?php
                                    echo '<p>';
                                    $num   = absint( $_GET['num'] ?? 50 );
                                    $paged = absint( $_GET['paged'] ?? 1 );

                                    if ( isset( $_GET['act'] ) && ( $_GET['act'] === 'migrate' ) ) {
                                        $args = [
                                                'post_type'      => 'product',
                                                'posts_per_page' => $num,
                                                'paged'          => $paged,
                                                'meta_query'     => [
                                                        [
                                                                'key'     => 'wooco_components',
                                                                'compare' => 'EXISTS'
                                                        ]
                                                ]
                                        ];

                                        $posts = get_posts( $args );

                                        if ( ! empty( $posts ) ) {
                                            foreach ( $posts as $post ) {
                                                $components = get_post_meta( $post->ID, 'wooco_components', true );

                                                if ( is_array( $components ) && ! empty( $components ) ) {
                                                    $new_components = [];

                                                    foreach ( $components as $component ) {
                                                        if ( ! empty( $component['products'] ) && is_string( $component['products'] ) ) {
                                                            $component['products'] = explode( ',', $component['products'] );
                                                        }

                                                        if ( ( $component['type'] === 'categories' ) && ! empty( $component['categories'] ) ) {
                                                            $component['type'] = 'product_cat';

                                                            if ( is_string( $component['categories'] ) ) {
                                                                $component['other'] = explode( ',', $component['categories'] );
                                                            } else {
                                                                $component['other'] = $component['categories'];
                                                            }

                                                            foreach ( $component['other'] as $k => $c ) {
                                                                $component['other'][ $k ] = WPCleverWooco_Helper::get_term_slug( $c, 'product_cat' );
                                                            }

                                                            unset( $component['categories'] );
                                                        }

                                                        if ( ( $component['type'] === 'tags' ) && ! empty( $component['tags'] ) ) {
                                                            $component['type'] = 'product_tag';

                                                            if ( is_string( $component['tags'] ) ) {
                                                                $component['other'] = explode( ',', $component['tags'] );
                                                            } else {
                                                                $component['other'] = $component['tags'];
                                                            }

                                                            foreach ( $component['other'] as $k => $t ) {
                                                                $component['other'][ $k ] = WPCleverWooco_Helper::get_term_slug( $t, 'product_tag' );
                                                            }

                                                            unset( $component['tags'] );
                                                        }

                                                        $new_key                    = WPCleverWooco_Helper::generate_key();
                                                        $new_components[ $new_key ] = $component;
                                                    }

                                                    update_post_meta( $post->ID, 'wooco_components', $new_components );
                                                }
                                            }

                                            echo '<span style="color: #2271b1; font-weight: 700">' . esc_html__( 'Migrating...', 'wpc-composite-products' ) . '</span>';
                                            echo '<p class="description">' . esc_html__( 'Please wait until it has finished!', 'wpc-composite-products' ) . '</p>';
                                            ?>
                                            <script type="text/javascript">
                                                (function ($) {
                                                    $(function () {
                                                        setTimeout(function () {
                                                            window.location.href = '<?php echo esc_url_raw( admin_url( 'admin.php?page=wpclever-wooco&tab=tools&act=migrate&num=' . $num . '&paged=' . ( $paged + 1 ) ) ); ?>';
                                                        }, 1000);
                                                    });
                                                })(jQuery);
                                            </script>
                                        <?php } else {
                                            echo '<span style="color: #2271b1; font-weight: 700">' . esc_html__( 'Finished!', 'wpc-composite-products' ) . '</span>';
                                        }
                                    } else {
                                        echo '<a class="button btn" href="' . esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=tools&act=migrate' ) ) . '">' . esc_html__( 'Migrate', 'wpc-composite-products' ) . '</a>';
                                    }
                                    echo '</p>';
                                    ?>
                                </td>
                            </tr>
                        </table>
                    <?php } elseif ( $active_tab == 'premium' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>
                                Get the Premium Version just $29!
                                <a href="https://wpclever.net/downloads/composite-products?utm_source=pro&utm_medium=wooco&utm_campaign=wporg"
                                   target="_blank">https://wpclever.net/downloads/composite-products</a>
                            </p>
                            <p><strong>Extra features for Premium Version:</strong></p>
                            <ul style="margin-bottom: 0">
                                <li>- Use Categories, Tags, or Attributes as the source for component options.</li>
                                <li>- Get the lifetime update & premium support.</li>
                            </ul>
                        </div>
                    <?php } ?>
                </div><!-- /.wpclever_settings_page_content -->
                <div class="wpclever_settings_page_suggestion">
                    <div class="wpclever_settings_page_suggestion_label">
                        <span class="dashicons dashicons-yes-alt"></span> Suggestion
                    </div>
                    <div class="wpclever_settings_page_suggestion_content">
                        <div>
                            To display custom engaging real-time messages on any wished positions, please install
                            <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart
                                Messages</a> plugin. It's free!
                        </div>
                        <div>
                            Wanna save your precious time working on variations? Try our brand-new free plugin
                            <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC
                                Variation Bulk Editor</a> and
                            <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC
                                Variation Duplicator</a>.
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        public function admin_enqueue_scripts( $hook ) {
            if ( apply_filters( 'wooco_ignore_backend_scripts', false, $hook ) ) {
                return null;
            }

            wp_enqueue_style( 'hint', WOOCO_URI . 'assets/css/hint.css' );
            wp_enqueue_style( 'wooco-backend', WOOCO_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WOOCO_VERSION );
            wp_enqueue_script( 'wooco-backend', WOOCO_URI . 'assets/js/backend.js', [
                    'jquery',
                    'jquery-ui-dialog',
                    'jquery-ui-sortable',
                    'wc-enhanced-select',
                    'selectWoo'
            ], WOOCO_VERSION, true );
            wp_localize_script(
                    'wooco-backend',
                    'wooco_vars',
                    [
                            'nonce' => wp_create_nonce( 'wooco_nonce' )
                    ]
            );
        }

        public function action_links( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOCO_FILE );
            }

            if ( $plugin === $file ) {
                $settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-composite-products' ) . '</a>';
                $links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wooco&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'wpc-composite-products' ) . '</a>';
                array_unshift( $links, $settings );
            }

            return (array) $links;
        }

        public function row_meta( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOCO_FILE );
            }

            if ( $plugin === $file ) {
                $row_meta = [
                        'docs'    => '<a href="' . esc_url( WOOCO_DOCS ) . '" target="_blank">' . esc_html__( 'Docs', 'wpc-composite-products' ) . '</a>',
                        'support' => '<a href="' . esc_url( WOOCO_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-composite-products' ) . '</a>',
                ];

                return array_merge( $links, $row_meta );
            }

            return (array) $links;
        }

        public function hidden_order_itemmeta( $hidden ) {
            return array_merge( $hidden, [
                    'wooco_parent_id',
                    'wooco_qty',
                    'wooco_ids',
                    'wooco_price',
                    'wooco_pos',
                    'wooco_component'
            ] );
        }

        public function before_order_itemmeta( $order_item_id, $order_item ) {
            if ( $ids = $order_item->get_meta( 'wooco_ids' ) ) {
                if ( $items = WPCleverWooco_Helper::get_items( $ids ) ) {
                    $items_str = [];

                    foreach ( $items as $item ) {
                        if ( $item_product = wc_get_product( $item['id'] ) ) {
                            if ( ( WPCleverWooco_Helper::get_setting( 'hide_component_name', 'yes' ) === 'no' ) && ! empty( $item['component'] ) ) {
                                $items_str[] = apply_filters( 'wooco_admin_order_component_product_name', '<li>' . $item['component'] . ': ' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item );
                            } else {
                                $items_str[] = apply_filters( 'wooco_admin_order_component_product_name', '<li>' . $item['qty'] . ' × ' . $item_product->get_name() . '</li>', $item );
                            }
                        }
                    }

                    $items_str = apply_filters( 'wooco_order_component_product_names', '<ul>' . implode( '', $items_str ) . '</ul>', $items );

                    echo wp_kses_post( apply_filters( 'wooco_before_admin_order_itemmeta_composite', '<div class="wooco-itemmeta-composite">' . sprintf( WPCleverWooco_Helper::localization( 'cart_components_s', /* translators: components */ esc_html__( 'Components: %s', 'wpc-composite-products' ) ), $items_str ) . '</div>', $order_item_id, $order_item ) );
                }
            }

            if ( ( $parent_id = $order_item->get_meta( 'wooco_parent_id' ) ) && ( $parent_product = wc_get_product( $parent_id ) ) ) {
                if ( ( $component = $order_item->get_meta( 'wooco_component' ) ) && ! empty( $component ) ) {
                    echo wp_kses_post( apply_filters( 'wooco_before_admin_order_itemmeta_component', '<div class="wooco-itemmeta-component">' . sprintf( WPCleverWooco_Helper::localization( 'cart_composite_s', /* translators: composite */ esc_html__( 'Composite: %s', 'wpc-composite-products' ) ), $parent_product->get_name() . apply_filters( 'wooco_name_separator', ' &rarr; ' ) . $component ) . '</div>', $order_item_id, $order_item ) );
                } else {
                    echo wp_kses_post( apply_filters( 'wooco_before_admin_order_itemmeta_component', '<div class="wooco-itemmeta-component">' . sprintf( WPCleverWooco_Helper::localization( 'cart_composite_s', /* translators: composite */ esc_html__( 'Composite: %s', 'wpc-composite-products' ) ), $parent_product->get_name() ) . '</div>', $order_item_id, $order_item ) );
                }
            }
        }

        public function display_post_states( $states, $post ) {
            if ( 'product' == get_post_type( $post->ID ) ) {
                if ( ( $product = wc_get_product( $post->ID ) ) && $product->is_type( 'composite' ) ) {
                    $count = 0;

                    if ( ( $components = $product->get_components() ) && is_array( $components ) ) {
                        $count = count( $components );
                    }

                    $states[] = apply_filters( 'wooco_post_states', '<span class="wooco-state">' . sprintf( /* translators: count */ esc_html__( 'Composite (%d)', 'wpc-composite-products' ), $count ) . '</span>', $count, $product );
                }
            }

            return $states;
        }

        public function product_type_selector( $types ) {
            $types['composite'] = esc_html__( 'Smart composite', 'wpc-composite-products' );

            return $types;
        }

        public function product_data_tabs( $tabs ) {
            $tabs['composite'] = [
                    'label'  => esc_html__( 'Components', 'wpc-composite-products' ),
                    'target' => 'wooco_settings',
                    'class'  => [ 'show_if_composite' ],
            ];

            return $tabs;
        }

        public function product_data_panels() {
            global $post, $thepostid, $product_object;

            if ( $product_object instanceof WC_Product ) {
                $product_id = $product_object->get_id();
            } elseif ( is_numeric( $thepostid ) ) {
                $product_id = $thepostid;
            } elseif ( $post instanceof WP_Post ) {
                $product_id = $post->ID;
            } else {
                $product_id = 0;
            }

            if ( ! $product_id ) {
                ?>
                <div id='wooco_settings' class='panel woocommerce_options_panel wooco_table'>
                    <p style="padding: 0 12px; color: #c9356e">
                        <?php esc_html_e( 'Product wasn\'t returned.', 'wpc-composite-products' ); ?>
                    </p>
                </div>
                <?php
                return;
            }

            $components    = get_post_meta( $product_id, 'wooco_components', true );
            $pricing       = get_post_meta( $product_id, 'wooco_pricing', true );
            $same_products = get_post_meta( $product_id, 'wooco_same_products', true );
            $shipping_fee  = get_post_meta( $product_id, 'wooco_shipping_fee', true );
            ?>
            <div id='wooco_settings' class='panel woocommerce_options_panel wooco_table'>
                <table class="wooco_components">
                    <thead></thead>
                    <tbody>
                    <?php if ( ! empty( $components ) && is_array( $components ) ) {
                        foreach ( $components as $component_key => $component ) {
                            $this->component( false, $component, $component_key );
                        }
                    } else {
                        $this->component( true );
                    } ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td>
                            <div>
                                <a href="#" class="wooco_add_component button">
                                    <?php esc_html_e( '+ Add component', 'wpc-composite-products' ); ?>
                                </a> <a href="#" class="wooco_expand_all">
                                    <?php esc_html_e( 'Expand All', 'wpc-composite-products' ); ?>
                                </a> <a href="#" class="wooco_collapse_all">
                                    <?php esc_html_e( 'Collapse All', 'wpc-composite-products' ); ?>
                                </a>
                            </div>
                            <div>
                                <!--
                                <a href="#" class="wooco_export_components hint--left" aria-label="<?php esc_attr_e( 'Remember to save current components before exporting to get the latest version.', 'wpc-composite-products' ); ?>">
                                    <?php esc_html_e( 'Export', 'wpc-composite-products' ); ?>
                                </a>
                                -->
                                <a href="#" class="wooco_save_components button button-primary">
                                    <?php esc_html_e( 'Save components', 'wpc-composite-products' ); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    </tfoot>
                </table>
                <table>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Pricing', 'wpc-composite-products' ); ?></th>
                        <td>
                            <label for="wooco_pricing"></label><select id="wooco_pricing" name="wooco_pricing">
                                <option value="only" <?php selected( $pricing, 'only' ); ?>>
                                    <?php esc_html_e( 'Only base price', 'wpc-composite-products' ); ?>
                                </option>
                                <option value="include" <?php selected( $pricing, 'include' ); ?>>
                                    <?php esc_html_e( 'Include base price', 'wpc-composite-products' ); ?>
                                </option>
                                <option value="exclude" <?php selected( $pricing, 'exclude' ); ?>>
                                    <?php esc_html_e( 'Exclude base price', 'wpc-composite-products' ); ?>
                                </option>
                            </select>
                            <span class="woocommerce-help-tip"
                                  data-tip="<?php esc_attr_e( '"Base price" is the price set in the General tab. When "Only base price" is chosen, the total price won\'t change despite the price changes in variable components.', 'wpc-composite-products' ); ?>"></span>
                            <span style="color: #c9356e">*
                                <?php esc_html_e( 'Always put a price in the General tab to display the Add to Cart button. This is also the base price.', 'wpc-composite-products' ); ?></span>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Discount', 'wpc-composite-products' ); ?></th>
                        <td style="vertical-align: middle; line-height: 30px;">
                            <label for="wooco_discount_percent"></label><input id="wooco_discount_percent"
                                                                               name="wooco_discount_percent"
                                                                               type="number" min="0.0001" step="0.0001"
                                                                               max="99.9999"
                                                                               value="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_discount_percent', true ) ?: '' ); ?>"
                                                                               style="width: 80px"/>%.
                            <span class="woocommerce-help-tip"
                                  data-tip="<?php esc_attr_e( 'The universal percentage discount will be applied equally on each component\'s price, not on the total.', 'wpc-composite-products' ); ?>"></span>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <?php
                        $min = get_post_meta( $product_id, 'wooco_qty_min', true ) ?: '';
                        $max = get_post_meta( $product_id, 'wooco_qty_max', true ) ?: '';

                        if ( class_exists( 'WPCleverWoopq' ) && ( WPCleverWoopq::get_setting( 'decimal', 'no' ) === 'yes' ) ) {
                            $step = '0.000001';
                        } else {
                            $step = '1';

                            if ( ! empty( $min ) ) {
                                $min = (int) $min;
                            }

                            if ( ! empty( $max ) ) {
                                $max = (int) $max;
                            }
                        }
                        ?>
                        <th><?php esc_html_e( 'Quantity', 'wpc-composite-products' ); ?></th>
                        <td style="vertical-align: middle; line-height: 30px;">
                            Min <label>
                                <input name="wooco_qty_min" type="number" style="width: 80px" min="0"
                                       step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $min ); ?>"/>
                            </label> Max <label>
                                <input name="wooco_qty_max" type="number" min="0" style="width: 80px"
                                       step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $max ); ?>"/>
                            </label>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Total limits', 'wpc-composite-products' ); ?></th>
                        <td>
                            <input id="wooco_total_limits" name="wooco_total_limits"
                                   type="checkbox" <?php echo( get_post_meta( $product_id, 'wooco_total_limits', true ) === 'on' ? 'checked' : '' ); ?> />
                            <label
                                    for="wooco_total_limits"><?php esc_html_e( 'Configure total limits for the current composite.', 'wpc-composite-products' ); ?></label>
                            <span class="wooco_show_if_total_limits">
                                Min <label for="wooco_total_limits_min"></label><input id="wooco_total_limits_min"
                                                                                       name="wooco_total_limits_min"
                                                                                       type="number" min="0"
                                                                                       style="width: 80px"
                                                                                       value="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_total_limits_min', true ) ); ?>"/>
                                Max <label for="wooco_total_limits_max"></label><input id="wooco_total_limits_max"
                                                                                       name="wooco_total_limits_max"
                                                                                       type="number" min="0"
                                                                                       style="width: 80px"
                                                                                       value="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_total_limits_max', true ) ); ?>"/>
                                <?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Same products', 'wpc-composite-products' ); ?></th>
                        <td>
                            <label for="wooco_same_products"></label><select id="wooco_same_products"
                                                                             name="wooco_same_products">
                                <option value="allow" <?php selected( $same_products, 'allow' ); ?>>
                                    <?php esc_html_e( 'Allow', 'wpc-composite-products' ); ?>
                                </option>
                                <option value="do_not_allow" <?php selected( $same_products, 'do_not_allow' ); ?>>
                                    <?php esc_html_e( 'Do not allow', 'wpc-composite-products' ); ?>
                                </option>
                            </select>
                            <span class="woocommerce-help-tip"
                                  data-tip="<?php esc_attr_e( 'Allow/Do not allow the buyer to choose the same products in the components.', 'wpc-composite-products' ); ?>"></span>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Shipping fee', 'wpc-composite-products' ); ?></th>
                        <td>
                            <label for="wooco_shipping_fee"></label><select id="wooco_shipping_fee"
                                                                            name="wooco_shipping_fee">
                                <option value="both" <?php selected( $shipping_fee, 'both' ); ?>>
                                    <?php esc_html_e( 'Apply to both composite & component', 'wpc-composite-products' ); ?>
                                </option>
                                <option value="whole" <?php selected( $shipping_fee, 'whole' ); ?>>
                                    <?php esc_html_e( 'Apply to the main composite product', 'wpc-composite-products' ); ?>
                                </option>
                                <option value="each" <?php selected( $shipping_fee, 'each' ); ?>>
                                    <?php esc_html_e( 'Apply to each component product', 'wpc-composite-products' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Custom display price', 'wpc-composite-products' ); ?></th>
                        <td>
                            <label>
                                <input type="text" name="wooco_custom_price" id="wooco_custom_price"
                                       value="<?php echo esc_attr( get_post_meta( $product_id, 'wooco_custom_price', true ) ); ?>"/>
                            </label> E.g: <code>From $10 to $100</code>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Above text', 'wpc-composite-products' ); ?></th>
                        <td>
                            <div class="w100">
                                <label>
                                    <textarea
                                            name="wooco_before_text"><?php echo esc_textarea( get_post_meta( $product_id, 'wooco_before_text', true ) ); ?></textarea>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr class="wooco_tr_space">
                        <th><?php esc_html_e( 'Under text', 'wpc-composite-products' ); ?></th>
                        <td>
                            <div class="w100">
                                <label>
                                    <textarea
                                            name="wooco_after_text"><?php echo esc_textarea( get_post_meta( $product_id, 'wooco_after_text', true ) ); ?></textarea>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <?php do_action( 'wooco_product_settings', $product_id ); ?>
                </table>
            </div>
            <?php
        }

        public function process_meta_composite( $post_id ) {
            if ( isset( $_POST['wooco_components'] ) ) {
                update_post_meta( $post_id, 'wooco_components', WPCleverWooco_Helper::sanitize_array( $_POST['wooco_components'] ) );
            }

            if ( isset( $_POST['wooco_pricing'] ) ) {
                update_post_meta( $post_id, 'wooco_pricing', sanitize_text_field( $_POST['wooco_pricing'] ) );
            }

            if ( isset( $_POST['wooco_discount_percent'] ) ) {
                update_post_meta( $post_id, 'wooco_discount_percent', sanitize_text_field( $_POST['wooco_discount_percent'] ) );
            }

            if ( isset( $_POST['wooco_qty_min'] ) ) {
                update_post_meta( $post_id, 'wooco_qty_min', sanitize_text_field( $_POST['wooco_qty_min'] ) );
            }

            if ( isset( $_POST['wooco_qty_max'] ) ) {
                update_post_meta( $post_id, 'wooco_qty_max', sanitize_text_field( $_POST['wooco_qty_max'] ) );
            }

            if ( isset( $_POST['wooco_total_limits'] ) ) {
                update_post_meta( $post_id, 'wooco_total_limits', 'on' );
            } else {
                update_post_meta( $post_id, 'wooco_total_limits', 'off' );
            }

            if ( isset( $_POST['wooco_total_limits_min'] ) ) {
                update_post_meta( $post_id, 'wooco_total_limits_min', sanitize_text_field( $_POST['wooco_total_limits_min'] ) );
            }

            if ( isset( $_POST['wooco_total_limits_max'] ) ) {
                update_post_meta( $post_id, 'wooco_total_limits_max', sanitize_text_field( $_POST['wooco_total_limits_max'] ) );
            }

            if ( isset( $_POST['wooco_same_products'] ) ) {
                update_post_meta( $post_id, 'wooco_same_products', sanitize_text_field( $_POST['wooco_same_products'] ) );
            }

            if ( isset( $_POST['wooco_shipping_fee'] ) ) {
                update_post_meta( $post_id, 'wooco_shipping_fee', sanitize_text_field( $_POST['wooco_shipping_fee'] ) );
            }

            if ( isset( $_POST['wooco_custom_price'] ) ) {
                update_post_meta( $post_id, 'wooco_custom_price', sanitize_post_field( 'post_content', $_POST['wooco_custom_price'], $post_id, 'display' ) );
            }

            if ( isset( $_POST['wooco_before_text'] ) ) {
                update_post_meta( $post_id, 'wooco_before_text', sanitize_post_field( 'post_content', $_POST['wooco_before_text'], $post_id, 'display' ) );
            }

            if ( isset( $_POST['wooco_after_text'] ) ) {
                update_post_meta( $post_id, 'wooco_after_text', sanitize_post_field( 'post_content', $_POST['wooco_after_text'], $post_id, 'display' ) );
            }

            // delete cache
            delete_transient( 'wooco_show_items_' . $post_id );
        }

        public function export_process( $value, $meta, $product ) {
            if ( $meta->key === 'wooco_components' ) {
                $components = get_post_meta( $product->get_id(), 'wooco_components', true );

                if ( ! empty( $components ) && is_array( $components ) ) {
                    return json_encode( $components );
                }
            }

            return $value;
        }

        public function import_process( $object, $data ) {
            if ( isset( $data['meta_data'] ) ) {
                foreach ( $data['meta_data'] as $meta ) {
                    if ( $meta['key'] === 'wooco_components' ) {
                        $object->update_meta_data( 'wooco_components', json_decode( $meta['value'], true ) );
                        break;
                    }
                }
            }

            return $object;
        }
    }

    function WPCleverWooco_Backend() {
        return WPCleverWooco_Backend::instance();
    }
}

