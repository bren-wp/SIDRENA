<?php
/**
 * Prefix and tooltip translations (WPML, Polylang, TranslatePress, Loco).
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Language list, storefront strings, and the default-language catalog scope.
 */
class Brendigo_Sidrena_Cijena_I18n {

	const CONTEXT = 'sidrena-cijena';

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Storefront prefix and tooltip, keyed by "key|language".
	 *
	 * @var array<string, string>
	 */
	private $front_cache = array();

	/**
	 * Nested default-language switches. Only the outermost call changes language.
	 *
	 * @var int
	 */
	private $language_depth = 0;

	/**
	 * Whether this request switched WPML to the default language.
	 *
	 * @var bool
	 */
	private $wpml_switched = false;

	/**
	 * WPML language to restore, including "all". Null when none was set.
	 *
	 * @var string|null
	 */
	private $wpml_previous = null;

	/**
	 * Whether this request replaced Polylang's current language.
	 *
	 * @var bool
	 */
	private $pll_switched = false;

	/**
	 * Polylang current language object to restore. False when none was set.
	 *
	 * @var object|false|null
	 */
	private $pll_previous = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register strings with WPML and Polylang.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_strings' ), 20 );
		add_action( 'wp', array( $this, 'warm_cache' ), 99 );
	}

	/**
	 * Resolve prefix and tooltip once, while the main query is still the page.
	 *
	 * Product loops run later. They must not call wpml_active_languages: that
	 * filter builds the language switcher for the current post.
	 */
	public function warm_cache() {
		if ( is_admin() ) {
			return;
		}

		$this->get_front_string( 'prefix' );
		$this->get_front_string( 'tooltip' );
	}

	/**
	 * Push default-language prefix and tooltip into string translation UIs.
	 */
	public function register_strings() {
		$settings = Brendigo_Sidrena_Cijena_Settings::instance();
		$prefix   = (string) $settings->get( 'prefix' );
		$tooltip  = (string) $settings->get( 'tooltip' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
		do_action( 'wpml_register_single_string', self::CONTEXT, 'prefix', $prefix );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
		do_action( 'wpml_register_single_string', self::CONTEXT, 'tooltip', $tooltip );

		if ( function_exists( 'pll_register_string' ) ) {
			pll_register_string( 'prefix', $prefix, 'Sidrena cijena', false );
			pll_register_string( 'tooltip', $tooltip, 'Sidrena cijena', true );
		}
	}



	/**
	 * Default (source) language code.
	 *
	 * @return string
	 */
	public function get_default_language() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
		$default = apply_filters( 'wpml_default_language', null );
		if ( is_string( $default ) && '' !== $default ) {
			return $default;
		}

		if ( function_exists( 'pll_default_language' ) ) {
			$pll = pll_default_language();
			if ( is_string( $pll ) && '' !== $pll ) {
				return $pll;
			}
		}

		$trp = $this->get_translatepress_default_language();
		if ( '' !== $trp ) {
			return $trp;
		}

		// The shop's default language must not change with a user's admin locale.
		return $this->normalize_code( get_locale() );
	}

	/**
	 * Current request language.
	 *
	 * @return string
	 */
	public function get_current_language() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
		$lang = apply_filters( 'wpml_current_language', null );
		if ( is_string( $lang ) && '' !== $lang && 'all' !== $lang ) {
			return $lang;
		}

		if ( function_exists( 'pll_current_language' ) ) {
			$pll = pll_current_language();
			if ( is_string( $pll ) && '' !== $pll ) {
				return $pll;
			}
		}

		if ( ! empty( $GLOBALS['TRP_LANGUAGE'] ) && is_string( $GLOBALS['TRP_LANGUAGE'] ) ) {
			return $this->normalize_code( $GLOBALS['TRP_LANGUAGE'] );
		}

		// Respect a WordPress user's preferred admin language or a switched locale.
		// In the absence of a multilingual plugin, get_default_language() describes
		// the site's source language, not necessarily the current request locale.
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		return $this->normalize_code( $locale );
	}

	/**
	 * Whether WPML or Polylang stores each translation as its own post.
	 *
	 * TranslatePress does not, so the catalog query must stay unchanged.
	 *
	 * @return bool
	 */
	public function uses_post_translations() {
		return has_filter( 'wpml_object_id' ) || function_exists( 'pll_get_post' );
	}

	/**
	 * Run a catalog build in the default language, then restore the previous one.
	 *
	 * Reentrant: a nested call keeps the language the outer call already set.
	 * Does nothing when the shop does not duplicate posts per language.
	 *
	 * @param callable $callback Catalog work.
	 * @return mixed Callback result.
	 */
	public function run_in_default_language( $callback ) {
		if ( ! $this->uses_post_translations() ) {
			return $callback();
		}

		$this->language_depth++;
		if ( 1 === $this->language_depth ) {
			$this->push_default_language();
		}

		try {
			return $callback();
		} finally {
			if ( 1 === $this->language_depth ) {
				$this->pop_default_language();
			}
			--$this->language_depth;
		}
	}

	/**
	 * Default-language product or variation for a post id.
	 *
	 * Returns 0 when that source post does not exist. Ids that are not
	 * products are returned unchanged.
	 *
	 * @param int $post_id Product, variation, or other id.
	 * @return int
	 */
	public function source_product_id( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! $this->uses_post_translations() ) {
			return $post_id > 0 ? $post_id : 0;
		}

		$type = get_post_type( $post_id );
		if ( 'product' !== $type && 'product_variation' !== $type ) {
			return $post_id;
		}

		$lang = $this->get_default_language();
		if ( '' === $lang ) {
			return $post_id;
		}

		if ( has_filter( 'wpml_object_id' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
			$mapped = apply_filters( 'wpml_object_id', $post_id, $type, false, $lang );
			return $mapped ? (int) $mapped : 0;
		}

		if ( function_exists( 'pll_get_post' ) ) {
			$mapped = pll_get_post( $post_id, $lang );
			return $mapped ? (int) $mapped : 0;
		}

		return $post_id;
	}

	/**
	 * Whether this product post is the default-language original.
	 *
	 * @param int $post_id Product or variation id.
	 * @return bool
	 */
	public function is_source_product( $post_id ) {
		if ( ! $this->uses_post_translations() ) {
			return true;
		}

		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}

		return $this->source_product_id( $post_id ) === $post_id;
	}

	/**
	 * Switch WPML and Polylang to the default language for one catalog build.
	 */
	private function push_default_language() {
		$default = $this->get_default_language();
		if ( '' === $default ) {
			return;
		}

		if ( has_filter( 'wpml_current_language' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
			$current             = apply_filters( 'wpml_current_language', null );
			$this->wpml_previous = ( is_string( $current ) && '' !== $current ) ? $current : null;
			$this->wpml_switched = true;
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
			do_action( 'wpml_switch_language', $default );
		}

		if ( ! function_exists( 'PLL' ) ) {
			return;
		}

		$pll = PLL();
		if ( ! is_object( $pll ) || ! isset( $pll->model ) || ! is_object( $pll->model ) || ! method_exists( $pll->model, 'get_language' ) ) {
			return;
		}

		$language = $pll->model->get_language( $default );
		if ( ! $language ) {
			return;
		}

		$this->pll_previous = isset( $pll->curlang ) ? $pll->curlang : false;
		$this->pll_switched = true;
		$pll->curlang       = $language;
	}

	/**
	 * Restore the language that was current before the catalog build.
	 */
	private function pop_default_language() {
		if ( $this->wpml_switched ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
			do_action( 'wpml_switch_language', $this->wpml_previous );
			$this->wpml_switched = false;
			$this->wpml_previous = null;
		}

		if ( ! $this->pll_switched || ! function_exists( 'PLL' ) ) {
			$this->pll_switched = false;
			$this->pll_previous = null;
			return;
		}

		$pll = PLL();
		if ( is_object( $pll ) ) {
			$pll->curlang = $this->pll_previous;
		}

		$this->pll_switched = false;
		$this->pll_previous = null;
	}

	/**
	 * Prefix or tooltip for the current storefront language.
	 *
	 * @param string $key prefix|tooltip.
	 * @return string
	 */
	public function get_front_string( $key ) {
		$lang      = $this->get_current_language();
		$cache_key = $key . '|' . $lang;

		if ( isset( $this->front_cache[ $cache_key ] ) ) {
			return $this->front_cache[ $cache_key ];
		}

		$result = $this->resolve_front_string( $key, $lang );

		if ( did_action( 'wp' ) ) {
			$this->front_cache[ $cache_key ] = $result;
		}

		return $result;
	}

	/**
	 * Prefix or tooltip for one language code.
	 *
	 * @param string $key  prefix|tooltip.
	 * @param string $lang Language code used for the cache key.
	 * @return string
	 */
	private function resolve_front_string( $key, $lang ) {
		$settings     = Brendigo_Sidrena_Cijena_Settings::instance();
		$base         = (string) $settings->get( $key );
		$default_lang = $this->get_default_language();

		if ( $lang !== $default_lang ) {
			$stored = $settings->get_i18n_value( $key, $lang );
			if ( '' !== $stored ) {
				return $stored;
			}

			$normalized = $this->normalize_code( $lang );
			if ( $normalized !== $lang ) {
				$stored = $settings->get_i18n_value( $key, $normalized );
				if ( '' !== $stored ) {
					return $stored;
				}
			}
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
		$translated = apply_filters( 'wpml_translate_single_string', $base, self::CONTEXT, $key );

		if ( function_exists( 'pll__' ) ) {
			$pll = pll__( $base );
			if ( is_string( $pll ) && '' !== $pll ) {
				$translated = $pll;
			}
		} elseif ( function_exists( 'pll_translate_string' ) && is_string( $lang ) && '' !== $lang ) {
			$pll = pll_translate_string( $base, $lang );
			if ( is_string( $pll ) && '' !== $pll ) {
				$translated = $pll;
			}
		}

		if ( $translated === $base ) {
			$translated = $this->apply_gettext_default( $key, $base );
		}

		return apply_filters( 'sidrena_cijena_translate_string', $translated, $key, $lang );
	}

	/**
	 * Loco / core gettext when the stored value is still the plugin default.
	 *
	 * @param string $key   prefix|tooltip.
	 * @param string $value Stored value.
	 * @return string
	 */
	private function apply_gettext_default( $key, $value ) {
		if ( 'prefix' === $key && Brendigo_Sidrena_Cijena_Settings::DEFAULT_PREFIX === $value ) {
			return __( 'Sidrena cijena', 'brendigo-sidrena-cijena' );
		}

		if ( 'tooltip' === $key && Brendigo_Sidrena_Cijena_Settings::DEFAULT_TOOLTIP === $value ) {
			return __( 'Sidrena cijena je referentna maloprodajna cijena koja je za proizvod ili uslugu vrijedila na navedeni datum.', 'brendigo-sidrena-cijena' );
		}

		return $value;
	}



	/**
	 * TranslatePress source language. Reads an option, not the current post.
	 *
	 * @return string
	 */
	private function get_translatepress_default_language() {
		$trp = get_option( 'trp_settings', array() );
		if ( ! is_array( $trp ) || empty( $trp['default-language'] ) || ! is_string( $trp['default-language'] ) ) {
			return '';
		}

		return $this->normalize_code( $trp['default-language'] );
	}


	/**
	 * Short language code (hr_HR → hr) when useful as a fallback key.
	 *
	 * @param string $code Locale or slug.
	 * @return string
	 */
	public function normalize_code( $code ) {
		$code = strtolower( str_replace( '-', '_', trim( (string) $code ) ) );
		if ( strlen( $code ) >= 2 ) {
			return substr( $code, 0, 2 );
		}

		return $code;
	}
}
