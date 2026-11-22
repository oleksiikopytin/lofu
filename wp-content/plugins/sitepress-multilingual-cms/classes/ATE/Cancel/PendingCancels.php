<?php

namespace WPML\TM\ATE\Cancel;

use WPML\Core\Component\ATE\Domain\SiteRegistration;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class PendingCancels {

	const OPTION = 'wpml_tm_ate_pending_cancels';

	const CAP = 200;

	const ADMIN_RETRY_INTERVAL = 60;

	const REPLAY_TIMEOUT = 10;

	const REPLAY_CHUNK = 50;

	const MAX_AGE = 2592000;

	const NOT_DIALLED_CODES = [ SiteRegistration::NOT_REGISTERED_ERROR_CODE, ApiCommunication::RECONNECTING_ERROR_CODE ];

	const ENDPOINT_CANCEL = 'cancel';

	const ENDPOINT_HIDE = 'hide';

	const BACKGROUND_LABEL = 'wpml-ate-cancel-replay';

	public static function isUnanswered( $response ) {
		if ( is_wp_error( $response ) ) {
			return \WPML_TM_ATE_API::isTransportFailure( $response )
				|| in_array( $response->get_error_code(), self::NOT_DIALLED_CODES, true );
		}

		return ! is_array( $response ) && ! is_object( $response );
	}

	public static function isOwed( $response ) {
		return self::isUnanswered( $response ) || \WPML_TM_ATE_API::isGatewayErrorAnswer( $response );
	}

	public static function firstTryRequestArgs() {
		return [ \WPML_TM_ATE_API::RAW_GATEWAY_ERROR_ARG => true ];
	}

	private static function wasDialled( $response ) {
		return ! ( is_wp_error( $response ) && in_array( $response->get_error_code(), self::NOT_DIALLED_CODES, true ) );
	}

	private static function stopsThePass( $response ) {
		return ! is_wp_error( $response )
			|| ApiCommunication::RECONNECTING_ERROR_CODE !== $response->get_error_code();
	}

	public static function remember( array $ateJobIds, $endpoint, $reason, $force = false, array $context = [], $firstTry = null ) {
		$byAteJobId = [];
		foreach ( self::ids( $ateJobIds ) as $ateJobId ) {
			$byAteJobId[ $ateJobId ] = $context;
		}

		self::rememberEach( $byAteJobId, $endpoint, $reason, $force, $firstTry );
	}

	public static function rememberEach( array $byAteJobId, $endpoint, $reason, $force = false, $firstTry = null ) {
		$entries = [];
		foreach ( $byAteJobId as $ateJobId => $context ) {
			$ateJobId = (int) $ateJobId;
			if ( $ateJobId <= 0 ) {
				continue;
			}
			$context                = is_array( $context ) ? $context : [];
			$entries[ $ateJobId ] = [
				'endpoint'    => self::ENDPOINT_HIDE === $endpoint ? self::ENDPOINT_HIDE : self::ENDPOINT_CANCEL,
				'reason'      => (string) $reason,
				'force'       => (bool) $force,
				'at'          => time(),
				'attempts'    => 0,
				'wpml_job_id' => isset( $context['wpml_job_id'] ) ? (int) $context['wpml_job_id'] : 0,
				'user_id'     => isset( $context['user_id'] ) ? (int) $context['user_id'] : (int) get_current_user_id(),
			];
		}

		if ( ! $entries ) {
			return;
		}

		$stored = self::read();
		foreach ( $entries as $ateJobId => $entry ) {
			$stored['jobs'][ (string) $ateJobId ] = $entry;
		}

		$excess  = count( $stored['jobs'] ) - self::CAP;
		$dropped = $excess > 0 ? array_map( 'intval', array_keys( array_slice( $stored['jobs'], 0, $excess, true ) ) ) : [];
		foreach ( $dropped as $ateJobId ) {
			unset( $stored['jobs'][ (string) $ateJobId ] );
		}

		self::write( $stored );

		$firstEntry = reset( $entries );
		JobLog::runAsBackgroundWork( self::BACKGROUND_LABEL, function () use ( $entries, $firstEntry, $endpoint, $reason, $stored, $dropped, $firstTry ) {
			JobLog::runInGroup( JobLog::GROUP_ID_CANCELS, 'ATE cancel owed', function () use ( $entries, $firstEntry, $endpoint, $reason, $stored, $dropped, $firstTry ) {
				if ( $firstTry instanceof \WP_Error && \WPML_TM_ATE_API::isGatewayErrorAnswer( $firstTry ) ) {
					JobLog::addError( 'ate_cancel_first_try_gateway_error', [
						'ate_job_ids'  => array_keys( $entries ),
						'wpml_job_ids' => array_values( array_column( $entries, 'wpml_job_id' ) ),
						'endpoint'     => $endpoint,
						'reason'       => $reason,
						'http_status'  => $firstTry->get_error_code(),
						'message'      => $firstTry->get_error_message(),
						'body'         => $firstTry->get_error_data(),
						'user_id'      => (int) $firstEntry['user_id'],
					] );
				}

				JobLog::add( 'ate_cancel_pending', [
					'ate_job_ids'  => array_keys( $entries ),
					'wpml_job_ids' => array_values( array_column( $entries, 'wpml_job_id' ) ),
					'endpoint'     => $endpoint,
					'reason'       => $reason,
					'user_id'      => (int) $firstEntry['user_id'],
					'pending'      => count( $stored['jobs'] ),
				] );

				if ( $dropped ) {
					JobLog::addError( 'ate_cancel_pending_dropped', [
						'ate_job_ids' => $dropped,
						'cap'         => self::CAP,
					] );
				}
			} );
		} );
	}

	public static function forget( array $ateJobIds ) {
		$ateJobIds = self::ids( $ateJobIds );
		if ( ! $ateJobIds ) {
			return;
		}

		$stored = self::read();
		foreach ( $ateJobIds as $ateJobId ) {
			unset( $stored['jobs'][ (string) $ateJobId ] );
		}
		self::write( $stored );
	}

	public static function pending() {
		return array_map( 'intval', array_keys( self::read()['jobs'] ) );
	}

	public static function retry( \WPML_TM_ATE_API $api ) {
		$stored = self::read();
		$result = [ 'sent' => [], 'kept' => [], 'failed' => [] ];

		if ( ! $stored['jobs'] ) {
			return $result;
		}

		$now = time();

		if ( $now - (int) $stored['last_try'] < self::ADMIN_RETRY_INTERVAL ) {
			$result['kept'] = array_map( 'intval', array_keys( $stored['jobs'] ) );

			return $result;
		}

		return JobLog::runAsBackgroundWork( self::BACKGROUND_LABEL, function () use ( $api, $stored, $now, $result ) {
			return JobLog::runInGroup(
				JobLog::GROUP_ID_CANCELS,
				'Owed ATE cancels replayed',
				function () use ( $api, $stored, $now, $result ) {
					return self::pass( $api, $stored, $now, $result );
				}
			);
		} );
	}

	private static function pass( \WPML_TM_ATE_API $api, array $stored, $now, array $result ) {
		$expired = [];
		foreach ( $stored['jobs'] as $ateJobId => $entry ) {
			if ( $now - (int) $entry['at'] > self::MAX_AGE ) {
				$expired[] = (int) $ateJobId;
				unset( $stored['jobs'][ $ateJobId ] );
			}
		}

		if ( $expired ) {
			JobLog::addError( 'ate_cancel_pending_expired', [
				'ate_job_ids' => $expired,
				'max_age'     => self::MAX_AGE,
			] );
		}

		$stored['last_try'] = $now;
		self::write( $stored );

		if ( ! $stored['jobs'] ) {
			return $result;
		}

		$groups = [];
		foreach ( $stored['jobs'] as $ateJobId => $entry ) {
			$key              = $entry['endpoint'] . '|' . $entry['reason'] . '|' . ( $entry['force'] ? 1 : 0 );
			$groups[ $key ][] = (int) $ateJobId;
		}

		$unreachable = false;
		$ateAnswered = false;
		$stuck       = [];
		$dialled     = [];
		foreach ( $groups as $key => $ateJobIds ) {
			list( $endpoint, $reason, $force ) = explode( '|', $key );

			foreach ( array_chunk( $ateJobIds, self::REPLAY_CHUNK ) as $chunk ) {
				if ( $unreachable ) {
					$result['kept'] = array_merge( $result['kept'], $chunk );
					continue;
				}

				$response = self::dial( $api, $endpoint, $reason, (bool) $force, $chunk );

				if ( self::wasDialled( $response ) ) {
					$dialled = array_merge( $dialled, $chunk );
				}

				if ( self::isUnanswered( $response ) ) {
					$unreachable    = self::stopsThePass( $response );
					$result['kept'] = array_merge( $result['kept'], $chunk );
					JobLog::addError( 'ate_cancel_retry_unanswered', [
						'ate_job_ids' => $chunk,
						'endpoint'    => $endpoint,
						'dialled'     => self::wasDialled( $response ),
						'error'       => is_wp_error( $response ) ? $response->get_error_message() : null,
						'code'        => is_wp_error( $response ) ? $response->get_error_code() : null,
					] );
					continue;
				}

				if ( \WPML_TM_ATE_API::isGatewayErrorAnswer( $response ) ) {
					$outcome = self::handleGatewayFailure( $api, $endpoint, $reason, (bool) $force, $chunk, $response, $stored['jobs'] );

					$result['sent']   = array_merge( $result['sent'], $outcome['sent'] );
					$result['kept']   = array_merge( $result['kept'], $outcome['kept'] );
					$result['failed'] = array_merge( $result['failed'], $outcome['failed'] );
					$unreachable      = $outcome['unreachable'];
					$ateAnswered      = $ateAnswered || $outcome['ate_answered'];
					if ( $outcome['stuck'] ) {
						$stuck[] = [ $endpoint, $reason, (bool) $force, $outcome['stuck'] ];
					}
					continue;
				}

				$ateAnswered    = true;
				$result['sent'] = array_merge( $result['sent'], $chunk );
				JobLog::add( 'ate_cancel_retried', [
					'ate_job_ids' => $chunk,
					'endpoint'    => $endpoint,
					'reason'      => $reason,
					'refused'     => is_wp_error( $response ) ? $response->get_error_code() : null,
				] );
			}
		}

		if ( $stuck ) {
			$ateIsUp = $ateAnswered && ! $unreachable;

			if ( ! $ateIsUp && ! $unreachable && self::isTheOnlyWork( $stuck, $result ) && self::keepsFailing( $stuck[0][3], $stored['jobs'] ) ) {
				$ateIsUp     = self::ateAnswersOtherCalls( $api, $stuck[0][3] );
				$ateAnswered = $ateAnswered || $ateIsUp;
			}

			$result = self::resolveStuck( $api, $stuck, $ateIsUp, $stored['jobs'], $result );
		}

		self::settle( array_merge( $result['sent'], $result['failed'] ), $dialled );

		$isDown = ! $ateAnswered && ! $result['sent'] && ! $result['failed'] && $result['kept'];
		self::recordAteDown( (bool) $isDown, ! $dialled ? 'reconnecting' : ( $unreachable ? 'unreachable' : 'nothing_answered' ), $result['kept'], $now, $stored['down'] );

		return $result;
	}

	private static function isTheOnlyWork( array $stuck, array $result ) {
		return 1 === count( $stuck ) && ! $result['sent'] && ! $result['failed'] && ! $result['kept'];
	}

	private static function keepsFailing( array $ateJobIds, array $entries ) {
		foreach ( $ateJobIds as $ateJobId ) {
			if ( (int) ( $entries[ (string) $ateJobId ]['attempts'] ?? 0 ) < 1 ) {
				return false;
			}
		}

		return (bool) $ateJobIds;
	}

	private static function ateAnswersOtherCalls( \WPML_TM_ATE_API $api, array $ateJobIds ) {
		try {
			$answer = $api->get_catalogue_version();
		} catch ( \Throwable $e ) {
			$answer = null;
		}

		$answered = is_array( $answer );

		JobLog::add( 'ate_cancel_lone_group_probe', [
			'ate_job_ids' => $ateJobIds,
			'call'        => 'GET catalogue version',
			'answered'    => $answered,
			'verdict'     => $answered ? 'ATE answers other calls: the group is decided as a failing group' : 'no 2xx: ATE down for the site, everything kept',
		] );

		return $answered;
	}

	private static function recordAteDown( $isDown, $reason, array $kept, $now, $before ) {
		if ( $isDown ) {
			$since = $before ? (int) $before['since'] : (int) $now;
			if ( ! $before ) {
				$stored         = self::read();
				$stored['down'] = [ 'since' => $since, 'reason' => $reason ];
				self::write( $stored );
			}

			JobLog::addError( 'ate_cancel_kept_ate_down', [
				'ate_job_ids' => array_values( array_map( 'intval', $kept ) ),
				'reason'      => $before ? (string) $before['reason'] : $reason,
				'since'       => $since,
				'down_for'    => max( 0, (int) $now - $since ),
				'shown'       => 'on the ATE screens until a pass is answered',
			] );

			return;
		}

		if ( ! $before ) {
			return;
		}

		$stored = self::read();
		if ( null !== $stored['down'] ) {
			$stored['down'] = null;
			self::write( $stored );
		}

		JobLog::add( 'ate_cancel_ate_answered_again', [
			'down_for' => max( 0, (int) $now - (int) $before['since'] ),
			'since'    => (int) $before['since'],
		] );
	}

	public static function ateDown() {
		$stored = self::read();

		if ( null === $stored['down'] || ! $stored['jobs'] ) {
			return null;
		}

		return [
			'since'  => (int) $stored['down']['since'],
			'reason' => (string) $stored['down']['reason'],
			'kept'   => count( $stored['jobs'] ),
		];
	}

	private static function resolveStuck( \WPML_TM_ATE_API $api, array $stuck, $ateIsUp, array $entries, array $result ) {
		if ( ! $ateIsUp ) {
			$ids = [];
			foreach ( $stuck as $chunk ) {
				$ids = array_merge( $ids, $chunk[3] );
			}

			JobLog::addError( 'ate_cancel_ate_down', [
				'reason'      => 'status_read_failed_for_every_group',
				'ate_job_ids' => $ids,
				'kept'        => 'everything owed, next pass in ' . self::ADMIN_RETRY_INTERVAL . ' s',
			] );
			$result['kept'] = array_merge( $result['kept'], $ids );

			return $result;
		}

		foreach ( $stuck as $index => $chunk ) {
			list( $endpoint, $reason, $force, $ids ) = $chunk;

			if ( 1 === count( $ids ) ) {
				self::giveUp( $ids[0], $entries[ (string) $ids[0] ] ?? [], 'status_read_failed_while_ate_answered', null );
				$result['failed'][] = $ids[0];
				continue;
			}

			$outcome        = self::split( $api, $endpoint, $reason, $force, $ids );
			$result['sent'] = array_merge( $result['sent'], $outcome['sent'] );
			$result['kept'] = array_merge( $result['kept'], $outcome['kept'] );

			if ( $outcome['unreachable'] ) {
				foreach ( array_slice( $stuck, $index + 1 ) as $rest ) {
					$result['kept'] = array_merge( $result['kept'], $rest[3] );
				}

				return $result;
			}
		}

		return $result;
	}

	private static function dial( \WPML_TM_ATE_API $api, $endpoint, $reason, $force, array $ateJobIds ) {
		try {
			return self::ENDPOINT_HIDE === $endpoint
				? $api->hideJobs( $ateJobIds, $force, $reason, self::requestArgs() )
				: $api->cancelJobs( $ateJobIds, false, $reason, self::requestArgs() );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function requestArgs() {
		return [
			'timeout'                                => self::REPLAY_TIMEOUT,
			\WPML_TM_ATE_API::RAW_GATEWAY_ERROR_ARG => true,
		];
	}

	/**
	 * ATE answered the replayed call with a 5xx (wpmldev-11664, ruling 1).
	 *
	 * (a) One status read of the chunk's ids. A read that never reached ATE
	 *     (the transport, a 503, a 426, the reconnect episode, a throw) means
	 *     ATE is down: everything stays owed and the pass stops. A read ATE
	 *     answered with an error, or with no status in it, says nothing
	 *     about the site (A-F1, Ivan's ruling "a"): the chunk is stuck, and
	 *     is decided after every other group of the pass was dialled
	 *     ({@see self::resolveStuck()}).
	 * (b) Every id ATE shows cancelled is settled, and so is an id ATE no
	 *     longer knows.
	 * (c) Some moved: the first id, in the order WPML sent, that did not move
	 *     is the job whose cancel fails (ATE cancels one by one and keeps no
	 *     order, so the order is WPML's). It is given up
	 *     ({@see self::giveUp()}); the rest stay owed for the next pass.
	 * (d) None moved: the chunk is split once, in halves, each dialled once.
	 *     Both halves fail: that chunk stays owed; ATE answered its status,
	 *     so ATE is up and the other groups still go out (A-F1). One half
	 *     answers: it is settled, the other stays owed, narrower, for the
	 *     next pass. A chunk of one id cannot be split: ATE answered its
	 *     status, so ATE is up and this is the failed job.
	 *
	 * @param \WPML_TM_ATE_API $api
	 * @param string           $endpoint
	 * @param string           $reason
	 * @param bool             $force
	 * @param int[]            $chunk
	 * @param \WP_Error        $response The raw gateway error.
	 * @param array            $entries  The stored entries, by ATE id.
	 *
	 * @return array{sent: int[], kept: int[], failed: int[], stuck: int[], unreachable: bool, ate_answered: bool}
	 *         `stuck`: the chunk whose status read ATE answered with an
	 *         error, still undecided; `ate_answered`: ATE answered a call of
	 *         this chunk with something other than a gateway error.
	 */
	private static function handleGatewayFailure( \WPML_TM_ATE_API $api, $endpoint, $reason, $force, array $chunk, \WP_Error $response, array $entries ) {
		$outcome = [ 'sent' => [], 'kept' => [], 'failed' => [], 'stuck' => [], 'unreachable' => false, 'ate_answered' => false ];

		JobLog::addError( 'ate_cancel_retry_gateway_error', [
			'ate_job_ids' => $chunk,
			'endpoint'    => $endpoint,
			'reason'      => $reason,
			'http_status' => $response->get_error_code(),
			'message'     => $response->get_error_message(),
			'body'        => $response->get_error_data(),
		] );

		$readReachedATE = false;
		$statuses       = self::readStatuses( $api, $chunk, $readReachedATE );

		if ( null === $statuses && ! $readReachedATE ) {
			JobLog::addError( 'ate_cancel_ate_down', [
				'reason'      => 'status_read_failed',
				'ate_job_ids' => $chunk,
				'kept'        => 'everything owed, next pass in ' . self::ADMIN_RETRY_INTERVAL . ' s',
			] );
			$outcome['kept']        = $chunk;
			$outcome['unreachable'] = true;

			return $outcome;
		}

		if ( null === $statuses ) {
			JobLog::addError( 'ate_cancel_group_stuck', [
				'reason'      => 'status_read_failed',
				'ate_job_ids' => $chunk,
				'endpoint'    => $endpoint,
				'decided'     => 'after the other groups of this pass',
			] );
			$outcome['stuck'] = $chunk;

			return $outcome;
		}

		$outcome['ate_answered'] = true;

		$moved    = [];
		$notMoved = [];
		foreach ( $chunk as $ateJobId ) {
			if ( ! array_key_exists( $ateJobId, $statuses ) || self::settledByStatus( $endpoint, $force, $statuses[ $ateJobId ] ) ) {
				$moved[] = $ateJobId;
			} else {
				$notMoved[] = $ateJobId;
			}
		}

		if ( $moved ) {
			JobLog::add( 'ate_cancel_settled_by_status', [
				'ate_job_ids' => $moved,
				'endpoint'    => $endpoint,
				'not_held'    => array_values( array_diff( $moved, array_keys( $statuses ) ) ),
			] );
			$outcome['sent'] = $moved;
		}

		if ( ! $notMoved ) {
			return $outcome;
		}

		if ( $moved || 1 === count( $notMoved ) ) {
			$failed = $notMoved[0];
			$rest   = array_slice( $notMoved, 1 );

			self::giveUp( $failed, $entries[ (string) $failed ] ?? [], $moved ? 'first_not_cancelled_after_partial_cancel' : 'only_id_of_the_call', $statuses[ $failed ] ?? null );
			$outcome['failed'] = [ $failed ];
			$outcome['kept']   = $rest;

			if ( $rest ) {
				JobLog::add( 'ate_cancel_kept_for_next_pass', [
					'ate_job_ids' => $rest,
					'endpoint'    => $endpoint,
				] );
			}

			return $outcome;
		}

		$split                  = self::split( $api, $endpoint, $reason, $force, $notMoved );
		$outcome['sent']        = array_merge( $outcome['sent'], $split['sent'] );
		$outcome['kept']        = array_merge( $outcome['kept'], $split['kept'] );
		$outcome['unreachable'] = $split['unreachable'];

		return $outcome;
	}

	private static function split( \WPML_TM_ATE_API $api, $endpoint, $reason, $force, array $ateJobIds ) {
		$outcome  = [ 'sent' => [], 'kept' => [], 'unreachable' => false ];
		$halves   = array_chunk( $ateJobIds, max( 1, (int) ceil( count( $ateJobIds ) / 2 ) ) );
		$verdicts = [];
		$failing  = 0;
		foreach ( $halves as $half ) {
			$answer = self::dial( $api, $endpoint, $reason, $force, $half );

			if ( \WPML_TM_ATE_API::isGatewayErrorAnswer( $answer ) ) {
				$verdicts[]      = [ 'ate_job_ids' => $half, 'outcome' => 'gateway_error', 'http_status' => $answer->get_error_code() ];
				$outcome['kept'] = array_merge( $outcome['kept'], $half );
				$failing ++;
				continue;
			}

			if ( self::isUnanswered( $answer ) ) {
				$verdicts[]      = [ 'ate_job_ids' => $half, 'outcome' => 'unanswered', 'code' => is_wp_error( $answer ) ? $answer->get_error_code() : null ];
				$outcome['kept'] = array_merge( $outcome['kept'], $half );
				$failing ++;
				if ( self::stopsThePass( $answer ) ) {
					$outcome['unreachable'] = true;
				}
				continue;
			}

			$verdicts[]      = [ 'ate_job_ids' => $half, 'outcome' => 'answered', 'refused' => is_wp_error( $answer ) ? $answer->get_error_code() : null ];
			$outcome['sent'] = array_merge( $outcome['sent'], $half );
		}

		JobLog::add( 'ate_cancel_split', [
			'ate_job_ids' => $ateJobIds,
			'endpoint'    => $endpoint,
			'halves'      => $halves,
			'verdicts'    => $verdicts,
		] );

		if ( $failing === count( $halves ) && ! $outcome['unreachable'] ) {
			JobLog::addError( 'ate_cancel_group_stuck', [
				'reason'      => 'both_halves_failed',
				'ate_job_ids' => $ateJobIds,
				'endpoint'    => $endpoint,
				'kept'        => 'this chunk owed, next pass in ' . self::ADMIN_RETRY_INTERVAL . ' s; the other groups go out',
			] );
		}

		return $outcome;
	}

	private static function settledByStatus( $endpoint, $force, $status ) {
		if ( in_array( $status, \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUSES_CANCELLED, true ) ) {
			return true;
		}

		if ( self::ENDPOINT_HIDE === $endpoint ) {
			return ! $force && \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_NOT_ENOUGH !== $status;
		}

		return in_array( $status, \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUSES_FINISHED, true );
	}

	private static function readStatuses( \WPML_TM_ATE_API $api, array $ateJobIds, &$reachedATE = false ) {
		try {
			$answer = $api->get_jobs( $ateJobIds, null, self::requestArgs() );
		} catch ( \Throwable $e ) {
			$answer = null;
		}

		$reachedATE = ! self::isUnanswered( $answer );
		$statuses   = is_wp_error( $answer ) || ! $reachedATE ? null : self::statusesFrom( $answer );

		JobLog::add( 'ate_cancel_status_read', [
			'ate_job_ids' => $ateJobIds,
			'statuses'    => $statuses,
			'error'       => is_wp_error( $answer ) ? [ 'code' => $answer->get_error_code(), 'message' => $answer->get_error_message() ] : null,
			'answered'    => null !== $statuses,
			'reached_ate' => $reachedATE,
			'unreadable'  => null === $statuses && ! is_wp_error( $answer ) && $reachedATE,
		] );

		return $statuses;
	}

	private static function statusesFrom( $answer ) {
		$items = is_object( $answer ) ? get_object_vars( $answer ) : $answer;
		if ( ! is_array( $items ) ) {
			return null;
		}
		if ( isset( $items['jobs'] ) && ( is_array( $items['jobs'] ) || is_object( $items['jobs'] ) ) ) {
			$items = is_object( $items['jobs'] ) ? get_object_vars( $items['jobs'] ) : $items['jobs'];
		}

		$statuses = [];
		foreach ( $items as $key => $item ) {
			$item = is_object( $item ) ? get_object_vars( $item ) : $item;
			if ( ! is_array( $item ) ) {
				continue;
			}

			$status = $item['status_id'] ?? ( $item['status'] ?? null );
			if ( ! is_numeric( $status ) ) {
				continue;
			}

			$id = is_numeric( $key ) ? (int) $key : (int) ( $item['ate_job_id'] ?? ( $item['id'] ?? 0 ) );
			if ( $id > 0 ) {
				$statuses[ $id ] = (int) $status;
			}
		}

		return $statuses ? $statuses : null;
	}

	private static function giveUp( $ateJobId, array $entry, $why, $ateStatus ) {
		$wpmlJobId = (int) ( $entry['wpml_job_id'] ?? 0 );
		$userId    = (int) ( $entry['user_id'] ?? 0 );

		JobLog::addError( 'ate_cancel_failed_job', [
			'ate_job_id'  => $ateJobId,
			'wpml_job_id' => $wpmlJobId,
			'endpoint'    => $entry['endpoint'] ?? null,
			'reason'      => $entry['reason'] ?? null,
			'why'         => $why,
			'ate_status'  => $ateStatus,
			'attempts'    => (int) ( $entry['attempts'] ?? 0 ) + 1,
			'user_id'     => $userId,
			'outcome'     => 'the job stays cancelled in WPML; ATE still holds it',
		] );

		if ( $userId <= 0 ) {
			JobLog::add( 'ate_cancel_notice_skipped', [
				'ate_job_id'  => $ateJobId,
				'wpml_job_id' => $wpmlJobId,
				'reason'      => 'no_user',
				'why'         => 'the cancel was owed by a request with no user (cron, CLI, an import)',
			] );

			return;
		}

		$queued = FailedCancelNotice::queue( $userId, [
			'ate_job_id'  => $ateJobId,
			'wpml_job_id' => $wpmlJobId,
		] );

		JobLog::add( $queued ? 'ate_cancel_notice_queued' : 'ate_cancel_notice_skipped', [
			'ate_job_id'  => $ateJobId,
			'wpml_job_id' => $wpmlJobId,
			'user_id'     => $userId,
			'reason'      => $queued ? null : 'not_stored',
			'shown'       => 'on that user\'s next admin page',
		] );
	}

	private static function settle( array $settled, array $dialled ) {
		if ( ! $settled && ! $dialled ) {
			return;
		}

		$stored = self::read();
		foreach ( $settled as $ateJobId ) {
			unset( $stored['jobs'][ (string) $ateJobId ] );
		}
		foreach ( $dialled as $ateJobId ) {
			if ( isset( $stored['jobs'][ (string) $ateJobId ] ) ) {
				$entry             = $stored['jobs'][ (string) $ateJobId ];
				$entry['attempts'] = ( isset( $entry['attempts'] ) ? (int) $entry['attempts'] : 0 ) + 1;

				$stored['jobs'][ (string) $ateJobId ] = $entry;
			}
		}
		self::write( $stored );
	}

	public static function retryOnAdminRequest() {
		$stored = self::read();

		if ( ! $stored['jobs'] ) {
			return;
		}

		if ( time() - (int) $stored['last_try'] < self::ADMIN_RETRY_INTERVAL ) {
			return;
		}

		if ( \WPML_TM_ATE_API::isUnreachableWindowLive() ) {
			return;
		}

		$api = make( \WPML_TM_ATE_API::class );
		if ( ! $api instanceof \WPML_TM_ATE_API ) {
			return;
		}

		self::retry( $api );
	}

	private static function ids( array $ateJobIds ) {
		return array_values( array_unique( array_filter( array_map( 'intval', $ateJobIds ) ) ) );
	}

	private static function read() {
		$stored = get_option( self::OPTION, [] );
		$down   = is_array( $stored ) && isset( $stored['down'] ) && is_array( $stored['down'] ) ? $stored['down'] : null;

		return [
			'jobs'     => is_array( $stored ) && isset( $stored['jobs'] ) && is_array( $stored['jobs'] ) ? $stored['jobs'] : [],
			'last_try' => is_array( $stored ) && isset( $stored['last_try'] ) ? (int) $stored['last_try'] : 0,
			'down'     => $down ? [ 'since' => (int) ( $down['since'] ?? 0 ), 'reason' => (string) ( $down['reason'] ?? '' ) ] : null,
		];
	}

	private static function write( array $stored ) {
		if ( ! $stored['jobs'] ) {
			delete_option( self::OPTION );

			return;
		}

		update_option( self::OPTION, $stored, false );
	}
}
