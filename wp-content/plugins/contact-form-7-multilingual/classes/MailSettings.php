<?php

namespace WPML\CF7;

class MailSettings implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const MAIL_FIELDS = [ '_mail', '_mail_2' ];

	const SYNC_KEYS = [ 'recipient', 'sender', 'additional_headers', 'attachments' ];

	const CHECKSUM_VERSION_OPTION = 'wpml_cf7_checksum_version';

	const CHECKSUM_VERSION = 2;

	private $sitepress;

	private $wpdb;

	private $actionHelper;

	private $previousMail = [];

	private $statusUpdaters = [];

	public function __construct( \SitePress $sitepress, \wpdb $wpdb, \WPML_TM_Action_Helper $actionHelper ) {
		$this->sitepress    = $sitepress;
		$this->wpdb         = $wpdb;
		$this->actionHelper = $actionHelper;
	}

	public function add_hooks() {
		add_filter( 'update_post_metadata', [ $this, 'rememberPreviousMail' ], 10, 3 );
		add_action( 'updated_post_meta', [ $this, 'syncMailSettings' ], 10, 3 );
		add_filter( 'wpml_tm_post_md5_content', [ $this, 'leaveBodyOutOfChecksum' ], 10, 2 );
		add_filter( 'wpml_custom_field_values_for_post_signature', [ $this, 'leaveMailSettingsOutOfChecksum' ], 10, 2 );
		add_filter( 'wpml_tm_delegate_translation_statuses_update', [ $this, 'delegateStatusesUpdate' ], 20, 3 );
		add_action( 'wpcf7_after_save', [ $this, 'runStatusesUpdate' ] );
		add_action( 'shutdown', [ $this, 'runAllStatusesUpdates' ] );
		add_action( 'admin_init', [ $this, 'rebaseChecksums' ] );
	}

	public function rememberPreviousMail( $check, $postId, $metaKey ) {
		if ( null === $check && in_array( $metaKey, self::MAIL_FIELDS, true ) && $this->isOriginalForm( (int) $postId ) ) {
			$this->previousMail[ $postId . ':' . $metaKey ] = get_post_meta( $postId, $metaKey, true );
		}

		return $check;
	}

	public function syncMailSettings( $metaId, $postId, $metaKey ) {
		$key = $postId . ':' . $metaKey;
		if ( ! array_key_exists( $key, $this->previousMail ) ) {
			return;
		}

		$previous = $this->previousMail[ $key ];
		unset( $this->previousMail[ $key ] );

		$current = get_post_meta( $postId, $metaKey, true );
		if ( ! is_array( $previous ) || ! is_array( $current ) ) {
			return;
		}

		$changed = array_filter(
			self::SYNC_KEYS,
			function ( $setting ) use ( $previous, $current ) {
				return ( $previous[ $setting ] ?? null ) !== ( $current[ $setting ] ?? null );
			}
		);
		if ( ! $changed ) {
			return;
		}

		foreach ( $this->getTranslationIds( (int) $postId ) as $translationId ) {
			$this->syncTranslation( $translationId, $metaKey, $changed, $previous, $current );
		}
	}

	private function syncTranslation( int $translationId, string $metaKey, array $changed, array $previous, array $current ) {
		$mail = get_post_meta( $translationId, $metaKey, true );
		if ( ! is_array( $mail ) ) {
			return;
		}

		$synced = $mail;
		foreach ( $changed as $setting ) {
			if ( ( $mail[ $setting ] ?? null ) === ( $previous[ $setting ] ?? null ) ) {
				$synced[ $setting ] = $current[ $setting ] ?? '';
			}
		}

		if ( $synced !== $mail ) {
			update_post_meta( $translationId, $metaKey, wp_slash( $synced ) );
		}
	}

	public function leaveBodyOutOfChecksum( $content, $post ) {
		return $this->isForm( $post ) ? '' : $content;
	}

	public function leaveMailSettingsOutOfChecksum( $values, $postId ) {
		if ( Constants::POST_TYPE !== get_post_type( $postId ) ) {
			return $values;
		}

		foreach ( self::MAIL_FIELDS as $field ) {
			if ( isset( $values[ $field ] ) && is_string( $values[ $field ] ) ) {
				$mail = json_decode( $values[ $field ], true );
				if ( is_array( $mail ) ) {
					$values[ $field ] = wp_json_encode( array_diff_key( $mail, array_flip( self::SYNC_KEYS ) ) );
				}
			}
		}

		return $values;
	}

	public function delegateStatusesUpdate( $isDelegated, $postId, $statusesUpdater ) {
		if ( $isDelegated || Constants::POST_TYPE !== get_post_type( $postId ) ) {
			return $isDelegated;
		}

		$this->statusUpdaters[ (int) $postId ] = $statusesUpdater;

		return true;
	}

	public function runStatusesUpdate( $contactForm ) {
		$postId = (int) $contactForm->id();
		if ( isset( $this->statusUpdaters[ $postId ] ) ) {
			$this->runUpdaters( [ $postId => $this->statusUpdaters[ $postId ] ] );
		}
	}

	public function runAllStatusesUpdates() {
		$this->runUpdaters( $this->statusUpdaters );
	}

	private function runUpdaters( array $updaters ) {
		$this->previousMail = array_filter(
			$this->previousMail,
			function ( $key ) use ( $updaters ) {
				return ! isset( $updaters[ (int) $key ] );
			},
			ARRAY_FILTER_USE_KEY
		);

		foreach ( $updaters as $postId => $updater ) {
			unset( $this->statusUpdaters[ $postId ] );
			$updater();
		}
	}

	public function rebaseChecksums() {
		if ( (int) get_option( self::CHECKSUM_VERSION_OPTION ) >= self::CHECKSUM_VERSION ) {
			return;
		}

		$wpdb        = $this->wpdb;
		$elementType = TranslationEditor\JobFilter::JOB_ORIGINAL_POST_TYPE_CONTACT_FORM_7;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.element_id AS original_id, s.rid, s.md5
				FROM {$wpdb->prefix}icl_translations o
				JOIN {$wpdb->prefix}icl_translations t ON t.trid = o.trid AND t.source_language_code IS NOT NULL
				JOIN {$wpdb->prefix}icl_translation_status s ON s.translation_id = t.translation_id
				WHERE o.element_type = %s AND o.source_language_code IS NULL AND s.needs_update = %d AND s.md5 <> %s",
				$elementType,
				0,
				''
			)
		);

		$upToDate = $this->withoutChecksumFilters(
			function () use ( $rows ) {
				$matches = [];
				foreach ( $rows as $row ) {
					$matches[ $row->rid ] = $this->actionHelper->post_md5_matches( (int) $row->original_id, $row->md5 );
				}

				return $matches;
			}
		);

		$checksums = [];
		foreach ( $rows as $row ) {
			if ( $upToDate[ $row->rid ] ) {
				$originalId = (int) $row->original_id;
				if ( ! isset( $checksums[ $originalId ] ) ) {
					$checksums[ $originalId ] = $this->actionHelper->post_md5( $originalId );
				}

				$wpdb->update(
					$wpdb->prefix . 'icl_translation_status',
					[ 'md5' => $checksums[ $originalId ] ],
					[ 'rid' => $row->rid ]
				);
			}
		}

		update_option( self::CHECKSUM_VERSION_OPTION, self::CHECKSUM_VERSION, true );
	}

	private function withoutChecksumFilters( callable $fn ) {
		remove_filter( 'wpml_tm_post_md5_content', [ $this, 'leaveBodyOutOfChecksum' ], 10 );
		remove_filter( 'wpml_custom_field_values_for_post_signature', [ $this, 'leaveMailSettingsOutOfChecksum' ], 10 );

		try {
			return $fn();
		} finally {
			add_filter( 'wpml_tm_post_md5_content', [ $this, 'leaveBodyOutOfChecksum' ], 10, 2 );
			add_filter( 'wpml_custom_field_values_for_post_signature', [ $this, 'leaveMailSettingsOutOfChecksum' ], 10, 2 );
		}
	}

	private function isForm( $post ): bool {
		return is_object( $post ) && isset( $post->post_type ) && Constants::POST_TYPE === $post->post_type;
	}

	private function isOriginalForm( int $postId ): bool {
		if ( Constants::POST_TYPE !== get_post_type( $postId ) ) {
			return false;
		}

		$details = $this->sitepress->get_element_language_details( $postId, TranslationEditor\JobFilter::JOB_ORIGINAL_POST_TYPE_CONTACT_FORM_7 );

		return $details && empty( $details->source_language_code );
	}

	private function getTranslationIds( int $postId ): array {
		$elementType  = TranslationEditor\JobFilter::JOB_ORIGINAL_POST_TYPE_CONTACT_FORM_7;
		$translations = $this->sitepress->get_element_translations( $this->sitepress->get_element_trid( $postId, $elementType ), $elementType );

		$ids = [];
		foreach ( $translations as $translation ) {
			if ( ! $translation->original && (int) $translation->element_id !== $postId ) {
				$ids[] = (int) $translation->element_id;
			}
		}

		return $ids;
	}
}
