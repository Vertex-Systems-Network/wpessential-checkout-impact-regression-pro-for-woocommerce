<?php
// Disposable CI store only; never run against an existing store.
if ( ! defined( 'WPECIR_GALLERY_SANDBOX' ) || ! WPECIR_GALLERY_SANDBOX ) {
	throw new RuntimeException( 'Gallery sandbox flag missing.' );
}
if ( get_bloginfo( 'version' ) !== '7.1.5' || WC_VERSION !== '11.3.1' || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 3 || WPECIR_VERSION !== '1.0.0' || WPECIR_PRO_VERSION !== '1.0.0' ) {
	throw new RuntimeException( 'Unexpected runtime versions.' );
}
wp_set_current_user( 1 );
if ( ! apply_filters( 'wpecir_pro_active', false ) ) {
	throw new RuntimeException( 'Pro is inactive.' );
}
function gallery_request( $route, $params ) {
	$request = new WP_REST_Request( 'POST', $route );
	$request->set_body_params( $params );
	$response = rest_do_request( $request );
	if ( $response->get_status() >= 300 ) {
		throw new RuntimeException( $route . ': ' . wp_json_encode( $response->get_data() ) );
	}
	return $response->get_data();
}
update_option( 'woocommerce_currency', 'EUR' );
update_option( 'woocommerce_default_country', 'DE' );
$product = new WC_Product_Simple();
$product->set_name( 'Demo T-shirt' );
$product->set_regular_price( '24.00' );
$product->set_status( 'publish' );
$product_id = $product->save();
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Germany' );
$zone->add_location( 'DE', 'country' );
$zone->save();
$method = $zone->add_shipping_method( 'flat_rate' );
$created = gallery_request( '/wpecir/v1/scenarios', array(
	'title' => 'Germany — standard shipping',
	'input' => array( 'market' => array( 'country' => 'DE' ), 'cart' => array( 'items' => array( array( 'product_id' => $product_id, 'quantity' => 1 ) ) ), 'currency' => 'EUR' ),
	'expectation' => array( 'shipping_cost' => '4.90' ),
) );
$id = (int) $created['id'];
$observed = static function ( $cost ) use ( $method ) {
	return array(
		'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:' . $method ),
		'shipping.cost' => array( 'state' => 'observed', 'value' => $cost ),
		'coupon.code' => array( 'state' => 'missing' ),
		'coupon.discount' => array( 'state' => 'missing' ),
		'payment.methods' => array( 'state' => 'observed', 'value' => 'bacs' ),
	);
};
$approved = gallery_request( '/wpecir/v1/scenarios/' . $id . '/baselines', array( 'observations' => $observed( '4.90' ) ) );
$baseline = (int) $approved['baseline']['id'];
$saved = gallery_request( '/wpecir/v1/comparisons', array( 'scenario_id' => $id, 'baseline_id' => $baseline, 'after' => $observed( '12.50' ) ) );
if ( empty( $saved['report']['id'] ) ) {
	throw new RuntimeException( 'Report not saved.' );
}
gallery_request( '/wpecir-pro/v1/alerts', array( 'enabled' => true, 'recipients' => 'ops@example.test, qa@example.test' ) );
echo 'Gallery fixture ready: scenario ' . $id . ', baseline ' . $baseline . ', report ' . $saved['report']['id'] . PHP_EOL;
