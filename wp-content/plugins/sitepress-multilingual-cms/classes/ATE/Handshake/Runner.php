<?php

namespace WPML\TM\ATE\Handshake;

use WPML\TM\ATE\CollectLock;
use WPML\TM\ATE\PullDelivery\Cadence;
use WPML\TM\ATE\PullDelivery\PendingJobs;
use WPML\TM\ATE\PullDelivery\State as PullState;
use WPML\TM\ATE\Receive\SingleJobDelivery;
use WPML\TM\ATE\Sync\Process;
use WPML\TM\Jobs\JobLog;

class Runner {

	const TRIGGER_WAKE_BACKGROUND = 'wake_background';
	const TRIGGER_WAKE_SPAWNED    = 'wake_spawned';
	const TRIGGER_DRIVE           = 'drive';
	const TRIGGER_ADMIN           = 'admin';
	const TRIGGER_CRON            = 'cron';
	const TRIGGER_FRONT           = 'front';
	const TRIGGER_MANUAL          = 'manual';
	const TRIGGER_DASHBOARD       = 'dashboard';

	const TRIGGERS = [
		self::TRIGGER_WAKE_BACKGROUND,
		self::TRIGGER_WAKE_SPAWNED,
		self::TRIGGER_DRIVE,
		self::TRIGGER_ADMIN,
		self::TRIGGER_CRON,
		self::TRIGGER_FRONT,
		self::TRIGGER_MANUAL,
		self::TRIGGER_DASHBOARD,
	];

	const READY_STATUSES = [
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_TRANSLATED,
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERING,
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_EDITED,
	];

	const CLOSURE_FAILED_ON_SITE = 'failed_on_site';
	const CLOSURE_NOT_NEEDED     = 'not_needed_by_site';
	const CLOSURE_DECLINED       = 'declined';

	const POST_RESPONSE_PRIORITY = 2;

	const REARM_LOOPBACK = 'loopback';
	const REARM_CRON     = 'cron';
	const REARM_NONE     = 'none';

	public static $jobLog = true;

	private $lock;

	private $ate;

	private $delivery;

	private $decline;

	private $marks;

	private $pending;

	private $budget;

	private $maxJobs;

	private $counts = [];

	private $reported = [];

	private $written = [];

	private $creditResume;

	private $deadline = 0.0;

	const BACKOFF_TRIGGERS = [ self::TRIGGER_ADMIN, self::TRIGGER_CRON, self::TRIGGER_FRONT, self::TRIGGER_DASHBOARD ];

	private $manualBackoff;

	private $leftOutBackedOff = false;

	private $trigger = '';

	public function __construct(
		?CollectLock $lock = null,
		?AteClient $ate = null,
		?Delivery $delivery = null,
		?Decline $decline = null,
		?Marks $marks = null,
		?PendingJobs $pending = null,
		$budget = null,
		$maxJobs = null,
		?CreditResume $creditResume = null,
		?\WPML\TM\ATE\Sync\ManualSyncBackoff $manualBackoff = null
	) {
		$this->manualBackoff = $manualBackoff ?: new \WPML\TM\ATE\Sync\ManualSyncBackoff();
		$this->creditResume = $creditResume ?: new CreditResume();
		$this->lock     = $lock ?: new CollectLock();
		$this->ate      = $ate ?: new AteClient();
		$this->delivery = $delivery ?: new Delivery();
		$this->marks    = $marks ?: new Marks();
		$this->decline  = $decline ?: new Decline( $this->ate, $this->marks );
		$this->pending  = $pending ?: new PendingJobs();
		$this->budget   = $budget ?: [ Cadence::class, 'collectBudgetSeconds' ];
		$this->maxJobs  = $maxJobs ?: [ Cadence::class, 'maxJobsPerRun' ];
	}

