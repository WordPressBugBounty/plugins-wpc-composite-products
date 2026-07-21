<?php
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class WPCleverWooco_Blocks_IntegrationInterface implements IntegrationInterface {
	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'wooco-blocks';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		wp_enqueue_style(
			'wooco-blocks',
			$this->get_url( 'blocks', 'css' ),
			[],
			WOOCO_VERSION
		);

		wp_register_script(
			'wooco-blocks',
			$this->get_url( 'blocks', 'js' ),
			[ 'wc-blocks-checkout' ],
			WOOCO_VERSION,
			true
		);

		wp_set_script_translations(
			'wooco-blocks',
			'wpc-composite-products',
			WOOCO_DIR . 'languages'
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return [ 'wooco-blocks' ];
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return [];
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return [];
	}

	public function get_url( $file, $ext ) {
		return plugins_url( $this->get_path( $ext ) . $file . '.' . $ext, WOOCO_FILE );
	}

	protected function get_path( $ext ) {
		return 'css' === $ext ? 'assets/css/' : 'assets/js/';
	}
}

if ( ! class_exists( 'WPCleverWooco_Blocks' ) ) {
	class WPCleverWooco_Blocks {
		function __construct() {
			add_filter( 'rest_request_after_callbacks', [ $this, 'cart_item_data' ], 10, 3 );
			add_filter( 'woocommerce_hydration_request_after_callbacks', [ $this, 'cart_item_data' ], 10, 3 );
			add_action(
				'woocommerce_blocks_mini-cart_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new WPCleverWooco_Blocks_IntegrationInterface() );
				}
			);
			add_action(
				'woocommerce_blocks_cart_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new WPCleverWooco_Blocks_IntegrationInterface() );
				}
			);
			add_action(
				'woocommerce_blocks_checkout_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new WPCleverWooco_Blocks_IntegrationInterface() );
				}
			);
		}

		function cart_item_data( $response, $server, $request ) {
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$route = $request->get_route();

			if ( ! str_contains( $route, 'wc/store' ) ) {
				return $response;
			}

			$data = $response->get_data();
			$items = null;
			$has_cart = '';

			// Safely search for items in Cart/Checkout REST API response (handles both array & object formats)
			if ( is_array( $data ) ) {
				if ( ! empty( $data['items'] ) ) {
					$items = &$data['items'];
				} elseif ( ! empty( $data['cart'] ) ) {
					$cart = &$data['cart'];
					if ( is_array( $cart ) && ! empty( $cart['items'] ) ) {
						$items = &$cart['items'];
						$has_cart = 'cart_arr';
					} elseif ( is_object( $cart ) && ! empty( $cart->items ) ) {
						$items = &$cart->items;
						$has_cart = 'cart_obj';
					}
				} elseif ( ! empty( $data['__experimentalCart'] ) ) {
					$ex_cart = &$data['__experimentalCart'];
					if ( is_array( $ex_cart ) && ! empty( $ex_cart['items'] ) ) {
						$items = &$ex_cart['items'];
						$has_cart = 'experimental_arr';
					} elseif ( is_object( $ex_cart ) && ! empty( $ex_cart->items ) ) {
						$items = &$ex_cart->items;
						$has_cart = 'experimental_obj';
					}
				}
			} elseif ( is_object( $data ) ) {
				if ( ! empty( $data->items ) ) {
					$items = &$data->items;
				} elseif ( ! empty( $data->cart ) ) {
					$cart = &$data->cart;
					if ( is_array( $cart ) && ! empty( $cart['items'] ) ) {
						$items = &$cart['items'];
						$has_cart = 'cart_arr';
					} elseif ( is_object( $cart ) && ! empty( $cart->items ) ) {
						$items = &$cart->items;
						$has_cart = 'cart_obj';
					}
				} elseif ( ! empty( $data->__experimentalCart ) ) {
					$ex_cart = &$data->__experimentalCart;
					if ( is_array( $ex_cart ) && ! empty( $ex_cart['items'] ) ) {
						$items = &$ex_cart['items'];
						$has_cart = 'experimental_arr';
					} elseif ( is_object( $ex_cart ) && ! empty( $ex_cart->items ) ) {
						$items = &$ex_cart->items;
						$has_cart = 'experimental_obj';
					}
				}
			}

			if ( empty( $items ) ) {
				return $response;
			}

			if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
				return $response;
			}

			$cart_contents       = WC()->cart->get_cart();
			$hide_composite_name = WPCleverWooco_Helper::get_setting( 'hide_composite_name', 'no' ) !== 'no';
			$hide_component      = WPCleverWooco_Helper::get_setting( 'hide_component', 'no' ) !== 'no';

			foreach ( $items as &$item_data ) {
				// Handle both array and object structures of items
				if ( is_array( $item_data ) ) {
					$cart_item_key = $item_data['key'];
					$cart_item     = $cart_contents[ $cart_item_key ] ?? null;

					if ( ! empty( $cart_item['wooco_ids'] ) ) {
						$item_data['wooco_composite'] = true;
					}

					if ( ! empty( $cart_item['wooco_parent_id'] ) ) {
						$item_data['wooco_component']           = true;
						$item_data['quantity_limits']->editable = false;

						if ( ! $hide_composite_name ) {
							$item_data['name'] = get_the_title( $cart_item['wooco_parent_id'] ) . apply_filters( 'wooco_name_separator', ' &rarr; ' ) . $item_data['name'];
						}

						if ( $hide_component ) {
							$item_data['wooco_hide_component'] = true;
						}
					}

					if ( ! empty( $cart_item['wooco_price'] ) ) {
						$item_data['wooco_price'] = $cart_item['wooco_price'];
					}
				} elseif ( is_object( $item_data ) ) {
					$cart_item_key = $item_data->key;
					$cart_item     = $cart_contents[ $cart_item_key ] ?? null;

					if ( ! empty( $cart_item['wooco_ids'] ) ) {
						$item_data->wooco_composite = true;
					}

					if ( ! empty( $cart_item['wooco_parent_id'] ) ) {
						$item_data->wooco_component           = true;
						$item_data->quantity_limits->editable = false;

						if ( ! $hide_composite_name ) {
							$item_data->name = get_the_title( $cart_item['wooco_parent_id'] ) . apply_filters( 'wooco_name_separator', ' &rarr; ' ) . $item_data->name;
						}

						if ( $hide_component ) {
							$item_data->wooco_hide_component = true;
						}
					}

					if ( ! empty( $cart_item['wooco_price'] ) ) {
						$item_data->wooco_price = $cart_item['wooco_price'];
					}
				}
			}

			// Re-assign mutated items list to response data structure
			if ( is_array( $data ) ) {
				if ( $has_cart === 'cart_arr' ) {
					$data['cart']['items'] = $items;
				} elseif ( $has_cart === 'cart_obj' ) {
					$data['cart']->items = $items;
				} elseif ( $has_cart === 'experimental_arr' ) {
					$data['__experimentalCart']['items'] = $items;
				} elseif ( $has_cart === 'experimental_obj' ) {
					$data['__experimentalCart']->items = $items;
				} else {
					$data['items'] = $items;
				}
			} elseif ( is_object( $data ) ) {
				if ( $has_cart === 'cart_arr' ) {
					$data->cart['items'] = $items;
				} elseif ( $has_cart === 'cart_obj' ) {
					$data->cart->items = $items;
				} elseif ( $has_cart === 'experimental_arr' ) {
					$data->__experimentalCart['items'] = $items;
				} elseif ( $has_cart === 'experimental_obj' ) {
					$data->__experimentalCart->items = $items;
				} else {
					$data->items = $items;
				}
			}

			$response->set_data( $data );

			return $response;
		}
	}

	new WPCleverWooco_Blocks();
}