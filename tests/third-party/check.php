<?php
// Native disposable QA: no external provider requests, checkout execution or payments.
if ( ! defined( 'WPECIR_THIRD_PARTY_QA' ) || ! WPECIR_THIRD_PARTY_QA ) {
	throw new RuntimeException( 'Disposable QA flag required.' );
}
if ( '7.1.5' !== get_bloginfo( 'version' ) || '11.3.1' !== WC_VERSION || '8.3' !== PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ) {
	throw new RuntimeException( 'Unexpected runtime versions.' );
}
function coexist_check( $condition, $label ) {
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
	echo 'PASS: ' . $label . PHP_EOL;
}
function coexist_rest( $method, $route, $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_body_params( $params );
	}
	$response = rest_do_request( $request );
	coexist_check( 200 === $response->get_status(), $method . ' ' . $route );
	return $response->get_data();
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$stage = getenv( 'QA_STAGE' ) ?: 'baseline';
$extensions = preg_split( '/\s+/', trim( (string) getenv( 'QA_EXTENSIONS' ) ), -1, PREG_SPLIT_NO_EMPTY );
$installed = get_plugins();
foreach ( $extensions as $slug ) {
	$found = array_filter( $installed, static function ( $file ) use ( $slug ) { return 0 === strpos( $file, $slug . '/' ); }, ARRAY_FILTER_USE_KEY );
	coexist_check( ! empty( $found ), $slug . ' installed' );
	$active = array_filter( array_keys( $found ), 'is_plugin_active' );
	coexist_check( 'active' === $stage ? ! empty( $active ) : empty( $active ), $slug . ' activation state ' . $stage );
	foreach ( $found as $file => $plugin ) {
		if ( is_plugin_active( $file ) ) {
			echo 'EXTENSION ' . $file . ' version ' . $plugin['Version'] . PHP_EOL;
		}
	}
}
coexist_check( true === apply_filters( 'wpecir_pro_active', false ), 'Pro is active ' . $stage );
$alerts = \WPEssential\CheckoutImpactRegressionPro\Alerts::class;
$repo = new \WPEssential\CheckoutImpactRegression\Storage\Repository();
$observed = static function ( $cost ) {
	return array(
		'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:1' ),
		'shipping.cost' => array( 'state' => 'observed', 'value' => $cost ),
		'coupon.code' => array( 'state' => 'missing' ),
		'coupon.discount' => array( 'state' => 'missing' ),
		'payment.methods' => array( 'state' => 'observed', 'value' => 'bacs' ),
	);
};
$order_count = wc_get_orders( array( 'limit' => 1, 'paginate' => true, 'return' => 'ids' ) )->total;
if ( 'baseline' === $stage ) {
	$scenario = coexist_rest( 'POST', '/wpecir/v1/scenarios', array(
		'title' => 'Third-party coexistence QA',
		'input' => array( 'market' => array( 'country' => 'DE' ), 'cart' => array( 'items' => array( array( 'product_id' => 1, 'quantity' => 1 ) ) ), 'currency' => 'EUR' ),
		'expectation' => array( 'shipping_cost' => '4.90' ),
	) );
	$id = (int) $scenario['id'];
	$baseline = coexist_rest( 'POST', '/wpecir/v1/scenarios/' . $id . '/baselines', array( 'observations' => $observed( '4.90' ) ) );
	update_option( 'wpecir_qa_coexistence', array( 'id' => $id, 'baseline' => (int) $baseline['baseline']['id'], 'orders' => $order_count ), false );
	$alerts::update( true, 'qa@example.test' );
} else {
	$fixture = get_option( 'wpecir_qa_coexistence' );
	coexist_check( is_array( $fixture ), 'baseline fixture preserved' );
	$id = $fixture['id'];
	$scenario = $repo->get_scenario( $id );
	coexist_check( ! is_wp_error( $scenario ) && 'Third-party coexistence QA' === $scenario['title'], 'scenario preserved' );
	$comparison = coexist_rest( 'POST', '/wpecir/v1/comparisons', array( 'scenario_id' => $id, 'baseline_id' => $fixture['baseline'], 'after' => $observed( '12.50' ) ) );
	$report = (int) $comparison['report']['id'];
	$json = coexist_rest( 'GET', '/wpecir-pro/v1/scenarios/' . $id . '/export', array( 'format' => 'json' ) );
	$csv = coexist_rest( 'GET', '/wpecir-pro/v1/scenarios/' . $id . '/export', array( 'format' => 'csv' ) );
	$parsed = json_decode( $json['content'], true, 512, JSON_THROW_ON_ERROR );
	coexist_check( in_array( $report, array_map( 'intval', array_column( $parsed['reports'], 'id' ) ), true ), 'new report in JSON' );
	coexist_check( false !== strpos( $csv['content'], '"shipping_cost","EUR 4.9","EUR 12.5","changed"' ), 'CSV preserves comparison' );
	coexist_check( 'qa@example.test' === $alerts::settings()['recipients'], 'alert settings preserved' );
	update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'QA ' . $stage . ' ' . wp_rand() ) );
	coexist_check( ! empty( get_option( $alerts::QUEUE_OPTION ) ), 'setting change queues alert' );
	coexist_check( $fixture['orders'] === $order_count, 'order count unchanged across activation lifecycle' );
}
coexist_check( $order_count === wc_get_orders( array( 'limit' => 1, 'paginate' => true, 'return' => 'ids' ) )->total, 'no order created by test' );
echo 'COEXISTENCE OK ' . $stage . PHP_EOL;
