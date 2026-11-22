<?php

namespace WPML\TM\AutomaticTranslation\Actions;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\BatchResult;
use WPML\FP\Obj;
use WPML\TM\API\Job\Map;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\ATE\Retry\Process;
use WPML\TM\Jobs\JobLog;
use WPML\Utilities\Lock;
use function WPML\Container\make;

class DeferredDispatch {

	const AJAX_ACTION = 'wpml_tea_deferred_dispatch';

	const RECORD_PREFIX = 'wpml_tea_dispatch_';

	const SENDING_PREFIX = 'wpml_tea_sending_';

	const TTL = HOUR_IN_SECONDS;

	const LOCK_WAIT_SECONDS = 10;

	private static $openPostId = null;

	private static $pending = [
		'groups'  => [],
		'cancels' => [],
	];

	private static $flushHooked = false;

	public static function isEnabled() {
		$default = ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! ( defined( 'DOING_CRON' ) && DOING_CRON );

		return (bool) apply_filters( 'wpml_tm_defer_automatic_dispatch', $default );
	}

	public static function open( $postId ) {
		self::$openPostId = self::isEnabled() ? (int) $postId : null;

		return null !== self::$openPostId;
	}

	public static function close() {
		if ( null === self::$openPostId ) {
			return;
		}

		self::$openPostId = null;

		if ( ( self::$pending['groups'] || self::$pending['cancels'] ) && ! self::$flushHooked ) {
			self::$flushHooked = true;
			add_action( 'shutdown', [ self::class, 'flush' ], 1000 );
		}
	}

	public static function isCollecting() {
		return null !== self::$openPostId;
	}

	public static function deferCreate( array $jobIds ) {
		$oldEditor  = wpml_tm_load_old_jobs_editor();
		$jobFactory = wpml_tm_load_job_factory();
		$group      = [];

		foreach ( $jobIds as $jobId ) {
			$jobId = (int) $jobId;
			if ( $jobId <= 0 ) {
				continue;
			}

			$status = Jobs::getStatus( $jobId );

			Jobs::setStatus( $jobId, ICL_TM_ATE_NEEDS_RETRY );
			$oldEditor->set( $jobId, \WPML_TM_Editors::ATE );
			$jobFactory->update_job_data( $jobId, [ 'automatic' => 1 ] );
			set_transient( self::SENDING_PREFIX . $jobId, time(), self::TTL );

			$group[ $jobId ] = null === $status || ICL_TM_ATE_NEEDS_RETRY === $status ? ICL_TM_IN_PROGRESS : $status;
		}

		if ( $group ) {
			self::$pending['groups'][] = $group;
			JobLog::add( 'auto_translate_create_deferred', [ 'job_ids' => array_keys( $group ) ] );
		}
	}

	public static function deferCancel( \WPML_TM_Post_Job_Entity $job, $reason ) {
		$ateJobId = (int) $job->get_editor_job_id();
		$fields   = [
			'rid'         => (int) $job->get_rid(),
			'wpml_job_id' => (int) $job->get_translate_job_id(),
			'ate_job_id'  => $ateJobId,
			'reason'      => (string) $reason,
			'words'       => $job->get_words_to_translate_count(),
			'credits'     => $job->get_automatic_translation_costs(),
			'status'      => (int) $job->get_status(),
			'delivered'   => self::isTranslated( (int) $job->get_translate_job_id() ),
		];

		self::$pending['cancels'][ (int) self::$openPostId ][] = $fields;

		PendingCancels::remember( [ $ateJobId ], PendingCancels::ENDPOINT_HIDE, $reason, true, $fields );

		JobLog::add( 'auto_translate_cancel_deferred', $fields );
	}

	public static function flush() {
		$pending           = self::$pending;
		self::$pending     = [
			'groups'  => [],
			'cancels' => [],
		];
		self::$flushHooked = false;

		if ( ! $pending['groups'] && ! $pending['cancels'] ) {
			return;
		}

		self::loopback(
			[
				'user'       => (int) get_current_user_id(),
				'groups'     => $pending['groups'],
				'cancels'    => $pending['cancels'],
				'superseded' => self::undeliveredSupersededByRid( $pending['cancels'] ),
			]
		);

		JobLog::add( 'auto_translate_dispatch_deferred', [
			'groups'      => array_map( 'array_keys', $pending['groups'] ),
			'ate_cancels' => array_sum( array_map( 'count', $pending['cancels'] ) ),
		] );
	}

