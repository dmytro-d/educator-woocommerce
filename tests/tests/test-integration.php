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

		$ms = IB_Educator_Memberships::get_instance();

		$u_membership = $ms->get_user_membership( 1 );

		$this->assertEquals( array(
			'ID'             => $u_membership['ID'],
			'user_id'        => 1,
			'membership_id'  => $membership->ID,
			'status'         => 'active',
			'expiration'     => $ms->calculate_expiration_date( 1, 'months' ),
			'paused'         => 0,
			'origin_type'    => 'wc_order',
			'origin_id'      => $order->id,
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
		$ms = IB_Educator_Memberships::get_instance();

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
			'origin_type'   => 'wc_order',
			'origin_id'     => $order->id,
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
}
