<?php
/**
 * Runtime integration check for the free plugin + Pro inside WordPress
 * Playground. Throws on the first failure so the blueprint run fails.
 *
 * @package WPEssential\CheckoutImpactRegressionPro
 */

$result_file = getenv( 'WPECIR_TEST_RESULT' ) ?: '/integration/result.txt';
@unlink( $result_file );
register_shutdown_function(
	static function () use ( $result_file ) {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) && ! is_file( $result_file ) ) {
			file_put_contents( $result_file, 'FATAL ' . $error['message'] . "\n" );
		}
	}
);
$native_root = getenv( 'WPECIR_TEST_WP_ROOT' );
if ( $native_root && '1' !== getenv( 'WPECIR_TEST_DISPOSABLE' ) ) {
	throw new RuntimeException( 'Native integration requires a disposable-store flag.' );
}
require_once ( $native_root ?: '/wordpress' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$results = array();
$check   = static function ( $condition, $message ) use ( &$results, $result_file ) {
	if ( ! $condition ) {
		$results[] = 'FAIL ' . $message;
		file_put_contents( $result_file, implode( "\n", $results ) . "\n" );
		throw new RuntimeException( 'Integration check failed: ' . $message );
	}
	$results[] = 'PASS ' . $message;
};

// Record the installed runtime, not just the requested test matrix.
$expected = $GLOBALS['wpecir_expected_versions'] ?? array( '', '', '' );
$actual_wp = get_bloginfo( 'version' );
$actual_wc = defined( 'WC_VERSION' ) ? WC_VERSION : 'not loaded';
$actual_php = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$results[] = 'Runtime: WP ' . $actual_wp . ' / WC ' . $actual_wc . ' / PHP ' . PHP_VERSION;
$check( '' === $expected[0] || $expected[0] === $actual_wp || ( $expected[0] . '.0' === $actual_wp && 1 === substr_count( $expected[0], '.' ) ), 'requested WordPress patch version is installed' );
$check( '' === $expected[1] || $expected[1] === $actual_wc, 'requested WooCommerce patch version is installed' );
$check( '' === $expected[2] || $expected[2] === $actual_php, 'requested PHP minor version is running' );

wp_set_current_user( 1 );

$check( is_plugin_active( 'woocommerce/woocommerce.php' ), 'WooCommerce is active' );
$check( is_plugin_active( 'wpessential-checkout-impact-regression-for-woocommerce/wpessential-checkout-impact-regression-for-woocommerce.php' ), 'free plugin is active' );
$check( is_plugin_active( 'wpessential-checkout-impact-regression-pro-for-woocommerce/wpessential-checkout-impact-regression-pro-for-woocommerce.php' ), 'Pro plugin is active' );
$check( did_action( 'wpecir_loaded' ) > 0 && defined( 'WPECIR_VERSION' ) && defined( 'WPECIR_PRO_VERSION' ), 'free loaded before Pro booted' );
$check( true === apply_filters( 'wpecir_pro_active', false ), 'Pro registered itself with the free plugin' );
$check( version_compare( WPECIR_VERSION, WPECIR_PRO_MIN_FREE_VERSION, '>=' ), 'free version satisfies Pro minimum' );

do_action( 'rest_api_init' );
$routes = rest_get_server()->get_routes();
foreach ( array( '/wpecir/v1/scenarios', '/wpecir/v1/settings', '/wpecir-pro/v1/alerts', '/wpecir-pro/v1/scenarios/(?P<id>\d+)/export' ) as $route ) {
	$check( isset( $routes[ $route ] ), "REST route {$route} registered" );
}

$request = new WP_REST_Request( 'POST', '/wpecir/v1/scenarios' );
$request->set_body_params(
	array(
		'title'       => 'Integration DE ' . wp_rand(),
		'input'       => array( 'market' => array( 'country' => 'DE' ), 'cart' => array( 'items' => array( array( 'product_id' => 1, 'quantity' => 1 ) ) ), 'currency' => 'EUR' ),
		'expectation' => array( 'shipping_cost' => '4.90' ),
	)
);
$response = rest_do_request( $request );
$check( 200 === $response->get_status(), 'free REST creates a scenario' );
$scenario_id = (int) $response->get_data()['id'];

$observed = static function ( $cost ) {
	return array(
		'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:1' ),
		'shipping.cost'   => array( 'state' => 'observed', 'value' => $cost ),
		'coupon.code'     => array( 'state' => 'missing' ),
		'coupon.discount' => array( 'state' => 'missing' ),
		'payment.methods' => array( 'state' => 'observed', 'value' => 'bacs' ),
	);
};
$save = new WP_REST_Request( 'POST', '/wpecir/v1/comparisons' );
$save->set_body_params( array( 'scenario_id' => $scenario_id, 'before' => $observed( '4.90' ), 'after' => $observed( '12.50' ) ) );
$saved = rest_do_request( $save );
$check( 200 === $saved->get_status() && ! empty( $saved->get_data()['report']['id'] ), 'free REST saves a comparison report' );
$report_id = (int) $saved->get_data()['report']['id'];

