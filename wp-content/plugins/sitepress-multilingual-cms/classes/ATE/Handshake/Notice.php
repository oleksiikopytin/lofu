<?php

namespace WPML\TM\ATE\Handshake;

use WPML\OutboundLinks\OutboundLinks;

class Notice implements \IWPML_Backend_Action {

	const CAPABILITIES = [ 'manage_options', 'manage_translations' ];

	const SUPPORT_URL = 'https://app.wpml.org/support';

	const KIND_FAILED = 'failed_on_site';
	const KIND_RESEND = 'needs_resend';

	private $marks;

	private static $logged = [];

	public function __construct( ?Marks $marks = null ) {
		$this->marks = $marks ?: new Marks();
	}

	public function add_hooks() {
		add_filter( 'wpml_tm_dashboard_notices', [ $this, 'addDashboardNotice' ] );
		add_action( 'admin_notices', [ $this, 'renderOnQueue' ] );
	}

	public function addDashboardNotice( $notices ) {
		$notices   = is_array( $notices ) ? $notices : [];
		$notices[] = [ $this, 'render' ];

		return $notices;
	}

	public function renderOnQueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( is_object( $screen ) && isset( $screen->id ) && false !== strpos( (string) $screen->id, 'translations-queue' ) ) {
			$this->render();
		}
	}

	public function render() {
		if ( ! self::userMayAct() ) {
			return;
		}

		$html = $this->html();

		if ( '' === $html ) {
			return;
		}

		echo wp_kses( $html, self::allowedHtml() );
		wp_print_inline_script_tag( $this->script() );
	}

	private static function allowedHtml() {
		return [
			'div'    => [
				'class'     => true,
				'data-kind' => true,
			],
			'p'      => [],
			'form'   => [
				'method' => true,
				'target' => true,
				'action' => true,
				'style'  => true,
			],
			'input'  => [
				'type'  => true,
				'name'  => true,
				'value' => true,
			],
			'button' => [
				'type'                        => true,
				'class'                       => true,
				'data-wpml-ate-handshake-url' => true,
			],
		];
	}

	public function html() {
		$failed = $this->marks->jobsMarked( Marks::FAILED_ON_SITE );
		$resend = $this->marks->jobsMarked( Marks::NEEDS_RESEND );

		if ( ! $failed && ! $resend ) {
			return '';
		}

		$html = '';

		if ( $failed ) {
			$html .= $this->failedHtml( $failed );
			self::logShown( self::KIND_FAILED, count( $failed ) );
		}

		if ( $resend ) {
			$html .= $this->resendHtml( $resend );
			self::logShown( self::KIND_RESEND, count( $resend ) );
		}

		return $html;
	}

	private function failedHtml( array $jobs ) {
		$count = count( $jobs );

		$text = sprintf(
			/* translators: Headline of the Translation delivery box, and of the admin notice that leads to it. %d: a number of translations. */
			_n( '%d translation could not be applied on this site.', '%d translations could not be applied on this site.', $count, 'sitepress' ),
			$count
		);

		$html  = '<div class="notice notice-error wpml-ate-handshake-notice" data-kind="' . esc_attr( self::KIND_FAILED ) . '">';
		$html .= '<p>' . esc_html( $text ) . ' ' . esc_html__( 'Retry asks our translation service to send them again. If that does not help, report it to our support team.', 'sitepress' ) . '</p>';
		$html .= '<p>';
		$html .= $this->actionButton( Routes::RETRY, __( 'Retry', 'sitepress' ), 'button button-primary' );
		$html .= ' ' . $this->reportForm( $jobs );
		$html .= '</p>';

		return $html . '</div>';
	}

	private function resendHtml( array $jobs ) {
		$count = count( $jobs );

		$text = sprintf(
			/* translators: %d: the number of translations. */
			_n( '%d translation needs to be sent again.', '%d translations need to be sent again.', $count, 'sitepress' ),
			$count
		);

		$html  = '<div class="notice notice-warning wpml-ate-handshake-notice" data-kind="' . esc_attr( self::KIND_RESEND ) . '">';
		$html .= '<p>' . esc_html( $text ) . ' ' . esc_html__( 'Our translation service has no record of them.', 'sitepress' ) . '</p>';
		$html .= '<p>' . $this->actionButton( Routes::RESEND, __( 'Resend', 'sitepress' ), 'button button-primary' ) . '</p>';

		return $html . '</div>';
	}

	private function actionButton( $endpoint, $label, $class ) {
		return '<button type="button" class="' . esc_attr( $class ) . '" data-wpml-ate-handshake-url="' . esc_url( Routes::url( $endpoint ) ) . '">' . esc_html( $label ) . '</button>';
	}

	private function reportForm( array $jobs ) {
		$url   = OutboundLinks::to(
			self::SUPPORT_URL,
			[
				'medium'   => 'notice',
				'campaign' => 'translations-not-applied',
			]
		);
		$parts = wp_parse_url( $url );
		$query = [];

		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		$query['ate_jobs'] = implode(
			',',
			array_map(
				function ( $job ) {
					return (int) $job['ate_job_id'];
				},
				$jobs
			)
		);

		$action = strtok( $url, '?' );
		$html   = '<form method="get" target="_blank" action="' . esc_url( (string) $action ) . '" style="display:inline">';

		foreach ( $query as $name => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '">';
		}

		return $html . '<button type="submit" class="button">' . esc_html__( 'Report', 'sitepress' ) . '</button></form>';
	}

	private function script() {
		$nonce = wp_create_nonce( 'wp_rest' );

		return '(function(){document.querySelectorAll("[data-wpml-ate-handshake-url]").forEach(function(b){b.addEventListener("click",function(){b.disabled=true;fetch(b.getAttribute("data-wpml-ate-handshake-url"),{method:"POST",credentials:"same-origin",headers:{"X-WP-Nonce":' . wp_json_encode( $nonce ) . '}}).then(function(){window.location.reload();},function(){b.disabled=false;});});});})();';
	}

	private static function logShown( $kind, $count ) {
		if ( isset( self::$logged[ $kind ] ) ) {
			return;
		}

		self::$logged[ $kind ] = true;

		Log::write( 'notice_shown', [ 'kind' => $kind, 'count' => (int) $count ] );
	}

	private static function userMayAct() {
		foreach ( self::CAPABILITIES as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	public static function resetForTests() {
		self::$logged = [];
	}
}
