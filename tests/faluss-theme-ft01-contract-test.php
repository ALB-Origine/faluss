<?php
function ft_assert($value, $message) { if (!$value) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
$plugin = file_get_contents(dirname(__DIR__) . '/plugins/faluss-theme/faluss-theme.php');
$source = file_get_contents(dirname(__DIR__) . '/plugins/faluss-theme/includes/class-faluss-theme.php');
foreach (array('Faluss Theme', 'register_activation_hook') as $needle) ft_assert(false !== strpos($plugin, $needle), 'Missing Faluss Theme plugin boundary: ' . $needle);
foreach (array("'canvas' => '#FFFDF5'", "'surface' => '#FFFFFF'", "'action' => '#080808'", "'action_active' => '#000000'", "'border_color' => '#080808'", "'border_opacity' => '12'", 'shadow_presets', '--faluss-', 'sanitize', 'wp-color-picker', 'Couleurs', 'Actions', 'Formes &amp; relief', 'Restaurer les valeurs canoniques', 'Aperçu', 'min( 100, $opacity )') as $needle) ft_assert(false !== strpos($source, $needle), 'Missing FT-01 invariant: ' . $needle);
foreach (array("'--faluss-card-radius:' . \$tokens['card_radius']", "';--faluss-control-radius:' . \$tokens['control_radius']", "';--faluss-pill-radius:' . \$tokens['pill_radius']") as $needle) ft_assert(false !== strpos($source, $needle), 'Missing same-value FT-01 radius alias: ' . $needle);
ft_assert(false === strpos($source, 'elementor_kit'), 'FT-01 must not alter the Elementor kit.');
ft_assert(false === strpos($source, 'switch_theme'), 'FT-01 must not alter the active WordPress theme.');
echo "FT-01 contract: OK\n";
