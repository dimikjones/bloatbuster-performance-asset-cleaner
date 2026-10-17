<?php
/**
 * Guess which plugin or theme owns an option.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Matches option names to WordPress core, installed plugins and themes.
 */
final class Autoload_Owners {

	/**
	 * Plugin prefixes cache, longest prefix first.
	 *
	 * @var array|null
	 */
	private static $prefixes = null;

	/**
	 * Get the owner of an option.
	 *
	 * @param string $name - Option name.
	 *
	 * @return array - Owner label and status: core, active, inactive or unknown.
	 *
	 * @since 1.0.0
	 */
	public static function get_owner( $name ) {
		if ( in_array( $name, Autoload_Options::get_core_options(), true ) ) {
			return self::owner( \__( 'WordPress', 'bloatbuster-performance-asset-cleaner' ), 'core' );
		}

		$name = self::strip_transient_prefix( $name );

		if ( 0 === strpos( $name, 'theme_mods_' ) ) {
			return self::get_theme_owner( substr( $name, strlen( 'theme_mods_' ) ) );
		}

		foreach ( self::get_prefixes() as $prefix => $plugin ) {
			if ( self::has_prefix( $name, $prefix ) ) {
				return self::owner( $plugin['name'], $plugin['status'] );
			}
		}

		if ( 0 === strpos( $name, 'widget_' ) ) {
			return self::owner( \__( 'Widgets', 'bloatbuster-performance-asset-cleaner' ), 'core' );
		}

		return self::owner( \__( 'Unknown', 'bloatbuster-performance-asset-cleaner' ), 'unknown' );
	}

	/**
	 * Get the owner of a theme_mods_ option.
	 *
	 * @param string $slug - Theme slug.
	 *
	 * @return array - Owner label and status.
	 *
	 * @since 1.0.0
	 */
	public static function get_theme_owner( $slug ) {
		if ( \get_stylesheet() === $slug || \get_template() === $slug ) {
			/* translators: %s: theme name. */
			return self::owner( sprintf( \__( 'Active theme: %s', 'bloatbuster-performance-asset-cleaner' ), \wp_get_theme( $slug )->get( 'Name' ) ), 'active' );
		}

		$theme = \wp_get_theme( $slug );

		if ( $theme->exists() ) {
			/* translators: %s: theme name. */
			return self::owner( sprintf( \__( 'Inactive theme: %s', 'bloatbuster-performance-asset-cleaner' ), $theme->get( 'Name' ) ), 'inactive' );
		}

		/* translators: %s: theme slug. */
		return self::owner( sprintf( \__( 'Deleted theme: %s', 'bloatbuster-performance-asset-cleaner' ), $slug ), 'inactive' );
	}

