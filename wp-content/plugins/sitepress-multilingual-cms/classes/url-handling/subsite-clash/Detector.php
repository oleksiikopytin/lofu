<?php

namespace WPML\UrlHandling\SubsiteClash;

use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\Clash;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\ClashRule;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\MainSiteUrlState;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\SitePath;

/**
 * The one entry point every sub-site clash surface uses (wpmldev-12087, spec
 * A6): the reserved names (B), the Add Site hint (C), the notices (D, F),
 * the URL format box (E) and the setup wizard's URL step (wpmldev-12232).
 *
 * Request-scoped and memoised: the main site's state is read once per context,
 * the `wp_blogs` lookup runs at most once, and only for a question that needs
 * sites, and names are read only for clashing sites. Nothing is stored in the
 * database. The memo is static on purpose: an admin request can hold more than
 * one instance of an action class, so per-instance state would not be shared.
 */
final class Detector {

	private static $reader;

	private static $states = [];

	private static $sites = [];

	private static $names = [];

	public static function forget() {
		self::$reader = null;
		self::$states = [];
		self::$sites  = [];
		self::$names  = [];
	}

	public static function useReader( MainSiteReader $reader ) {
		self::forget();
		self::$reader = $reader;
	}

	public static function state() {
		$context = is_multisite() && (int) get_current_blog_id() !== (int) get_main_site_id() ? 'raw' : 'main';

		if ( ! isset( self::$states[ $context ] ) ) {
			self::$states[ $context ] = self::reader()->state();
		}

		return self::$states[ $context ];
	}

	public static function reservedCodes() {
		return ClashRule::directoryCodes( self::state() );
	}

	public static function clashes() {
		$state = self::state();
		if ( ! ClashRule::directoryCodes( $state ) ) {
			return [];
		}

		return self::named( ClashRule::clashes( $state, self::sites( $state ) ) );
	}

	public static function potentialClashes() {
		$state = self::state();
		if ( ! ClashRule::directoryCodes( $state, true ) ) {
			return [];
		}

		return self::named( ClashRule::clashes( $state, self::sites( $state ), true ) );
	}

	public static function wizardClashes() {
		$state = self::state()->forSetupWizard();
		if ( ! ClashRule::directoryCodes( $state, true ) ) {
			return [];
		}

		return self::named( ClashRule::clashes( $state, self::sites( $state ), true ) );
	}

	public static function defaultLanguageClash() {
		$state = self::state();
		if ( ! ClashRule::inScope( $state ) ) {
			return null;
		}

		$clash = ClashRule::defaultLanguageClash( $state, self::sites( $state ) );

		return $clash ? self::named( [ $clash ] )[0] : null;
	}

	public static function defaultFixApplies(): bool {
		return ClashRule::defaultFixApplies( self::state(), self::clashes() );
	}

	public static function defaultFixDirectoryCode(): string {
		$state = self::state();
		if ( ! ClashRule::defaultFixApplies( $state, self::clashes() ) ) {
			return '';
		}

		foreach ( self::clashes() as $clash ) {
			if ( $clash->languageCode() === $state->defaultLanguage() ) {
				return $clash->directoryCode();
			}
		}

		return '';
	}

	private static function sites( MainSiteUrlState $state ) {
		$paths = ClashRule::candidatePaths( $state );
		if ( ! $paths ) {
			return [];
		}

		$key = $state->domain() . '|' . implode( '|', $paths );
		if ( ! isset( self::$sites[ $key ] ) ) {
			self::$sites[ $key ] = self::reader()->sitesAt( $paths, $state->domain() );
		}

		return self::$sites[ $key ];
	}

	private static function named( array $clashes ) {
		$missing = [];
		foreach ( $clashes as $clash ) {
			if ( ! array_key_exists( $clash->siteId(), self::$names ) ) {
				$missing[ $clash->siteId() ] = $clash->siteId();
			}
		}

		if ( $missing ) {
			self::$names = self::reader()->siteNames( array_values( $missing ) ) + self::$names;
			foreach ( $missing as $id ) {
				if ( ! array_key_exists( $id, self::$names ) ) {
					self::$names[ $id ] = '';
				}
			}
		}

		return array_map(
			function ( Clash $clash ) {
				return $clash->withSiteName( self::$names[ $clash->siteId() ] );
			},
			$clashes
		);
	}

	private static function reader() {
		if ( null === self::$reader ) {
			self::$reader = new MainSiteReader();
		}

		return self::$reader;
	}
}
