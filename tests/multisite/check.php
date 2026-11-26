<?php
// Synthetic disposable multisite QA; never run against an existing store.
if ( ! defined( 'WPECIR_MULTISITE_QA' ) || ! WPECIR_MULTISITE_QA || ! is_multisite() ) {
	throw new RuntimeException( 'Disposable multisite QA flag required.' );
}
if ( '7.1.5' !== get_bloginfo( 'version' ) || '11.3.1' !== WC_VERSION || '8.3' !== PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ) {
	throw new RuntimeException( 'Unexpected runtime versions.' );
}
function qa_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	echo 'PASS: ' . $message . PHP_EOL;
}
function qa_rest( $method, $route, $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_body_params( $params );
	}
	$result = rest_do_request( $request );
	qa_check( 200 === $result->get_status(), $method . ' ' . $route );
	return $result->get_data();
}
$site = is_main_site() ? 'parent' : 'child';
$other = is_main_site() ? 'child' : 'parent';
$mode = getenv( 'WPECIR_QA_MODE' );
$repo = new \WPEssential\CheckoutImpactRegression\Storage\Repository();
$alerts = \WPEssential\CheckoutImpactRegressionPro\Alerts::class;
qa_check( true === apply_filters( 'wpecir_pro_active', false ), $site . ' Pro is active' );
qa_check( '2' === get_option( 'wpecir_schema_version' ), $site . ' schema installed' );
$observed = static function ( $cost ) {
	return array(
		'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:1' ),
		'shipping.cost' => array( 'state' => 'observed', 'value' => $cost ),
		'coupon.code' => array( 'state' => 'missing' ),
		'coupon.discount' => array( 'state' => 'missing' ),
		'payment.methods' => array( 'state' => 'observed', 'value' => 'bacs' ),
	);
};
if ( 'seed' === $mode ) {
	$start = microtime( true );
	$created = qa_rest( 'POST', '/wpecir/v1/scenarios', array(
		'title' => 'QA ' . $site,
		'input' => array( 'market' => array( 'country' => 'DE' ), 'cart' => array( 'items' => array( array( 'product_id' => 1, 'quantity' => 1 ) ) ), 'currency' => 'EUR' ),
		'expectation' => array( 'shipping_cost' => '4.90' ),
	) );
	$id = (int) $created['id'];
	$count = is_main_site() ? 45 : 1;
	$ids = array();
	for ( $i = 0; $i <= $count; ++$i ) {
		$report = qa_rest( 'POST', '/wpecir/v1/comparisons', array(
			'scenario_id' => $id,
			'before' => $observed( '4.90' ),
			'after' => $observed( '12.50' ),
		) );
		$ids[] = (int) $report['report']['id'];
	}
	// Expire one synthetic row directly; exercise the actual public export filtering.
	global $wpdb;
	$expired = array_shift( $ids );
	qa_check( 1 === $wpdb->update( $wpdb->prefix . 'wpecir_runs', array( 'expires_at' => '2000-01-01 00:00:00' ), array( 'id' => $expired ) ), 'expired fixture created' );
	update_option( 'wpecir_qa_fixture', array( 'id' => $id, 'ids' => $ids, 'expired' => $expired ), false );
	qa_rest( 'POST', '/wpecir-pro/v1/alerts', array( 'enabled' => true, 'recipients' => $site . '@example.test' ) );
	update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'QA ' . $site ) );
	qa_check( false !== wp_next_scheduled( $alerts::HOOK ), $site . ' alert cron scheduled' );
	printf( "Seed %s: %d live reports in %.3f seconds\n", $site, $count, microtime( true ) - $start );
} elseif ( 'verify' === $mode ) {
	$fixture = get_option( 'wpecir_qa_fixture' );
	qa_check( is_array( $fixture ), 'site fixture exists' );
	$scenario = $repo->get_scenario( $fixture['id'] );
	qa_check( ! is_wp_error( $scenario ) && 'QA ' . $site === $scenario['title'], 'site-local scenario title' );
	$rows = $repo->list_scenarios( 1, 25 );
	qa_check( is_array( $rows ) && 1 === count( $rows ) && 'QA ' . $site === $rows[0]['title'], 'other site scenario is not visible' );
	qa_check( $site . '@example.test' === $alerts::settings()['recipients'], 'site-local alert recipients' );
	qa_check( false !== wp_next_scheduled( $alerts::HOOK ), 'site-local cron is present' );
	$start = microtime( true );
	$memory = memory_get_usage( true );
	$json = qa_rest( 'GET', '/wpecir-pro/v1/scenarios/' . $fixture['id'] . '/export', array( 'format' => 'json' ) );
	$csv = qa_rest( 'GET', '/wpecir-pro/v1/scenarios/' . $fixture['id'] . '/export', array( 'format' => 'csv' ) );
	$parsed = json_decode( $json['content'], true, 512, JSON_THROW_ON_ERROR );
	$actual = array_map( 'intval', array_column( $parsed['reports'], 'id' ) );
	$expected = $fixture['ids'];
	rsort( $expected );
	qa_check( $expected === $actual, 'export returns every nonexpired ID exactly once, in stable order' );
	qa_check( count( $expected ) === (int) $json['count'] && count( $expected ) === (int) $csv['count'], 'CSV/JSON report count matches' );
	qa_check( 'QA ' . $site === $parsed['scenario']['title'] && false === strpos( $csv['content'], 'QA ' . $other ), 'cross-site scenario content excluded' );
	qa_check( ! in_array( $fixture['expired'], $actual, true ), 'expired report excluded' );
	printf( "Export %s: %d reports in %.3f seconds; allocated memory delta %d bytes; peak %d bytes\n", $site, count( $expected ), microtime( true ) - $start, memory_get_usage( true ) - $memory, memory_get_peak_usage( true ) );
} else {
	throw new RuntimeException( 'Invalid QA mode' );
}