$export = new WP_REST_Request( 'GET', '/wpecir-pro/v1/scenarios/' . $scenario_id . '/export' );
$export->set_param( 'format', 'csv' );
$export_response = rest_do_request( $export );
$csv             = 200 === $export_response->get_status() ? (string) $export_response->get_data()['content'] : '';
$check( 1 === (int) $export_response->get_data()['count'] && 0 === strpos( $csv, '"report_id"' ), 'Pro export returns the saved report' );
$check( false !== strpos( $csv, '"' . $report_id . '",' ) && false !== strpos( $csv, '"shipping_cost","EUR 4.9","EUR 12.5","changed"' ), 'exported CSV contains the report\'s before/after values and result' );

$json_request = new WP_REST_Request( 'GET', '/wpecir-pro/v1/scenarios/' . $scenario_id . '/export' );
$json_request->set_param( 'format', 'json' );
$json_response = rest_do_request( $json_request );
$check( 200 === $json_response->get_status(), 'JSON export returns success' );
$json_data = $json_response->get_data();
$decoded = json_decode( $json_data['content'], true );
$check( 'application/json' === $json_data['mime'] && 1 === (int) $json_data['count'] && is_array( $decoded ) && $scenario_id === (int) $decoded['scenario']['id'] && $report_id === (int) $decoded['reports'][0]['id'], 'JSON export contains exactly the selected scenario and saved report' );
$check( 'changed' === $decoded['reports'][0]['changes']['shipping_cost']['state'], 'JSON export contains changed shipping cost result' );

\WPEssential\CheckoutImpactRegressionPro\Alerts::update( true, 'ops@example.test, qa@example.test' );
delete_option( \WPEssential\CheckoutImpactRegressionPro\Alerts::QUEUE_OPTION );
$secret_title = 'COD secret ' . wp_rand();
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => $secret_title ) );
$queue = get_option( \WPEssential\CheckoutImpactRegressionPro\Alerts::QUEUE_OPTION );
$check( is_array( $queue ) && count( $queue ) >= 1, 'a free change event reaches the Pro alert queue' );
$mail = null;
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mail ) {
		$mail = $atts;
		return true; // Capture instead of sending.
	},
	10,
	2
);
$check( true === \WPEssential\CheckoutImpactRegressionPro\Alerts::send_digest(), 'Pro sends the digest for free events' );
$check( is_array( $mail ) && array( 'ops@example.test', 'qa@example.test' ) === array_values( (array) $mail['to'] ), 'digest goes to exactly the configured recipients' );
$check( false !== strpos( (string) $mail['message'], 'woocommerce_cod_settings' ) && false !== strpos( (string) $mail['message'], 'page=wpessential-checkout-impact' ), 'digest names the changed setting and links to the free screen' );
$check( false === strpos( (string) $mail['message'], $secret_title ), 'digest does not include setting values' );
$check( ! get_option( \WPEssential\CheckoutImpactRegressionPro\Alerts::QUEUE_OPTION ), 'sent events are removed from the queue' );
 
// Exercise cron retry transitions without delivering any mail or waiting 15 minutes.
$alert_class = \WPEssential\CheckoutImpactRegressionPro\Alerts::class;
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'no', 'title' => 'synthetic retry' ) );
$retry_queue = get_option( $alert_class::QUEUE_OPTION );
$check( is_array( $retry_queue ) && ! empty( $retry_queue ), 'retry fixture has a queued change' );
$force_failure = static function () { return false; };
add_filter( 'pre_wp_mail', $force_failure, 99 );
for ( $attempt_no = 1; $attempt_no <= 3; ++$attempt_no ) {
	// WP Cron removes the current single event before executing its callback.
	wp_clear_scheduled_hook( $alert_class::HOOK );
	$check( false === $alert_class::send_digest(), 'mail failure attempt ' . $attempt_no . ' returns false' );
	$check( $retry_queue === get_option( $alert_class::QUEUE_OPTION ), 'mail failure preserves queued IDs on attempt ' . $attempt_no );
	if ( $attempt_no < 3 ) {
		$check( $attempt_no === (int) get_option( $alert_class::ATTEMPTS_OPTION ), 'retry attempt counter ' . $attempt_no );
		$check( false !== wp_next_scheduled( $alert_class::HOOK ), 'retry cron scheduled after attempt ' . $attempt_no );
	}
}
$check( false === wp_next_scheduled( $alert_class::HOOK ) && ! get_option( $alert_class::ATTEMPTS_OPTION ) && ! empty( $alert_class::settings()['last_failure'] ), 'third failure stops retries and exposes failure status' );
remove_filter( 'pre_wp_mail', $force_failure, 99 );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'synthetic recovery' ) );
$check( false !== wp_next_scheduled( $alert_class::HOOK ), 'new change schedules recovery after retry exhaustion' );
wp_clear_scheduled_hook( $alert_class::HOOK );
$check( true === $alert_class::send_digest(), 'mail recovery succeeds' );
$check( ! get_option( $alert_class::QUEUE_OPTION ) && ! get_option( $alert_class::ATTEMPTS_OPTION ) && '' === $alert_class::settings()['last_failure'], 'recovery clears queue, attempts and failure status' );


