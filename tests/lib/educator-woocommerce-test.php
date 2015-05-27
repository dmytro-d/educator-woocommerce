<?php

class Educator_WooCommerce_Test {
	public static $instance = null;
	private $db;

	private function __construct() {
		global $wpdb;

		$this->db = $wpdb;
	}

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function addCourse() {
		$course_id = wp_insert_post( array(
			'post_title'  => 'A Course',
			'post_type'   => 'ib_educator_course',
			'post_status' => 'publish',
		) );
		
		return get_post( $course_id );	
	}

	public function addMembership( $data ) {
		$membership_id = wp_insert_post( array(
			'post_title'  => 'Dummy Membership',
			'post_type'   => 'ib_edu_membership',
			'post_status' => 'publish',
		) );

		$ms = IB_Educator_Memberships::get_instance();
		
		$meta = $ms->get_membership_meta();

		$meta['price']      = $data['price'];      // float
		$meta['period']     = $data['period'];     // days, months, years
		$meta['duration']   = $data['duration'];   // integer
		$meta['categories'] = $data['categories']; // array

		update_post_meta( $membership_id, '_ib_educator_membership', $meta );
		
		return get_post( $membership_id );
	}

	public function addProduct( $post ) {
		$product_id = wp_insert_post( array(
			'post_title'  => sprintf( '%s Product', $post->post_title ),
			'post_type'   => 'product',
			'post_status' => 'publish',
		) );

		$product_meta = array(
			'_price'             => 10,
			'_regular_price'     => 10,
			'_sku'               => 'SAMPLE SKU',
			'_virtual'           => 'yes',
			'_stock'             => '',
			'_stock_status'      => 'instock',
			'_sold_individually' => 'yes',
			'_manage_stock'      => 'no',
			'_back_orders'       => 'no',
			'_downloadable'      => 'no',
		);

		foreach ( $product_meta as $key => $value ) {
			update_post_meta( $product_id, $key, $value );
		}

		update_post_meta( $post->ID, '_edu_wc_product', $product_id );

		return new WC_Product_Simple( $product_id );
	}

	public function addOrder( $data, array $products ) {
		$address = array(
			'first_name' => 'John',
			'last_name'  => 'Smith',
			'company'    => 'Test Company',
			'email'      => 'john@educatorplugin.com',
			'phone'      => '123-45-67',
			'address_1'  => 'Test Street #1',
			'address_2'  => '',
			'city'       => 'Test City',
			'state'      => 'Test State',
			'postcode'   => '12345',
			'country'    => 'US',
		);

		$order = wc_create_order( array(
			'customer_id' => $data['customer_id'],
			'status'      => apply_filters( 'woocommerce_default_order_status', 'pending' ),
		) );

		foreach ( $products as $product ) {
			$order->add_product( $product, 1 );
		}

		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );
		$order->calculate_totals();

		return $order;
	}
}