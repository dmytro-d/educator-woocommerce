<?php
/**
 * Plugin Name: Educator WooCommerce Integration
 * Plugin URI: http://educatorplugin.com/add-ons/educator-woocommerce-integration/
 * Description: Integrate WooCommerce with Educator.
 * Version: 2.0
 * Author: educatorteam
 * Author URI: http://educatorplugin.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: educator-wc
 */

/*
Copyright (C) 2015 http://educatorplugin.com/ - contact@educatorplugin.com

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! defined( 'ABSPATH' ) ) exit;

function edu_wc_initialize() {
	require 'includes/educator-woocommerce.php';

	if ( defined( 'EDR_VERSION' ) ) {
		Educator_WooCommerce::get_instance();

		if ( is_admin() ) {
			require 'includes/educator-woocommerce-admin.php';
			Educator_WooCommerce_Admin::get_instance();
		}
	} else {
		require 'includes/educator-woocommerce-old.php';
		Educator_WooCommerce_Old::get_instance();

		if ( is_admin() ) {
			require 'includes/educator-woocommerce-admin.php';
			require 'includes/educator-woocommerce-admin-old.php';
			Educator_WooCommerce_Admin_Old::get_instance();
		}
	}
}

add_action( 'plugins_loaded', 'edu_wc_initialize' );