$subscriber = wp_insert_user( array( 'user_login' => 'gallery-test-subscriber-' . wp_rand(), 'user_pass' => wp_generate_password( 24 ), 'role' => 'subscriber' ) );
$check( ! is_wp_error( $subscriber ), 'synthetic subscriber created' );
foreach ( array( 0, $subscriber ) as $user_id ) {
	wp_set_current_user( $user_id );
	$actor = $user_id ? 'subscriber' : 'anonymous';
	$denied_write = new WP_REST_Request( 'POST', '/wpecir-pro/v1/alerts' );
	$denied_write->set_body_params( array( 'enabled' => false, 'recipients' => 'denied@example.test' ) );
	foreach ( array( new WP_REST_Request( 'GET', '/wpecir-pro/v1/alerts' ), $denied_write, $json_request ) as $denied ) {
		// New request instance: avoid cached attributes from a previous dispatch.
		$probe = new WP_REST_Request( $denied->get_method(), $denied->get_route() );
		$probe->set_body_params( $denied->get_body_params() );
		$probe->set_query_params( $denied->get_query_params() );
		$check( in_array( rest_do_request( $probe )->get_status(), array( 401, 403 ), true ), 'Pro REST denies ' . $actor . ' ' . $probe->get_method() . ' ' . $probe->get_route() );
	}
}
wp_set_current_user( 1 );
$check( true === \WPEssential\CheckoutImpactRegressionPro\Alerts::settings()['enabled'], 'denied requests did not disable alerts' );

// Pro must not run without the free plugin.
wp_set_current_user( 1 );
$pro_file  = 'wpessential-checkout-impact-regression-pro-for-woocommerce/wpessential-checkout-impact-regression-pro-for-woocommerce.php';
$free_file = 'wpessential-checkout-impact-regression-for-woocommerce/wpessential-checkout-impact-regression-for-woocommerce.php';
WP_Plugin_Dependencies::initialize();
$check( WP_Plugin_Dependencies::has_active_dependents( $free_file ), 'WordPress lists Pro as an active dependent of the free plugin (free cannot be deactivated from the Plugins screen)' );
deactivate_plugins( array( $pro_file, $free_file ), true );
$attempt = activate_plugin( $pro_file );
$check( is_wp_error( $attempt ) && ! is_plugin_active( $pro_file ), 'Pro cannot be activated without the free plugin (' . ( is_wp_error( $attempt ) ? $attempt->get_error_code() : 'activated' ) . ')' );
activate_plugin( $free_file );
$check( ! is_wp_error( activate_plugin( $pro_file ) ) && is_plugin_active( $pro_file ), 'Pro activates once the free plugin is active' );

// Verify uninstall isolation by running the actual uninstall entrypoint in this disposable store.
deactivate_plugins( $pro_file );
update_option( 'wpecir_pro_alert_queue', array( 123 ) );
update_option( 'wpecir_pro_alert_attempts', 1 );
update_option( 'wpecir_pro_alert_last_failure', 'synthetic' );
update_option( 'wpecir_pro_alert_send_lock', '1:synthetic', false );
wp_schedule_single_event( time() + 900, 'wpecir_pro_send_alert_digest' );
define( 'WP_UNINSTALL_PLUGIN', $pro_file );
require WP_PLUGIN_DIR . '/' . dirname( $pro_file ) . '/uninstall.php';
foreach ( array( 'wpecir_pro_alerts', 'wpecir_pro_alert_queue', 'wpecir_pro_alert_attempts', 'wpecir_pro_alert_last_failure', 'wpecir_pro_alert_send_lock' ) as $option ) {
	$check( false === get_option( $option ), 'uninstall deletes ' . $option );
}
$check( false === wp_next_scheduled( 'wpecir_pro_send_alert_digest' ), 'uninstall clears Pro cron' );
$remaining = rest_do_request( new WP_REST_Request( 'GET', '/wpecir/v1/scenarios/' . $scenario_id . '/comparisons' ) );
$check( 200 === $remaining->get_status() && $report_id === (int) $remaining->get_data()['reports'][0]['id'], 'Free report survives Pro uninstall' );
$check( is_plugin_active( $free_file ), 'Free remains active after Pro uninstall' );

file_put_contents( $result_file, implode( "\n", $results ) . "\nINTEGRATION OK\n" );
