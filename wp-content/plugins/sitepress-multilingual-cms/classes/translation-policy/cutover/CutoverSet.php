<?php

namespace WPML\TranslationPolicy;

use WPML\Upgrade\CommandsStatus;
use WPML_Upgrade_Command_Definition;
use WPML_Upgrade_Command_Factory;

class CutoverSet {

	public const SCOPES = [ 'admin', 'cli' ];

	public const CORE_STEPS = [];

	public static function isPending(): bool {
		return FeatureSwitch::isArmed() && ! FeatureSwitch::isOn() && ! self::isDryRunRequest();
	}

	private static function isDryRunRequest(): bool {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI', false ) ) {
			return false;
		}

		$runner = \WP_CLI::get_runner();

		return array_slice( (array) $runner->arguments, 0, 3 ) === explode( ' ', 'wpml ' . \WPML\CLI\Core\Commands\TranslationPolicyCutover::COMMAND )
			&& ! empty( $runner->assoc_args['dry-run'] );
	}

	public static function definitions( WPML_Upgrade_Command_Factory $factory, \SitePress $sitepress, CommandsStatus $status ): array {
		if ( ! self::isPending() ) {
			return [];
		}

		$definitions = [];
		foreach ( self::CORE_STEPS as $step ) {
			$definitions[] = $factory->create_command_definition( $step, [], self::SCOPES );
		}

		$definitions[] = $factory->create_command_definition( MarkCutover::class, [ $sitepress, $status ], self::SCOPES );

		return $definitions;
	}

	public static function stepsIn( array $definitions ): array {
		$steps = [];
		foreach ( $definitions as $definition ) {
			if ( ! $definition instanceof WPML_Upgrade_Command_Definition ) {
				continue;
			}
			$class = $definition->get_class_name();
			if ( MarkCutover::class !== $class && is_subclass_of( $class, CutoverCommand::class ) ) {
				$steps[] = $class;
			}
		}

		return array_values( array_unique( $steps ) );
	}
}
