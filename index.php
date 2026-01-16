<!DOCTYPE html>
<html <?php language_attributes(); ?> translate="no">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="google" content="notranslate">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	
	<div id="root">
		<!-- React app mounts here -->
		<div style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">
			<p>Loading...</p>
		</div>
	</div>
	
	<?php wp_footer(); ?>
</body>
</html>
