<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Educator_WooCommerce_Admin {
	protected static $instance;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );
	}

	public function add_meta_box() {
		foreach ( array( 'ib_educator_course', 'ib_edu_membership' ) as $screen ) {
			add_meta_box(
				'edu_wc_products',
				__( 'Product', 'ibeducator' ),
				array( $this, 'products_meta_box' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	public function products_meta_box( $post ) {
		wp_nonce_field( 'edu_wc_products', 'edu_wc_products_nonce' );

		$cur_product_id = get_post_meta( $post->ID, '_edu_wc_product', true );

		$products = get_posts( array(
			'post_type'   => 'product',
			'post_status' => 'publish',
		) );

		$output = '';

		if ( ! empty( $products ) ) {
			$output .= '<select name="_edu_wc_product">';
			$output .= '<option value="0"></option>';

			foreach ( $products as $product ) {
				$output .= '<option value="' . esc_attr( $product->ID ) . '"' . selected( $cur_product_id, $product->ID, false ) . '>' . esc_html( $product->post_title ) . '</option>';
			}

			$output .= '</select>';
		}

		echo $output;
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['edu_wc_products_nonce'] ) || ! wp_verify_nonce( $_POST['edu_wc_products_nonce'], 'edu_wc_products' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$post = get_post( $post_id );

		if ( empty( $post ) || ! in_array( $post->post_type, array( 'ib_educator_course', 'ib_edu_membership' ) ) ) {
			return;
		}

		$obj = get_post_type_object( $post->post_type );

		if ( ! $obj || ! current_user_can( $obj->cap->edit_post, $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_edu_wc_product'] ) ) {
			update_post_meta( $post_id, '_edu_wc_product', intval( $_POST['_edu_wc_product'] ) );
		}
	}
}
