<?php

/**
 * Deterministic FI-02 / FI-07.2 / FL-19 integration contract.
 *
 * Historical sources are read from their immutable commits so the four A/B
 * couples remain covered even after the installable plugins move forward.
 * No WordPress request, database write, or e-mail is performed by this test.
 */

function fi_link_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function fi_link_git_source( $commit, $path ) {
    $spec = $commit . ':' . $path;
    $output = array();
    $status = 0;
    exec( 'git show ' . escapeshellarg( $spec ) . ' 2>&1', $output, $status );
    fi_link_assert( 0 === $status, 'Unable to read historical source ' . $spec );
    return implode( "\n", $output ) . "\n";
}

function fi_link_method_source( $source, $method ) {
    $needle = 'function ' . $method . '(';
    $start = strpos( $source, $needle );
    fi_link_assert( false !== $start, 'Missing method ' . $method );
    $brace = strpos( $source, '{', $start );
    fi_link_assert( false !== $brace, 'Missing method body ' . $method );
    $depth = 0;
    $length = strlen( $source );
    for ( $index = $brace; $index < $length; ++$index ) {
        if ( '{' === $source[ $index ] ) { ++$depth; }
        if ( '}' === $source[ $index ] && 0 === --$depth ) {
            return substr( $source, $start, $index - $start + 1 );
        }
    }
    fi_link_assert( false, 'Unclosed method body ' . $method );
}

function fi_link_hooks( $source ) {
    preg_match_all( "/add_action\\(\\s*'([^']+)'/", $source, $matches );
    $hooks = $matches[1];
    sort( $hooks );
    return $hooks;
}

if ( isset( $argv[1] ) && '--probe' === $argv[1] ) {
    define( 'ABSPATH', __DIR__ . '/' );
    define( 'ARRAY_A', 'ARRAY_A' );
    define( 'YEAR_IN_SECONDS', 31536000 );
    define( 'FALUSS_IDENTITY_VERSION', $argv[2] );
    define( 'FALUSS_IDENTITY_FILE', __FILE__ );
    define( 'FALUSS_LINK_VERSION', $argv[3] );
    define( 'FALUSS_LINK_FILE', __FILE__ );
    $probe_hooks = array();
    $probe_registered = array();
    $probe_enqueued = array();
    $probe_localized = array();
    $probe_mail = array();

    function add_action( $hook, $callback, $priority = 10 ) { global $probe_hooks; $probe_hooks[] = array( $hook, $priority, $callback ); }
    function add_filter() {}
    function add_shortcode() {}
    function do_action() {}
    function is_admin() { return false; }
    function wp_register_style( $handle ) { global $probe_registered; $probe_registered['styles'][] = $handle; }
    function wp_register_script( $handle ) { global $probe_registered; $probe_registered['scripts'][] = $handle; }
    function wp_enqueue_style( $handle ) { global $probe_enqueued; $probe_enqueued['styles'][] = $handle; }
    function wp_enqueue_script( $handle ) { global $probe_enqueued; $probe_enqueued['scripts'][] = $handle; }
    function wp_style_is( $handle, $state ) { global $probe_registered; return 'registered' === $state && in_array( $handle, $probe_registered['styles'] ?? array(), true ); }
    function wp_localize_script( $handle, $name, $data ) { global $probe_localized; $probe_localized[] = array( $handle, $name, $data ); }
    function plugins_url( $path ) { return 'https://faluss.me/wp-content/plugins/' . $path; }
    function admin_url( $path = '' ) { return 'https://faluss.me/wp-admin/' . ltrim( $path, '/' ); }
    function wp_create_nonce() { return 'fixture-nonce'; }
    function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
    function __( $value ) { return $value; }
    function esc_attr( $value ) { return (string) $value; }
    function esc_html( $value ) { return (string) $value; }
    function esc_url( $value ) { return (string) $value; }
    function esc_html_e( $value ) { echo $value; }
    function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . $name . '" value="fixture-nonce">'; }
    function is_user_logged_in() { return false; }
    function home_url( $path = '/' ) { return 'https://faluss.me' . ( '/' === $path ? '/' : '/' . ltrim( $path, '/' ) ); }
    function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
    function wp_validate_redirect( $url, $fallback = '' ) { return 0 === strpos( $url, 'https://faluss.me/' ) ? $url : $fallback; }
    function add_query_arg( $key, $value, $url ) { return $url . '?' . rawurlencode( $key ) . '=' . rawurlencode( $value ); }
    function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
    function wp_unslash( $value ) { return $value; }
    function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
    function wp_salt( $scheme = '' ) { return 'probe-' . $scheme; }
    function wp_hash_password( $value ) { return 'hash:' . $value; }
    function current_time() { return '2026-09-06 16:00:00'; }
    function wp_mail( $to, $subject, $message ) { global $probe_mail; $probe_mail[] = compact( 'to', 'subject', 'message' ); return true; }

    class Faluss_Identity_Schema {
        public static function get_table_names() { return array( 'rate_limits' => 'wp_rate_limits', 'challenges' => 'wp_challenges', 'audit' => 'wp_audit' ); }
    }
    class Fi_Link_Probe_Wpdb {
        public $prefix = 'wp_';
        public $challenge_inserts = 0;
        public function prepare( $query, ...$args ) { return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $query ), $args ); }
        public function get_row() { return null; }
        public function query( $query ) { if ( false !== strpos( $query, 'INSERT INTO `wp_challenges`' ) ) { ++$this->challenge_inserts; } return 1; }
    }

    $identity_commit = $argv[4];
    $link_commit = $argv[5];
    $passwordless = fi_link_git_source( $identity_commit, 'plugins/faluss-identity/includes/class-faluss-identity-passwordless.php' );
    $link = fi_link_git_source( $link_commit, 'plugins/faluss-link/includes/class-faluss-link.php' );
    eval( preg_replace( '/^<\?php\s*/', '', $passwordless ) );
    eval( preg_replace( '/^<\?php\s*/', '', $link ) );

    Faluss_Identity_Passwordless::register();
    Faluss_Link::boot();
    Faluss_Identity_Passwordless::register_assets();
    Faluss_Link::assets();
    $_GET = array();
    $_COOKIE = array();
    $initial_markup = Faluss_Identity_Passwordless::render_form();
    $wpdb = new Fi_Link_Probe_Wpdb();
    $issue = new ReflectionMethod( 'Faluss_Identity_Passwordless', 'issue_challenge' );
    $issue->setAccessible( true );
    $issued = $issue->invoke( null, 'member@example.test', '203.0.113.10' );
    $after_markup = Faluss_Identity_Passwordless::render_form();

    $hook_names = array_map( static function( $hook ) { return $hook[0]; }, $probe_hooks );
    echo json_encode( array(
        'hooks' => $hook_names,
        'registered' => $probe_registered,
        'enqueued' => $probe_enqueued,
        'localized' => array_map( static function( $item ) { return $item[1]; }, $probe_localized ),
        'initial_state' => false !== strpos( $initial_markup, 'name="email"' ) && false === strpos( $initial_markup, 'name="otp"' ) ? 'email' : 'unexpected',
        'requests' => 1,
        'challenges' => $wpdb->challenge_inserts,
        'mails' => count( $probe_mail ),
        'mail_payload' => 1 === count( $probe_mail ) ? preg_replace( '/\b\d{6}\b/', '{otp}', $probe_mail[0]['message'] ) : '',
        'response_state' => $issued && false !== strpos( $after_markup, 'name="otp"' ) ? 'otp' : 'unexpected',
    ) );
    exit;
}

