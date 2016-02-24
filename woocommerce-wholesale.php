<?php
/*
 * Plugin Name: WooCommerce Wholesale
 * Plugin URI:
 * Description: Add roles and options for wholesale/ customers.
 * Version: 1.0.0
 * Author: Hall Internet Marketing
 * Author URI: http://hallme.com/
 * Developer: Tim Howe
 * Developer URI: http://hallme.com/
 * Text Domain: woocommerce-wholesale
 * Domain Path: /lang
 *
 * Copyright: © 2009-2015 WooThemes.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Wholesale {

    /*--------------------------------------------*
     * Constructor
     *--------------------------------------------*/

    /**
     * Initializes the plugin by setting localization, filters, and administration functions.
     *
     * @since WooCommerce Wholesale 1.0
     */
    function __construct() {

        // Load plugin text domain
        add_action( 'init', array( $this, 'plugin_textdomain' ) );

		// Add and Save wholesale price for products.
		add_action( 'woocommerce_product_options_pricing', array( $this, 'add_wholesale_price_options' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_wholesale_price_options' ) );

		// Add and Save wholesale price for variable products.
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'add_variation_wholesale_price_options' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_wholesale_price_options' ), 10, 2 );

		// Set the price to the wholesale price.
		add_filter( 'woocommerce_get_price', array( $this, 'get_wholesale_price' ), 10, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'get_variation_wholesale_price' ), 10, 3 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( $this, 'get_variation_wholesale_price' ), 10, 3 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( $this, 'get_variation_wholesale_price' ), 10, 3 );

		// Make sure the correct variable price transient is called.
		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'add_role_to_woocommerce_get_variation_prices_hash' ), 10, 1 );

		// Removes all sales and just shows the wholesale price.
		add_filter( 'woocommerce_product_is_on_sale', array( $this, 'remove_product_is_on_sale' ), 10, 2 );

        // Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
        register_activation_hook( __FILE__, array( $this, 'activate' ) );

    }

    /**
     * Fired when the plugin is activated.
     *
     * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
     *
     * @since WooCommerce Wholesale 1.0
     */
    public function activate( $network_wide ) {

		// Add retailer role.
		add_role( 'wholesaler', __( 'Wholesale Customer', 'woocommerce' ), array( 'read' => true ) );

    }

    /**
     * Loads the plugin text domain for translation.
     *
     * @since WooCommerce Wholesale 1.0
     */
    public function plugin_textdomain() {

        load_plugin_textdomain( 'woocommerce-wholesale', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

    }

    /**
     * Registers and enqueues admin-specific JavaScript.
     *
     * @since WooCommerce Wholesale 1.0
     */
    public function register_admin_scripts() {

        wp_enqueue_script( 'woocommerce-wholesale', dirname( plugin_basename( __FILE__ ) ) . '/js/admin.js' );

    }

    /*--------------------------------------------*
     * Core Functions
     *---------------------------------------------*/

     /**
	 * Add option for wholesale prices.
	 *
	 * @since WooCommerce Wholesale 1.0
	 */
	public function add_wholesale_price_options() {
	    global $post, $thepostid;

		// Wholesale Price.
		woocommerce_wp_text_input(
			array(
				'id' => '_wholesale_price',
				'label' => __( 'Wholesale Price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'data_type' => 'price'
			)
		);
	}

	/**
	 * Save wholesale prices
	 *
	 * @since WooCommerce Wholesale 1.0
	 */
	public function save_wholesale_price_options( $post_id ) {

		// Get the product type.
		$product_type = empty( $_POST['product-type'] ) ? 'simple' : sanitize_title( stripslashes( $_POST['product-type'] ) );

		// Sales and prices.
		if ( in_array( $product_type, array( 'variable', 'grouped' ) ) ) {

			// Variable and grouped products have no prices.
			update_post_meta( $post_id, '_wholesale_price', '' );

		} else {

			// Else save the price data.
			$wholesale_price = isset( $_POST['_wholesale_price'] ) ? wc_clean( $_POST['_wholesale_price'] ) : '';
			update_post_meta( $post_id, '_wholesale_price', '' === $wholesale_price ? '' : wc_format_decimal( $wholesale_price ) );

		}
	}

	/**
	 * Add option for wholesale prices to variations.
	 *
	 * @param int $loop - Position in the loop.
	 * @param array $variation_data
	 * @param obj $variation - Variation post object.
	 *
	 * @since WooCommerce Wholesale 1.0
	 */
	public function add_variation_wholesale_price_options($loop, $variation_data, $variation) {

		$_wholesale_price = get_post_meta( $variation->ID, '_wholesale_price', true ); ?>

		<p class="form-row form-row-first">
			<label><?php echo __( 'Wholesale Price:', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')'; ?></label>
			<input type="text" size="5" name="variable_wholesale_price[<?php echo $loop; ?>]" value="<?php if ( isset( $_wholesale_price ) ) echo esc_attr( $_wholesale_price ); ?>" class="wc_input_price" placeholder="<?php esc_attr_e( 'Wholesale price', 'woocommerce' ); ?>" />
		</p>

		<?php
	}

	/**
	 * Save wholesale prices for variation.
	 *
	 * @param int $variation_id - The variation post id
	 * @param int $i - Position in the loop
	 *
	 * @since WooCommerce Wholesale 1.0
	 */
	public function save_variation_wholesale_price_options( $variation_id, $i ) {

		$variable_wholesale_price = $_POST['variable_wholesale_price'];
		$wholesale_price = wc_format_decimal( $variable_wholesale_price[ $i ] );
		update_post_meta( $variation_id, '_wholesale_price', $wholesale_price );

	}

	/**
	 * Returns the product's regular price.
	 *
	 * @param string $price - The price
	 * @param obj $product - The product post object
	 *
	 * @return string price
	 *
	 * @since WooCommerce Wholesale 1.0
	 */
	public function get_wholesale_price( $price, $product ) {

		// If the user is not logged in return normal price.
		if ( !is_user_logged_in() ) {
			return $price;
		}

		// Check the current user is a retailer and set it to the wholesale price
		$current_user = wp_get_current_user();
		if ( in_array( 'wholesaler', (array) $current_user->roles ) ) {

			// If there is a wholesale price set it as the price
			if ( $wholesale_price = get_post_meta( $product->get_id(), '_wholesale_price', true ) ) {
				$price = $wholesale_price;
			}
		}

		return $price;
	}

	/**
	 * Update the variation Price to the wholesale price.
	 *
	 * @param string $variation_price - The price.
	 * @param obj $variation - The variation object.
	 * @param obj $instance - The variable product object.
	 *
	 * @return string
	 *
	 * @since WooCommerce Wholesale 1.0
	 */
	public function get_variation_wholesale_price( $variation_price, $variation, $instance) {
		//var_dump($variation);

		// If the user is not logged in return normal price.
		if ( !is_user_logged_in() ) {
			return $variation_price;
		}

		// Check the current user is a retailer and set it to the wholesale price
		$current_user = wp_get_current_user();
		if ( in_array( 'wholesaler', (array) $current_user->roles ) ) {

			// If there is a wholesale price set it as the price
			if ( $wholesale_price = get_post_meta( $variation->get_id(), '_wholesale_price', true ) ) {
				$variation_price = $wholesale_price;
			}
		}

		return $variation_price;
	}

	/**
	 * Get the min or max variation regular price. - https://woocommerce.wordpress.com/2015/09/14/caching-and-dynamic-pricing-upcoming-changes-to-the-get_variation_prices-method/
	 *
	 * @param $hash Whether the value is going to be displayed.
	 *
	 * @return string
	 *
	 * @since WooCommerce Wholesale 1.0
	 */
	public function add_role_to_woocommerce_get_variation_prices_hash( $hash ) {

	  	// If the user is not logged in return normal price.
		if ( !is_user_logged_in() ) {
			return $hash;
		}

		// Check the current user is a retailer and add the role to the hash
		$current_user = wp_get_current_user();
		if ( in_array( 'wholesaler', (array) $current_user->roles ) ) {
			$hash[] = 'wholesaler';
		}

		return $hash;
	}

	/**
	 * Returns whether or not the product is on sale.
	 *
	 * @param bool $is_sale - If the sale price doesn't equal the regular price and does equal the price.
	 * @param obj $product - The product object.
	 *
	 * @return bool
	 */
	public function remove_product_is_on_sale( $is_on_sale, $product ) {
		// If the user is not logged in return normal price.
		if ( !is_user_logged_in() ) {
			return $is_on_sale;
		}

		// Check the current user is a retailer and add the role to the hash
		$current_user = wp_get_current_user();
		if ( in_array( 'wholesaler', (array) $current_user->roles ) ) {
			$is_on_sale = false;
		}

		return $is_on_sale;
	}

} // End WC_Wholesale.

// Test if woocommerce is installed and active.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    $plugin_name = new WC_Wholesale();
}