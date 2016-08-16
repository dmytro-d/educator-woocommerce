<?php

class Educator_WooCommerce_Admin_Old extends Educator_WooCommerce_Admin {
	public function set_price( $object_id, $price, $object_type ) {
		if ( $this->ew->get_pt_course() == $object_type ) {
			if ( $price != get_post_meta( $object_id, '_ibedu_price', true ) ) {
				update_post_meta( $object_id, '_ibedu_price', $price );
			}
		} else {
			$meta = Edr_Memberships::get_instance()->get_membership_meta( $object_id );

			if ( $price != $meta['price'] ) {
				$meta['price'] = $price;
				update_post_meta( $object_id, '_ib_educator_membership', $meta );
			}
		}
	}

	public function update_currency( $old_currency, $new_currency ) {
		$settings = get_option( 'ib_educator_settings', array() );
		$settings['currency'] = $new_currency;
		update_option( 'ib_educator_settings', $settings );
	}
}
