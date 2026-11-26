<?php
// Disposable native QA only. Never execute against an existing store.
if ( ! defined( 'WPECIR_STRESS_QA' ) || ! WPECIR_STRESS_QA ) {
	throw new RuntimeException( 'Disposable QA flag required.' );
}
if ( '7.1.5' !== get_bloginfo( 'version' ) || '11.3.1' !== WC_VERSION || '8.3' !== PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION || ! apply_filters( 'wpecir_pro_active', false ) ) {
	throw new RuntimeException( 'Unexpected runtime or inactive Pro.' );
}

$alerts = \WPEssential\CheckoutImpactRegressionPro\Alerts::class;
$mode = getenv( 'QA_MODE' );
$sync = getenv( 'QA_SYNC' );
if ( ! is_dir( $sync ) ) {
	throw new RuntimeException( 'Missing coordination directory.' );
}
if ( 'seed' === $mode ) {
	$alerts::update( true, 'qa@example.test' );
	delete_option( $alerts::QUEUE_OPTION );
	delete_option( $alerts::ATTEMPTS_OPTION );
	delete_option( $alerts::LAST_ERROR_OPTION );
	wp_clear_scheduled_hook( $alerts::HOOK );
	update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'fixture-' . wp_rand() ) );
	$queue = get_option( $alerts::QUEUE_OPTION );
	if ( ! is_array( $queue ) || ! $queue ) {
		throw new RuntimeException( 'No seeded queue.' );
	}
	file_put_contents( $sync . '/original.json', wp_json_encode( $queue ) );
} elseif ( 'send-hold' === $mode || 'send' === $mode ) {
	// Capture only; hold the first sender inside wp_mail to force overlap.
	add_filter( 'pre_wp_mail', static function ( $short, $atts ) use ( $sync, $mode ) {
		file_put_contents( $sync . '/deliveries.log', $mode . "\n", FILE_APPEND | LOCK_EX );
		if ( 'send-hold' === $mode ) {
			touch( $sync . '/entered' );
			$deadline = microtime( true ) + 25;
			while ( ! is_file( $sync . '/release' ) && microtime( true ) < $deadline ) {
				usleep( 20000 );
				clearstatcache();
			}
			if ( ! is_file( $sync . '/release' ) ) {
				throw new RuntimeException( 'Barrier timed out.' );
			}
		}
		return true;
	}, 99, 2 );
	$result = $alerts::send_digest();
	echo $mode . ' result: ' . ( $result ? 'sent' : 'not sent' ) . PHP_EOL;
} elseif ( 'queue-new' === $mode ) {
	update_option( 'woocommerce_cod_settings', array( 'enabled' => 'no', 'title' => 'overlap-' . wp_rand() ) );
	$original = json_decode( file_get_contents( $sync . '/original.json' ), true );
	$current = get_option( $alerts::QUEUE_OPTION, array() );
	$new = array_values( array_diff( $current, $original ) );
	if ( ! $new ) {
		throw new RuntimeException( 'New change did not enter queue.' );
	}
	file_put_contents( $sync . '/new.json', wp_json_encode( $new ) );
} elseif ( 'verify-duplicate' === $mode ) {
	$count = count( file( $sync . '/deliveries.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) );
	echo "Concurrent mail deliveries: $count (expected 1)\n";
	if ( 1 !== $count ) {
		throw new RuntimeException( 'Duplicate digest reproduced.' );
	}
} elseif ( 'verify-new' === $mode ) {
	$new = json_decode( file_get_contents( $sync . '/new.json' ), true );
	$current = get_option( $alerts::QUEUE_OPTION, array() );
	echo 'Remaining queue: ' . wp_json_encode( $current ) . '; expected new IDs: ' . wp_json_encode( $new ) . PHP_EOL;
	if ( array_diff( $new, $current ) ) {
		throw new RuntimeException( 'New events lost during concurrent send.' );
	}
} else {
	throw new RuntimeException( 'Unknown test mode.' );
}
