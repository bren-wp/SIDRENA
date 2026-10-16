<?php
/** Publication failure notifications and de-duplication. */
defined( 'ABSPATH' ) || exit;

final class Brendigo_Sidrena_Cijena_Notifications {
	const STATE_OPTION = 'brendigo_sidrena_publication_alert_state';
	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'brendigo_sidrena_publication_result', array( $this, 'publication_result' ), 10, 3 );
		add_action( 'brendigo_sidrena_publication_missed', array( $this, 'missed_publication' ) );
	}

	public function publication_result( $status, $source, $error_code ) {
		if ( 'failed' === $status ) {
			$this->notify( 'publication-' . sanitize_key( $error_code ), sprintf(
				/* translators: 1: publication source identifier, 2: error code. */
				__( 'Objava javnog cjenika nije uspjela. Izvor: %1$s. Kod pogreške: %2$s.', 'brendigo-sidrena-cijena' ),
				sanitize_key( $source ),
				sanitize_key( $error_code )
			) );
		} else {
			$state = get_option( self::STATE_OPTION, array() );
			$state = is_array( $state ) ? $state : array();
			$state['last_success'] = time();
			update_option( self::STATE_OPTION, $state, false );
		}
	}

	public function missed_publication() {
		$this->notify( 'missed-daily', __( 'Zakazana dnevna objava cjenika nije dovršena unutar zadanog vremena. Provjerite zakazivanje zadataka i datoteke cjenika.', 'brendigo-sidrena-cijena' ) );
	}

	private function notify( $reason, $message ) {
		$settings = Brendigo_Sidrena_Cijena_Settings::instance();
		if ( 'yes' !== $settings->get( 'email_alerts', 'yes' ) ) {
			return;
		}
		$recipient = sanitize_email( $settings->get( 'alert_email', '' ) );
		if ( ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email', '' );
		}
		if ( ! is_email( $recipient ) ) {
			return;
		}
		$reason = sanitize_key( $reason );
		$state = get_option( self::STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		$last = isset( $state['alerts'][ $reason ] ) ? (int) $state['alerts'][ $reason ] : 0;
		// De-duplicate repeated faults, including failed mail transports.
		if ( time() - $last < 12 * HOUR_IN_SECONDS ) {
			return;
		}
		if ( ! isset( $state['alerts'] ) || ! is_array( $state['alerts'] ) ) {
			$state['alerts'] = array();
		}
		$state['alerts'][ $reason ] = time();
		if ( count( $state['alerts'] ) > 30 ) {
			asort( $state['alerts'] );
			$state['alerts'] = array_slice( $state['alerts'], -30, null, true );
		}
		update_option( self::STATE_OPTION, $state, false );
		$subject = sprintf(
			/* translators: %s: website host name. */
			__( '[SIDRENA] Problem s cjenikom – %s', 'brendigo-sidrena-cijena' ),
			wp_parse_url( home_url(), PHP_URL_HOST )
		);
		$body = $message . "\n\n" . __( 'Web:', 'brendigo-sidrena-cijena' ) . ' ' . home_url( '/' ) . "\n"
			. __( 'Administracija:', 'brendigo-sidrena-cijena' ) . ' ' . admin_url( 'admin.php?page=brendigo-sidrena-cijena&tab=javni-xml' ) . "\n";
		$sent = wp_mail( $recipient, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		$state['last_delivery'] = array( 'time' => time(), 'sent' => (bool) $sent, 'reason' => $reason );
		update_option( self::STATE_OPTION, $state, false );
	}
}
