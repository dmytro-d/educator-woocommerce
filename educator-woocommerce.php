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
	$args = array(
		'post_type'      => array( 'ib_educator_course', 'ib_edu_membership' ),
		'post_status'    => 'publish',
		'meta_query'     => array(),
		'posts_per_page' => -1,
	);

	if ( is_numeric( $product_id ) ) {
		$args['meta_query'][] = array(
			'key'     => '_edu_wc_product',
			'value'   => $product_id,
			'compare' => '=',
		);
	} elseif ( is_array( $product_id ) ) {
		$args['meta_query'][] = array(
			'key'     => '_edu_wc_product',
			'value'   => $product_id,
			'compare' => 'IN',
		);
	}

	$query = new WP_Query( $args );

	if ( $query->have_posts() ) {
		return $query->posts;
	}

	return array();
}

class Educator_WooCommerce {
	protected $version = '0.1';

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

		// Order has been created, but hasn't been paid.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'complete_order' ) );
		// Payment for an order has been completed.
		add_action( 'woocommerce_payment_complete', array( $this, 'complete_order' ) );
		// Order has been completed.
		add_action( 'woocommerce_order_status_completed', array( $this, 'complete_order' ) );
		// Order has been cancelled.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order' ) );
		// Order has been refunded.
		add_action( 'woocommerce_order_status_refunded', array( $this, 'cancel_order' ) );

		// Disable guest checkout if a visitor has a course or a membership in his/her cart.
		add_filter( 'pre_option_woocommerce_enable_guest_checkout', array( $this, 'can_guest_checkout' ) );

		if ( is_admin() ) {
			require_once 'admin/admin.php';
			Educator_WooCommerce_Admin::get_instance();
		}
	}

	/**
	 * Check if a visitor checkout without registration.
	 *
	 * @param string $option_value
	 * @return string
	 */
	public function can_guest_checkout( $option_value ) {
		global $woocommerce;

		// We do not need to block guest checkout while in admin panel.
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $option_value;
		}

		foreach ( $woocommerce->cart->get_cart() as $item_key => $values ) {
			$objects = edu_wc_get_objects_by_product( $values['data']->id );

			foreach ( $objects as $object ) {
				if ( in_array( $object->post_type, array( 'ib_educator_course' ) ) ) {
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

	public function get_price_widget_html( $product ) {
		$output = '<div class="ib-edu-price-widget">';
		$output .= $product->get_price_html();
		$output .= sprintf( '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" data-quantity="%s" class="button %s product_type_%s">%s</a>',
			esc_url( $product->add_to_cart_url() ),
			esc_attr( $product->id ),
			esc_attr( $product->get_sku() ),
			esc_attr( isset( $quantity ) ? $quantity : 1 ),
			$product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button' : '',
			esc_attr( $product->product_type ),
			esc_html( $product->add_to_cart_text() )
		);
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

		$product_id = get_post_meta( $course_id, '_edu_wc_product', true );

		if ( empty( $product_id ) ) {
			return $output;
		}

		$product = wc_get_product( $product_id );

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
		$product_id = get_post_meta( $membership_id, '_edu_wc_product', true );

		if ( empty( $product_id ) ) {
			return $output;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return $output;
		}

		return $this->get_price_widget_html( $product );
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
		$order_status = $order->get_status();
		$valid_statuses = array( 'completed', 'processing' );

		foreach ( $items as $item_id => $item ) {
			if ( in_array( $order_status, $valid_statuses ) || 0 == $item['line_total'] ) {
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
					// just update its status to inprogress.
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
				$ms = IB_Educator_Memberships::get_instance();
				$ms->setup_membership( $order->user_id, $object->ID );
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
		$user_membership = null;
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
					$user_membership = $ms->get_user_membership( $order->user_id );
				}

				if ( $user_membership && $user_membership['membership_id'] == $object->ID ) {
					$user_membership['status'] = 'expired';
					
					if ( ! empty( $user_membership['expiration'] ) && is_numeric( $user_membership['expiration'] ) ) {
						$user_membership['expiration'] = date( 'Y-m-d H:i:s', $user_membership['expiration'] );
					}

					$ms->update_user_membership( $user_membership );
					$ms->update_membership_entries( $order->user_id, 'paused' );
				}
			}
		}
	}
}

Educator_WooCommerce::get_instance();
