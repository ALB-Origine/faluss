<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Native, private configuration screen. It never displays the stored connector secret. */
final class Token_Engine_Connector_Admin {
    const PAGE = 'token-engine-connector';
    const CAPABILITY = 'manage_options';

    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_post_token_engine_connector_save', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_token_engine_connector_test', array( __CLASS__, 'test_connection' ) );
    }

    public static function menu() { add_menu_page( 'Token Engine Connector', 'Token Engine Connector', self::CAPABILITY, self::PAGE, array( __CLASS__, 'page' ), 'dashicons-admin-links', 60 ); }
    public static function enqueue_assets( $hook ) { if ( 'toplevel_page_' . self::PAGE === $hook ) { wp_enqueue_style( 'token-engine-connector-admin', plugins_url( 'assets/css/token-engine-connector-admin.css', TOKEN_ENGINE_CONNECTOR_FILE ), array(), TOKEN_ENGINE_CONNECTOR_VERSION ); } }

    public static function page() {
        if ( ! current_user_can( self::CAPABILITY ) ) { return; }
        $tab = self::tab( $_GET['tab'] ?? '' );
        ?><div class="wrap token-engine-connector-admin"><div class="token-engine-connector-admin__shell"><aside class="token-engine-connector-admin__sidebar" aria-label="Navigation Token Engine Connector"><div class="token-engine-connector-admin__brand"><strong>Faluss</strong><span>by Alternative LAB</span></div><nav class="token-engine-connector-admin__nav" aria-label="Token Engine Connector"><?php foreach ( self::tabs() as $key => $label ) : ?><a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::url( $key ) ); ?>" <?php echo $tab === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav></aside><section class="token-engine-connector-admin__workspace" aria-labelledby="token-engine-connector-title"><header><p>Token Engine Connector</p><h1 id="token-engine-connector-title"><?php echo esc_html( self::tabs()[ $tab ] ); ?></h1><span>Configuration locale, sans ledger ni solde stocké.</span></header><div class="token-engine-connector-admin__panel"><?php self::notice(); if ( 'configuration' === $tab ) { self::configuration_page(); } else { self::diagnostic_page(); } ?></div></section></div></div><?php
    }

    public static function save() {
        self::guard( 'token_engine_connector_save' );
        $result = Token_Engine_Connector_Service::save_configuration( $_POST );
        self::redirect( 'configuration', is_wp_error( $result ) ? $result->get_error_code() : 'saved' );
    }

    public static function test_connection() {
        self::guard( 'token_engine_connector_test' );
        $connection = Token_Engine_Connector_Service::test_connection();
        $subject = Token_Engine_Connector_Service::subject_diagnostic();
        set_transient( self::result_key(), array( 'connected' => ! is_wp_error( $connection ), 'project_key' => is_array( $connection ) ? $connection['project_key'] : '', 'permissions' => is_array( $connection ) ? $connection['permissions'] : array(), 'subject' => $subject ), MINUTE_IN_SECONDS );
        self::redirect( 'diagnostic', is_wp_error( $connection ) ? 'test_failed' : 'test_ok' );
    }

    private static function configuration_page() {
        $settings = Token_Engine_Connector_Service::configuration();
        ?><h2>Configuration du Core</h2><p>Le secret est chiffré localement après enregistrement et n’est jamais réaffiché.</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="token_engine_connector_save"><?php wp_nonce_field( 'token_engine_connector_save', 'token_engine_connector_nonce' ); ?><table class="form-table" role="presentation"><tbody><tr><th><label for="token-engine-connector-url">URL HTTPS du Core</label></th><td><input id="token-engine-connector-url" class="regular-text code" type="url" name="core_url" required placeholder="https://core.example" value="<?php echo esc_attr( $settings['core_url'] ); ?>"><p class="description">URL exacte de l’installation WordPress qui héberge le Core.</p></td></tr><tr><th><label for="token-engine-connector-client">Identifiant client</label></th><td><input id="token-engine-connector-client" class="regular-text code" name="client_id" required value="<?php echo esc_attr( $settings['client_id'] ); ?>"></td></tr><tr><th><label for="token-engine-connector-secret">Secret client</label></th><td><input id="token-engine-connector-secret" class="regular-text" type="password" name="client_secret" autocomplete="new-password" placeholder="<?php echo $settings['secret_configured'] ? 'Secret déjà configuré — laissez vide pour le conserver' : 'Coller le secret une seule fois'; ?>"><p class="description">Il n’est jamais affiché après l’enregistrement.</p></td></tr><tr><th><label for="token-engine-connector-project">Clé projet attendue</label></th><td><input id="token-engine-connector-project" class="regular-text code" name="project_key" required value="<?php echo esc_attr( $settings['project_key'] ); ?>"></td></tr></tbody></table><?php submit_button( 'Enregistrer la configuration' ); ?></form><?php
    }

    private static function diagnostic_page() {
        $result = get_transient( self::result_key() );
        if ( is_array( $result ) ) { delete_transient( self::result_key() ); }
        $subject = Token_Engine_Connector_Service::subject_diagnostic();
        ?><h2>Diagnostic de connexion</h2><p>Le test échange un jeton court uniquement en mémoire puis vérifie l’accès de lecture. Aucun secret, jeton ou sujet n’est affiché.</p><dl class="token-engine-connector-admin__diagnostic"><dt>Configuration</dt><dd><?php echo Token_Engine_Connector_Service::is_configured() ? 'Prête' : 'Incomplète'; ?></dd><dt>Adaptateur Identity</dt><dd><?php echo ! empty( $subject['identity_adapter_available'] ) ? 'Disponible' : 'Non détecté'; ?></dd><dt>Sujet courant</dt><dd><?php echo ! empty( $subject['subject_available'] ) ? 'Résolu sans affichage de valeur' : 'Non résolu'; ?></dd></dl><?php if ( is_array( $result ) ) : ?><div class="notice <?php echo ! empty( $result['connected'] ) ? 'notice-success' : 'notice-error'; ?>"><p><?php echo ! empty( $result['connected'] ) ? 'Connexion validée pour le projet ' . esc_html( $result['project_key'] ) . '.' : 'Connexion non validée. Vérifiez l’URL HTTPS, le client, le secret et le projet.'; ?></p></div><?php endif; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="token_engine_connector_test"><?php wp_nonce_field( 'token_engine_connector_test', 'token_engine_connector_nonce' ); ?><?php submit_button( 'Tester la connexion', 'secondary' ); ?></form><?php
    }

    private static function guard( $action ) { if ( ! current_user_can( self::CAPABILITY ) || ! isset( $_POST['token_engine_connector_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['token_engine_connector_nonce'] ) ), $action ) ) { wp_die( 'Accès refusé.' ); } }
    private static function tabs() { return array( 'configuration' => 'Configuration', 'diagnostic' => 'Diagnostic' ); }
    private static function tab( $value ) { $value = sanitize_key( wp_unslash( $value ) ); return isset( self::tabs()[ $value ] ) ? $value : 'configuration'; }
    private static function url( $tab ) { return add_query_arg( array( 'page' => self::PAGE, 'tab' => $tab ), admin_url( 'admin.php' ) ); }
    private static function redirect( $tab, $notice ) { wp_safe_redirect( add_query_arg( 'token_engine_connector_notice', sanitize_key( $notice ), self::url( $tab ) ) ); exit; }
    private static function result_key() { return 'token_engine_connector_test_' . get_current_user_id(); }
    private static function notice() { $notice = sanitize_key( wp_unslash( $_GET['token_engine_connector_notice'] ?? '' ) ); if ( 'saved' === $notice ) { echo '<div class="notice notice-success"><p>Configuration enregistrée.</p></div>'; } elseif ( '' !== $notice && 'test_ok' !== $notice && 'test_failed' !== $notice ) { echo '<div class="notice notice-error"><p>La configuration ne peut pas être enregistrée.</p></div>'; } }
}
