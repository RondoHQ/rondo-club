<?php
/**
 * Standalone native consent page, shared by all club adapters.
 *
 * @package Rondo\Mobile
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html lang="nl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Verbinden met Rondo</title>
	<style>
		@font-face{font-family:Figtree;font-style:normal;font-weight:700;font-display:swap;src:url('<?php echo esc_url( $brand_url . '/figtree-latin-700-normal.woff2' ); ?>') format('woff2')}
		*{box-sizing:border-box}body{margin:0;background:#f7fbff;color:#001b60;font:16px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
		main{max-width:440px;margin:0 auto;padding:24px 24px max(24px,env(safe-area-inset-bottom))}header{display:flex;align-items:center;gap:14px;margin-bottom:28px}
		.club-logo{display:block;width:38px;height:38px;object-fit:contain;border:0;padding:0}.rondo-logo{display:block;width:100px;height:36px;object-fit:contain}
		h1{font:700 28px/1.2 Figtree,system-ui,sans-serif;letter-spacing:-.5px;margin:0 0 12px}p{margin:0 0 20px;color:#3d4e6a}.club-name{font-weight:600;color:#001b60;overflow-wrap:anywhere}
		ul{list-style:none;padding:0;margin:0 0 20px}li{display:flex;gap:10px;margin:12px 0;color:#3d4e6a}li::before{content:'✓';color:#007975;font-weight:700;flex:none}
		.session-note{font-size:14px;line-height:1.5;margin:0 0 24px}form{display:grid;gap:10px}button{width:100%;min-height:48px;border-radius:12px;border:0;padding:12px 16px;font:600 16px/1.5 system-ui,sans-serif;cursor:pointer;background:#007975;color:#fff}
		button[value=deny]{background:transparent;border:1px solid #cbd8e5;color:#3d4e6a}button:focus-visible{outline:3px solid #227ac0;outline-offset:3px}button:hover{filter:brightness(.95)}
	</style>
</head>
<body>
<main>
	<header aria-label="Club en Rondo">
		<?php if ( $club_logo !== '' ) : ?>
			<img class="club-logo" src="<?php echo esc_url( $club_logo ); ?>" alt="<?php echo esc_attr( $club_name ); ?>">
		<?php endif; ?>
		<img class="rondo-logo" src="<?php echo esc_url( $brand_url . '/rondo-wordmark.svg' ); ?>" alt="Rondo" width="100" height="36">
	</header>
	<h1>Verbinden met Rondo</h1>
	<p>Geef de app toegang tot jouw gegevens bij <span class="club-name"><?php echo esc_html( $club_name ); ?></span>.</p>
	<ul aria-label="Toegang voor de app">
		<li>Je eigen gegevens en die van je gezin bekijken.</li>
		<li>Beschikbare ledenpassen toevoegen aan Wallet.</li>
		<?php if ( in_array( $params['scope'], [ static::MEMBER_SCOPE, static::PROFILE_SCOPE ], true ) ) : ?>
			<li>Jezelf aanmelden en afmelden voor vrijwilligersdiensten.</li>
		<?php endif; ?>
		<?php if ( $params['scope'] === static::PROFILE_SCOPE ) : ?>
			<li>Je contactgegevens en gezinsadres wijzigen. Een nieuw e-mailadres bevestig je per e-mail.</li>
		<?php endif; ?>
	</ul>
	<p class="session-note">Je blijft maximaal <?php echo (int) ( static::DEVICE_TTL / DAY_IN_SECONDS ); ?> dagen ingelogd op dit apparaat. Je kunt altijd uitloggen; ook je club kan de toegang intrekken.</p>
	<form method="post">
		<?php
		wp_nonce_field( static::ACTION );
		foreach ( [ 'action', 'client_id', 'redirect_uri', 'scope', 'response_type', 'code_challenge_method', 'state', 'code_challenge' ] as $rondo_consent_key ) {
			echo '<input type="hidden" name="' . esc_attr( $rondo_consent_key ) . '" value="' . esc_attr( (string) $params[ $rondo_consent_key ] ) . '">';
		}
		?>
		<button name="decision" value="approve">Verbinden</button>
		<button name="decision" value="deny">Annuleren</button>
	</form>
</main>
</body>
</html>
