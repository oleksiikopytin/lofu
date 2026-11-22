<?php

namespace WPML\TM\ATE\Handshake;

use WPML\Request\Adapter\Rest;
use WPML\Request\Policy\Policy;
use WPML\TM\ATE\PullDelivery\PendingJobs;

class Routes extends \WPML_TM_ATE_Required_Rest_Base {

	const WAKE    = '/ate/handshake/wake';
	const DRIVE   = '/ate/handshake/drive';
	const COLLECT = '/ate/handshake/collect';
	const RUN     = '/ate/handshake/run';
	const RETRY   = '/ate/handshake/retry';
	const RESEND  = '/ate/handshake/resend';
	const STATUS  = '/ate/handshake/status';

	const CAPABILITIES = [ 'manage_options', 'manage_translations' ];

	private $pending;

	private $marks;

	private $runnerFactory;

	private $plan;

	private $counts;

	private $creditResume;

	public function __construct( ?PendingJobs $pending = null, ?Marks $marks = null, $runnerFactory = null, $plan = null, $counts = null, ?CreditResume $creditResume = null ) {
		parent::__construct();
		$this->pending       = $pending ?: new PendingJobs();
		$this->marks         = $marks ?: new Marks();
		$this->runnerFactory = $runnerFactory ?: function () {
			return new Runner();
		};
		$this->plan          = $plan ?: [ Plan::class, 'decide' ];
		$this->counts        = $counts ?: function () {
			$counts = new \WPML\TM\ATE\AutoTranslate\Repository\CachedJobsCount(
				new \WPML\TM\ATE\AutoTranslate\Repository\JobsCount( new \WPML\TM\ATE\Jobs() )
			);

			return $counts->get( false );
		};
		$this->creditResume  = $creditResume;
	}

	public function add_hooks() {
		$this->register_routes();
		add_filter( 'rest_post_dispatch', [ $this, 'answerRefusedSignature' ], 10, 3 );
	}

