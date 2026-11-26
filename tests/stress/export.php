<?php
// Disposable native QA only. Never execute against an existing store.
if ( ! defined( 'WPECIR_STRESS_QA' ) || ! WPECIR_STRESS_QA ) {
	throw new RuntimeException( 'Disposable QA flag required.' );
}
if ( '7.1.5' !== get_bloginfo( 'version' ) || '11.3.1' !== WC_VERSION || '8.3' !== PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION || ! apply_filters( 'wpecir_pro_active', false ) ) {
	throw new RuntimeException( 'Unexpected runtime or inactive Pro.' );
}

function stress_rest( $method, $route, $params ) {
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_body_params( $params );
	}
	$result = rest_do_request( $request );
	if ( 200 !== $result->get_status() ) {
		throw new RuntimeException( $route . ': ' . wp_json_encode( $result->get_data() ) );
	}
	return $result->get_data();
}
$scenario = stress_rest( 'POST', '/wpecir/v1/scenarios', array(
	'title' => 'Synthetic export boundary',
	'input' => array( 'market' => array( 'country' => 'DE' ), 'cart' => array( 'items' => array( array( 'product_id' => 1, 'quantity' => 1 ) ) ), 'currency' => 'EUR' ),
	'expectation' => array( 'shipping_cost' => '4.90' ),
) );
$id = (int) $scenario['id'];
$observed = static function ( $cost ) {
	return array(
		'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:1' ),
		'shipping.cost' => array( 'state' => 'observed', 'value' => $cost ),
		'coupon.code' => array( 'state' => 'missing' ),
		'coupon.discount' => array( 'state' => 'missing' ),
		'payment.methods' => array( 'state' => 'observed', 'value' => 'bacs' ),
	);
};
$ids = array();
$start = microtime( true );
for ( $i = 0; $i < 2001; ++$i ) {
	$result = stress_rest( 'POST', '/wpecir/v1/comparisons', array(
		'scenario_id' => $id,
		'before' => $observed( '4.90' ),
		'after' => $observed( '12.50' ),
	) );
	$ids[] = (int) $result['report']['id'];
	if ( 0 === ( $i + 1 ) % 250 ) {
		printf( "Saved %d reports\n", $i + 1 );
	}
}
printf( "Created 2001 reports in %.3f s\n", microtime( true ) - $start );
$expected = array_reverse( $ids );
$expected = array_slice( $expected, 0, 2000 );
$start = microtime( true );
$json = stress_rest( 'GET', '/wpecir-pro/v1/scenarios/' . $id . '/export', array( 'format' => 'json' ) );
$parsed = json_decode( $json['content'], true, 512, JSON_THROW_ON_ERROR );
$actual = array_map( 'intval', array_column( $parsed['reports'], 'id' ) );
if ( $expected !== $actual || 2000 !== (int) $json['count'] || true !== $json['truncated'] || 2000 !== $json['limit'] ) {
	throw new RuntimeException( 'JSON cap/order/deduplication failed.' );
}
printf( "JSON: %d reports, %d bytes, %.3f s\n", count( $actual ), strlen( $json['content'] ), microtime( true ) - $start );
unset( $json, $parsed, $actual );
$start = microtime( true );
$csv = stress_rest( 'GET', '/wpecir-pro/v1/scenarios/' . $id . '/export', array( 'format' => 'csv' ) );
$stream = fopen( 'php://temp', 'w+' );
fwrite( $stream, $csv['content'] );
rewind( $stream );
fgetcsv( $stream, 0, ',', '"', '' );
$csv_ids = array();
while ( false !== ( $row = fgetcsv( $stream, 0, ',', '"', '' ) ) ) {
	$csv_ids[ (int) $row[0] ] = true;
}
fclose( $stream );
if ( $expected !== array_keys( $csv_ids ) || 2000 !== (int) $csv['count'] || true !== $csv['truncated'] || 2000 !== $csv['limit'] ) {
	throw new RuntimeException( 'CSV cap/order/deduplication failed.' );
}
printf( "CSV: %d reports, %d bytes, %.3f s; peak PHP allocated bytes %d\n", count( $csv_ids ), strlen( $csv['content'] ), microtime( true ) - $start, memory_get_peak_usage( true ) );
// Exactly 2000 eligible rows is not truncated. Expire the oldest synthetic run.
global $wpdb;
if ( 1 !== $wpdb->update( $wpdb->prefix . 'wpecir_runs', array( 'expires_at' => '2000-01-01 00:00:00' ), array( 'id' => $ids[0] ) ) ) {
	throw new RuntimeException( 'Could not expire oldest fixture.' );
}
$exact = stress_rest( 'GET', '/wpecir-pro/v1/scenarios/' . $id . '/export', array( 'format' => 'json' ) );
if ( 2000 !== $exact['count'] || false !== $exact['truncated'] ) {
	throw new RuntimeException( 'Exact cap falsely reported as truncated.' );
}
echo "PASS: exact cap is complete, not truncated\n";
echo "EXPORT CAP OK (2000 newest of 2001; truncation metadata true)\n";