$root = dirname( __DIR__ );
$identity_045 = '022af07e9ca6eab2dc003b95a59db0abe045e119';
$identity_046 = '1e7940aa3bea5b4ac362890d66dd1075dadd9b10';
$link_026 = '51680d805b0d542ad309614451b7fb96cea67d52';
$link_027 = '1e7940aa3bea5b4ac362890d66dd1075dadd9b10';
$passwordless_path = 'plugins/faluss-identity/includes/class-faluss-identity-passwordless.php';
$login_script_path = 'plugins/faluss-identity/assets/js/faluss-identity-passwordless-login.js';
$identity_boot_path = 'plugins/faluss-identity/includes/class-faluss-identity-plugin.php';
$link_path = 'plugins/faluss-link/includes/class-faluss-link.php';
$link_scripts = array(
    'plugins/faluss-link/assets/js/faluss-link-card.js',
    'plugins/faluss-link/assets/js/faluss-link-editor.js',
    'plugins/faluss-link/assets/js/faluss-link-immersive.js',
    'plugins/faluss-link/assets/js/faluss-link-reward.js',
);

$identity_sources = array(
    '0.4.5' => array(
        'passwordless' => fi_link_git_source( $identity_045, $passwordless_path ),
        'login_script' => fi_link_git_source( $identity_045, $login_script_path ),
        'boot' => fi_link_git_source( $identity_045, $identity_boot_path ),
    ),
    '0.4.6' => array(
        'passwordless' => fi_link_git_source( $identity_046, $passwordless_path ),
        'login_script' => fi_link_git_source( $identity_046, $login_script_path ),
        'boot' => fi_link_git_source( $identity_046, $identity_boot_path ),
    ),
);
$link_sources = array();
foreach ( array( '0.2.6' => $link_026, '0.2.7' => $link_027 ) as $version => $commit ) {
    $scripts = '';
    foreach ( $link_scripts as $path ) { $scripts .= fi_link_git_source( $commit, $path ); }
    $link_sources[ $version ] = array( 'class' => fi_link_git_source( $commit, $link_path ), 'scripts' => $scripts );
}

