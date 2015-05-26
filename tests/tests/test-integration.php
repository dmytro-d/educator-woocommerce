<?php

class Integration extends WP_UnitTestCase {
	public function testCourseOrder() {
		$ewt = Educator_WooCommerce_Test::instance();
		$course = $ewt->addCourse();
		$product = $ewt->addProduct($course);

		$order = $ewt->addOrder( array(
			'customer_id' => 1,
			'product'     => $product,
		) );

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
		$product = $ewt->addProduct($membership);

		$order = $ewt->addOrder( array(
			'customer_id' => 1,
			'product'     => $product,
		) );

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
}