	private static function loopback( array $record ) {
		$token          = wp_generate_password( 32, false, false );
		$record['at']   = time();
		set_transient( self::RECORD_PREFIX . $token, $record, self::TTL );

		$response = wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			[
				'timeout'   => 1,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => [
					'action' => self::AJAX_ACTION,
					'token'  => $token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			JobLog::addError( 'auto_translate_deferred_loopback_failed', [ 'error' => $response->get_error_message() ] );
		}

		return $token;
	}

	public static function handleRequest() {
		$token = self::requestToken();

		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		self::run( $token );

		wp_die( '', '', [ 'response' => 200 ] );
	}

	public static function requestToken() {
		$raw = filter_input( INPUT_POST, 'token', FILTER_UNSAFE_RAW );

		return self::tokenFrom( is_string( $raw ) ? sanitize_text_field( $raw ) : '' );
	}

	public static function tokenFrom( $value ) {
		return is_string( $value ) ? (string) preg_replace( '/[^A-Za-z0-9]/', '', $value ) : '';
	}

	public static function hasRecord( $token ) {
		return is_string( $token ) && strlen( $token ) >= 20 && is_array( get_transient( self::RECORD_PREFIX . $token ) );
	}

	public static function run( $token ) {
		if ( ! is_string( $token ) || strlen( $token ) < 20 ) {
			return null;
		}

		$record = get_transient( self::RECORD_PREFIX . $token );
		if ( ! is_array( $record ) ) {
			return null;
		}

		if ( false === delete_transient( self::RECORD_PREFIX . $token ) ) {
			return null;
		}

		$previousUser = (int) get_current_user_id();
		if ( ! empty( $record['user'] ) ) {
			wp_set_current_user( (int) $record['user'] );
		}

		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_TRANSLATE_EVERYTHING, 'Auto-translate on save (deferred ATE calls)', [] );

		$result = [
			'cancelled' => [],
			'sent'      => [],
			'skipped'   => [],
		];

		try {
			$result['cancelled'] = self::runCancels( isset( $record['cancels'] ) ? (array) $record['cancels'] : [] );

			$superseded = isset( $record['superseded'] ) ? (array) $record['superseded'] : [];
			foreach ( array_values( isset( $record['groups'] ) ? (array) $record['groups'] : [] ) as $group ) {
				list( $sent, $skipped ) = self::runCreate( (array) $group, $superseded );
				$result['sent']    = array_merge( $result['sent'], $sent );
				$result['skipped'] = array_merge( $result['skipped'], $skipped );
			}
		} finally {
			JobLog::add( 'auto_translate_deferred_done', $result );
			JobLog::finishCurrentGroup();
			wp_set_current_user( $previousUser );
		}

		return $result;
	}

	private static function runCancels( array $cancelsByPost ) {
		if ( ! $cancelsByPost ) {
			return [];
		}

		$sent    = [];
		$pending = PendingCancels::pending();

		foreach ( $cancelsByPost as $cancels ) {
			foreach ( (array) $cancels as $fields ) {
				$ateJobId = (int) $fields['ate_job_id'];
				if ( ! in_array( $ateJobId, $pending, true ) ) {
					continue;
				}

				PendingCancels::forget( [ $ateJobId ] );
				do_action( 'wpml_tm_job_cancelled', self::jobEntity( $fields ), $fields['reason'] );
				$sent[] = $ateJobId;
			}
		}

		return $sent;
	}

	private static function runCreate( array $statusByJobId, array $supersededByRid = [] ) {
		$send    = [];
		$skipped = [];

		foreach ( array_keys( $statusByJobId ) as $jobId ) {
			delete_transient( self::SENDING_PREFIX . (int) $jobId );
		}

		$lock = make( Lock::class, [ ':name' => Process::LOCK_NAME ] );
		if ( ! self::takeLock( $lock ) ) {
			JobLog::add( 'auto_translate_deferred_lock_busy', [ 'job_ids' => array_keys( $statusByJobId ) ] );

			return [ [], array_map( 'intval', array_keys( $statusByJobId ) ) ];
		}

		try {
			$featureOn = AutomaticTranslationCapabilities::shouldTranslateEverythingFresh();

			foreach ( $statusByJobId as $jobId => $status ) {
				$jobId = (int) $jobId;

				$wanted = $featureOn && self::isStillParkedAndCurrent( $jobId );
				if ( $featureOn && ! $wanted && self::isOverwrittenByLateDelivery( $jobId, $supersededByRid ) ) {
					JobLog::add( 'auto_translate_deferred_late_delivery_overwrote', [ 'job_id' => $jobId ] );
					$wanted = true;
				}

				if ( ! $wanted ) {
					$skipped[] = $jobId;
					continue;
				}

				Jobs::setStatus( $jobId, (int) $status );
				$send[] = $jobId;
			}
		} finally {
			$lock->release();
		}

		if ( $send ) {
			do_action( 'wpml_added_translation_jobs', [ 'local' => $send ], Jobs::SENT_AUTOMATICALLY );

			if ( ! AutomaticTranslationCapabilities::shouldTranslateEverythingFresh() ) {
				JobLog::add( 'auto_translate_deferred_tea_off_after_binding', [ 'job_ids' => $send ] );
				do_action( 'wpml_cancel_all_automatic_jobs', make( BatchResult::class ) );
			}
		}

		return [ $send, $skipped ];
	}