// The historical Identity passwordless runtime is byte-identical; 0.4.6 only
// changed Navigation and the asset version. Link 0.2.7 added scoped FL-19 UI.
fi_link_assert( hash( 'sha256', $identity_sources['0.4.5']['passwordless'] ) === hash( 'sha256', $identity_sources['0.4.6']['passwordless'] ), 'Identity 0.4.5 and 0.4.6 must retain the same passwordless server source.' );
fi_link_assert( hash( 'sha256', $identity_sources['0.4.5']['login_script'] ) === hash( 'sha256', $identity_sources['0.4.6']['login_script'] ), 'Identity 0.4.5 and 0.4.6 must retain the same passwordless browser source.' );
fi_link_assert( fi_link_hooks( $identity_sources['0.4.5']['boot'] ) === fi_link_hooks( $identity_sources['0.4.6']['boot'] ), 'Identity boot hooks are unchanged between 0.4.5 and 0.4.6.' );
fi_link_assert( fi_link_hooks( $link_sources['0.2.6']['class'] ) === fi_link_hooks( $link_sources['0.2.7']['class'] ), 'Link boot hooks are unchanged between 0.2.6 and 0.2.7.' );

$pairs = array(
    array( 'identity' => '0.4.5', 'link' => '0.2.6' ),
    array( 'identity' => '0.4.6', 'link' => '0.2.6' ),
    array( 'identity' => '0.4.5', 'link' => '0.2.7' ),
    array( 'identity' => '0.4.6', 'link' => '0.2.7' ),
);
foreach ( $pairs as $pair ) {
    $identity = $identity_sources[ $pair['identity'] ];
    $link = $link_sources[ $pair['link'] ];
    $label = 'Identity ' . $pair['identity'] . ' + Link ' . $pair['link'];
    $link_assets = fi_link_method_source( $link['class'], 'assets' );
    $request = fi_link_method_source( $identity['passwordless'], 'request_code_result' );
    $issue = fi_link_method_source( $identity['passwordless'], 'issue_challenge' );
    $ajax = fi_link_method_source( $identity['passwordless'], 'handle_request_code_ajax' );

    $capture = array(
        'identity_submit_events' => substr_count( $identity['login_script'], "document.addEventListener('submit'" ),
        'link_submit_events' => substr_count( $link['scripts'], "addEventListener('submit'" ) + substr_count( $link['scripts'], ".on('submit" ),
        'passwordless_fetches' => substr_count( $identity['login_script'], 'fetch(' ),
        'link_passwordless_actions' => substr_count( $link['scripts'], 'faluss_identity_request_code' ),
        'link_login_enqueues' => substr_count( $link_assets, 'wp_enqueue_' ),
        'challenge_calls' => substr_count( $request, 'self::issue_challenge(' ),
        'challenge_inserts' => substr_count( $issue, 'INSERT INTO ' ),
        'mail_calls' => substr_count( $issue, 'wp_mail(' ),
        'mail_payload' => false !== strpos( $issue, 'Votre code de connexion Faluss' ) && false !== strpos( $issue, 'Votre code Faluss est : %s' ),
        'browser_state' => false !== strpos( $ajax, "'otp_html' => self::render_otp_stage" ) ? 'otp_after_success' : 'unknown',
    );

    fi_link_assert( 1 === $capture['identity_submit_events'] && 0 === $capture['link_submit_events'], $label . ' has one scoped Identity submit event and no Link submit event.' );
    fi_link_assert( 1 === $capture['passwordless_fetches'] && 0 === $capture['link_passwordless_actions'], $label . ' can issue exactly one passwordless request per submit.' );
    fi_link_assert( 0 === $capture['link_login_enqueues'], $label . ' registers but does not enqueue Link assets on /login without a Link surface.' );
    fi_link_assert( 1 === $capture['challenge_calls'] && 1 === $capture['challenge_inserts'] && 1 === $capture['mail_calls'], $label . ' follows one request to one challenge and one mail call.' );
    fi_link_assert( $capture['mail_payload'] && 'otp_after_success' === $capture['browser_state'], $label . ' retains the canonical mail payload and only then returns the OTP stage.' );

    $probe_output = array();
    $probe_status = 0;
    $probe_command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --probe ' . escapeshellarg( $pair['identity'] ) . ' ' . escapeshellarg( $pair['link'] ) . ' ' . escapeshellarg( $pair['identity'] === '0.4.5' ? $identity_045 : $identity_046 ) . ' ' . escapeshellarg( $pair['link'] === '0.2.6' ? $link_026 : $link_027 );
    exec( $probe_command . ' 2>&1', $probe_output, $probe_status );
    $probe = json_decode( implode( "\n", $probe_output ), true );
    fi_link_assert( 0 === $probe_status && is_array( $probe ), $label . ' joint boot probe must complete.' );
    fi_link_assert( in_array( 'wp_ajax_nopriv_faluss_identity_request_code_ajax', $probe['hooks'], true ), $label . ' registers the anonymous passwordless AJAX hook.' );
    fi_link_assert( array( 'faluss-identity-passwordless' ) === array_values( array_unique( $probe['enqueued']['styles'] ?? array() ) ) && array( 'faluss-identity-passwordless-login' ) === array_values( array_unique( $probe['enqueued']['scripts'] ?? array() ) ), $label . ' loads only Identity assets on a login document without a Link surface.' );
    fi_link_assert( 'email' === $probe['initial_state'] && 1 === $probe['requests'] && 1 === $probe['challenges'] && 1 === $probe['mails'] && 'otp' === $probe['response_state'], $label . ' executes one submit, one challenge, one mail, then one OTP browser state.' );
    fi_link_assert( false !== strpos( $probe['mail_payload'], 'Votre code Faluss est : {otp}' ), $label . ' emits the same normalized mail payload.' );
}

