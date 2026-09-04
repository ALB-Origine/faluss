<?php
function fl07_assert( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$widgets = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-widgets.php' );
$card = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$studio = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$docs = file_get_contents( $root . '/docs/FALUSS_LINK.md' );

foreach ( array( "'#BE79FF' => 'Rose'", "'#FFFFFF' => 'Blanc'", "'#000000' => 'Noir'", "'#82206B' => 'Prune'" ) as $needle ) { fl07_assert( false !== strpos( $link, $needle ), 'Missing exact Faluss name-color choice: ' . $needle ); }
foreach ( array( "'name_color' => '#000000'", 'private static function name_color', "return isset( self::NAME_COLORS[ \$color ] ) ? \$color : '#000000';", "'name_color' => \$name_color" ) as $needle ) { fl07_assert( false !== strpos( $link, $needle ), 'Name color does not default to black or persist safely: ' . $needle ); }
foreach ( array( 'faluss-link-name-colors', '<legend>', 'name="name_color" type="radio"', 'checked( $preferences[\'name_color\']', 'faluss-link-name-color__state', 'input:checked', 'input:focus-visible' ) as $needle ) { fl07_assert( false !== strpos( $link . $studio, $needle ), 'Name-color choices are not accessible radios with a selected state: ' . $needle ); }
fl07_assert( false === strpos( $link, 'name="name_color" type="color"' ), 'Studio must not expose a free name-color picker.' );
foreach ( array( 'resolve_card_styles', '--fl-name-color:%s', "'name_color' => self::name_color", 'faluss-link-card__name{color:var(--fl-name-color,var(--fl-ink))}' ) as $needle ) { fl07_assert( false !== strpos( $link . $card, $needle ), 'Name color is not resolved and emitted only for the public name: ' . $needle ); }
fl07_assert( false === strpos( $card, 'faluss-link-card__handle{color:var(--fl-name-color' ) && false === strpos( $card, 'faluss-link-card__bio{color:var(--fl-name-color' ), 'Name color must not recolor the handle or bio.' );
foreach ( array( "[name=\"name_color\"]:checked", "setProperty('--fl-name-color', nameColor)" ) as $needle ) { fl07_assert( false !== strpos( $editor, $needle ), 'Studio preview does not update the name color live: ' . $needle ); }
foreach ( array( "add_control( 'name_color'", "'{{WRAPPER}} .faluss-link-card__name' => '--fl-name-color:{{VALUE}} !important;'" ) as $needle ) { fl07_assert( false !== strpos( $widgets, $needle ), 'An explicit Elementor name color cannot override the member preference: ' . $needle ); }
fl07_assert( false === strpos( $widgets, "'name_color', array( 'label' => 'Couleur du nom', 'default'" ), 'An empty Elementor name-color control must not overwrite the member preference.' );
foreach ( array( 'thème sélectionné, préférences du membre, puis tokens Faluss Theme', 'aucun thème n’est sélectionnable' ) as $needle ) { fl07_assert( false !== strpos( $docs, $needle ), 'Future card-style priority is not documented: ' . $needle ); }
echo "FL-07 contract: OK\n";
