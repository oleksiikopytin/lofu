<?php

namespace WPML\TM\ATE\Sync;

use WPML\TM\ATE\Jobs;
use WPML\TM\Jobs\JobLog;
use WPML\Translation\CancelJobsServiceFactory;

class OrphanedJobs {

	const BATCH  = 50;
	const LOG_ID = 'ate_sync_orphaned_jobs_closed';

	private $jobs;

	private $cancel;

	public function __construct( $jobs = null, $cancel = null ) {
		$this->jobs   = $jobs;
		$this->cancel = $cancel;
	}

	public function close() {
		try {
			$jobs = $this->jobs ?: new Jobs();
			$rows = $jobs->getOrphanedPendingJobs( self::BATCH );

			if ( ! $rows ) {
				return 0;
			}

			$jobIds    = [];
			$ateJobIds = [];
			foreach ( $rows as $row ) {
				$jobIds[]    = (int) $row->job_id;
				$ateJobIds[] = (int) $row->editor_job_id;
			}

			JobLog::maybeInitRequest();
			JobLog::createNewGroup( JobLog::GROUP_ID_SYNC_JOBS, 'ATE jobs whose original is gone, closed' );
			JobLog::add( self::LOG_ID, [ 'job_ids' => $jobIds, 'ate_job_ids' => $ateJobIds ] );

			$cancel = $this->cancel ?: function ( array $ids ) {
				return CancelJobsServiceFactory::create()->cancelJobs( $ids );
			};
			$result = call_user_func( $cancel, $jobIds );

			JobLog::finishCurrentGroup();

			return is_array( $result ) && isset( $result['cancelledJobIds'] ) ? count( $result['cancelledJobIds'] ) : 0;
		} catch ( \Throwable $e ) {
			JobLog::addError( self::LOG_ID . '_failed', [ 'message' => $e->getMessage() ] );
			JobLog::finishCurrentGroup();

			return 0;
		}
	}
}
