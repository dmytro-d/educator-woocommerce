<?php

class Integration extends WP_UnitTestCase {
	public function testCourseOrder() {
		$ewt = Educator_WooCommerce_Test::instance();
		$course = $ewt->addCourse();
		$product = $ewt->addProduct($course);

		$order = $ewt->addOrder( array(
			'customer_id' => 1,
		), array( $product ) );

		$order->update_status( 'processing' );

		$api = IB_Educator::get_instance();
		$entry = $api->get_entry( array( 'user_id' => $order->user_id, 'course_id' => $course->ID ) );

		$this->assertEquals( array(
			'course_id'    => $course->ID,
			'payment_id'   => $order->id,
			'entry_origin' => 'wc_order',
			'entry_status' => 'inprogress',
			'user_id'      => $order->user_id,
		), array(
			'course_id'    => $entry->course_id,
			'payment_id'   => $entry->payment_id,
			'entry_origin' => $entry->entry_origin,
			'entry_status' => $entry->entry_status,
			'user_id'      => $entry->user_id,
		) );
	}

	public function testMembershipOrder() {
		$ewt = Educator_WooCommerce_Test::instance();
		$membership = $ewt->addMembership( array(
			'price'      => 10,
			'period'     => 'months',
			'duration'   => 1,
			'categories' => array(),
		) );
		$product = $ewt->addProduct( $membership );

		$order = $ewt->addOrder( array(
			'customer_id' => 1,
		), array( $product ) );

		$order->update_status( 'completed' );

		$ms = Edr_Memberships::get_instance();

		$u_membership = $ms->get_user_membership( 1 );

		$this->assertEquals( array(
			'ID'            => $u_membership['ID'],
			'user_id'       => 1,
			'membership_id' => $membership->ID,
			'status'        => 'active',
			'expiration'    => $ms->calculate_expiration_date( 1, 'months' ),
			'paused'        => 0,
		), $u_membership );
	}

	public function testGetObjectsByProduct() {
		$ewt = Educator_WooCommerce_Test::instance();
		
		$course = $ewt->addCourse();
		$course_product = $ewt->addProduct( $course );

		$membership = $ewt->addMembership( array(
			'price'      => 10,
			'period'     => 'months',
			'duration'   => 1,
			'categories' => array(),
		) );
		$membership_product = $ewt->addProduct( $membership );

		$product_ids = array( $course_product->id, $membership_product->id );

		$objects = edu_wc_get_objects_by_product( $product_ids );

		$this->assertEquals( 2, count( $objects ) );

		$this->assertEquals(
			array( $objects[0]->ID, $objects[1]->ID ),
			array( $course->ID, $membership->ID )
		);
	}

	public function testCanGuestCheckout() {
		$ewt = Educator_WooCommerce_Test::instance();

		$course = $ewt->addCourse();
		$course_product = $ewt->addProduct( $course );

		$this->assertEquals( 'yes', get_option( 'woocommerce_enable_guest_checkout' ) );

		WC()->cart->add_to_cart( $course_product->id );

		$this->assertEquals( '', get_option( 'woocommerce_enable_guest_checkout' ) );
	}

	public function testCancelOrder() {
		$ewt = Educator_WooCommerce_Test::instance();
		$api = IB_Educator::get_instance();
		$ms = Edr_Memberships::get_instance();

		$course = $ewt->addCourse();
		$course_product = $ewt->addProduct( $course );

		$membership = $ewt->addMembership( array(
			'price'      => 10,
			'period'     => 'months',
			'duration'   => 1,
			'categories' => array(),
		) );
		$membership_product = $ewt->addProduct( $membership );

		$order = $ewt->addOrder( array( 'customer_id' => 1 ), array( $course_product, $membership_product ) );

		$order->update_status( 'completed' );

		// Check if entry and membership were created
		$entry = $api->get_entry( array( 'user_id'   => $order->user_id, 'course_id' => $course->ID ) );
		$u_membership = $ms->get_user_membership( $order->user_id );

		$this->assertEquals( array(
			'course_id'    => $course->ID,
			'payment_id'   => $order->id,
			'entry_origin' => 'wc_order',
			'entry_status' => 'inprogress',
			'user_id'      => $order->user_id,
		), array(
			'course_id'    => $entry->course_id,
			'payment_id'   => $entry->payment_id,
			'entry_origin' => $entry->entry_origin,
			'entry_status' => $entry->entry_status,
			'user_id'      => $entry->user_id,
		) );

		$this->assertEquals( array(
			'ID'            => $u_membership['ID'],
			'user_id'       => 1,
			'membership_id' => $membership->ID,
			'status'        => 'active',
			'expiration'    => $ms->calculate_expiration_date( 1, 'months' ),
			'paused'        => 0,
		), $u_membership );

		$expiration = $u_membership['expiration'];

		// Cancel order
		$order->update_status( 'cancelled' );

		// Check if entry and membership were cancelled
		$entry = $api->get_entry( array( 'user_id'   => $order->user_id, 'course_id' => $course->ID ) );
		$u_membership = $ms->get_user_membership( $order->user_id );

		$this->assertEquals( array(
			'entry_status' => 'cancelled',
		), array(
			'entry_status' => $entry->entry_status,
		) );

		$this->assertEquals( array(
			'status'     => 'expired',
			'expiration' => $ms->modify_expiration_date( 1, 'months', '-', $expiration ),
		), array(
			'status'     => $u_membership['status'],
			'expiration' => $u_membership['expiration'],
		) );
	}

	public function testOneMembershioPerCart() {
		$ewt = Educator_WooCommerce_Test::instance();

		// Membership 1
		$membership1 = $ewt->addMembership( array(
			'price'      => 10,
			'period'     => 'months',
			'duration'   => 1,
			'categories' => array(),
		) );
		$product1 = $ewt->addProduct( $membership1 );

		// Membership 2
		$membership2 = $ewt->addMembership( array(
			'price'      => 25,
			'period'     => 'years',
			'duration'   => 1,
			'categories' => array(),
		) );
		$product2 = $ewt->addProduct( $membership2 );

		WC()->cart->empty_cart();

		WC()->cart->add_to_cart( $product1->id );

		$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product2->id, 1 );

		if ( $passed_validation ) {
			WC()->cart->add_to_cart( $product2->id );
		}

		$cart_items = WC()->cart->get_cart();

		$this->assertEquals( 1, count( $cart_items ) );

		$this->assertEquals( $product2->id, array_pop( $cart_items )['data']->id );
	}

	public function testUpdateWooCommerceCurrency() {
		$bootstrap = Educator_WooCommerce_Bootstrap::instance();

		require_once $bootstrap->get_edu_wc_path() . '/admin/admin.php';
		
		$admin = Educator_WooCommerce_Admin::get_instance();

		add_action( 'update_option_woocommerce_currency', array( $admin, 'update_currency' ), 20, 2 );

		$this->assertEquals( 'GBP', get_option( 'woocommerce_currency' ) );
		$this->assertEquals( 'USD', ib_edu_get_currency() );

		update_option( 'woocommerce_currency', 'CHF' );

		$this->assertEquals( 'CHF', ib_edu_get_currency() );
	}
}
