<?php
// Disposable CI fixture only. No checkout requests, order creation, or payment execution.
if ( ! defined( 'WPECIR_MIGRATION_QA' ) || ! WPECIR_MIGRATION_QA ) {
	throw new RuntimeException( 'Disposable QA flag required.' );
}
if ( '7.1.5' !== get_bloginfo( 'version' ) || '11.3.1' !== WC_VERSION || '8.3' !== PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION || ! apply_filters( 'wpecir_pro_active', false ) ) {
	throw new RuntimeException( 'Unexpected runtime or inactive Pro.' );
}
function migration_check( $condition, $label ) {
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
	echo 'PASS: ' . $label . PHP_EOL;
}
function migration_rest( $method, $route, $params ) {
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_body_params( $params );
	}
	$response = rest_do_request( $request );
	migration_check( 200 === $response->get_status(), $method . ' ' . $route );
	return $response->get_data();
}
global $wpdb;
$repo = new \WPEssential\CheckoutImpactRegression\Storage\Repository();
$alerts = \WPEssential\CheckoutImpactRegressionPro\Alerts::class;
$observed = static function ( $cost ) {
	return array(
		'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:1' ),
		'shipping.cost' => array( 'state' => 'observed', 'value' => $cost ),
		'coupon.code' => array( 'state' => 'missing' ),
		'coupon.discount' => array( 'state' => 'missing' ),
		'payment.methods' => array( 'state' => 'observed', 'value' => 'bacs' ),
	);
};
$scenario = migration_rest( 'POST', '/wpecir/v1/scenarios', array(
	'title' => 'Migration QA',
	'input' => array( 'market' => array( 'country' => 'DE' ), 'cart' => array( 'items' => array( array( 'product_id' => 1, 'quantity' => 1 ) ) ), 'currency' => 'EUR' ),
	'expectation' => array( 'shipping_cost' => '4.90' ),
) );
$id = (int) $scenario['id'];
$baseline = migration_rest( 'POST', '/wpecir/v1/scenarios/' . $id . '/baselines', array( 'observations' => $observed( '4.90' ) ) );
$baseline_id = (int) $baseline['baseline']['id'];
$report = migration_rest( 'POST', '/wpecir/v1/comparisons', array( 'scenario_id' => $id, 'baseline_id' => $baseline_id, 'after' => $observed( '12.50' ) ) );
$report_id = (int) $report['report']['id'];
$alerts::update( true, 'qa@example.test' );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Before migration' ) );
$events = $repo->list_change_events();
migration_check( ! is_wp_error( $events ) && count( $events ) > 0, 'pre-upgrade event exists' );
$event_id = (int) $events[0]['id'];
$queue_before = get_option( $alerts::QUEUE_OPTION );
migration_check( is_array( $queue_before ) && in_array( $event_id, $queue_before, true ), 'pre-upgrade Pro alert queue exists' );
// Reconstruct the known v1 schema difference on synthetic data.
$table = $wpdb->prefix . 'wpecir_change_events';
migration_check( false !== $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN object_label', $table ) ), 'v1 schema fixture created' );
update_option( 'wpecir_schema_version', '1', false );
wp_set_current_user( 0 );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'no', 'title' => 'After migration' ) );
migration_check( '2' === get_option( 'wpecir_schema_version' ), 'anonymous setting hook migrates schema to version 2' );
migration_check( 'object_label' === $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'object_label' ) ), 'object_label restored' );
wp_set_current_user( 1 );
$events = $repo->list_change_events();
migration_check( in_array( $event_id, array_map( 'intval', array_column( $events, 'id' ) ), true ), 'old event retained' );
migration_check( count( $events ) > 1, 'event recorded after migration' );
migration_check( ! array_diff( $queue_before, get_option( $alerts::QUEUE_OPTION, array() ) ), 'Pro queued event survives migration' );
migration_check( ! is_wp_error( $repo->get_baseline( $id, $baseline_id ) ), 'approved baseline survives migration' );
$rows = $repo->list_manual_comparisons( $id );
migration_check( in_array( $report_id, array_map( 'intval', array_column( $rows, 'id' ) ), true ), 'comparison survives migration' );
$orders_before = wc_get_orders( array( 'limit' => 1, 'paginate' => true, 'return' => 'ids' ) )->total;
foreach ( array( 'classic' => '[woocommerce_checkout]', 'block' => '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout"></div><!-- /wp:woocommerce/checkout -->' ) as $mode => $content ) {
	$page = wp_insert_post( array( 'post_title' => 'Synthetic ' . $mode, 'post_content' => $content, 'post_type' => 'page', 'post_status' => 'publish' ), true );
	migration_check( ! is_wp_error( $page ) && $page > 0, $mode . ' configuration page exists' );
	update_option( 'woocommerce_checkout_page_id', $page );
	migration_check( (int) wc_get_page_id( 'checkout' ) === $page, $mode . ' selected as Woo checkout page' );
	$stored = get_post_field( 'post_content', $page );
	migration_check( 'classic' === $mode ? has_shortcode( $stored, 'woocommerce_checkout' ) : has_block( 'woocommerce/checkout', $stored ), $mode . ' stored checkout format verified' );
	$before = $repo->get_scenario( $id );
	$result = migration_rest( 'POST', '/wpecir/v1/comparisons', array( 'scenario_id' => $id, 'baseline_id' => $baseline_id, 'after' => $observed( '12.50' ) ) );
	migration_check( ! empty( $result['report']['id'] ), $mode . ' manual admin comparison saves' );
	$after = $repo->get_scenario( $id );
	migration_check( $before['latest_version']['context_fingerprint'] === $after['latest_version']['context_fingerprint'], $mode . ' saved scenario identity remains intact' );
}
$orders_after = wc_get_orders( array( 'limit' => 1, 'paginate' => true, 'return' => 'ids' ) )->total;
migration_check( $orders_before === $orders_after, 'no order created in tested manual operations' );
echo "MIGRATION AND CHECKOUT CONFIGURATION OK\n";
