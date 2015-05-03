<?php
/**
 * Plugin Name: Educator WooCommerce Integration.
 * Description: Integrate WooCommerce with Educator.
 * Version: 0.1
 * Author: dmytro.d
 * Author URI: http://educatorplugin.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get objects given product id.
 *
 * @param int $product_id
 * @return array
 */
function edu_wc_get_objects_by_product( $product_id ) {
	return get_posts( array(
		'post_type'      => array( 'ib_educator_course', 'ib_edu_membership' ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => array(
			array(
				'key'     => '_edu_wc_product',
				'value'   => $product_id,
				'compare' => is_numeric( $product_id ) ? '=' : 'IN',
			)
		),
	) );
}

class Educator_WooCommerce {
	protected $version = '0.1';

	protected $cache = array();

	protected static $instance;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Alter entry origins.
		add_filter( 'ib_educator_entry_origins', array( $this, 'entry_origins' ) );

		// Alter price/register widgets.
		add_filter( 'ib_educator_course_price_widget', array( $this, 'course_price_widget' ), 10, 4 );
		add_filter( 'ib_educator_membership_price_widget', array( $this, 'membership_price_widget' ), 10, 4 );
		add_filter( 'ib_edu_pre_purchase_link', array( $this, 'alter_purchase_link' ), 10, 2 );

		// Process free items (immediately), before payment.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_free_items_before_payment' ) );

		// When a payment is complete and only courses and/or memberships were ordered,
		// change order's status from "processing" to "completed".
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'order_needs_processing' ), 10, 2 );

		// Process ordered items when order's status changes to "completed".
		add_action( 'woocommerce_order_status_completed', array( $this, 'complete_order' ) );
		
		// Cancel ordered items when order's status changes to "cancelled" or "refunded".
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'cancel_order' ) );

		// Disable guest checkout if a visitor has a course or a membership in his/her cart.
		add_filter( 'pre_option_woocommerce_enable_guest_checkout', array( $this, 'can_guest_checkout' ) );

		// Prevent users from adding more than 1 membership to cart,
		// and replace old membership by the new one.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'one_membership_in_cart' ), 10, 2 );

		if ( is_admin() ) {
			require_once 'admin/admin.php';
			Educator_WooCommerce_Admin::get_instance();
		}
	}

	public function get_object_product( $object_id ) {
		$product = null;
		$product_id = get_post_meta( $object_id, '_edu_wc_product', true );

		if ( ! empty( $product_id ) ) {
			$product = wc_get_product( $product_id );
		}

		return $product;
	}

	/**
	 * Check if a visitor checkout without registration.
	 *
	 * @param string $option_value
	 * @return string
	 */
	public function can_guest_checkout( $option_value ) {
		// We do not need to block guest checkout while in admin panel.
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $option_value;
		}

		foreach ( WC()->cart->get_cart() as $item_key => $values ) {
			$objects = edu_wc_get_objects_by_product( $values['data']->id );

			foreach ( $objects as $object ) {
				if ( in_array( $object->post_type, array( 'ib_educator_course', 'ib_edu_membership' ) ) ) {
					$option_value = '';
					break;
				}
			}
		}

		return $option_value;
	}

	/**
	 * Add an entry origin to track entries that
	 * originated from WooCommerce checkout.
	 *
	 * @param array $origins
	 * @return array
	 */
	public function entry_origins( $origins ) {
		$origins['wc_order'] = __( 'WooCommerce Order', 'educator-wc' );
		return $origins;
	}

	protected function get_add_to_cart_button( $product ) {
		return sprintf( '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" data-quantity="%s" class="button %s product_type_%s">%s</a>',
			esc_url( $product->add_to_cart_url() ),
			esc_attr( $product->id ),
			esc_attr( $product->get_sku() ),
			esc_attr( isset( $quantity ) ? $quantity : 1 ),
			$product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button' : '',
			esc_attr( $product->product_type ),
			esc_html( $product->add_to_cart_text() )
		);
	}

	protected function get_price_widget_html( $product ) {
		$output = '<div class="ib-edu-price-widget">';
		$output .= $product->get_price_html();
		$output .= $this->get_add_to_cart_button( $product );
		$output .= '</div>';
		return $output;
	}

	/**
	 * Alter Educator's course price widget.
	 *
	 * @param string $output
	 * @param bool $membership_access
	 * @param int $course_id
	 * @param int $user_id
	 */
	public function course_price_widget( $output, $membership_access, $course_id, $user_id ) {
		if ( $membership_access ) {
			return $output;
		}

		$product = $this->get_object_product( $course_id );

		if ( ! $product ) {
			return $output;
		}

		return $this->get_price_widget_html( $product );
	}

	/**
	 * Alter Educator's membership price widget.
	 *
	 * @param string $output
	 * @param bool $membership_id
	 */
	public function membership_price_widget( $output, $membership_id ) {
		$product = $this->get_object_product( $membership_id );

		if ( ! $product ) {
			return $output;
		}

		return $this->get_price_widget_html( $product );
	}

	public function alter_purchase_link( $html, $atts ) {
		$product = $this->get_object_product( $atts['object_id'] );

		if ( $product ) {
			$html = $this->get_add_to_cart_button( $product );
		}

		return $html;
	}

	public function get_user_entries( $order ) {
		$active_entries = array();
		$order_entries = array();
		$api = IB_Educator::get_instance();
		$tmp = $api->get_entries( array( 'user_id' => $order->user_id ) );

		if ( ! empty( $tmp ) ) {
			foreach ( $tmp as $row ) {
				if ( 'inprogress' == $row->entry_status ) {
					$active_entries[ $row->course_id ] = $row;
				} elseif ( 'wc_order' == $row->entry_origin && $order->id == $row->payment_id ) {
					$order_entries[ $row->course_id ] = $row;
				}
			}

			unset( $tmp );
		}

		return array(
			'active' => $active_entries,
			'order'  => $order_entries,
		);
	}

	public function order_needs_processing( $new_status, $id ) {
		if ( 'processing' != $new_status ) {
			return $new_status;
		}

		$order = new WC_Order( $id );

		if ( ! isset( $order ) ) {
			return $new_status;
		}

		$product_ids = array();
		
		foreach ( $order->get_items() as $item ) {
			if ( $item['product_id'] > 0 ) {
				$product = $order->get_product_from_item( $item );

				if ( ! $product->is_virtual() ) {
					return $new_status;
				}

				$product_ids[] = $item['product_id'];
			}
		}

		if ( empty( $product_ids ) ) {
			return $new_status;
		}

		$objects = edu_wc_get_objects_by_product( $product_ids );

		if ( ! empty( $objects ) && count( $objects ) == count( $product_ids ) ) {
			// We proved that ordered products are virtual.
			return 'completed';
		}

		return $new_status;
	}

	/**
	 * Process free items before payment.
	 *
	 * @param int $order_id
	 */
	public function process_free_items_before_payment( $order_id ) {
		if ( WC()->cart->needs_payment() ) {
			// Process only those items that are free,
			// paid items will be processed after payment.
			$this->complete_order( $order_id );
		}
	}

	/**
	 * Complete WooCommerce order.
	 * Create entries and/or memberships for purchased products.
	 *
	 * @param int $order_id
	 */
	public function complete_order( $order_id ) {
		$order = new WC_Order( $order_id );

		if ( ! isset( $order ) ) {
			return;
		}

		$items = $order->get_items();

		if ( empty( $items ) ) {
			return;
		}

		$product_ids = array();
		$is_order_ready = in_array( $order->get_status(), array( 'completed', 'processing' ) );

		foreach ( $items as $item_id => $item ) {
			if ( $is_order_ready || 0 == $item['line_total'] ) {
				// Process item only if it's free or order is complete.
				$product_ids[] = $item['product_id'];
			}
		}

		if ( empty( $product_ids ) ) {
			return;
		}

		// Get posts associated with ordered products.
		$objects = edu_wc_get_objects_by_product( $product_ids );

		if ( empty( $objects ) ) {
			return;
		}

		$entries = null;

		foreach ( $objects as $object ) {
			if ( 'ib_educator_course' == $object->post_type ) {
				if ( is_null( $entries ) ) {
					$entries = $this->get_user_entries( $order );
				}

				if ( array_key_exists( $object->ID, $entries['active'] ) ) {
					// User has an "inprogress" entry for this course.
					continue;
				}

				if ( array_key_exists( $object->ID, $entries['order'] ) ) {
					// Entry associated with current item exists,
					// just update its status to "inprogress".
					$entry = $entries['order'][ $object->ID ];
					$entry->entry_status = 'inprogress';
				} else {
					// Create a new entry for this item.
					$entry = IB_Educator_Entry::get_instance();
					$entry->course_id = $object->ID;
					$entry->user_id = $order->user_id;
					$entry->entry_origin = 'wc_order';
					$entry->payment_id = $order->id;
					$entry->entry_status = 'inprogress';
					$entry->entry_date = date( 'Y-m-d H:i:s' );
				}

				$entry->save();
			} elseif ( 'ib_edu_membership' == $object->post_type ) {
				IB_Educator_Memberships::get_instance()->setup_membership(
					$order->user_id,
					$object->ID,
					array(
						'origin_type' => 'wc_order',
						'origin_id'   => $order->id,
					)
				);
			}
		}
	}

	/**
	 * Cancel entries memberships from a given WooCommerce order.
	 *
	 * @param int $order_id
	 */
	public function cancel_order( $order_id ) {
		$order = new WC_Order( $order_id );

		if ( ! isset( $order ) ) {
			return;
		}

		$items = $order->get_items();

		if ( empty( $items ) ) {
			return;
		}

		$product_ids = array();

		foreach ( $items as $item_id => $item ) {
			$product_ids[] = $item['product_id'];
		}

		// Get posts associated with ordered products.
		$objects = edu_wc_get_objects_by_product( $product_ids );

		if ( empty( $objects ) ) {
			return;
		}

		$entries = null;
		$u_membership = null;
		$ms = null;

		foreach ( $objects as $object ) {
			if ( 'ib_educator_course' == $object->post_type ) {
				// Cancel only those entries which originated from the current order.
				if ( is_null( $entries ) ) {
					$entries = array();
					$tmp = IB_Educator::get_instance()->get_entries( array(
						'payment_id' => $order->id,
						'user_id'    => $order->user_id,
					) );

					if ( ! empty( $tmp ) ) {
						foreach ( $tmp as $row ) {
							$entries[ $row->course_id ] = $row;
						}

						unset( $tmp );
					}
				}

				if ( array_key_exists( $object->ID, $entries ) ) {
					$entries[ $object->ID ]->entry_status = 'cancelled';
					$entries[ $object->ID ]->save();
				}
			} elseif ( 'ib_edu_membership' == $object->post_type ) {
				if ( is_null( $ms ) ) {
					$ms = IB_Educator_Memberships::get_instance();
					$u_membership = $ms->get_user_membership( $order->user_id );
				}
				
				if ( $u_membership && 'wc_order' == $u_membership['origin_type'] && $order->id == $u_membership['origin_id'] ) {
					$u_membership['status'] = 'expired';
					
					if ( ! empty( $u_membership['expiration'] ) && is_numeric( $u_membership['expiration'] ) ) {
						$u_membership['expiration'] = date( 'Y-m-d H:i:s', $u_membership['expiration'] );
					}

					$ms->update_user_membership( $u_membership );

					// Pause course entries which originated from this membership.
					$ms->update_membership_entries( $u_membership['user_id'], 'paused' );
				}
			}
		}
	}

	public function one_membership_in_cart( $valid, $product_id ) {
		$product_ids = array();
		$product_ids[] = $product_id;

		foreach ( WC()->cart->get_cart() as $item_key => $values ) {
			$product_ids[ $item_key ] = $values['data']->id;
		}

		$objects = edu_wc_get_objects_by_product( $product_ids );

		if ( ! empty( $objects ) ) {
			$cart_object_id = 0;
			$new_object_id = 0;
			$item_key = null;

			foreach ( $objects as $object ) {
				if ( 'ib_edu_membership' == $object->post_type ) {
					$obj_product_id = get_post_meta( $object->ID, '_edu_wc_product', true );

					if ( $product_id == $obj_product_id ) {
						$new_object_id = $object->ID;
					} else {
						$cart_object_id = $object->ID;
						$item_key = array_search( $obj_product_id, $product_ids );
					}
				}
			}

			if ( $new_object_id > 0 && $cart_object_id > 0 ) {
				$this->cache['one_in_cart'] = $new_object_id;

				if ( $item_key ) {
					WC()->cart->set_quantity( $item_key, 0 );
				}
			}
		}

		return $valid;
	}
}

Educator_WooCommerce::get_instance();
