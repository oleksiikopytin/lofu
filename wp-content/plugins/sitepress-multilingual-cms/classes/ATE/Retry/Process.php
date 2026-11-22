<?php

namespace WPML\TM\ATE\Retry;

use WPML\Collect\Support\Collection;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\BatchResult;
use WPML\Core\Component\Translation\Domain\Entity\JobError;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\API\ClientRestrictionState;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\TM\ATE\Log\Entry;
use WPML\TM\ATE\Log\EventsTypes;
use WPML\TM\ATE\Log\Storage;
use WPML\TM\Jobs\JobLog;
use WPML\Utilities\Lock;
use WPML_TM_ATE_Job_Repository;
use function WPML\Container\make;

class Process {

	const JOBS_PROCESSED_PER_REQUEST = 10;

	const LOCK_NAME = 'ate_retry';

	const LOCK_RELEASE_TIMEOUT = MINUTE_IN_SECONDS;

	private $ateRepository;

	private $trigger;

	public function __construct(
		WPML_TM_ATE_Job_Repository $ateRepository,
		Trigger $trigger
	) {
		$this->ateRepository = $ateRepository;
		$this->trigger       = $trigger;
	}

	public function run( $jobsToProcess ) {
		if ( ! $jobsToProcess ) {
			return $this->runRetry( null );
		}

		return JobLog::runInGroup(
			JobLog::GROUP_ID_RETRIES,
			'ATE job retry (requested)',
			function () use ( $jobsToProcess ) {
				return $this->runRetry( $jobsToProcess );
			}
		);
	}

	private function runRetry( $jobsToProcess ) {
		$result = new Result();

		$lock = make( Lock::class, [ ':name' => self::LOCK_NAME ] );

		if ( ! $lock->create( self::LOCK_RELEASE_TIMEOUT ) ) {
			JobLog::add( 'ate_retry_skipped_lock_busy', [] );

			return $result;
		}

		try {
			if ( $jobsToProcess ) {
				$jobs   = wpml_collect( $jobsToProcess );
				$result = $this->isHeldByRestriction( $jobs ) ? $result : $this->retry( $result, $jobs );
			} else {
				$result = $this->runRetryInit( $result );
			}

			if ( $result->jobsToProcess->isEmpty() && $this->trigger->isRetryRequired() ) {
				$this->trigger->setLastRetry( time() );

				do_action( 'wpml_tm_ate_retry_cadence' );
			}
		} finally {
			$lock->release();
		}

		return $result;
	}

	private function runRetryInit( Result $result ) {
		$wpmlJobIds = $this->getWpmlJobIdsToRetry();

		if ( $this->trigger->isRetryRequired() && ! $wpmlJobIds->isEmpty() && ! $this->isHeldByRestriction( $wpmlJobIds ) ) {
			$result = JobLog::runInGroup(
				JobLog::GROUP_ID_RETRIES,
				'ATE job retry (cadence)',
				function () use ( $result, $wpmlJobIds ) {
					return $this->retry( $result, $wpmlJobIds );
				}
			);
		}

		return $result;
	}

	private function isHeldByRestriction( Collection $jobs ) {
		if ( ! ClientRestrictionState::isRestricted() ) {
			return false;
		}

		Storage::add(
			Entry::createForType(
				EventsTypes::JOB_RETRY,
				[
					'held'         => 'client-restricted',
					'debtor_block' => ClientRestrictionState::debtorBlock(),
					'job_ids'      => array_map( 'intval', array_values( $jobs->toArray() ) ),
				]
			),
			true
		);

		return true;
	}

	private function retry( Result $result, Collection $jobs ) {
		$featureOn = AutomaticTranslationCapabilities::shouldTranslateEverythingFresh();

		$jobsChunks = $this->withoutHeldBack( $jobs, $featureOn )->chunk( self::JOBS_PROCESSED_PER_REQUEST );
		$chunk      = $jobsChunks->isEmpty() ? wpml_collect( [] ) : $jobsChunks->shift();

		$result->processed     = $this->handleJobs( $this->stillParked( $chunk ) );
		$result->jobsToProcess = $jobsChunks->flatten( 1 );

		if ( $result->processed && $featureOn && ! AutomaticTranslationCapabilities::shouldTranslateEverythingFresh() ) {
			JobLog::add( 'ate_retry_tea_off_after_binding', [ 'job_ids' => $result->processed ] );
			do_action( 'wpml_cancel_all_automatic_jobs', make( BatchResult::class ) );
		}

		return $result;
	}

	private function withoutHeldBack( Collection $jobs, $featureOn ) {
		if ( $featureOn || $jobs->isEmpty() ) {
			return $jobs;
		}

		$heldBack = [];

		$retryable = $jobs
			->filter(
				function ( $jobId ) use ( &$heldBack ) {
					if ( Jobs::isAutomatic( (int) $jobId ) ) {
						$heldBack[] = (int) $jobId;

						return false;
					}

					return true;
				}
			)
			->values();

		if ( $heldBack ) {
			JobLog::add( 'ate_retry_tea_off_holding_parked', [ 'job_ids' => $heldBack ] );
		}

		return $retryable;
	}

	private function stillParked( Collection $chunk ) {
		return $chunk
			->filter(
				function ( $jobId ) {
					return ICL_TM_ATE_NEEDS_RETRY === Jobs::getStatus( (int) $jobId );
				}
			)
			->values();
	}

	private function getWpmlJobIdsToRetry() {
		return wpml_collect(
			$this->ateRepository
				->get_jobs_to_retry( JobError::RETRY_LIMITS[ JobError::TYPE_CREATE ] )
				->map_to_property( 'translate_job_id' )
		);
	}

	private function handleJobs( Collection $items ) {
		if ( $items->isEmpty() ) {
			return [];
		}

		do_action( 'wpml_added_translation_jobs', [ 'local' => $items->toArray() ], Jobs::SENT_RETRY );

		return $items->toArray();
	}
}