	public static function spawn( $trigger ) {
		$plan = Plan::decide();

		if ( Plan::BACKGROUND === $plan ) {
			self::runAfterResponse( $trigger );

			return Plan::BACKGROUND;
		}

		if ( Plan::SPAWNED === $plan ) {
			self::requestCollect( $trigger );

			return Plan::SPAWNED;
		}

		( new self() )->run( $trigger );

		return 'inline';
	}

	public static function runAfterResponse( $trigger ) {
		add_action(
			'shutdown',
			function () use ( $trigger ) {
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					if ( function_exists( 'ignore_user_abort' ) ) {
						ignore_user_abort( true );
					}

					fastcgi_finish_request();
				}

				( new self() )->run( $trigger );
			},
			self::POST_RESPONSE_PRIORITY
		);
	}

	public static function requestCollect( $trigger, $recordSpawn = true ) {
		$changes = $recordSpawn ? State::spawnChanges() : [];

		if ( $changes ) {
			State::update( $changes );
		}

		wp_remote_post(
			Routes::url( Routes::COLLECT ),
			[
				'timeout'    => 1,
				'blocking'   => false,
				'sslverify'  => false,
				'user-agent' => Plan::LOOPBACK_USER_AGENT,
				'body'       => [
					'token'   => CollectToken::mint(),
					'trigger' => (string) $trigger,
				],
			]
		);
	}

	public function run( $trigger ) {
		$trigger      = in_array( $trigger, self::TRIGGERS, true ) ? $trigger : self::TRIGGER_MANUAL;
		$this->counts = self::emptyCounts();

		$key = $this->lock->create();

		if ( ! $key ) {
			$this->counts['lock_busy'] = true;
			Log::write( 'run_start', [ 'trigger' => $trigger, 'lock_busy' => true ] );

			return $this->counts;
		}

		$requestId                  = Declaration::begin();
		$this->counts['request_id'] = $requestId;
		$this->trigger              = $trigger;
		$this->leftOutBackedOff     = $this->leavesOutBackedOff( $trigger );
		$this->reported             = [];
		$this->written              = [];
		$ownsJobLog                 = $this->openJobLog( $trigger );

		try {
			Log::write( 'run_start', [ 'trigger' => $trigger ] );
			State::update(
				[
					'last_run_at'      => Clock::now(),
					'last_run_trigger' => $trigger,
					'spawned_at'       => 0,
				]
			);

			$this->deadline = Clock::exact() + max( 1, (int) call_user_func( $this->budget ) );
			$this->collect( (string) $key, $this->deadline );
		} catch ( \Throwable $e ) {
			$this->counts['error'] = $e->getMessage();
		} finally {
			Declaration::end();
			$this->lock->release();
			$this->closeJobLog( $ownsJobLog );
		}

		return $this->finish( $trigger );
	}

	private function collect( $key, $deadline ) {
		$inFlight  = $this->ate->inFlight( $this->leftOutBackedOff );
		$ateJobIds = isset( $inFlight['ateJobIds'] ) ? array_values( array_map( 'intval', $inFlight['ateJobIds'] ) ) : [];
		$ateJobIds = array_values( array_unique( array_merge( $this->failedOnSiteAteIds(), $ateJobIds ) ) );
		$chunks    = array_chunk( $ateJobIds, max( 1, self::chunkSize() ) );
		$maxJobs   = max( 1, (int) call_user_func( $this->maxJobs ) );
		$listedAll = true;

		foreach ( $chunks as $index => $chunk ) {
			if ( Clock::exact() >= $deadline || $this->processed() >= $maxJobs ) {
				$this->counts['budget_hit'] = true;
				$this->counts['work_left']  = true;
				$listedAll                  = false;
				break;
			}

			$answer = $this->ate->syncAll( $chunk, $inFlight );

			$this->lock->create( $key );

			if ( is_wp_error( $answer ) || ! is_object( $answer ) ) {
				$this->counts['ate_error'] = is_wp_error( $answer ) ? (string) $answer->get_error_code() : 'no_answer';
				$listedAll                 = false;
				break;
			}

			$this->counts['listed'] += count( $chunk );

			$ready = self::oldestJobFirst( $this->readAnswer( $answer ) );

			foreach ( $ready as $position => $item ) {
				if ( Clock::exact() >= $deadline || $this->processed() >= $maxJobs ) {
					$this->counts['budget_hit'] = true;
					$this->counts['work_left']  = true;
					$listedAll                  = $listedAll && ( count( $chunks ) - 1 === $index );
					break 2;
				}

				$this->writeOne( $item );
			}
		}

		if ( $listedAll ) {
			$this->handReported();
			$this->markManualSlotUsed();
		}
	}

	private function leavesOutBackedOff( $trigger ) {
		if ( ! in_array( $trigger, self::BACKOFF_TRIGGERS, true ) ) {
			return false;
		}

		return ! $this->manualBackoff->isDue();
	}

	private function markManualSlotUsed() {
		if ( ! $this->leftOutBackedOff && in_array( $this->trigger, self::BACKOFF_TRIGGERS, true ) ) {
			$this->manualBackoff->markSynced();
		}
	}

	private function readAnswer( $answer ) {
		$ready = [];
		$items = isset( $answer->items ) && is_array( $answer->items ) ? $answer->items : [];
		$jobIds = $this->localJobIds( $items );
		$marks  = $jobIds ? $this->marks->marksOf( array_values( array_filter( $jobIds ) ) ) : [];

		foreach ( $items as $index => $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}

			$ateJobId = isset( $item->ate_id ) ? (int) $item->ate_id : 0;
			$rid      = isset( $item->id ) ? (int) $item->id : 0;
			$status   = isset( $item->status ) ? (int) $item->status : -1;
			$closure  = isset( $item->closure ) && is_string( $item->closure ) ? $item->closure : '';
			$jobId    = isset( $jobIds[ $index ] ) ? $jobIds[ $index ] : 0;
			$mark     = self::markOn( $marks, $jobId, $ateJobId );

			if ( '' !== $closure ) {
				if ( null === $mark || ! self::markIsFor( $mark['mark'], $closure ) ) {
					$this->takeClosure( $jobId, $ateJobId, $closure );
				}
				continue;
			}

			if ( in_array( $status, \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUSES_CANCELLED, true ) && $jobId > 0 ) {
				$this->delivery->reflectCancelled( $jobId, $ateJobId );
				$this->counts['cancelled_by_ate']++;
			}

			if ( \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_CANCELED_BY_USER_SHOULD_HIDE === $status ) {
				if ( $jobId > 0 ) {
					$this->delivery->clearError( $jobId );
				}
				continue;
			}

			if ( \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_CANCELLED === $status ) {
				continue;
			}

			$unsolvable = ! empty( $item->is_unsolvable )
				|| in_array( $status, [ \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_FAILED, \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_MISSING_LANGUAGE_MAPPING ], true );

			if ( $unsolvable ) {
				$this->report( 'unsolvable_job_ids', $jobId );

				if ( $jobId > 0 ) {
					$this->delivery->recordUnsolvable( $jobId, $ateJobId, $status, isset( $item->message ) && is_string( $item->message ) ? $item->message : '' );
				}
			} elseif ( \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERED === $status && ! ( $jobId > 0 && $this->delivery->isTermBundle( $jobId ) ) ) {
				if ( $jobId > 0 && $this->delivery->isTaxonomyTerm( $jobId ) && $this->delivery->completeDeliveredTermJob( $jobId ) ) {
					$this->counts['terms_completed']++;
				}
			} elseif ( \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_NOT_ENOUGH === $status ) {
				$this->report( 'insufficient_balance_job_ids', $jobId );
				$this->report( 'insufficient_balance_ate_job_ids', $ateJobId );
			} elseif ( \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_WAITING_AUTOMATIC_PAYMENT === $status ) {
				$this->report( 'payment_pending_job_ids', $jobId );
				$this->report( 'payment_pending_ate_job_ids', $ateJobId );
			} elseif ( ( in_array( $status, self::READY_STATUSES, true ) || \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERED === $status ) && $jobId > 0 ) {
				if ( null !== $mark && ! $this->takeReopened( $jobId, $ateJobId, $mark, self::reopenedAt( $item ) ) ) {
					continue;
				}

				$ready[] = [
					'ate_job_id' => $ateJobId,
					'rid'        => $rid,
					'job_id'     => $jobId,
				];
			}
		}

		$this->counts['ready'] += count( $ready );

		$unknown = isset( $answer->unknown_ids ) && is_array( $answer->unknown_ids ) ? $answer->unknown_ids : [];

		foreach ( $unknown as $ateJobId ) {
			$this->takeUnknown( (int) $ateJobId );
		}

		return $ready;
	}

	private function takeClosure( $jobId, $ateJobId, $closure ) {
		$marks = [
			self::CLOSURE_FAILED_ON_SITE => Marks::FAILED_ON_SITE,
			self::CLOSURE_NOT_NEEDED     => Marks::NOT_NEEDED,
			self::CLOSURE_DECLINED       => Marks::DECLINED,
		];

		if ( ! isset( $marks[ $closure ] ) || $jobId < 1 ) {
			Log::write( 'closure_seen', [ 'closure' => $closure, 'taken' => false ], $jobId ?: null, $ateJobId );

			return;
		}

		$this->marks->set( $jobId, $ateJobId, $marks[ $closure ] );
		$this->delivery->removeFromInFlight( $jobId, $ateJobId );
		$this->counts['closures']++;

		Log::write( 'closure_seen', [ 'closure' => $closure, 'taken' => true ], $jobId, $ateJobId );
	}

	private function failedOnSiteAteIds() {
		$ids = [];

		foreach ( $this->marks->jobsMarked( Marks::FAILED_ON_SITE ) as $job ) {
			if ( (int) $job['ate_job_id'] > 0 ) {
				$ids[] = (int) $job['ate_job_id'];
			}
		}

		return $ids;
	}

	private function localJobIds( array $items ) {
		$jobIds = [];

		foreach ( $items as $index => $item ) {
			if ( is_object( $item ) ) {
				$jobIds[ $index ] = $this->delivery->localJobId(
					isset( $item->ate_id ) ? (int) $item->ate_id : 0,
					isset( $item->id ) ? (int) $item->id : 0
				);
			}
		}

		return $jobIds;
	}

	private static function markOn( array $marks, $jobId, $ateJobId ) {
		if ( $jobId < 1 || ! isset( $marks[ $jobId ] ) ) {
			return null;
		}

		return (int) $marks[ $jobId ]['ate_job_id'] === (int) $ateJobId ? $marks[ $jobId ] : null;
	}

	private static function markIsFor( $mark, $closure ) {
		$byClosure = [
			self::CLOSURE_FAILED_ON_SITE => Marks::FAILED_ON_SITE,
			self::CLOSURE_NOT_NEEDED     => Marks::NOT_NEEDED,
			self::CLOSURE_DECLINED       => Marks::DECLINED,
		];

		return isset( $byClosure[ $closure ] ) && $byClosure[ $closure ] === $mark;
	}

	private function takeReopened( $jobId, $ateJobId, array $mark, $reopenedAt ) {
		if ( Marks::FAILED_ON_SITE !== $mark['mark'] || $reopenedAt < 1 || $reopenedAt <= (int) $mark['marked_at'] ) {
			return false;
		}

		$this->delivery->takeBackReopened( $this->marks, $jobId );
		$this->counts['reopened']++;

		Log::write(
			'mark_cleared_by_reopen',
			[
				'mark'        => $mark['mark'],
				'marked_at'   => (int) $mark['marked_at'],
				'reopened_at' => (int) $reopenedAt,
			],
			$jobId,
			$ateJobId
		);

		return true;
	}

	private static function reopenedAt( $item ) {
		if ( ! isset( $item->reopened_at ) ) {
			return 0;
		}

		$value = $item->reopened_at;

		if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
			$seconds = (float) $value;

			return (int) ( $seconds > 99999999999 ? floor( $seconds / 1000 ) : floor( $seconds ) );
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return 0;
		}

		$time = strtotime( $value );

		return false === $time ? 0 : (int) $time;
	}

	private function takeUnknown( $ateJobId ) {
		$jobId = $this->delivery->localJobId( $ateJobId );

		if ( $jobId > 0 ) {
			$this->marks->set( $jobId, $ateJobId, Marks::NEEDS_RESEND );
			$this->delivery->giveUpForResend( $jobId, $ateJobId );
		}

		$this->counts['unknown']++;

		Log::write( 'unknown_seen', [ 'taken' => $jobId > 0 ], $jobId ?: null, $ateJobId );
	}

	private function writeOne( array $item ) {
		$jobId    = $item['job_id'];
		$ateJobId = $item['ate_job_id'];

		$refusal = $this->delivery->refusal( $jobId );

		if ( SingleJobDelivery::ALREADY_APPLIED === $refusal ) {
			$this->confirmEarlierWrite( $jobId, $ateJobId );

			return;
		}

		if ( null !== $refusal ) {
			$this->declineRefusal( $jobId, $ateJobId, $refusal );

			return;
		}

		$syncNamedJobId = $this->delivery->jobIdOfRid( $item['rid'] );

		if ( $this->delivery->namesAnotherOriginal( $jobId, $syncNamedJobId ) ) {
			$this->delivery->recordWrongOriginal( $jobId, $ateJobId, $syncNamedJobId );
			$this->declineRefusal( $jobId, $ateJobId, SingleJobDelivery::WRONG_ORIGINAL );

			return;
		}

		$this->counts['fetched']++;
		Log::write( 'fetch', [], $jobId, $ateJobId );

		try {
			$outcome = $this->delivery->deliver( $jobId );
		} catch ( \Throwable $e ) {
			Log::write( 'fetch', [ 'outcome' => 'error', 'message' => $e->getMessage() ], $jobId, $ateJobId );

			if ( $this->delivery->isGivenUp( $jobId ) && $this->wasDeclinedByWrite( $jobId ) ) {
				$this->counts['declined']++;
			}

			return;
		}

		if ( SingleJobDelivery::APPLIED === $outcome ) {
			if ( $this->delivery->isGivenUp( $jobId ) && $this->wasDeclinedByWrite( $jobId ) ) {
				$this->counts['declined']++;

				return;
			}

			$this->counts['written']++;
			$this->counts['confirmed']++;
			$this->written[ $jobId ] = $ateJobId;
			Log::write( 'written', [], $jobId, $ateJobId );
			Log::write( 'confirmed', [], $jobId, $ateJobId );

			return;
		}

		if ( SingleJobDelivery::ALREADY_APPLIED === $outcome ) {
			$this->confirmEarlierWrite( $jobId, $ateJobId );

			return;
		}

		if ( null !== Decline::reasonFor( $outcome ) ) {
			$this->declineRefusal( $jobId, $ateJobId, $outcome );

			return;
		}

		Log::write( 'fetch', [ 'outcome' => $outcome ], $jobId, $ateJobId );
	}

	private function wasDeclinedByWrite( $jobId ) {
		$marks = $this->marks->marksOf( [ $jobId ] );

		return Marks::DECLINED === ( isset( $marks[ $jobId ]['mark'] ) ? $marks[ $jobId ]['mark'] : null );
	}

	private function confirmEarlierWrite( $jobId, $ateJobId ) {
		$confirmed = $this->delivery->confirmApplied( $jobId, $ateJobId );

		$this->counts['confirmed_earlier']++;

		if ( $confirmed ) {
			$this->counts['confirmed']++;
		}

		Log::write( 'confirmed', [ 'already_applied' => true, 'ok' => $confirmed ], $jobId, $ateJobId );
	}

	private function declineRefusal( $jobId, $ateJobId, $outcome ) {
		$reason = Decline::reasonFor( $outcome );

		if ( null === $reason ) {
			return;
		}

		if ( $this->decline->send( $ateJobId, $jobId, $reason ) ) {
			$this->counts['declined']++;
		} else {
			$this->counts['decline_failed']++;
		}
	}

	private static function oldestJobFirst( array $ready ) {
		$keyed = [];

		foreach ( array_values( $ready ) as $index => $item ) {
			$keyed[] = [ (int) $item['job_id'], $index, $item ];
		}

		usort(
			$keyed,
			function ( $a, $b ) {
				return [ $a[0], $a[1] ] <=> [ $b[0], $b[1] ];
			}
		);

		return array_column( $keyed, 2 );
	}

	private function report( $key, $id ) {
		if ( $id > 0 ) {
			$this->reported[ $key ][] = (int) $id;
		}
	}

	private function handReported() {
		$lists = [
			'unsolvable_job_ids'               => [],
			'insufficient_balance_job_ids'     => [],
			'insufficient_balance_ate_job_ids' => [],
			'payment_pending_job_ids'          => [],
			'payment_pending_ate_job_ids'      => [],
		];
		$lists = array_merge( $lists, $this->reported );

		$this->putFirstParkedSetOnFile( $lists );

		$this->inferSpendCap( $lists['insufficient_balance_ate_job_ids'] );

		$state = PullState::getFresh();

		foreach ( [ 'insufficient_balance' => 'insufficient_balance_ate_job_ids', 'payment_pending' => 'payment_pending_ate_job_ids' ] as $prefix => $key ) {
			$before = (int) $state[ $prefix . '_since' ];
			$listed = ! empty( $lists[ $key ] );

			$lists[ $prefix . '_since' ] = $listed ? ( $before > 0 && ! empty( $state[ $key ] ) ? $before : Clock::now() ) : 0;
		}

		PullState::update( $lists, $state );
	}

	private function putFirstParkedSetOnFile( array $lists ) {
		if ( ! $lists['insufficient_balance_ate_job_ids'] || PullState::getFresh()['insufficient_balance_ate_job_ids'] ) {
			return;
		}

		PullState::update(
			[
				'insufficient_balance_job_ids'     => $lists['insufficient_balance_job_ids'],
				'insufficient_balance_ate_job_ids' => $lists['insufficient_balance_ate_job_ids'],
			]
		);
	}

	private function inferSpendCap( array $parkedAteIds ) {
		if ( ! $parkedAteIds ) {
			return;
		}

		try {
			$credits = $this->readCredits();
		} catch ( \Throwable $e ) {
			$credits = null;
		}

		\WPML\TM\ATE\API\SpendCapState::inferFromParkedJobs( is_array( $credits ) ? $credits : [], $parkedAteIds );
	}

	protected function readCredits() {
		return \WPML\TM\API\ATE\Account::getCredits( true )->getOrElse( null );
	}

	private function processed() {
		return $this->counts['fetched'] + $this->counts['declined'] + $this->counts['decline_failed'] + $this->counts['confirmed_earlier'];
	}

	private function finish( $trigger ) {
		$summary = $this->pending->summary();

		$this->counts['remaining'] = (int) $summary['count'];

		State::update(
			array_merge(
				State::inFlightSummaryChanges( $summary ),
				[
					'last_run_at'        => Clock::now(),
					'last_run_trigger'   => $trigger,
					'last_run_work_left' => (bool) $this->counts['work_left'],
				]
			)
		);

		$this->delivery->announceWritten( $this->written );

		Log::write(
			'run_end',
			array_merge( [ 'trigger' => $trigger ], array_diff_key( $this->counts, [ 'request_id' => true ] ) ),
			null,
			null,
			$this->counts['request_id']
		);

		if ( $this->counts['work_left'] && self::TRIGGER_DRIVE !== $trigger ) {
			$this->rearm( $trigger );
		} else {
			self::logRearm( self::REARM_NONE, $trigger, (bool) $this->counts['work_left'] );
		}

		$this->resumeParked();

		return $this->counts;
	}

	private function resumeParked() {
		if ( $this->counts['budget_hit'] || Clock::exact() >= $this->deadline ) {
			return;
		}

		$this->creditResume->run( false );
	}

	protected function rearm( $trigger ) {
		if ( ! $this->counts['work_left'] || ! $this->counts['budget_hit'] ) {
			self::logRearm( self::REARM_NONE, $trigger, (bool) $this->counts['work_left'] );

			return self::REARM_NONE;
		}

		Plan::reprobeIfSpawnUnanswered();

		$verdict = PullState::getKey( 'loopback_ok' );
		$probed  = false;

		if ( ! is_bool( $verdict ) ) {
			$verdict = (bool) $this->probeLoopback();
			$probed  = true;
		}

		if ( $verdict ) {
			$this->chainOverLoopback( self::TRIGGER_WAKE_BACKGROUND === $trigger ? self::TRIGGER_WAKE_SPAWNED : $trigger );
			self::logRearm( self::REARM_LOOPBACK, $trigger, true, [ 'probed' => $probed ] );

			return self::REARM_LOOPBACK;
		}

		$due = CronTick::openForWorkLeft();
		self::logRearm( self::REARM_CRON, $trigger, true, [ 'probed' => $probed, 'tick_due' => $due ] );

		return self::REARM_CRON;
	}

	protected function probeLoopback() {
		return Plan::loopbackVerdict();
	}

	protected function chainOverLoopback( $trigger ) {
		self::requestCollect( $trigger );
	}

	private static function logRearm( $branch, $trigger, $workLeft, array $extra = [] ) {
		Log::write(
			'rearm',
			array_merge(
				[
					'branch'    => $branch,
					'trigger'   => $trigger,
					'work_left' => (bool) $workLeft,
				],
				$extra
			)
		);
	}

	private function openJobLog( $trigger ) {
		if ( ! self::$jobLog ) {
			return false;
		}

		$owns = JobLog::maybeInitBackgroundRequest( 'wpml-ate-handshake' );
		JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE level handshake run', [ 'trigger' => $trigger ] );

		return (bool) $owns;
	}

	private function closeJobLog( $owns ) {
		if ( ! self::$jobLog ) {
			return;
		}

		JobLog::finishCurrentGroup();

		if ( $owns ) {
			JobLog::flushBackgroundRequest();
		}
	}

	private static function chunkSize() {
		$size = apply_filters( 'wpml_ate_sync_all_chunk', Process::SYNC_ALL_CHUNK );

		return ( is_int( $size ) && $size >= 1 ) ? $size : Process::SYNC_ALL_CHUNK;
	}

	private static function emptyCounts() {
		return [
			'lock_busy'         => false,
			'written'           => 0,
			'confirmed'         => 0,
			'confirmed_earlier' => 0,
			'declined'          => 0,
			'decline_failed'    => 0,
			'fetched'           => 0,
			'listed'            => 0,
			'ready'             => 0,
			'unknown'           => 0,
			'closures'          => 0,
			'cancelled_by_ate'  => 0,
			'terms_completed'   => 0,
			'reopened'          => 0,
			'budget_hit'        => false,
			'work_left'         => false,
			'remaining'         => 0,
			'request_id'        => '',
		];
	}
}