	/**
	 * Get option prefixes of installed plugins, the active theme and known plugins.
	 *
	 * @return array - Prefix => owner name and status, longest prefix first.
	 *
	 * @since 1.0.0
	 */
	public static function get_prefixes() {
		if ( null !== self::$prefixes ) {
			return self::$prefixes;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins  = \get_plugins();
		$prefixes = array();

		foreach ( $plugins as $file => $plugin ) {
			$owner = array(
				'name'   => $plugin['Name'],
				'status' => \is_plugin_active( $file ) ? 'active' : 'inactive',
			);

			$slug = false === strpos( $file, '/' ) ? basename( $file, '.php' ) : dirname( $file );

			foreach ( array( $slug, $plugin['TextDomain'] ) as $candidate ) {
				$candidate = strtolower( (string) $candidate );

				if ( strlen( $candidate ) < 3 ) {
					continue;
				}

				$prefixes[ $candidate ]                          = $owner;
				$prefixes[ str_replace( '-', '_', $candidate ) ] = $owner;
			}
		}

		foreach ( self::get_known_prefixes() as $prefix => $plugin_file ) {
			// Prefixes from installed plugin headers win over the known list.
			if ( isset( $prefixes[ $prefix ] ) ) {
				continue;
			}

			if ( isset( $plugins[ $plugin_file ] ) ) {
				$prefixes[ $prefix ] = array(
					'name'   => $plugins[ $plugin_file ]['Name'],
					'status' => \is_plugin_active( $plugin_file ) ? 'active' : 'inactive',
				);
			} else {
				$prefixes[ $prefix ] = array(
					/* translators: %s: plugin folder name. */
					'name'   => sprintf( \__( 'Not installed: %s', 'bloatbuster-performance-asset-cleaner' ), dirname( $plugin_file ) ),
					'status' => 'inactive',
				);
			}
		}

		$theme_slug = strtolower( \get_stylesheet() );

		if ( strlen( $theme_slug ) >= 3 ) {
			$theme_owner = array(
				/* translators: %s: theme name. */
				'name'   => sprintf( \__( 'Active theme: %s', 'bloatbuster-performance-asset-cleaner' ), \wp_get_theme()->get( 'Name' ) ),
				'status' => 'active',
			);

			$prefixes[ $theme_slug ]                          = $theme_owner;
			$prefixes[ str_replace( '-', '_', $theme_slug ) ] = $theme_owner;
		}

		uksort( $prefixes, array( __CLASS__, 'compare_prefix_length' ) );

		self::$prefixes = $prefixes;

		return self::$prefixes;
	}

	/**
	 * Get known option prefixes of plugins whose options don't start with their slug.
	 *
	 * @return array - Prefix => plugin file.
	 *
	 * @since 1.0.0
	 */
	public static function get_known_prefixes() {
		$known = array(
			'wpseo'           => 'wordpress-seo/wp-seo.php',
			'yoast'           => 'wordpress-seo/wp-seo.php',
			'rank_math'       => 'seo-by-rank-math/rank-math.php',
			'aioseo'          => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
			'wpcf7'           => 'contact-form-7/wp-contact-form-7.php',
			'woocommerce'     => 'woocommerce/woocommerce.php',
			'wc'              => 'woocommerce/woocommerce.php',
			'elementor'       => 'elementor/elementor.php',
			'_elementor'      => 'elementor/elementor.php',
			'litespeed'       => 'litespeed-cache/litespeed-cache.php',
			'wp_rocket'       => 'wp-rocket/wp-rocket.php',
			'rocket'          => 'wp-rocket/wp-rocket.php',
			'w3tc'            => 'w3-total-cache/w3-total-cache.php',
			'updraft'         => 'updraftplus/updraftplus.php',
			'updraftplus'     => 'updraftplus/updraftplus.php',
			'gform'           => 'gravityforms/gravityforms.php',
			'rg_gforms'       => 'gravityforms/gravityforms.php',
			'acf'             => 'advanced-custom-fields/acf.php',
			'monsterinsights' => 'google-analytics-for-wordpress/googleanalytics.php',
			'wordfence'       => 'wordfence/wordfence.php',
			'wf'              => 'wordfence/wordfence.php',
			'jetpack'         => 'jetpack/jetpack.php',
			'akismet'         => 'akismet/akismet.php',
			'wpforms'         => 'wpforms-lite/wpforms.php',
			'sbi'             => 'instagram-feed/instagram-feed.php',
			'mc4wp'           => 'mailchimp-for-wp/mailchimp-for-wp.php',
			'redirection'     => 'redirection/redirection.php',
			'wp_mail_smtp'    => 'wp-mail-smtp/wp_mail_smtp.php',
			'ai1wm'           => 'all-in-one-wp-migration/all-in-one-wp-migration.php',
			'smush'           => 'wp-smushit/wp-smush.php',
			'wp-smush'        => 'wp-smushit/wp-smush.php',
			'autoptimize'     => 'autoptimize/autoptimize.php',
			'wpsc'            => 'wp-super-cache/wp-cache.php',
			'ossdl'           => 'wp-super-cache/wp-cache.php',
		);

		/**
		 * Filter the known option prefixes of plugins.
		 *
		 * @param array $known - Prefix => plugin file, e.g. 'wpseo' => 'wordpress-seo/wp-seo.php'.
		 *
		 * @since 1.0.0
		 */
		return (array) \apply_filters( 'bloatbuster_autoload_known_prefixes', $known );
	}

	/**
	 * Check if an option name starts with a prefix, followed by a separator or nothing.
	 *
	 * @param string $name   - Option name.
	 * @param string $prefix - Prefix.
	 *
	 * @return bool - True if the name starts with the prefix.
	 *
	 * @since 1.0.0
	 */
	public static function has_prefix( $name, $prefix ) {
		$name = strtolower( $name );

		if ( 0 !== strpos( $name, $prefix ) ) {
			return false;
		}

		if ( strlen( $name ) === strlen( $prefix ) ) {
			return true;
		}

		return in_array( $name[ strlen( $prefix ) ], array( '_', '-', '.', ':' ), true );
	}

	/**
	 * Remove the transient prefix from an option name.
	 *
	 * @param string $name - Option name.
	 *
	 * @return string - Name without the transient prefix.
	 *
	 * @since 1.0.0
	 */
	public static function strip_transient_prefix( $name ) {
		$prefixes = array( '_site_transient_timeout_', '_site_transient_', '_transient_timeout_', '_transient_' );

		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return substr( $name, strlen( $prefix ) );
			}
		}

		return $name;
	}

	/**
	 * Sort prefixes by length, longest first, for uksort().
	 *
	 * @param string $a - First prefix.
	 * @param string $b - Second prefix.
	 *
	 * @return int - Comparison result.
	 *
	 * @since 1.0.0
	 */
	public static function compare_prefix_length( $a, $b ) {
		return strlen( (string) $b ) - strlen( (string) $a );
	}

	/**
	 * Build an owner array.
	 *
	 * @param string $label  - Owner label.
	 * @param string $status - Status: core, active, inactive or unknown.
	 *
	 * @return array - Owner label and status.
	 *
	 * @since 1.0.0
	 */
	public static function owner( $label, $status ) {
		return array(
			'label'  => $label,
			'status' => $status,
		);
	}
}
