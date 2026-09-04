<?php
function ft_assert($value, $message) { if (!$value) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
$plugin = file_get_contents(dirname(__DIR__) . '/plugins/faluss-theme/faluss-theme.php');
$source = file_get_contents(dirname(__DIR__) . '/plugins/faluss-theme/includes/class-faluss-theme.php');
foreach (array('Faluss Theme', 'register_activation_hook') as $needle) ft_assert(false !== strpos($plugin, $needle), 'Missing Faluss Theme plugin boundary: ' . $needle);
foreach (array("'canvas'=>'#FFFDF5'", "'surface'=>'#FFFFFF'", "'ink'=>'#080808'", "'muted'=>'#6F6A63'", "'accent'=>'#FF3D16'", "'action'=>'#080808'", "'action_text'=>'#FFFFFF'", "'card_radius'=>'24px'", "'control_radius'=>'8px'", "'pill_radius'=>'999px'", "'shadow'=>'0 12px 30px rgba(8, 8, 8, 0.06)'", '--faluss-', 'sanitize', 'Restaurer les valeurs canoniques', 'is_faluss_site', 'preg_match') as $needle) ft_assert(false !== strpos($source, $needle), 'Missing FT-01 invariant: ' . $needle);
ft_assert(false === strpos($source, 'elementor_kit'), 'FT-01 must not alter the Elementor kit.');
ft_assert(false === strpos($source, 'switch_theme'), 'FT-01 must not alter the active WordPress theme.');
echo "FT-01 contract: OK\n";
