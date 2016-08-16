<?php

class Educator_WooCommerce_Old extends Educator_WooCommerce {
	public function __construct() {
		parent::__construct();

		$this->pt_course = 'ib_educator_course';
		$this->pt_membership = 'ib_edu_membership';

		add_filter( 'ib_educator_entry_origins', array( $this, 'entry_origins' ) );
		add_filter( 'ib_educator_course_price_widget', array( $this, 'course_price_widget' ), 10, 4 );
		add_filter( 'ib_educator_membership_price_widget', array( $this, 'membership_price_widget' ), 10, 4 );
		add_filter( 'ib_edu_pre_purchase_link', array( $this, 'alter_purchase_link' ), 10, 2 );
	}

	public function get_entries( $args ) {
		return IB_Educator::get_instance()->get_entries( $args );
	}

	protected function get_price_widget_html( $product ) {
		$output = '<div class="ib-edu-price-widget">';
		$output .= '<span class="price">' . $product->get_price_html() . '</span>';
		$output .= $this->get_add_to_cart_button( $product );
		$output .= '</div>';

		return $output;
	}

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
			$html = $this->get_add_to_cart_button( $product, array( 'class' => '' ) );
		}

		return $html;
	}
}
