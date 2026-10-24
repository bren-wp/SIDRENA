<?php
/** Read-only REST catalog discovery and feed endpoints. */
defined( 'ABSPATH' ) || exit;

final class Brendigo_Sidrena_Cijena_Public_API {
	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	public function register() {
		register_rest_route( 'brendigo-sidrena/v1', '/cjenici', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'index' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( 'brendigo-sidrena/v1', '/arhiva', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'archive_index' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'kind' => array( 'type' => 'string', 'enum' => array( 'proizvodi', 'usluge' ) ),
				'outlet' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );
		register_rest_route( 'brendigo-sidrena/v1', '/cjenici/(?P<kind>proizvodi|usluge)/(?P<format>xml|csv)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'feed' ),
			'permission_callback' => '__return_true',
			'args'                => array( 'oznaka' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) ),
		) );
	}

	public function index() {
		$export = Brendigo_Sidrena_Cijena_Export::instance();
		$meta = $export->get_public_meta();
		$out = array(
			'api_version'  => 1,
			'plugin_version' => BRENDIGO_SIDRENA_CIJENA_VERSION,
			'legal' => Brendigo_Sidrena_Cijena_Legal::public_contract(),
			'published_at' => isset( $meta['generated'] ) ? gmdate( 'c', (int) $meta['generated'] ) : null,
			'cjenik'       => esc_url_raw( $export->get_cjenik_url() ),
			'archive'      => esc_url_raw( $export->get_archive_url() ),
			'type_archive' => esc_url_raw( rest_url( 'brendigo-sidrena/v1/arhiva' ) ),
			'combined'     => esc_url_raw( $export->get_public_url() ),
			'feeds'        => array(),
			'outlets'      => array(),
		);
		foreach ( array( 'proizvodi', 'usluge' ) as $kind ) {
			$out['feeds'][ $kind ] = array();
			foreach ( array( 'xml', 'csv' ) as $ext ) {
				$out['feeds'][ $kind ][ $ext ] = esc_url_raw( rest_url( 'brendigo-sidrena/v1/cjenici/' . $kind . '/' . $ext ) );
			}
		}
		if ( Brendigo_Sidrena_Cijena_Settings::instance()->is_multi_poslovnica() ) {
			foreach ( Brendigo_Sidrena_Cijena_Poslovnice::instance()->all() as $outlet ) {
				$code = isset( $outlet['oznaka'] ) ? (string) $outlet['oznaka'] : '';
				if ( '' === $code ) {
					continue;
				}
				$entry = array( 'name' => isset( $outlet['naziv'] ) ? $outlet['naziv'] : $code, 'code' => $code, 'feeds' => array() );
				foreach ( array( 'proizvodi', 'usluge' ) as $kind ) {
					foreach ( array( 'xml', 'csv' ) as $ext ) {
						$entry['feeds'][ $kind ][ $ext ] = esc_url_raw( add_query_arg( 'oznaka', $code, rest_url( 'brendigo-sidrena/v1/cjenici/' . $kind . '/' . $ext ) ) );
					}
				}
				$out['outlets'][] = $entry;
			}
		}
		return rest_ensure_response( $out );
	}

	public function archive_index( $request ) {
		$kind = $request->get_param( 'kind' );
		$outlet = $request->get_param( 'outlet' );
		return rest_ensure_response( Brendigo_Sidrena_Cijena_Archive::instance()->separate_archive_files( $kind ? $kind : '', $outlet ? sanitize_key( $outlet ) : '' ) );
	}

	public function feed( $request ) {
		$kind = $request->get_param( 'kind' );
		$ext = $request->get_param( 'format' );
		$oznaka = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $request->get_param( 'oznaka' ) );
		if ( '' !== $oznaka && ! Brendigo_Sidrena_Cijena_Poslovnice::instance()->get_by_oznaka( $oznaka ) ) {
			return new WP_Error( 'sidrena_unknown_outlet', __( 'Nepoznata poslovnica.', 'brendigo-sidrena-cijena' ), array( 'status' => 404 ) );
		}
		$files = Brendigo_Sidrena_Cijena_Feed_Files::instance();
		$path = $files->get_type_public_path( $kind, $ext, $oznaka );
		if ( ! $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'sidrena_feed_missing', __( 'Cjenik još nije objavljen.', 'brendigo-sidrena-cijena' ), array( 'status' => 404 ) );
		}
		// Read only already-published files. Never regenerate on a public REST hit.
		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', $files->get_type_public_url( $kind, $ext, $oznaka ) );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}
}
