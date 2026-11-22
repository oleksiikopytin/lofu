<?php

namespace WPML\TM\ATE\Handshake;

use WPML\FP\Obj;
use WPML\TM\API\Job\Map;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Download\Job;
use WPML\TM\ATE\JobError\Recorder;
use WPML\TM\ATE\Receive\JobKind;
use WPML\TM\ATE\Receive\SingleJobDelivery;
use WPML\TM\ATE\Receive\TranslationApplier;
use WPML\TM\ATE\Sync\Process;
use WPML\TM\ATE\SyncLock;
use WPML\TM\Jobs\JobLog;
use WPML\Translation\TranslateJobErrorServiceFactory;
use function WPML\Container\make;

class Delivery {

	const HOLDER = 'levelHandshake';

	private $single;

	private $jobErrorRecorder;

	public function __construct( ?SingleJobDelivery $single = null, ?Recorder $jobErrorRecorder = null ) {
		$this->single           = $single;
		$this->jobErrorRecorder = $jobErrorRecorder;
	}

	private function single() {
		if ( ! $this->single ) {
			$this->single = new SingleJobDelivery(
				make( SyncLock::class ),
				function () {
					return make( TranslationApplier::class );
				}
			);
		}

		return $this->single;
	}

	public function refusal( $jobId ) {
		$refusal = $this->single()->refusal( (int) $jobId );

		return $refusal ? (string) $refusal['outcome'] : null;
	}

	public function deliver( $jobId ) {
		self::loadWriter();

		return (string) $this->single()->deliver( (int) $jobId, self::HOLDER )['outcome'];
	}

	public static function loadWriter() {
		if ( function_exists( 'wpml_tm_save_data' ) ) {
			return;
		}

		if ( function_exists( 'tm_after_load' ) ) {
			tm_after_load();
		}

		if ( ! function_exists( 'wpml_tm_save_data' ) && defined( 'WPML_TM_PATH' ) ) {
			require_once WPML_TM_PATH . '/inc/wpml-private-actions-tm.php';
		}
	}

