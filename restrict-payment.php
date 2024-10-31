<?php
/**
 * Plugin Name: Restrict Payment Method
 * Plugin URI: https://www.highriskshop.com/payment-gateway/woocommerce-payment-redirect-to-other-woocommerce-website-api/
 * Description: Restrict selected WooCommerce payment method to old customers with at least one previous completed order.
 * Version: 1.0.0
 * Author: HighRiskShop.COM
 * Author URI: https://www.highriskshop.com/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

// Add settings page to the admin menu
add_action( 'admin_menu', 'highriskshoprestrict_restrict_payment_method_settings_page' );

function highriskshoprestrict_restrict_payment_method_settings_page() {
	add_options_page(
		esc_html__( 'Restrict Payment Methods Settings', 'highriskshoprestrict' ),
		esc_html__( 'Restrict Payment Methods', 'highriskshoprestrict' ),
		'manage_options',
		'highriskshoprestrict-restrict-payment-method-settings',
		'highriskshoprestrict_render_restrict_payment_method_settings_page'
	);
}

// Render settings page
function highriskshoprestrict_render_restrict_payment_method_settings_page() {
	?>
	<div class="wrap">
		<h2><?php esc_html_e( 'Restrict Payment Methods Settings', 'highriskshoprestrict' ); ?></h2>
		
		<form method="post" action="options.php">
			<?php 
			settings_fields( 'highriskshoprestrict_restrict_payment_method_settings_group' );
			do_settings_sections( 'highriskshoprestrict_restrict_payment_method_settings_group' );
			
			// Add nonce for security
			wp_nonce_field( 'highriskshoprestrict_restrict_payment_method_settings_nonce', 'highriskshoprestrict_restrict_payment_method_settings_nonce' );
			?>
			
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

// Register and initialize settings
add_action( 'admin_init', 'highriskshoprestrict_restrict_payment_method_initialize_settings' );

function highriskshoprestrict_restrict_payment_method_initialize_settings() {
	
	register_setting(
		'highriskshoprestrict_restrict_payment_method_settings_group',
		'highriskshoprestrict_restrict_payment_method_settings',
		'highriskshoprestrict_sanitize_restrict_payment_method_settings'
	);

	add_settings_section(
		'highriskshoprestrict_restrict_payment_method_settings_section',
		esc_html__( 'Settings', 'highriskshoprestrict' ),
		'highriskshoprestrict_render_restrict_payment_method_settings_section',
		'highriskshoprestrict_restrict_payment_method_settings_group'
	);

	add_settings_field(
		'restricted_payment_method',
		esc_html__( 'Restrict Payment Method', 'highriskshoprestrict' ),
		'highriskshoprestrict_render_restricted_payment_method_field', 
		'highriskshoprestrict_restrict_payment_method_settings_group',
		'highriskshoprestrict_restrict_payment_method_settings_section'
	);

	add_settings_field(
		'error_message', 
		esc_html__( 'Error Message', 'highriskshoprestrict' ),
		'highriskshoprestrict_render_error_message_field',
		'highriskshoprestrict_restrict_payment_method_settings_group',
		'highriskshoprestrict_restrict_payment_method_settings_section'
	);

}

// Sanitize settings
function highriskshoprestrict_sanitize_restrict_payment_method_settings( $input ) {
	
	 // Check if the nonce is set and valid
    if ( ! isset( $_POST['highriskshoprestrict_restrict_payment_method_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['highriskshoprestrict_restrict_payment_method_settings_nonce'])), 'highriskshoprestrict_restrict_payment_method_settings_nonce' ) ) {
        return $input;
    }
	
	$sanitized_input = array();

	// Sanitize restricted payment method
	if ( isset( $input['restricted_payment_method'] ) ) {
		$sanitized_input['restricted_payment_method'] = sanitize_text_field( $input['restricted_payment_method'] );
	}

	// Sanitize error message
	if ( isset( $input['error_message'] ) ) {
		$sanitized_input['error_message'] = sanitize_text_field( $input['error_message'] );
	}

	return $sanitized_input;
}

// Render settings section
function highriskshoprestrict_render_restrict_payment_method_settings_section() {
	esc_html_e( 'Configure the settings for restricting payment method to customers with previous completed orders.', 'highriskshoprestrict' );
}

// Render restricted payment method field
function highriskshoprestrict_render_restricted_payment_method_field() {
	
	$options = get_option( 'highriskshoprestrict_restrict_payment_method_settings' );
	
	$restricted_payment_method = isset( $options['restricted_payment_method'] ) ? $options['restricted_payment_method'] : 'cod';

	// Get available payment methods
	$payment_methods = WC()->payment_gateways->payment_gateways();
	
	?>
	<select name="highriskshoprestrict_restrict_payment_method_settings[restricted_payment_method]">
		
	<?php foreach ( $payment_methods as $id => $gateway ) : ?>
		
		<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $restricted_payment_method, $id ); ?>>
			<?php echo esc_html( $gateway->get_title() ); ?>
		</option>
		
	<?php endforeach; ?>
	
	</select>
	<?php
}

// Render error message field
function highriskshoprestrict_render_error_message_field() {
	
	$options = get_option( 'highriskshoprestrict_restrict_payment_method_settings' );
	
	$error_message = isset( $options['error_message'] ) ? $options['error_message'] : esc_html__( 'You must have at least one completed order to pay using this method', 'highriskshoprestrict' );
	
	?>
	<input type="text" name="highriskshoprestrict_restrict_payment_method_settings[error_message]" value="<?php echo esc_attr( $error_message ); ?>" />
	<?php
}

// Add filter to disable payment method
add_action( 'woocommerce_after_checkout_validation', 'highriskshoprestrict_restrict_payment_method_for_new_customers', 10, 2 );

function highriskshoprestrict_restrict_payment_method_for_new_customers( $posted, $errors ) {

  $options = get_option( 'highriskshoprestrict_restrict_payment_method_settings' );
  
  $restricted_payment_method = isset( $options['restricted_payment_method'] ) ? sanitize_text_field( $options['restricted_payment_method'] ) : 'cod';

  $error_message = isset( $options['error_message'] ) ? esc_html( $options['error_message'] ) : esc_html__( 'You must have at least one completed order to pay using this method', 'highriskshoprestrict' );

  if ( isset( WC()->session->chosen_payment_method ) && WC()->session->chosen_payment_method === $restricted_payment_method ) {

    if ( ! is_wc_endpoint_url( 'order-received' ) ) {

      $email = sanitize_email( $posted['billing_email'] );
      
      $customer_orders = new WC_Order_Query( array( 
        'customer' => $email, 
        'status' => array( 'completed' )
      ) );

      $completed_orders = $customer_orders->get_orders();

      if ( count( $completed_orders ) < 1 ) {

        unset( WC()->session->chosen_payment_method );
        
        wc_add_notice( esc_html( $error_message ), 'error' );
      }
    }
  }
}