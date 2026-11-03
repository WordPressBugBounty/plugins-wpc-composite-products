<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWooco_Helper' ) ) {
	class WPCleverWooco_Helper {
		protected static array $settings = [];
		protected static array $localization = [];

		public static function init(): void {
			self::$settings     = (array) get_option( 'wooco_settings', [] );
			self::$localization = (array) get_option( 'wooco_localization', [] );
		}

		public static function get_settings(): array {
			return apply_filters( 'wooco_get_settings', self::$settings );
		}

		public static function get_setting( string $name, $default = false ) {
			$value = $default;

			if ( isset( self::$settings[ $name ] ) && ( self::$settings[ $name ] !== '' ) ) {
				$value = self::$settings[ $name ];
			}

			return apply_filters( 'wooco_get_setting', $value, $name, $default );
		}

		public static function localization( string $key = '', string $default = '' ): string {
			$str = '';

			if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
				$str = self::$localization[ $key ];
			} elseif ( ! empty( $default ) ) {
				$str = $default;
			}

			return apply_filters( 'wooco_localization_' . $key, $str );
		}

		public static function sanitize_array( array $arr ): array {
			foreach ( $arr as $k => $v ) {
				if ( is_array( $v ) ) {
					$arr[ $k ] = self::sanitize_array( $v );
				} else {
					$arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
				}
			}

			return $arr;
		}

		public static function get_product_id( $id = null ): int {
			$is_sku_key = is_string( $id ) && str_starts_with( $id, '_sku_' );

			if ( $is_sku_key || ! ( is_numeric( $id ) && (int) $id == $id ) ) {
				// prefixed or non-numeric value: it is always a SKU
				$product_id = wc_get_product_id_by_sku( str_replace( '_sku_', '', (string) $id ) );
			} else {
				// raw numeric value: either a product ID or a legacy numeric SKU stored without the prefix
				$product_id = self::resolve_numeric_id( $id );
			}

			$product_id = $product_id ?: absint( $id );

			return (int) apply_filters( 'wooco_get_product_id', $product_id, $id );
		}

		/**
		 * Resolve a raw numeric value (no '_sku_' prefix) to a product ID.
		 *
		 * Since a raw ID is only stored for products without a SKU (see get_product_sku_or_id),
		 * the value is treated as an ID only when that product exists and has no SKU.
		 * Otherwise, it is treated as a numeric SKU (e.g. legacy data saved before the prefix existed).
		 *
		 * @param int|string $id
		 *
		 * @return int
		 */
		protected static function resolve_numeric_id( $id ): int {
			$raw_id  = absint( $id );
			$product = $raw_id ? wc_get_product( $raw_id ) : false;

			if ( $product && ! $product->get_sku( 'edit' ) ) {
				return $raw_id;
			}

			$sku_id = (int) wc_get_product_id_by_sku( (string) $id );

			return $sku_id ?: $raw_id;
		}

		/**
		 * Normalize product references of components to the canonical format
		 * ('_sku_{sku}' for products with SKU, product ID otherwise) before saving.
		 *
		 * @param array $components
		 *
		 * @return array
		 */
		public static function normalize_components( array $components ): array {
			foreach ( $components as $key => $component ) {
				if ( ! is_array( $component ) ) {
					continue;
				}

				foreach ( [ 'products', 'exclude' ] as $field ) {
					if ( ! empty( $component[ $field ] ) && is_array( $component[ $field ] ) ) {
						$components[ $key ][ $field ] = array_map( [ __CLASS__, 'normalize_product_ref' ], $component[ $field ] );
					}
				}

				if ( isset( $component['default'] ) && is_scalar( $component['default'] ) && $component['default'] !== '' ) {
					$components[ $key ]['default'] = self::normalize_product_ref( $component['default'] );
				}
			}

			return $components;
		}

		public static function normalize_product_ref( $ref ) {
			if ( ! is_scalar( $ref ) || $ref === '' ) {
				return $ref;
			}

			$product_id = self::get_product_id( $ref );
			$product    = $product_id ? wc_get_product( $product_id ) : false;

			// keep the original value if the product cannot be found, to avoid data loss
			return $product ? self::get_product_sku_or_id( $product ) : $ref;
		}

		/**
		 * Build a selected <option> for a stored product reference.
		 * Unresolved references (changed SKU, trashed product, out-of-sync lookup table...) are still
		 * rendered, so they are posted back and not silently dropped on the next save.
		 *
		 * @param mixed $ref
		 *
		 * @return string
		 */
		public static function product_option_html( $ref ): string {
			if ( ! is_scalar( $ref ) || $ref === '' ) {
				return '';
			}

			$product_id = self::get_product_id( $ref );
			$product    = $product_id ? wc_get_product( $product_id ) : false;

			if ( $product ) {
				return '<option value="' . esc_attr( self::get_product_sku_or_id( $product ) ) . '" selected="selected">' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
			}

			$ref   = (string) $ref;
			$label = str_starts_with( $ref, '_sku_' ) ? 'SKU: ' . substr( $ref, 5 ) : '#' . $ref;

			/* translators: %s: SKU or ID of the missing product */
			return '<option value="' . esc_attr( $ref ) . '" selected="selected">' . esc_html( sprintf( __( '%s (product not found)', 'wpc-composite-products' ), $label ) ) . '</option>';
		}

		/**
		 * Parse components from an import/export value (array, JSON or serialized string).
		 *
		 * @param mixed $value
		 *
		 * @return array Empty array if the value cannot be parsed.
		 */
		public static function parse_components( $value ): array {
			if ( is_array( $value ) ) {
				return $value;
			}

			if ( ! is_string( $value ) || trim( $value ) === '' ) {
				return [];
			}

			$data = json_decode( $value, true );

			if ( ! is_array( $data ) && is_serialized( $value ) ) {
				$data = unserialize( trim( $value ), [ 'allowed_classes' => false ] );
			}

			return is_array( $data ) ? $data : [];
		}

		public static function get_product_sku_or_id( WC_Product $product ) {
			return apply_filters(
				'wooco_get_product_sku_or_id',
				$product->get_sku( 'edit' ) ? '_sku_' . $product->get_sku( 'edit' ) : $product->get_id(),
				$product
			);
		}

		public static function enable_cache( string $context = 'default' ): bool {
			return (bool) apply_filters( 'wooco_enable_cache', false, $context );
		}

		public static function get_items( string $ids ): array {
			$arr = [];

			if ( ! empty( $ids ) ) {
				$items = explode( ',', $ids );

				foreach ( $items as $item ) {
					$item_arr = explode( '/', $item );
					$arr[]    = [
						'id'  => absint( $item_arr[0] ?? 0 ),
						'qty' => (float) ( $item_arr[1] ?? 1 ),
						'key' => sanitize_key( $item_arr[2] ?? '' ),
					];
				}
			}

			return apply_filters( 'wooco_get_items', $arr, $ids );
		}

		public static function format_price( string $price ): string {
			// Keep 'd' for different price from default product
			return preg_replace( '/[^d\d.%]/', '', $price );
		}

		public static function get_new_price( float $old_price, string $new_price ): float {
			if ( str_contains( $new_price, '%' ) ) {
				$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

				return (float) round( ( (float) $new_price * $old_price ) / 100, $decimals );
			}

			return (float) $new_price;
		}

		public static function get_discount( $number ): float {
			if ( is_numeric( $number ) && ( (float) $number < 100 ) && ( (float) $number > 0 ) ) {
				return (float) $number;
			}

			return 0.0;
		}

		public static function generate_key(): string {
			$key         = '';
			$key_str     = apply_filters( 'wooco_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
			$key_str_len = strlen( $key_str );

			for ( $i = 0; $i < apply_filters( 'wooco_key_length', 4 ); $i ++ ) {
				$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
			}

			if ( is_numeric( $key ) ) {
				$key = self::generate_key();
			}

			return apply_filters( 'wooco_generate_key', $key );
		}

		public static function clean_ids( string $ids ): string {
			return preg_replace( '/[^,.%\/0-9a-zA-Z]/', '', $ids );
		}

		public static function data_attributes( array $attrs ): string {
			$attrs_arr = [];

			foreach ( $attrs as $key => $attr ) {
				$attrs_arr[] = esc_attr( 'data-' . sanitize_title( $key ) ) . '="' . esc_attr( $attr ) . '"';
			}

			return implode( ' ', $attrs_arr );
		}

		public static function get_term_slug( $id, string $taxonomy ): string {
			$term = get_term( $id, $taxonomy );

			if ( ! is_wp_error( $term ) && ! empty( $term->slug ) ) {
				return $term->slug;
			}

			return (string) $id;
		}

		public static function is_composite( WC_Product $product ): bool {
			return $product->is_type( 'composite' );
		}
	}
}
