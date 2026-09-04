<?php
function fl02_assert($value, $message) { if (!$value) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
$source = file_get_contents(dirname(__DIR__) . '/plugins/faluss-link/includes/class-faluss-link.php');
$widgets = file_get_contents(dirname(__DIR__) . '/plugins/faluss-link/includes/class-faluss-link-widgets.php');
$css = file_get_contents(dirname(__DIR__) . '/plugins/faluss-link/assets/css/faluss-link.css');
$script = file_get_contents(dirname(__DIR__) . '/plugins/faluss-link/assets/js/faluss-link-editor.js');
$identity = file_get_contents(dirname(__DIR__) . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php');
$identity_css = file_get_contents(dirname(__DIR__) . '/plugins/faluss-identity/assets/css/faluss-identity-public-profile.css');
foreach (array('faluss_link_card', 'faluss_link_appearance', 'faluss_link_studio', 'render_studio', 'faluss-link-card__handle', "@<?php echo esc_html(\$p['public_slug'])", 'faluss-link-card--align-', 'faluss-link-card--cover-', 'faluss-link-card--avatar-', 'Faluss_Identity_Registry', 'get_public_profiles_table', 'render_editor', 'render_studio_fields', 'faluss-link-studio__preview') as $needle) fl02_assert(false !== strpos($source, $needle), 'Missing FL-02 public card or studio invariant: ' . $needle);
foreach (array('Faluss_Link_Card_Widget', 'Faluss_Link_Appearance_Widget', 'Faluss_Link_Studio_Widget', 'add_responsive_control', 'Group_Control_Typography', 'Group_Control_Border', 'Group_Control_Box_Shadow', "'align'", 'selectors_dictionary', "'action_hover'") as $needle) fl02_assert(false !== strpos($widgets, $needle), 'Missing FL-02 Elementor control or historical widget: ' . $needle);
foreach (array('--faluss-action', '--faluss-accent', 'faluss-link-card--align-center', 'faluss-link-card--align-right', 'faluss-link-card--cover-yes.faluss-link-card--avatar-yes', 'focus-visible', 'prefers-reduced-motion', 'mask-image') as $needle) fl02_assert(false !== strpos($css, $needle), 'Missing FL-02 style invariant: ' . $needle);
fl02_assert(false === strpos($css, 'background:#FF3D16'), 'FL-02 must not make orange the default primary action.');
foreach (array('data-fl-tab', 'data-fl-panel', 'aria-selected') as $needle) fl02_assert(false !== strpos($script, $needle), 'Missing accessible Studio tab behavior: ' . $needle);
fl02_assert(false === strpos($source, 'user_email') && false === strpos($source, 'subscription') && false === strpos($source, 'alb'), 'FL-02 must not duplicate Identity or business data.');
foreach (array('render_studio_fields', 'faluss_identity_save_public_profile', 'data-fl-panel="profile"', 'data-fl-panel="links"') as $needle) fl02_assert(false !== strpos($identity, $needle), 'Missing minimal Identity-owned Studio contract: ' . $needle);
foreach (array('var(--faluss-canvas, #FFFDF5)', 'var(--faluss-action, #080808)', 'var(--faluss-action_hover, #28231F)') as $needle) fl02_assert(false !== strpos($identity_css, $needle), 'Identity Studio fields must consume Faluss Theme tokens: ' . $needle);
echo "FL-02 contract: OK\n";
