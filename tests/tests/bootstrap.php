<?php

class Educator_WooCommerce_Bootstrap {
	private static $instance = null;
	private $wc_path;
	private $edu_path;
	private $edu_wc_path;

	private function __construct() {
		$this->edu_path = dirname( __FILE__ ) . '/../../../ibeducator';
		$this->wc_path = dirname( __FILE__ ) . '/../../../woocommerce';
		$this->edu_wc_path = dirname( __FILE__ ) . '/../../../educator-woocommerce-integration';

		$this->setup();
	}

	private function setup() {
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SERVER['SERVER_NAME'] = 'localhost';

		$_tests_dir = getenv('WP_TESTS_DIR');

		if ( !$_tests_dir ) {
			$_tests_dir = '/tmp/wordpress-tests-lib';
		}

		require_once $_tests_dir . '/includes/functions.php';

		tests_add_filter( 'muplugins_loaded', array( $this, 'load_plugins' ) );
		tests_add_filter( 'setup_theme', array( $this, 'install_plugins' ) );

		require $_tests_dir . '/includes/bootstrap.php';
		require dirname( __FILE__ ) . '/../lib/educator-woocommerce-test.php';
	}

	public function load_plugins() {
		require $this->edu_wc_path . '/educator-woocommerce-integration.php';
		require $this->wc_path . '/woocommerce.php';
		require $this->edu_path . '/ibeducator.php';
	}

	public function install_plugins() {
		// Educator.
		require_once IBEDUCATOR_PLUGIN_DIR . 'includes/ib-educator-install.php';
		$ibe_install = new IB_Educator_Install();
		$ibe_install->activate();

		// WooCommmerce.
		define( 'WP_UNINSTALL_PLUGIN', true );
		include( $this->wc_path . '/uninstall.php' );
		WC_Install::install();
		$GLOBALS['wp_roles']->reinit();
	}

	public function get_edu_wc_path() {
		return $this->edu_wc_path;
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

Educator_WooCommerce_Bootstrap::instance();
