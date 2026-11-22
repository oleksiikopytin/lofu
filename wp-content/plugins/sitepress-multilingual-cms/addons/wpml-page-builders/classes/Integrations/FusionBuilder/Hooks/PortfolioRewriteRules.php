<?php

namespace WPML\Compatibility\FusionBuilder\Hooks;

use WPML\LIB\WP\Hooks;
use WPML\Utilities\RequestMemo;
use WPML\Utils\Unserialize;
use function WPML\FP\spreadArgs;

class PortfolioRewriteRules implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_AJAX_Action, \IWPML_REST_Action {

	use OptionNameOfLanguage;

	const PORTFOLIO_POST_TYPE = 'avada_portfolio';

	const SLUG_OPTION_KEY = 'portfolio_slug';

	const AVADA_DEFAULT_SLUG = 'portfolio-items';

	const PRIORITY_AFTER_AVADA_FILTER = 2;

	private $memo;

	public function add_hooks() {
		Hooks::onFilter( 'rewrite_rules_array' )
			->then( spreadArgs( [ $this, 'storeRulesWithDefaultLanguageSlug' ] ) );

		Hooks::onFilter( 'option_rewrite_rules', self::PRIORITY_AFTER_AVADA_FILTER )
			->then( spreadArgs( [ $this, 'readRulesWithRegisteredSlug' ] ) );
	}

	public function storeRulesWithDefaultLanguageSlug( $rules ) {
		$registeredSlug = $this->getRegisteredSlug();

		if ( ! is_array( $rules ) || ! $registeredSlug ) {
			return $rules;
		}

		$defaultSlug = $this->getDefaultLanguageSlug();

		if ( $registeredSlug === $defaultSlug ) {
			return $rules;
		}

		$rulePrefix = $this->getRulePrefix();

		return $this->replaceRuleBase( $rules, $rulePrefix . $registeredSlug . '/', $rulePrefix . $defaultSlug . '/' );
	}

	public function readRulesWithRegisteredSlug( $rules ) {
		$registeredSlug = $this->getRegisteredSlug();

		if ( ! is_array( $rules ) || ! $registeredSlug ) {
			return $rules;
		}

		return $this->getMemo()->rewriteRules(
			$rules,
			function ( $rules ) use ( $registeredSlug ) {
				$defaultSlug = $this->getDefaultLanguageSlug();

				if ( $registeredSlug === $defaultSlug ) {
					return $rules;
				}

				$rulePrefix = $this->getRulePrefix();

				return $this->replaceRuleBase( $rules, $rulePrefix . $defaultSlug . '/', $rulePrefix . $registeredSlug . '/' );
			},
			$registeredSlug,
			true
		);
	}

	private function getMemo() {
		if ( null === $this->memo ) {
			$optionName = $this->getOptionName( apply_filters( 'wpml_default_language', null ) );
			$this->memo = new RequestMemo(
				array_merge( RequestMemo::REWRITE_RULES_WRITES, [ 'add_option_' . $optionName, 'update_option_' . $optionName ] )
			);
		}

		return $this->memo;
	}

	private function replaceRuleBase( $rules, $fromBase, $toBase ) {
		$replacedRules = [];

		foreach ( $rules as $match => $query ) {
			if ( 0 === strpos( $match, $fromBase ) ) {
				$replaced = $toBase . substr( $match, strlen( $fromBase ) );
				$match    = array_key_exists( $replaced, $rules ) ? $match : $replaced;
			}

			$replacedRules[ $match ] = $query;
		}

		return $replacedRules;
	}

	private function getRegisteredSlug() {
		$postType = get_post_type_object( self::PORTFOLIO_POST_TYPE );

		return $postType && ! empty( $postType->rewrite['slug'] ) ? $postType->rewrite['slug'] : null;
	}

	private function getDefaultLanguageSlug() {
		return $this->getMemo()->remember(
			'defaultLanguageSlug',
			function () {
				$optionName = $this->getOptionName( apply_filters( 'wpml_default_language', null ) );
				$options    = Unserialize::maybe( apply_filters( 'wpml_unfiltered_admin_string', get_option( $optionName ), $optionName ) );

				return is_array( $options ) && ! empty( $options[ self::SLUG_OPTION_KEY ] ) ? $options[ self::SLUG_OPTION_KEY ] : self::AVADA_DEFAULT_SLUG;
			}
		);
	}

	private function getRulePrefix() {
		global $wp_rewrite;

		$postType = get_post_type_object( self::PORTFOLIO_POST_TYPE );

		return ! empty( $postType->rewrite['with_front'] ) && $wp_rewrite ? substr( $wp_rewrite->front, 1 ) : '';
	}
}
