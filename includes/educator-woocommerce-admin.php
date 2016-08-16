<?php

class Educator_WooCommerce_Admin {
	/**
	 * @var Educator_WooCommerce_Admin
	 */
	protected static $instance;

	/**
	 * @var Educator_WooCommerce
	 */
	protected $ew;

	/**
	 * Get instance.
	 *
	 * @return Educator_WooCommerce_Admin
	 */
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
		$this->ew = Educator_WooCommerce::get_instance();

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );
		add_action( 'save_post', array( $this, 'update_price' ), 20 );
		add_action( 'update_option_woocommerce_currency', array( $this, 'update_currency' ), 20, 2 );
	}

	/**
	 * Add Product meta box to courses and memberships.
	 */
	public function add_meta_box() {
		$sold_post_types = $this->ew->get_sold_post_types();

		foreach ( $sold_post_types as $screen ) {
			add_meta_box(
				'edu_wc_products',
				__( 'Product', 'educator-wc' ),
				array( $this, 'products_meta_box' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Output product meta box.
	 *
	 * @param WP_Post $post
	 */
	public function products_meta_box( $post ) {
		wp_nonce_field( 'edu_wc_products', 'edu_wc_products_nonce' );

		$cur_product_id = get_post_meta( $post->ID, '_edu_wc_product', true );

		$products = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'simple' )
			),
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

	/**
	 * Save post meta.
	 *
	 * @param int $post_id
	 */
	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['edu_wc_products_nonce'] ) || ! wp_verify_nonce( $_POST['edu_wc_products_nonce'], 'edu_wc_products' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$post = get_post( $post_id );
		$sold_post_types = $this->ew->get_sold_post_types();

		if ( empty( $post ) || ! in_array( $post->post_type, $sold_post_types ) ) {
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

	/**
	 * Update course/membership price when a
	 * related product's price is updated.
	 *
	 * @param int $post_id
	 */
	public function update_price( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$post = get_post( $post_id );

		if ( empty( $post ) ) {
			return;
		}

		$obj = get_post_type_object( $post->post_type );

		if ( ! $obj || ! current_user_can( $obj->cap->edit_post, $post_id ) ) {
			return;
		}

		if ( 'product' == $post->post_type ) {
			$product = wc_get_product( $post_id );
			$product_price = $product->get_price();
			$objects = $this->ew->get_objects_by_product( $post_id );

			foreach ( $objects as $object ) {
				$this->set_price( $object->ID, $product_price, $object->post_type );
			}
		} elseif ( in_array( $post->post_type, $this->ew->get_sold_post_types() ) ) {
			$product_id = get_post_meta( $post_id, '_edu_wc_product', true );

			if ( $product_id ) {
				$product = wc_get_product( $product_id );

				if ( $product ) {
					$product_price = $product->get_price();
					$this->set_price( $post_id, $product_price, $post->post_type );
				}
			}
		}
	}

	/**
	 * Set the price of an object (course or membership).
	 *
	 * @param int $object_id
	 * @param float $price
	 * @param string $object_type
	 */
	public function set_price( $object_id, $price, $object_type ) {
		if ( $price != get_post_meta( $object_id, '_edr_price', true ) ) {
			update_post_meta( $object_id, '_edr_price', $price );
		}
	}

	/**
	 * Update Educator's currency when WooCommerce's currency changes.
	 *
	 * @param string $old_currency
	 * @param string $new_currency
	 */
	public function update_currency( $old_currency, $new_currency ) {
		$settings = get_option( 'edr_settings', array() );
		$settings['currency'] = $new_currency;
		update_option( 'edr_settings', $settings );
	}
}
