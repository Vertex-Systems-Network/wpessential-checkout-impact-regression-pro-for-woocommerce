<?php
/**
 * Email alerts when a plugin update or tracked setting change is recorded.
 *
 * @package WPEssential\CheckoutImpactRegressionPro
 */

namespace WPEssential\CheckoutImpactRegressionPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batches change events and sends one digest email per window.
 */
final class Alerts {
	const OPTION       = 'wpecir_pro_alerts';
	const QUEUE_OPTION = 'wpecir_pro_alert_queue';
	const HOOK         = 'wpecir_pro_send_alert_digest';
	const DELAY        = 900; // 15 minutes: an update run often records several events.
	const MAX_QUEUE    = 50;
	const MAX_ATTEMPTS = 3;
	const ATTEMPTS_OPTION   = 'wpecir_pro_alert_attempts';
	const LAST_ERROR_OPTION = 'wpecir_pro_alert_last_failure';
	const SEND_LOCK_OPTION  = 'wpecir_pro_alert_send_lock';
	const LOCK_SECONDS      = 900;
	const QUEUE_RETRIES     = 30;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'wpecir_change_event_recorded', array( __CLASS__, 'queue' ), 10, 3 );
		add_action( self::HOOK, array( __CLASS__, 'send_digest' ) );
	}

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'enabled'      => ! empty( $saved['enabled'] ),
			'recipients'   => isset( $saved['recipients'] ) ? (string) $saved['recipients'] : (string) get_option( 'admin_email' ),
			'last_failure' => (string) get_option( self::LAST_ERROR_OPTION, '' ),
		);
	}

	/**
	 * Validate and store settings.
	 *
	 * @param mixed $enabled    Whether alerts are on.
	 * @param mixed $recipients Comma-separated email addresses.
	 * @return array|\WP_Error
	 */
	public static function update( $enabled, $recipients ) {
		$list = self::parse_recipients( (string) $recipients );
		if ( ! is_bool( $enabled ) ) {
			return new \WP_Error( 'wpecir_pro_invalid', __( 'Choose whether alerts are on or off.', 'wpessential-checkout-impact-regression-pro-for-woocommerce' ), array( 'status' => 400 ) );
		}
		if ( $enabled && ! $list ) {
			return new \WP_Error( 'wpecir_pro_invalid_recipients', __( 'Enter at least one valid email address.', 'wpessential-checkout-impact-regression-pro-for-woocommerce' ), array( 'status' => 400 ) );
		}
		update_option( self::OPTION, array( 'enabled' => $enabled, 'recipients' => implode( ', ', $list ) ), false );
		return self::settings();
	}

	/**
	 * Parse up to 5 valid addresses.
	 *
	 * @param string $raw Raw input.
	 * @return string[]
	 */
	public static function parse_recipients( $raw ) {
		$out = array();
		foreach ( explode( ',', $raw ) as $address ) {
			$address = sanitize_email( trim( $address ) );
			if ( $address && is_email( $address ) && ! in_array( $address, $out, true ) ) {
				$out[] = $address;
			}
		}
		return array_slice( $out, 0, 5 );
	}

	/**
	 * Add an event to the digest queue and schedule a send.
	 *
	 * @param int    $event_id    Event identifier.
	 * @param string $source      Event source.
	 * @param string $object_type Object type.
	 * @return void
	 */
	public static function queue( $event_id, $source, $object_type ) {
		$settings = self::settings();
		if ( ! $settings['enabled'] ) {
			return;
		}
		$stored = self::mutate_queue(
			static function ( $queue ) use ( $event_id ) {
				$queue[] = absint( $event_id );
				return array_slice( array_values( array_unique( $queue ) ), -self::MAX_QUEUE );
			}
		);
		if ( ! $stored ) {
			self::storage_failure();
			return;
		}
		self::schedule_if_needed( self::DELAY );
	}

	/**
	 * Send one digest for all queued events.
	 *
	 * @return bool Whether an email was sent.
	 */
	public static function send_digest() {
		$token = self::acquire_send_lock();
		if ( ! $token ) {
			return false;
		}
		try {
			return self::send_locked();
		} finally {
			self::release_send_lock( $token );
		}
	}

	/**
	 * Send a snapshot while holding the per-site sender lease.
	 *
	 * @return bool
	 */
	private static function send_locked() {
		$raw = self::read_option_row( self::QUEUE_OPTION );
		if ( false === $raw ) {
			self::storage_failure();
			return self::retry_later();
		}
		$queue    = self::decode_queue( $raw );
		$settings = self::settings();
		$to       = self::parse_recipients( $settings['recipients'] );
		if ( ! $settings['enabled'] || ! $to || ! $queue ) {
			self::forget( $queue );
			return false;
		}

		global $wpdb;
		$ids    = $queue;
		$table  = $wpdb->prefix . 'wpecir_change_events';
		$holder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders are generated from the ID count.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT source, object_type, object_label, occurred_at FROM %i WHERE id IN ({$holder}) ORDER BY occurred_at ASC", array_merge( array( $table ), $ids ) ), ARRAY_A );
		if ( null === $rows || '' !== $wpdb->last_error ) {
			return self::retry_later();
		}
		if ( ! $rows ) {
			self::forget( $ids );
			return false;
		}

		$lines = array();
		foreach ( $rows as $row ) {
			$what    = '' !== $row['object_label'] ? $row['object_label'] : $row['object_type'];
			$kind    = 'plugin_update' === $row['source'] ? __( 'Plugin updated', 'wpessential-checkout-impact-regression-pro-for-woocommerce' ) : __( 'Setting changed', 'wpessential-checkout-impact-regression-pro-for-woocommerce' );
			$lines[] = '- ' . $kind . ': ' . $what . ' (' . $row['occurred_at'] . ' UTC)';
		}
		$link = admin_url( 'admin.php?page=wpessential-checkout-impact#compare' );
		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] Checkout-related change recorded — compare your scenarios', 'wpessential-checkout-impact-regression-pro-for-woocommerce' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$body    = __( 'Checkout Impact recorded these changes on your store:', 'wpessential-checkout-impact-regression-pro-for-woocommerce' ) . "\n\n" . implode( "\n", $lines ) . "\n\n" .
			__( 'Open checkout for your saved scenarios, record what you see and compare it with the approved baseline:', 'wpessential-checkout-impact-regression-pro-for-woocommerce' ) . "\n" . $link . "\n";

		if ( ! wp_mail( $to, $subject, $body ) ) {
			return self::retry_later();
		}
		self::forget( $ids );
		return true;
	}

	/**
	 * Remove delivered IDs, keeping any that were queued while sending.
	 *
	 * @param int[] $sent Delivered event IDs.
	 * @return void
	 */
	private static function forget( $sent ) {
		$remaining = array();
		$stored = self::mutate_queue(
			static function ( $queue ) use ( $sent, &$remaining ) {
				$remaining = array_values( array_diff( $queue, $sent ) );
				return $remaining;
			}
		);
		if ( ! $stored ) {
			self::storage_failure();
			// The mail may already have been accepted; never claim exactly-once delivery.
			self::schedule_if_needed( self::DELAY );
			return;
		}
		delete_option( self::ATTEMPTS_OPTION );
		delete_option( self::LAST_ERROR_OPTION );
		if ( $remaining ) {
			self::schedule_if_needed( self::DELAY );
		}
	}

	/**
	 * Uncached option row: null means absent, false means database error.
	 * Per-site options table, with no read-through object-cache snapshot.
	 *
	 * @param string $name Internal option name.
	 * @return string|null|false
	 */
	private static function read_option_row( $name ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- concurrency requires a fresh row.
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $name ) );
		return '' === $wpdb->last_error ? $value : false;
	}

	/**
	 * Decode stored queue IDs; retain the existing option format.
	 *
	 * @param string|null $raw Serialized option.
	 * @return int[]
	 */
	private static function decode_queue( $raw ) {
		$value = null === $raw ? array() : maybe_unserialize( $raw );
		return is_array( $value ) ? array_values( array_unique( array_map( 'absint', $value ) ) ) : array();
	}

	/**
	 * Atomically replace an existing option value or insert an absent row.
	 * The options table has a unique option_name key. A stale snapshot cannot
	 * overwrite a competing writer; no long transaction is held during mail.
	 *
	 * @param string      $name Internal option name.
	 * @param string|null $old  Observed database value.
	 * @param string      $new  Replacement.
	 * @return bool
	 */
	private static function compare_option( $name, $old, $new ) {
		global $wpdb;
		if ( null === $old ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic insertion uses the unique option_name key.
			$changed = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $wpdb->options, $name, $new ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- conditional replacement avoids lost updates.
			$changed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s AND option_value = %s', $wpdb->options, $new, $name, $old ) );
		}
		if ( 1 !== $changed ) {
			return false;
		}
		self::clear_option_cache( $name );
		return true;
	}

	/**
	 * Invalidate standard WordPress options caches after direct writes.
	 *
	 * @param string $name Internal option name.
	 * @return void
	 */
	private static function clear_option_cache( $name ) {
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * Apply a bounded optimistic update to the current queue.
	 *
	 * @param callable $change Queue transformation.
	 * @return bool
	 */
	private static function mutate_queue( $change ) {
		for ( $attempt = 0; $attempt < self::QUEUE_RETRIES; ++$attempt ) {
			$old = self::read_option_row( self::QUEUE_OPTION );
			if ( false === $old ) {
				return false;
			}
			$new = maybe_serialize( $change( self::decode_queue( $old ) ) );
			if ( $old === $new || self::compare_option( self::QUEUE_OPTION, $old, $new ) ) {
				return true;
			}
			usleep( 10000 );
		}
		return false;
	}

	/**
	 * Acquire a bounded per-site sender lease; never wait for another mail call.
	 *
	 * @return string|false
	 */
	private static function acquire_send_lock() {
		$old = self::read_option_row( self::SEND_LOCK_OPTION );
		if ( false === $old ) {
			self::storage_failure();
			return false;
		}
		if ( null !== $old && (int) $old > time() ) {
			return false;
		}
		$token = ( time() + self::LOCK_SECONDS ) . ':' . wp_generate_uuid4();
		return self::compare_option( self::SEND_LOCK_OPTION, $old, $token ) ? $token : false;
	}

	/**
	 * Release only our own lease, never a successor's lease.
	 *
	 * @param string $token Lease token.
	 * @return void
	 */
	private static function release_send_lock( $token ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- owner-checked lease release.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND option_value = %s', $wpdb->options, self::SEND_LOCK_OPTION, $token ) );
		self::clear_option_cache( self::SEND_LOCK_OPTION );
	}

	/**
	 * Refresh the cron option before checking for a scheduled digest.
	 *
	 * @param int $delay Delay in seconds.
	 * @return void
	 */
	private static function schedule_if_needed( $delay ) {
		self::clear_option_cache( 'cron' );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + $delay, self::HOOK );
		}
	}

	/**
	 * Expose a database/contended queue write failure without leaking store data.
	 *
	 * @return void
	 */
	private static function storage_failure() {
		update_option( self::LAST_ERROR_OPTION, gmdate( 'Y-m-d H:i:s' ), false );
		do_action( 'wpecir_pro_alert_storage_failed' );
	}

	/**
	 * Keep the queue and try again later, up to MAX_ATTEMPTS times.
	 *
	 * @return bool Always false (nothing was sent).
	 */
	private static function retry_later() {
		$attempts = absint( get_option( self::ATTEMPTS_OPTION, 0 ) ) + 1;
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			delete_option( self::ATTEMPTS_OPTION );
			update_option( self::LAST_ERROR_OPTION, gmdate( 'Y-m-d H:i:s' ), false );
			return false;
		}
		update_option( self::ATTEMPTS_OPTION, $attempts, false );
		self::schedule_if_needed( self::DELAY * $attempts );
		return false;
	}

	/**
	 * Clear scheduled digests on deactivation.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
