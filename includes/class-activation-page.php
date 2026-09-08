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

		// Identical response whether or not anyone matched — and sent BEFORE the mail
		// goes out. Delivering the mail takes an API round-trip; doing that first would
		// make a known address measurably slower than an unknown one, which is an
		// enumeration oracle no matter how identical the HTML is.
		$this->render_confirmation();
		self::flush_response();

		if ( ! empty( $persons ) ) {
			$available = array_filter( $persons, fn( $person_id ) => ! ActivationService::has_account( $person_id ) );
			if ( ! empty( $available ) ) {
				ActivationService::send_activation_email( $email, ActivationService::create_token( $email ) );
			} elseif ( ! ActivationService::send_magic_login_email( $email, $persons ) ) {
				// Keep the existing recovery page as a fallback when Magic Login is unavailable.
				ActivationService::send_activation_email( $email, ActivationService::create_token( $email ) );
			}
		}
	}

	/**
	 * Hand the response to the client and keep running.
	 *
	 * Under PHP-FPM this genuinely closes the connection; elsewhere we settle for
	 * flushing the buffers, which is close enough to hide a mail round-trip.
	 */
	private static function flush_response(): void {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
			return;
		}

		if ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		flush();
	}

	/**
	 * GET/POST /activeren/{token}.
	 *
	 * @param string $token Raw token from the URL.
	 */
	private function handle_token_request( string $token ) {
		$email = ActivationService::email_for_token( $token );

		if ( $email === null ) {
			ActivationService::record_invalid_token_failure( $token );
			$this->render_error( 'Deze activatielink is verlopen of ongeldig. Vraag een nieuwe aan.' );
			return;
		}

		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			check_admin_referer( self::NONCE_ACTION );

			$identity      = isset( $_POST['identity'] ) ? sanitize_text_field( wp_unslash( $_POST['identity'] ) ) : '';
			$guardian_name = isset( $_POST['guardian_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_name'] ) ) : '';
			if ( ! preg_match( '/^(self|guardian):(\d+)$/', $identity, $matches ) ) {
				ActivationService::record_token_failure(
					$token,
					'activation_identity_missing',
					'Er is geen geldige persoon gekozen bij de accountactivatie.'
				);
				$this->render_error( 'Kies voor wie je het account aanmaakt.' );
				return;
			}

			$person_id = (int) $matches[2];
			$result    = $matches[1] === 'guardian'
				? ActivationService::activate_guardian( $token, $person_id, $guardian_name )
				: ActivationService::activate( $token, $person_id );

			if ( is_wp_error( $result ) ) {
				ActivationService::record_token_failure(
					$token,
					$result->get_error_code(),
					$result->get_error_message(),
					$person_id
				);
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
		$branding = PublicPageChrome::branding();
		$this->open( 'Activeer je ' . $branding['name'] . '-account' );
		?>
	<div class="card">
		<p>Met je account kun je:</p>
		<ul class="activation-benefits" role="list">
			<li><span aria-hidden="true">✏️</span><span>Je adres, telefoonnummer en e-mailadres bekijken en aanpassen.</span></li>
			<li><span aria-hidden="true">🙋</span><span>Je inschrijven voor vrijwilligerstaken.</span></li>
			<li><span aria-hidden="true">🪪</span><span>Je digitale ledenpas vinden.</span></li>
		</ul>
		<?php if ( $error ) : ?>
			<p class="error-hint"><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( home_url( '/activeren' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<p class="activation-email-field">
				<label for="rondo-activation-email" class="activation-email-label">E-mailadres</label>
				<span id="rondo-activation-email-help" class="activation-email-help">Gebruik het e-mailadres waarop je nieuwsbrieven van <?php echo esc_html( $branding['name'] ); ?> ontvangt.</span>
				<input type="email" id="rondo-activation-email" name="email" required autocomplete="email" aria-describedby="rondo-activation-email-help"
					oninvalid="this.setCustomValidity(this.validity.valueMissing ? 'Vul je e-mailadres in.' : 'Vul een geldig e-mailadres in.')"
					oninput="this.setCustomValidity('')"
					style="width:100%;padding:12px;font-size:16px;border:1px solid #cbd5e1;border-radius:8px;margin-top:6px;" />
			</p>
			<p class="activation-help">Ben je ouder of verzorger? Via de activatielink kun je kiezen voor een eigen ouderaccount.</p>
			<button type="submit" class="btn btn-primary activation-submit">Stuur mij een activatielink</button>
			<p class="activation-help">Je ontvangt een e-mail met een link om verder te gaan.</p>
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
		<p>Als dit e-mailadres bij ons bekend is, hebben we een e-mail gestuurd met de juiste link om je account te activeren of direct in te loggen.</p>
		<p class="confirmation-help">Geen mail ontvangen? Zoek in je spam-map naar een bericht van <?php echo esc_html( ActivationService::ACTIVATION_FROM_EMAIL ); ?>. Ook daar niets? Neem dan contact op met de ledenadministratie.</p>
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
		$persons       = ActivationService::persons_for_email( $email );
		$available     = array_values( array_filter( $persons, fn( $id ) => ! ActivationService::has_account( $id ) ) );
		$youth_persons = array_filter( $available, fn( $id ) => GuardianAccountService::is_youth_person( (int) $id ) );

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
		$single_identity = count( $available ) === 1 && empty( $youth_persons );
		?>
	<div class="card">
		<?php if ( $single_identity ) : ?>
			<p>Je maakt een account aan voor <strong><?php echo esc_html( get_the_title( $available[0] ) ); ?></strong>.</p>
		<?php else : ?>
			<h2>Voor wie maak je een account?</h2>
			<p>Kies hieronder wie gaat inloggen.</p>
		<?php endif; ?>
		<form class="activation-picker" method="post" action="<?php echo esc_url( ActivationService::activation_url( $token ) ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<?php if ( $single_identity ) : ?>
				<input type="hidden" name="identity" value="self:<?php echo esc_attr( $available[0] ); ?>" />
			<?php else : ?>
				<?php foreach ( $available as $index => $person_id ) : ?>
					<div class="activation-person">
						<label class="activation-choice">
							<input type="radio" name="identity" value="self:<?php echo esc_attr( $person_id ); ?>" <?php checked( 0, $index ); ?> required />
							<span><strong>Voor mezelf</strong><span class="activation-choice-detail">Ik ben <?php echo esc_html( get_the_title( $person_id ) ); ?></span></span>
						</label>
						<?php if ( in_array( $person_id, $youth_persons, true ) ) : ?>
							<label class="activation-choice">
								<input type="radio" name="identity" value="guardian:<?php echo esc_attr( $person_id ); ?>" />
								<span><strong>Voor mij als ouder/verzorger</strong><span class="activation-choice-detail">Van <?php echo esc_html( get_the_title( $person_id ) ); ?></span></span>
							</label>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
			<?php if ( ! empty( $youth_persons ) ) : ?>
				<div class="activation-guardian-field">
					<label for="rondo-guardian-name">Jouw volledige naam</label>
					<input type="text" id="rondo-guardian-name" name="guardian_name" maxlength="120" autocomplete="name" aria-describedby="rondo-guardian-help" />
					<p id="rondo-guardian-help" class="activation-help">Vul je eigen voor- en achternaam in, niet die van je kind.</p>
				</div>
			<?php endif; ?>
			<button type="submit" class="btn btn-primary activation-submit">Account aanmaken</button>
		</form>
		<p class="activation-help">Deze link werkt één keer. Voor een volgend account vraag je een nieuwe link aan.</p>
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
			$branding['logo_url'],
			get_theme_file_uri( '/public/images/og-account-activation.png' )
		);
		echo '<div class="container activation-page">';
		PublicPageChrome::header_card( $heading );
	}

	private function close() {
		echo '</div>';
		PublicPageChrome::footer();
	}
}