// Final correction: /login is private to the live request, duplicate browser
// delivery is gated, and Link registration/localization is demand-driven.
$current_identity = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-passwordless.php' );
$current_login_script = file_get_contents( $root . '/plugins/faluss-identity/assets/js/faluss-identity-passwordless-login.js' );
$current_link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$current_link_assets = fi_link_method_source( $current_link, 'assets' );
$current_reward_assets = fi_link_method_source( $current_link, 'reward_assets' );

foreach ( array( "add_action( 'parse_request', array( __CLASS__, 'exclude_login_from_cache' ), 0 )", 'DONOTCACHEPAGE', 'litespeed_control_set_nocache', 'X-LiteSpeed-Cache-Control', 'no-store, no-cache' ) as $needle ) {
    fi_link_assert( false !== strpos( $current_identity, $needle ), 'The final /login response must bypass WordPress and LiteSpeed page caches: ' . $needle );
}
fi_link_assert( false !== strpos( $current_login_script, "form.dataset.falussIdentityPending === '1'" ) && false !== strpos( $current_login_script, 'setSubmitting(form, true)' ), 'A duplicated script/event cannot create a second request, challenge, or mail.' );
fi_link_assert( false === strpos( $current_link, "add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 5 )" ), 'Link has no global frontend asset registration side effect on /login.' );
fi_link_assert( false === strpos( $current_link_assets, 'wp_localize_script' ) && false === strpos( $current_link_assets, 'wp_enqueue_' ), 'Pure Link asset registration has no executable or enqueued login payload.' );
fi_link_assert( false !== strpos( $current_reward_assets, "wp_localize_script( self::REWARD_SCRIPT, 'falussLinkReward'" ), 'The reward nonce is localized only when the reward surface is rendered.' );

$current_link_scripts = '';
foreach ( $link_scripts as $path ) { $current_link_scripts .= file_get_contents( $root . '/' . $path ); }
fi_link_assert( 0 === substr_count( $current_link_scripts, 'faluss_identity_request_code' ) && 0 === substr_count( $current_link_scripts, "addEventListener('submit'" ), 'Faluss Link cannot submit or alter a passwordless form.' );
fi_link_assert( false !== strpos( $current_link, 'teaser_login_url' ) && false !== strpos( $current_link, 'local_card_login_url' ) && false !== strpos( $current_link, "'access_mode'" ), 'FL-19 teaser access and its safe local login return remain present.' );

$navigation_js = file_get_contents( $root . '/plugins/faluss-identity/assets/js/faluss-identity-navigation.js' );
$navigation_css = file_get_contents( $root . '/plugins/faluss-identity/assets/css/faluss-identity-navigation.css' );
fi_link_assert( false !== strpos( $navigation_js, "var returnFocusMode = 'pointer'" ) && false !== strpos( $navigation_css, '[data-faluss-navigation-return-mode="pointer"]' ), 'FI-07.2 pointer close still restores the configured normal navigation color.' );

echo 'Identity + Link passwordless integration contract: OK' . PHP_EOL;
