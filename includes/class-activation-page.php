<?php
/**
 * ActivationPage
 *
 * Public, unauthenticated self-service account activation at:
 *
 *   GET  /activeren            — ask for an email address
 *   POST /activeren            — mail an activation link, if the address is known
 *   GET  /activeren/{token}    — "who are you?" for the people on that address
 *   POST /activeren/{token}    — create the account, redirect to set a password
 *
 * The response to POST /activeren is IDENTICAL whether or not the address is known.
 * Do not add a "no member found" message: that turns this page into a membership
 * oracle for anyone with a list of email addresses.
 *
 * All logic lives in ActivationService; this class only routes and renders.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

use Rondo\Pages\PublicPageChrome;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ActivationPage {

	const NONCE_ACTION = 'rondo_activate';

	public function __construct() {
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ], 0 );
	}

	/**
	 * `/activeren` and `/activeren/{64-char-hex-token}`.
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule( '^activeren/?$', 'index.php?rondo_activation=1', 'top' );
		add_rewrite_rule( '^activeren/([a-f0-9]{64})/?$', 'index.php?rondo_activation_token=$matches[1]', 'top' );
	}

	/**
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'rondo_activation';
		$vars[] = 'rondo_activation_token';
		return $vars;
	}

	/**
	 * Dispatch. Fires at template_redirect priority 0, before the React SPA catch-all,
	 * and always exits so the SPA never renders over us.
	 */
	public function handle_request() {
		$token = get_query_var( 'rondo_activation_token' );

		if ( ! empty( $token ) ) {
			$this->handle_token_request( sanitize_key( $token ) );
			exit;
		}

		if ( ! get_query_var( 'rondo_activation' ) ) {
			return; // Not our request.
		}

		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			$this->handle_request_link();
			exit;
		}

		$this->render_email_form();
		exit;
	}

	/**
	 * POST /activeren — mail a link, and say the same thing either way.
	 */
	private function handle_request_link() {
		check_admin_referer( self::NONCE_ACTION );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$ip    = ActivationService::client_ip();

		// An invalid address never reaches the limiter or the database.
		if ( ! is_email( $email ) ) {
			$this->render_email_form( 'Vul een geldig e-mailadres in.' );
			return;
		}

		if ( ActivationService::is_rate_limited( $email, $ip ) ) {
			$this->render_confirmation();
			return;
		}

		ActivationService::record_attempt( $email, $ip );

		$persons = ActivationService::persons_for_email( $email );

		if ( ! empty( $persons ) ) {
			ActivationService::send_activation_email( $email, ActivationService::create_token( $email ) );
		}

		// Identical response whether or not anyone matched.
		$this->render_confirmation();
	}

	/**
	 * GET/POST /activeren/{token}.
	 *
	 * @param string $token Raw token from the URL.
	 */
	private function handle_token_request( string $token ) {
		$email = ActivationService::email_for_token( $token );

		if ( $email === null ) {
			$this->render_error( 'Deze activatielink is verlopen of ongeldig. Vraag een nieuwe aan.' );
			return;
		}

		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			check_admin_referer( self::NONCE_ACTION );

			$person_id = isset( $_POST['person_id'] ) ? absint( wp_unslash( $_POST['person_id'] ) ) : 0;
			$result    = ActivationService::activate( $token, $person_id );

			if ( is_wp_error( $result ) ) {
				$this->render_error( $result->get_error_message() );
				return;
			}

			wp_safe_redirect( $result );
			exit;
		}

		$this->render_person_picker( $token, $email );
	}

	// ------------------------------------------------------------------ views

	/**
	 * @param string $error Optional error to show above the form.
	 */
	private function render_email_form( string $error = '' ) {
		$this->open( 'Account activeren' );
		?>
	<div class="card">
		<p>Vul het e-mailadres in dat bij de club bekend is. We sturen je een link om je account te activeren.</p>
		<?php if ( $error ) : ?>
			<p class="error-hint"><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( home_url( '/activeren' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<p>
				<label for="rondo-activation-email">E-mailadres</label><br />
				<input type="email" id="rondo-activation-email" name="email" required autocomplete="email"
					style="width:100%;padding:12px;font-size:16px;border:1px solid #cbd5e1;border-radius:8px;margin-top:6px;" />
			</p>
			<button type="submit" class="btn-primary">Stuur mij een activatielink</button>
		</form>
	</div>
		<?php
		$this->close();
	}

	/**
	 * Deliberately says nothing about whether the address was found.
	 */
	private function render_confirmation() {
		$this->open( 'Account activeren' );
		?>
	<div class="card">
		<h2>Kijk in je mailbox</h2>
		<p>Als dit e-mailadres bij ons bekend is, hebben we er een activatielink naartoe gestuurd. De link is twee uur geldig.</p>
		<p class="error-hint">Geen mail ontvangen? Kijk in je spam-map, of neem contact op met de ledenadministratie.</p>
	</div>
		<?php
		$this->close();
	}

	/**
	 * "Who are you?" — a family mailbox can hold several members.
	 *
	 * @param string $token Raw token.
	 * @param string $email Address behind the token.
	 */
	private function render_person_picker( string $token, string $email ) {
		$persons   = ActivationService::persons_for_email( $email );
		$available = array_filter( $persons, fn( $id ) => ! ActivationService::has_account( $id ) );

		$this->open( 'Account activeren' );

		if ( empty( $available ) ) {
			?>
	<div class="card">
		<h2>Je hebt al een account</h2>
		<p>Voor iedereen op dit e-mailadres bestaat al een account. Gebruik <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">wachtwoord vergeten</a> als je niet meer kunt inloggen.</p>
	</div>
			<?php
			$this->close();
			return;
		}
		?>
	<div class="card">
		<h2>Wie ben je?</h2>
		<p>Op dit e-mailadres staan meerdere leden. Kies voor wie je een account aanmaakt.</p>
		<form method="post" action="<?php echo esc_url( ActivationService::activation_url( $token ) ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<?php foreach ( $available as $index => $person_id ) : ?>
				<p>
					<label>
						<input type="radio" name="person_id" value="<?php echo esc_attr( $person_id ); ?>" <?php checked( 0, $index ); ?> required />
						<?php echo esc_html( get_the_title( $person_id ) ); ?>
					</label>
				</p>
			<?php endforeach; ?>
			<button type="submit" class="btn-primary">Account aanmaken</button>
		</form>
		<p class="error-hint">Deze link werkt één keer. Wil je daarna nog een account aanmaken voor iemand anders op dit adres, vraag dan een nieuwe link aan.</p>
	</div>
		<?php
		$this->close();
	}

	/**
	 * @param string $message Dutch error message.
	 */
	private function render_error( string $message ) {
		status_header( 400 );
		$this->open( 'Account activeren' );
		?>
	<div class="card error-card">
		<h2>Dat lukte niet</h2>
		<p><?php echo esc_html( $message ); ?></p>
		<p><a href="<?php echo esc_url( home_url( '/activeren' ) ); ?>">Vraag een nieuwe activatielink aan</a></p>
	</div>
		<?php
		$this->close();
	}

	// ----------------------------------------------------------------- chrome

	/**
	 * @param string $heading Page heading.
	 */
	private function open( string $heading ) {
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		$branding = PublicPageChrome::branding();
		PublicPageChrome::header(
			$heading . ' — ' . $branding['name'],
			$branding['accent_color'],
			$branding['accent_background_color'],
			$branding['logo_url']
		);
		echo '<div class="container">';
		PublicPageChrome::header_card( $heading );
	}

	private function close() {
		echo '</div>';
		PublicPageChrome::footer();
	}
}