	private static function takeLock( $lock ) {
		$until = microtime( true ) + self::LOCK_WAIT_SECONDS;
		do {
			if ( $lock->create( Process::LOCK_RELEASE_TIMEOUT ) ) {
				return true;
			}
			usleep( 200000 );
		} while ( microtime( true ) < $until );

		return false;
	}

	private static function isStillParkedAndCurrent( $jobId ) {
		$rid = Map::ridFromDB( $jobId );

		return $rid
			&& Map::jobIdFromDB( $rid ) === $jobId
			&& ICL_TM_ATE_NEEDS_RETRY === Jobs::getStatus( $jobId );
	}

	private static function isOverwrittenByLateDelivery( $jobId, array $supersededByRid ) {
		$rid = Map::ridFromDB( $jobId );
		if ( ! $rid || empty( $supersededByRid[ $rid ] ) || Map::jobIdFromDB( $rid ) !== $jobId ) {
			return false;
		}

		$job = Jobs::get( $jobId );
		if ( ! $job || (int) Obj::prop( 'editor_job_id', $job ) > 0 || self::isTranslated( $jobId ) ) {
			return false;
		}

		if ( ICL_TM_COMPLETE !== Jobs::getStatus( $jobId ) ) {
			return false;
		}

		foreach ( $supersededByRid[ $rid ] as $supersededJobId ) {
			if ( self::isTranslated( (int) $supersededJobId ) ) {
				return true;
			}
		}

		return false;
	}

	private static function undeliveredSupersededByRid( array $cancelsByPost ) {
		$byRid = [];
		foreach ( $cancelsByPost as $cancels ) {
			foreach ( (array) $cancels as $fields ) {
				if ( empty( $fields['delivered'] ) ) {
					$byRid[ (int) $fields['rid'] ][] = (int) $fields['wpml_job_id'];
				}
			}
		}

		return $byRid;
	}

	private static function isTranslated( $jobId ) {
		$job = Jobs::get( $jobId );

		return $job && 1 === (int) Obj::prop( 'translated', $job );
	}

	private static function jobEntity( array $fields ) {
		$job = new \WPML_TM_Post_Job_Entity(
			(int) $fields['rid'],
			\WPML_TM_Job_Entity::POST_TYPE,
			0,
			new \WPML_TM_Jobs_Batch( 0, '' ),
			(int) $fields['status'],
			[]
		);
		$job->set_translate_job_id( (int) $fields['wpml_job_id'] );
		$job->set_translation_service( 'local' );
		$job->set_editor( \WPML_TM_Editors::ATE );
		$job->set_editor_job_id( (int) $fields['ate_job_id'] );
		$job->set_words_to_translate_count( $fields['words'] );
		$job->set_automatic_translation_costs( $fields['credits'] );

		return $job;
	}

	public static function isSending( $jobId ) {
		return (bool) get_transient( self::SENDING_PREFIX . (int) $jobId );
	}

	public static function isParkedForSending( $status, $jobId ) {
		return ICL_TM_ATE_NEEDS_RETRY === (int) $status
			&& (int) $jobId > 0
			&& self::isSending( (int) $jobId );
	}

	public static function statusForEditor( $status, $jobId ) {
		return self::isParkedForSending( $status, $jobId ) ? ICL_TM_IN_PROGRESS : $status;
	}

	public static function statusForEditorByRid( $status, $rid ) {
		if ( ICL_TM_ATE_NEEDS_RETRY !== (int) $status || (int) $rid <= 0 ) {
			return $status;
		}

		return self::statusForEditor( $status, Map::jobIdFromDB( (int) $rid ) );
	}

	public static function forgetSending( array $jobIds ) {
		foreach ( $jobIds as $jobId ) {
			delete_transient( self::SENDING_PREFIX . (int) $jobId );
		}
	}

	public static function reset() {
		self::$openPostId  = null;
		self::$flushHooked = false;
		self::$pending     = [
			'groups'  => [],
			'cancels' => [],
		];
	}
}
