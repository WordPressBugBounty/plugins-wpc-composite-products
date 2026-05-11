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
			$product_id = ! ( is_numeric( $id ) && (int) $id == $id ) || ( is_string( $id ) && ( str_starts_with( $id, '_sku_' ) ) )
				? wc_get_product_id_by_sku( str_replace( '_sku_', '', $id ) )
				: false;
			$product_id = $product_id ?: absint( $id );

			return (int) apply_filters( 'wooco_get_product_id', $product_id, $id );
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
				return ( (float) $new_price * $old_price ) / 100;
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