	public function register_routes() {
		$this->registerMachine( self::WAKE, [ Signature::class, 'verifyWake' ], [ $this, 'wake' ], 'ATE level-handshake wake-up: HMAC-SHA256 over wake|site|timestamp|nonce with the site\'s AMS secret' );
		$this->registerMachine( self::DRIVE, [ Signature::class, 'verifyDrive' ], [ $this, 'drive' ], 'ATE level-handshake drive: HMAC-SHA256 over drive|site|timestamp|nonce with the site\'s AMS secret' );
		$this->registerMachine( self::COLLECT, [ $this, 'verifyCollect' ], [ $this, 'collect' ], 'level-handshake loopback: the one-use token the site wrote (CollectToken::consumeToken)' );

		foreach ( [ self::RUN => 'runNow', self::RETRY => 'retry', self::RESEND => 'resend' ] as $endpoint => $method ) {
			parent::register_route(
				$endpoint,
				[
					'methods'  => 'POST',
					'callback' => [ $this, $method ],
				]
			);
		}

		parent::register_route(
			self::STATUS,
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'status' ],
			]
		);
	}

	private function registerMachine( $endpoint, $verifier, $callback, $reason ) {
		parent::register_route(
			$endpoint,
			[
				'methods'             => 'POST',
				'callback'            => $callback,
				'permission_callback' => Rest::permission(
					Policy::machine( $verifier, $reason ),
					self::REST_NAMESPACE . $endpoint
				),
			]
		);
	}

	public function verifyCollect( $request ) {
		$token = is_object( $request ) && method_exists( $request, 'get_param' ) ? $request->get_param( 'token' ) : null;

		if ( CollectToken::consumeToken( $token ) ) {
			return true;
		}

		return new \WP_Error( 'handshake_collect_token', '', [ 'status' => 403 ] );
	}

	public function get_allowed_capabilities( \WP_REST_Request $request ) {
		return self::CAPABILITIES;
	}

	public function wake( $request ) {
		$ateRequestId = Signature::header( $request, Signature::HEADER_REQUEST_ID );
		$summary      = $this->pending->summary();
		$inFlight     = (int) $summary['count'];
		$plan         = (string) call_user_func( $this->plan );

		$collects = $inFlight > 0 || $this->marks->jobsMarked( Marks::FAILED_ON_SITE );
		$spawns   = $collects && Plan::SPAWNED === $plan;

		State::update(
			array_merge(
				State::inFlightSummaryChanges( $summary, $inFlight ),
				[
					'last_wake_received_at' => Clock::now(),
					'last_wake_plan'        => $plan,
				],
				$spawns ? State::spawnChanges() : []
			)
		);

		Log::write( 'wake_received', [ 'in_flight' => $inFlight, 'plan' => $plan ], null, null, $ateRequestId );

		CronTick::reconcile();

		if ( $collects ) {
			if ( Plan::BACKGROUND === $plan ) {
				Runner::runAfterResponse( Runner::TRIGGER_WAKE_BACKGROUND );
			} elseif ( $spawns ) {
				Runner::requestCollect( Runner::TRIGGER_WAKE_SPAWNED, false );
			}
		}

		return new \WP_REST_Response(
			[
				'handshake'  => Declaration::VALUE,
				'confirmed'  => true,
				'in_flight'  => $inFlight,
				'plan'       => $plan,
				'request_id' => $ateRequestId,
			],
			200
		);
	}

	public function drive( $request ) {
		$ateRequestId = Signature::header( $request, Signature::HEADER_REQUEST_ID );

		State::update( [ 'last_drive_at' => Clock::now() ] );

		$counts = $this->runner()->run( Runner::TRIGGER_DRIVE );

		Log::write(
			'drive_received',
			[
				'run_request_id' => $counts['request_id'],
				'lock_busy'      => $counts['lock_busy'],
				'written'        => $counts['written'],
				'confirmed'      => $counts['confirmed'],
				'declined'       => $counts['declined'],
				'remaining'      => $counts['remaining'],
				'budget_hit'     => $counts['budget_hit'],
			],
			null,
			null,
			$ateRequestId
		);

		return new \WP_REST_Response(
			[
				'handshake'  => Declaration::VALUE,
				'done'       => true,
				'written'    => $counts['written'],
				'confirmed'  => $counts['confirmed'],
				'declined'   => $counts['declined'],
				'remaining'  => $counts['remaining'],
				'budget_hit' => $counts['budget_hit'],
				'request_id' => $ateRequestId,
			],
			200
		);
	}

	public function collect( $request ) {
		$trigger = (string) $request->get_param( 'trigger' );
		$trigger = in_array( $trigger, Runner::TRIGGERS, true ) && Runner::TRIGGER_DRIVE !== $trigger ? $trigger : Runner::TRIGGER_WAKE_SPAWNED;

		$counts = $this->runner()->run( $trigger );

		return new \WP_REST_Response( self::publicCounts( $counts ), 200 );
	}

	public function runNow( $request ) {
		$counts = $this->runner()->run( Runner::TRIGGER_MANUAL );

		return new \WP_REST_Response( self::publicCounts( $counts ), 200 );
	}

	public function status( $request ) {
		if ( ! CreditResume::hasParkedSet( \WPML\TM\ATE\PullDelivery\State::get() ) && \WPML\TM\ATE\API\SpendCapState::isReached() ) {
			$this->creditResume()->run( false );
		}

		return new \WP_REST_Response(
			[
				'inFlight' => max( 0, (int) State::getKey( 'in_flight' ) ),
				'counts'   => call_user_func( $this->counts ),
				'stalled'  => Support\Stalled::forPage(),
				'parked'   => Support\Stalled::parkedLists(),
			],
			200
		);
	}

	public function retry( $request ) {
		$ate      = new AteClient();
		$delivery = new Delivery();
		$retried  = 0;
		$failed   = 0;

		Declaration::begin();

		try {
			foreach ( $this->marks->jobsMarked( Marks::FAILED_ON_SITE ) as $job ) {
				$answer = $ate->retryClosure( $job['ate_job_id'] );
				$ok     = $answer['code'] >= 200 && $answer['code'] < 300;

				if ( $ok ) {
					$delivery->takeBackReopened( $this->marks, $job['job_id'] );
					$retried++;
				} else {
					$failed++;
				}

				Log::write( 'retry_sent', [ 'ok' => $ok, 'http_code' => $answer['code'] ], $job['job_id'], $job['ate_job_id'] );
			}
		} finally {
			Declaration::end();
		}

		if ( $retried > 0 ) {
			State::recordInFlightSummary( $this->pending->summary() );
		}

		return new \WP_REST_Response( [ 'retried' => $retried, 'failed' => $failed ], 200 );
	}

	public function resend( $request ) {
		$jobIds = array_map(
			function ( $job ) {
				return $job['job_id'];
			},
			$this->marks->jobsMarked( Marks::NEEDS_RESEND )
		);

		if ( ! $jobIds ) {
			return new \WP_REST_Response( [ 'resent' => 0 ], 200 );
		}

		try {
			global $wpml_dic;

			$result = $wpml_dic->make( \WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobsService::class )->resend( $jobIds );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [ 'resent' => 0, 'error' => $e->getMessage() ], 200 );
		}

		$resent = isset( $result['resentJobIds'] ) && is_array( $result['resentJobIds'] ) ? $result['resentJobIds'] : [];

		foreach ( $resent as $jobId ) {
			$this->marks->clear( (int) $jobId );
		}

		return new \WP_REST_Response( [ 'resent' => count( $resent ) ], 200 );
	}

	public function answerRefusedSignature( $result, $server, $request ) {
		if ( ! is_object( $result ) || ! method_exists( $result, 'get_data' ) || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $result;
		}

		$route = '/' . self::REST_NAMESPACE;

		if ( ! in_array( $request->get_route(), [ $route . self::WAKE, $route . self::DRIVE ], true ) ) {
			return $result;
		}

		$data = $result->get_data();

		if ( ! is_array( $data ) || ! isset( $data['code'] ) || Signature::ERROR_CODE !== $data['code'] ) {
			return $result;
		}

		return new \WP_REST_Response( [ 'error' => Signature::ERROR_CODE ], 401 );
	}

	public static function url( $endpoint ) {
		return self::get_url( $endpoint );
	}

	private function runner() {
		return call_user_func( $this->runnerFactory );
	}

	private function creditResume() {
		if ( ! $this->creditResume ) {
			$this->creditResume = new CreditResume();
		}

		return $this->creditResume;
	}

	private static function publicCounts( array $counts ) {
		return array_intersect_key(
			$counts,
			array_flip( [ 'lock_busy', 'written', 'confirmed', 'declined', 'remaining', 'budget_hit', 'request_id' ] )
		);
	}
}
