<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'FALUSS_IDENTITY_FILE', dirname( __DIR__ ) . '/plugins/faluss-identity/faluss-identity.php' );
define( 'FALUSS_IDENTITY_VERSION', '0.4.4' );

$fi07_logged_in = false;
$fi07_registered = array();
$_SERVER['REQUEST_URI'] = '/origin?source=menu';

function __( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function home_url( $path = '/' ) { return 'https://faluss.me' . ( '/' === $path ? '/' : '/' . ltrim( $path, '/' ) ); }
function wp_validate_redirect( $url, $fallback = '' ) { return 0 === strpos( $url, 'https://faluss.me/' ) ? $url : $fallback; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function is_user_logged_in() { global $fi07_logged_in; return $fi07_logged_in; }
function wp_logout_url( $return ) { return 'https://faluss.me/wp-login.php?action=logout&_wpnonce=fixture&redirect_to=' . rawurlencode( $return ); }
function plugins_url( $path, $file ) { return 'https://faluss.me/wp-content/plugins/faluss-identity/' . $path; }
function wp_register_style( $handle, $url, $deps, $version ) { global $fi07_registered; $fi07_registered['style'] = compact( 'handle', 'url', 'deps', 'version' ); }
function wp_register_script( $handle, $url, $deps, $version, $footer ) { global $fi07_registered; $fi07_registered['script'] = compact( 'handle', 'url', 'deps', 'version', 'footer' ); }

function fi07_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
require_once $root . '/plugins/faluss-identity/includes/class-faluss-identity-navigation.php';

// The three actions use the exact local paths, with no page creation or remote redirect.
$fi07_logged_in = false;
$anonymous_actions = Faluss_Identity_Navigation::actions();
fi07_assert( 3 === count( $anonymous_actions ), 'Navigation renders exactly three actions.' );
fi07_assert( 'https://faluss.me/mon-faluss/' === $anonymous_actions[0]['url'] && 'https://faluss.me/list/' === $anonymous_actions[1]['url'], 'The two fixed actions use their exact local paths.' );
fi07_assert( 'Connexion' === $anonymous_actions[2]['label'] && 0 === strpos( $anonymous_actions[2]['url'], 'https://faluss.me/login/?redirect_to=' ), 'Anonymous users receive the local login action.' );
fi07_assert( false !== strpos( rawurldecode( $anonymous_actions[2]['url'] ), 'https://faluss.me/origin?source=menu' ), 'The login return keeps the current local URL.' );

$_SERVER['REQUEST_URI'] = '//attacker.example/return';
fi07_assert( 'https://faluss.me/' === Faluss_Identity_Navigation::current_local_return_url(), 'Protocol-relative returns fail closed to the local homepage.' );
$fi07_logged_in = true;
$logged_actions = Faluss_Identity_Navigation::actions();
fi07_assert( 'Déconnexion' === $logged_actions[2]['label'] && false !== strpos( $logged_actions[2]['url'], 'wp-login.php?action=logout&_wpnonce=' ), 'Logged-in users receive a WordPress nonce-protected logout action.' );
fi07_assert( false !== strpos( rawurldecode( $logged_actions[2]['url'] ), 'redirect_to=https://faluss.me/' ), 'Logout keeps a safe local return URL.' );

Faluss_Identity_Navigation::register_assets();
fi07_assert( 'faluss-identity-navigation' === $fi07_registered['style']['handle'] && false !== strpos( $fi07_registered['style']['url'], 'assets/css/faluss-identity-navigation.css' ), 'The navigation stylesheet is registered as an Elementor dependency.' );
fi07_assert( 'faluss-identity-navigation' === $fi07_registered['script']['handle'] && true === $fi07_registered['script']['footer'] && false !== strpos( $fi07_registered['script']['url'], 'assets/js/faluss-identity-navigation.js' ), 'The navigation script is registered in the footer as an Elementor dependency.' );

$plugin = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-plugin.php' );
$widget = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-navigation-elementor-widget.php' );
$css = file_get_contents( $root . '/plugins/faluss-identity/assets/css/faluss-identity-navigation.css' );
$script = file_get_contents( $root . '/plugins/faluss-identity/assets/js/faluss-identity-navigation.js' );
$public_profile = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php' );

foreach ( array( "'Faluss_Identity_Navigation', 'register_assets'", 'elementor/frontend/after_register_scripts', 'elementor/frontend/after_register_styles', 'class-faluss-identity-navigation-elementor-widget.php', 'Faluss_Identity_Navigation_Elementor_Widget' ) as $required ) {
    fi07_assert( false !== strpos( $plugin, $required ), 'The plugin bootstraps the Navigation Faluss assets and widget early: ' . $required );
}
foreach ( array( "return 'faluss_identity_navigation'", "return __( 'Navigation Faluss'", 'get_style_depends', 'get_script_depends', '<template data-faluss-navigation-template>', '<dialog class="faluss-identity-navigation-portal"', 'wp_unique_id', 'home_url( \'/mon-faluss/\' )', 'home_url( \'/list/\' )', 'wp_logout_url', 'login_url' ) as $required ) {
    fi07_assert( false !== strpos( $widget, $required ) || false !== strpos( file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-navigation.php' ), $required ), 'The widget/service retains its navigation contract: ' . $required );
}
fi07_assert( false === strpos( $widget, 'theplus' ) && false === strpos( $script, 'theplus' ), 'Navigation Faluss has no dependency on The Plus Popup Builder.' );
fi07_assert( false === strpos( $script, "document.addEventListener('click'") && false === strpos( $script, 'stopPropagation' ), 'The script does not intercept global clicks or external components.' );
fi07_assert( false === strpos( $css, 'html { pointer-events:' ) && false === strpos( $css, 'body { pointer-events:' ) && false === strpos( $css, '.faluss-identity-navigation { pointer-events:' ), 'The component never neutralizes page, header, or card interactions with pointer-events.' );
fi07_assert( false !== strpos( $css, '.faluss-identity-navigation-portal__sidebar::before' ) && false !== strpos( $css, 'pointer-events: none;' ), 'Only the local decorative sidebar pseudo-element is non-interactive.' );
foreach ( array( 'dialog.faluss-identity-navigation-portal', '100dvh', 'position: fixed', 'margin-block-start: auto', 'safe-area-inset-top', 'prefers-reduced-motion' ) as $required ) {
    fi07_assert( false !== strpos( $css, $required ), 'The sidebar has the required viewport, split layout, safe-area, and motion treatment: ' . $required );
}
foreach ( array( 'document.body.appendChild(portal)', 'portal.showModal()', "portal.addEventListener('cancel'", "querySelector('[data-faluss-navigation-close]')", "querySelector('[data-faluss-navigation-sidebar]')", 'trigger.focus', 'WeakSet', 'elementor/frontend/init', 'frontend/element_ready/faluss_identity_navigation.default' ) as $required ) {
    fi07_assert( false !== strpos( $script, $required ), 'The body portal, accessibility lifecycle, and idempotent Elementor initialization are present: ' . $required );
}
fi07_assert( false === strpos( $public_profile, 'Faluss_Identity_Navigation' ), 'FI-07 does not alter the public-profile shell or take ownership of existing headers.' );

echo 'FI-07 Navigation Faluss contract: OK' . PHP_EOL;