	public function confirmApplied( $jobId, $ateJobId ) {
		try {
			return (bool) make( \WPML\TM\API\ATE::class )->confirmApplied( (int) $jobId, (int) $ateJobId );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	public function localJobId( $ateJobId, $rid = 0 ) {
		$byAteId = $ateJobId > 0 ? (int) make( \WPML_TM_ATE_Jobs::class )->get_wpml_job_id( (int) $ateJobId ) : 0;

		if ( $byAteId > 0 ) {
			return $byAteId;
		}

		return $rid > 0 ? (int) Map::jobIdFromRid( (int) $rid ) : 0;
	}

	public function jobIdOfRid( $rid ) {
		return $rid > 0 ? (int) Map::jobIdFromRid( (int) $rid ) : 0;
	}

	public function namesAnotherOriginal( $boundJobId, $syncNamedJobId ) {
		if ( ! $syncNamedJobId || $boundJobId === $syncNamedJobId ) {
			return false;
		}

		$bound = Jobs::get( (int) $boundJobId );
		$named = Jobs::get( (int) $syncNamedJobId );

		if ( ! $bound || ! $named ) {
			return false;
		}

		$boundOriginal = (int) Obj::prop( 'original_doc_id', $bound );
		$namedOriginal = (int) Obj::prop( 'original_doc_id', $named );

		return $boundOriginal && $namedOriginal && $boundOriginal !== $namedOriginal;
	}

	public function recordWrongOriginal( $boundJobId, $ateJobId, $syncNamedJobId ) {
		$boundJobId     = (int) $boundJobId;
		$ateJobId       = (int) $ateJobId;
		$syncNamedJobId = (int) $syncNamedJobId;

		$message = sprintf(
			'The delivery of ATE job %1$d was refused: our translation system reports the job\'s source as the translation of a different content (original %2$d) than the local job holding this ATE job id (job %3$d, original %4$d). Applying it would write another original\'s translation, so nothing was written. Repair the local job-to-ATE mapping (WPML > Support > Troubleshooting > Synchronize local job ids with ATE jobs) and resend the content for translation.',
			$ateJobId,
			(int) Obj::prop( 'original_doc_id', Jobs::get( $syncNamedJobId ) ),
			$boundJobId,
			(int) Obj::prop( 'original_doc_id', Jobs::get( $boundJobId ) )
		);

		JobLog::add(
			'delivery_refused_wrong_original',
			[
				'jobId'      => $boundJobId,
				'ateJobId'   => $ateJobId,
				'namedJobId' => $syncNamedJobId,
			]
		);

		$recorder = $this->jobErrorRecorder ?: new Recorder();
		$recorder->giveUp( $boundJobId, $ateJobId, Recorder::TYPE_APPLY, $message, [ 'sync_named_job_id' => $syncNamedJobId ] );
	}

	public function isGivenUp( $jobId ) {
		return ICL_TM_ATE_UNSOLVABLE === Jobs::getStatus( (int) $jobId );
	}

	public function removeFromInFlight( $jobId, $ateJobId ) {
		make( Process::class )->reflectNotHeldByAte( (int) $jobId, (int) $ateJobId );
	}

	public function reflectCancelled( $jobId, $ateJobId ) {
		$this->removeFromInFlight( $jobId, $ateJobId );
	}

	public function recordUnsolvable( $jobId, $ateJobId, $ateStatus, $message ) {
		( new Recorder( TranslateJobErrorServiceFactory::create() ) )->record(
			(int) $jobId,
			(int) $ateJobId,
			Recorder::TYPE_SYNC,
			'' !== (string) $message ? (string) $message : 'Job marked as unsolvable by ATE',
			[ 'ateStatus' => (int) $ateStatus ]
		);
	}

	public function clearError( $jobId ) {
		( new Recorder( TranslateJobErrorServiceFactory::create() ) )->clear( (int) $jobId );
	}

	public function isTaxonomyTerm( $jobId ) {
		return JobKind::isTaxonomyTerm( (int) $jobId );
	}

	public function isTermBundle( $jobId ) {
		return JobKind::isTermBundle( (int) $jobId );
	}

	public function completeDeliveredTermJob( $jobId ) {
		if ( JobKind::isTermBundle( (int) $jobId ) ) {
			return false;
		}

		$lock = make( SyncLock::class );

		if ( ! $lock->create( self::HOLDER ) ) {
			return false;
		}

		try {
			Jobs::setStatus( (int) $jobId, ICL_TM_COMPLETE );
			JobLog::add( 'pull_collect_completed_delivered_term_job', [ 'jobId' => (int) $jobId ] );

			return true;
		} finally {
			$lock->release();
		}
	}

	public function giveUpForResend( $jobId, $ateJobId ) {
		( new Recorder() )->giveUp(
			(int) $jobId,
			(int) $ateJobId,
			Recorder::TYPE_SYNC,
			'Our translation service has no record of this job. Send the content for translation again.'
		);
	}

	public function putBackInFlight( $jobId ) {
		Jobs::setStatus( (int) $jobId, ICL_TM_IN_PROGRESS );
	}

	public function takeBackReopened( Marks $marks, $jobId ) {
		$marks->clear( (int) $jobId );
		$this->putBackInFlight( (int) $jobId );
	}

	public function announceWritten( array $written ) {
		if ( ! $written ) {
			return;
		}

		$jobs = [];

		foreach ( $written as $jobId => $ateJobId ) {
			$job            = new Job();
			$job->jobId     = (int) $jobId;
			$job->ateJobId  = (int) $ateJobId;
			$job->status    = ICL_TM_COMPLETE;
			$wpmlJob        = Jobs::get( (int) $jobId );
			$job->automatic = $wpmlJob ? (bool) Obj::propOr( false, 'automatic', $wpmlJob ) : false;
			$jobs[]         = $job;
		}

		do_action( 'wpml_tm_ate_jobs_downloaded', wpml_collect( $jobs ) );
	}
}
