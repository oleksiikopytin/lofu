<?php

namespace WPML\Compatibility\Divi\ThemeBuilderRepair;

class Runner {

	private $store;

	public function __construct( Store $store ) {
		$this->store = $store;
	}

	public static function forSite( $wpdb, $sitepress ) {
		return new self( new Store( $wpdb, $sitepress ) );
	}

	public function run( $dryRun ) {
		$dryRun  = (bool) $dryRun;
		$posts   = $this->store->publishedThemeBuilderPosts();
		$keep    = $this->keep( $posts, $this->store->defaultLanguage() );
		$extra   = [];
		$keptIds = [];

		$keptTemplates = $keep ? $this->store->templates( $keep['id'] ) : [];
		foreach ( $keptTemplates as $template ) {
			$keptIds[ $template['id'] ] = true;
		}

		$otherTemplates = [];
		foreach ( $posts as $post ) {
			if ( $keep && $post['id'] === $keep['id'] ) {
				continue;
			}
			$templates = $this->store->templates( $post['id'] );
			foreach ( $templates as $template ) {
				if ( ! isset( $keptIds[ $template['id'] ] ) && ! isset( $otherTemplates[ $template['id'] ] ) ) {
					$otherTemplates[ $template['id'] ] = $template;
				}
			}
			$extra[] = $post + [ 'templates' => count( $templates ) ];
		}

		$languageRows = $this->store->languageRowCount();
		$settings     = false;
		foreach ( $this->store->storedModes() as $mode ) {
			$settings = $settings || 0 !== $mode['mode'] || $mode['unlocked'];
		}

		$answer = [
			'dryRun'         => $dryRun,
			'todo'           => count( $extra ) > 0 || $languageRows > 0 || $settings,
			'keep'           => $keep,
			'unpublish'      => $extra,
			'keptTemplates'  => $keptTemplates,
			'otherTemplates' => array_values( $otherTemplates ),
			'languageRows'   => $languageRows,
			'settings'       => $settings,
			'unpublished'    => 0,
			'rowsDeleted'    => 0,
			'modesReset'     => false,
		];

		if ( $dryRun || ! $answer['todo'] ) {
			return $answer;
		}

		$time = gmdate( 'Y-m-d H:i:s' );
		foreach ( $extra as $post ) {
			$record = [
				'status'   => 'publish',
				'language' => $post['language'],
				'kept'     => $keep ? $keep['id'] : 0,
				'time'     => $time,
			];
			if ( $this->store->unpublish( $post['id'], $record ) ) {
				$answer['unpublished']++;
				$this->store->log(
					sprintf(
						'Theme Builder post %d (%s) moved to draft; post %d stays the Theme Builder.',
						$post['id'],
						null === $post['language'] ? 'no language' : $post['language'],
						$keep ? $keep['id'] : 0
					)
				);
			}
		}

		if ( $languageRows > 0 ) {
			$answer['rowsDeleted'] = $this->store->deleteLanguageRows();
			$this->store->log( sprintf( '%d language rows of post_et_theme_builder deleted.', $answer['rowsDeleted'] ) );
		}

		if ( $settings ) {
			$this->store->resetModes();
			$answer['modesReset'] = true;
			$this->store->log( 'et_theme_builder and et_template set back to Not translatable.' );
		}

		$this->store->writeLast(
			[
				'time'        => $time,
				'kept'        => $keep ? $keep['id'] : 0,
				'unpublished' => array_map(
					function ( $post ) {
						return $post['id'];
					},
					$extra
				),
				'rowsDeleted' => $answer['rowsDeleted'],
				'modesReset'  => $answer['modesReset'],
			]
		);

		return $answer;
	}

	private function keep( array $posts, $defaultLanguage ) {
		foreach ( $posts as $post ) {
			if ( $defaultLanguage === $post['language'] ) {
				return $post;
			}
		}

		return $posts ? $posts[0] : null;
	}
}
