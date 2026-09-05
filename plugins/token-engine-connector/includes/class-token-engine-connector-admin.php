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
        add_action( 'admin_post_token_engine_connector_test', array( __CLASS__, 'test_core_connection' ) );
        add_action( 'admin_post_token_engine_connector_test_core', array( __CLASS__, 'test_core_connection' ) );
        add_action( 'admin_post_token_engine_connector_test_subject', array( __CLASS__, 'test_subject' ) );
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

    /** Core authentication and read permission only: no subject is resolved here. */
    public static function test_core_connection() {
        self::guard( 'token_engine_connector_test_core' );
        $connection = Token_Engine_Connector_Service::core_connection_test();
        $error_data = is_wp_error( $connection ) ? $connection->get_error_data() : array();
        $diagnostic_id = is_wp_error( $connection ) ? ( is_array( $error_data ) ? (string) ( $error_data['diagnostic_id'] ?? '' ) : '' ) : (string) ( $connection['diagnostic_id'] ?? '' );
        $result = array(
            'connected' => ! is_wp_error( $connection ),
            'code' => is_wp_error( $connection ) ? $connection->get_error_code() : 'connector_core_valid',
            'project_key' => is_array( $connection ) ? $connection['project_key'] : '',
            'permissions' => is_array( $connection ) ? $connection['permissions'] : array(),
            'protocol_version' => is_array( $connection ) ? (string) ( $connection['protocol_version'] ?? '' ) : '',
            'steps' => is_array( $connection ) && is_array( $connection['steps'] ?? null ) ? $connection['steps'] : array(),
            'stage' => is_wp_error( $connection ) && is_array( $error_data ) ? (string) ( $error_data['stage'] ?? '' ) : '',
            'diagnostic_id' => $diagnostic_id,
        );
        set_transient( self::result_key( 'core' ), $result, MINUTE_IN_SECONDS );
        if ( ! empty( $result['connected'] ) ) { set_transient( self::core_valid_key(), true, 5 * MINUTE_IN_SECONDS ); } else { delete_transient( self::core_valid_key() ); }
        self::redirect( 'diagnostic', ! empty( $result['connected'] ) ? 'test_ok' : 'test_failed' );
    }

    /** Faluss Identity/profile diagnostic is separate from any remote Core call. */
    public static function test_subject() {
        self::guard( 'token_engine_connector_test_subject' );
        if ( ! get_transient( self::core_valid_key() ) ) {
            self::redirect( 'diagnostic', 'subject_core_required' );
        }
        $subject = Token_Engine_Connector_Service::faluss_subject_diagnostic();
        $subject['diagnostic_id'] = wp_generate_uuid4();
        set_transient( self::result_key( 'subject' ), $subject, MINUTE_IN_SECONDS );
        self::redirect( 'diagnostic', 'subject_tested' );
    }

    private static function configuration_page() {
        $settings = Token_Engine_Connector_Service::configuration();
        ?><h2>Configuration du Core</h2><p>Collez uniquement l’URL canonique du site Core fournie par Token Engine. Le Connector détecte ensuite sa forme REST ; le secret est chiffré localement et n’est jamais réaffiché.</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="token_engine_connector_save"><?php wp_nonce_field( 'token_engine_connector_save', 'token_engine_connector_nonce' ); ?><table class="form-table" role="presentation"><tbody><tr><th><label for="token-engine-connector-url">URL du site Core</label></th><td><input id="token-engine-connector-url" class="regular-text code" type="url" name="core_site_url" required placeholder="https://core.example" value="<?php echo esc_attr( $settings['core_site_url'] ); ?>"><p class="description">HTTPS uniquement. Un sous-répertoire WordPress est accepté ; ne saisissez ni <code>/wp-json</code>, ni <code>rest_route</code>, ni endpoint Token Engine.</p></td></tr><tr><th><label for="token-engine-connector-client">Identifiant client</label></th><td><input id="token-engine-connector-client" class="regular-text code" name="client_id" required value="<?php echo esc_attr( $settings['client_id'] ); ?>"></td></tr><tr><th><label for="token-engine-connector-secret">Secret client</label></th><td><input id="token-engine-connector-secret" class="regular-text" type="password" name="client_secret" autocomplete="new-password" placeholder="<?php echo $settings['secret_configured'] ? 'Laissez vide pour conserver le secret enregistré' : 'Coller le secret une seule fois'; ?>"><p class="description"><strong><?php echo 'saved' === $settings['secret_state'] ? 'Secret enregistré.' : 'Secret requis.'; ?></strong> Il n’est jamais affiché. Après une régénération côté Core, remplacez-le ici avant un nouveau test.</p></td></tr><tr><th><label for="token-engine-connector-project">Clé projet attendue</label></th><td><input id="token-engine-connector-project" class="regular-text code" name="project_key" required value="<?php echo esc_attr( $settings['project_key'] ); ?>"></td></tr></tbody></table><?php submit_button( 'Enregistrer la configuration' ); ?></form><?php
    }

    private static function diagnostic_page() {
        $core = self::take_result( 'core' );
        $subject = self::take_result( 'subject' );
        $core_valid = (bool) get_transient( self::core_valid_key() );
        ?><h2>Diagnostic</h2><p>Les deux contrôles sont séparés. Aucun secret, jeton, en-tête d’autorisation ni réponse distante brute n’est affiché.</p><section class="token-engine-connector-admin__diagnostic-section" aria-labelledby="token-engine-connector-core-title"><h3 id="token-engine-connector-core-title">Connexion au Core</h3><p>Valide l’URL du site Core, détecte la forme REST, puis vérifie route, identité, protocole, client, secret, <code>wallet.read</code> et jeton court. Ce contrôle ne dépend pas d’une session Faluss, d’un profil ou d’un solde.</p><?php self::core_result( $core ); ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="token_engine_connector_test_core"><?php wp_nonce_field( 'token_engine_connector_test_core', 'token_engine_connector_nonce' ); ?><?php submit_button( 'Tester la connexion au Core', 'secondary' ); ?></form></section><section class="token-engine-connector-admin__diagnostic-section" aria-labelledby="token-engine-connector-subject-title"><h3 id="token-engine-connector-subject-title">Diagnostic Sujet Faluss</h3><p>Disponible seulement après une connexion Core valide. Il vérifie l’adaptateur Identity, le profil actif de l’utilisateur WordPress courant et la disponibilité d’un sujet, sans afficher le Faluss ID.</p><?php self::subject_result( $subject ); ?><?php if ( $core_valid ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="token_engine_connector_test_subject"><?php wp_nonce_field( 'token_engine_connector_test_subject', 'token_engine_connector_nonce' ); ?><?php submit_button( 'Diagnostiquer le sujet Faluss', 'secondary' ); ?></form><?php else : ?><p class="description">Validez d’abord la connexion au Core.</p><?php endif; ?></section><?php
    }

    private static function core_result( $result ) {
        if ( ! is_array( $result ) ) { return; }
        $success = ! empty( $result['connected'] );
        ?><div class="notice <?php echo $success ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html( self::core_message( $result['code'] ?? '' ) ); ?><?php if ( $success && ! empty( $result['project_key'] ) ) : ?> <?php echo esc_html( 'Projet : ' . $result['project_key'] . '.' ); ?><?php endif; ?></p><?php if ( $success ) : ?><ol class="token-engine-connector-admin__steps"><?php foreach ( self::step_labels() as $key => $label ) : ?><li><?php echo esc_html( $label . ' : ' . ( $result['steps'][ $key ] ?? 'non validée' ) ); ?></li><?php endforeach; ?></ol><?php elseif ( ! empty( $result['stage'] ) ) : ?><p class="description">Étape bloquante : <?php echo esc_html( self::step_labels()[ $result['stage'] ] ?? $result['stage'] ); ?></p><?php endif; ?><?php if ( ! empty( $result['diagnostic_id'] ) ) : ?><p class="description">ID de diagnostic : <code><?php echo esc_html( $result['diagnostic_id'] ); ?></code></p><?php endif; ?></div><?php
    }

    private static function subject_result( $result ) {
        if ( ! is_array( $result ) ) { return; }
        ?><dl class="token-engine-connector-admin__diagnostic"><dt>Utilisateur WordPress</dt><dd><?php echo ! empty( $result['signed_in'] ) ? 'Connecté' : 'Non connecté'; ?></dd><dt>Faluss Identity</dt><dd><?php echo ! empty( $result['identity_adapter_available'] ) ? 'Détecté' : 'Non détecté'; ?></dd><dt>Profil Identity actif</dt><dd><?php echo ! empty( $result['active_identity_profile'] ) ? 'Détecté' : 'Non détecté'; ?></dd><dt>Sujet Faluss</dt><dd><?php echo ! empty( $result['subject_available'] ) ? 'Résolu sans affichage de valeur' : 'Non résolu'; ?></dd><?php if ( ! empty( $result['subject_fingerprint'] ) ) : ?><dt>Empreinte de diagnostic</dt><dd><code><?php echo esc_html( $result['subject_fingerprint'] ); ?></code></dd><?php endif; ?><dt>ID de diagnostic</dt><dd><code><?php echo esc_html( $result['diagnostic_id'] ?? '' ); ?></code></dd></dl><?php
    }

    private static function core_message( $code ) {
        $messages = array(
            'connector_core_valid' => 'Connexion au Core validée.',
            'connector_core_url_invalid' => 'URL du site Core invalide ou non HTTPS.',
            'connector_core_inaccessible' => 'Core inaccessible.',
            'connector_core_redirect_rejected' => 'Redirection inattendue refusée.',
            'connector_route_missing' => 'Route Token Engine introuvable.',
            'connector_core_unidentified' => 'Le serveur distant n’est pas le Core Token Engine attendu.',
            'connector_protocol_incompatible' => 'Version de protocole Core/Connector incompatible.',
            'connector_project_invalid' => 'Clé projet locale invalide.',
            'connector_project_rejected' => 'Projet du Core absent ou différent.',
            'connector_project_inactive' => 'Projet du Core inactif.',
            'connector_client_invalid' => 'Identifiant client local invalide.',
            'connector_client_rejected' => 'Identifiant client refusé par le Core.',
            'connector_secret_missing' => 'Secret client manquant.',
            'connector_secret_required' => 'Secret client requis.',
            'connector_secret_unavailable' => 'Secret client local indisponible.',
            'connector_secret_rejected' => 'Secret client refusé. Il a peut-être été régénéré côté Core : remplacez-le manuellement.',
            'connector_credentials_missing' => 'Identifiants connecteur absents côté Core.',
            'connector_permission_wallet_read_missing' => 'Permission wallet.read absente ou révoquée côté Core.',
            'connector_token_rejected' => 'Jeton court refusé ou expiré.',
            'https_required' => 'Le Core exige HTTPS.',
        );
        return $messages[ $code ] ?? 'Connexion au Core non validée.';
    }

    private static function take_result( $kind ) { $result = get_transient( self::result_key( $kind ) ); if ( is_array( $result ) ) { delete_transient( self::result_key( $kind ) ); return $result; } return null; }
    private static function step_labels() { return array( 'url' => 'URL du site Core', 'rest' => 'Mécanisme REST détecté', 'route' => 'Route Core', 'core' => 'Core Token Engine', 'protocol' => 'Protocole', 'credentials' => 'Credentials', 'permission' => 'Permission wallet.read', 'token' => 'Jeton court' ); }
    private static function guard( $action ) { if ( ! current_user_can( self::CAPABILITY ) || ! isset( $_POST['token_engine_connector_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['token_engine_connector_nonce'] ) ), $action ) ) { wp_die( 'Accès refusé.' ); } }
    private static function tabs() { return array( 'configuration' => 'Configuration', 'diagnostic' => 'Diagnostic' ); }
    private static function tab( $value ) { $value = sanitize_key( wp_unslash( $value ) ); return isset( self::tabs()[ $value ] ) ? $value : 'configuration'; }
    private static function url( $tab ) { return add_query_arg( array( 'page' => self::PAGE, 'tab' => $tab ), admin_url( 'admin.php' ) ); }
    private static function redirect( $tab, $notice ) { wp_safe_redirect( add_query_arg( 'token_engine_connector_notice', sanitize_key( $notice ), self::url( $tab ) ) ); exit; }
    private static function result_key( $kind ) { return 'token_engine_connector_' . sanitize_key( $kind ) . '_' . get_current_user_id(); }
    private static function core_valid_key() { return 'token_engine_connector_core_valid_' . get_current_user_id(); }
    private static function notice() { $notice = sanitize_key( wp_unslash( $_GET['token_engine_connector_notice'] ?? '' ) ); if ( 'saved' === $notice ) { echo '<div class="notice notice-success"><p>Configuration enregistrée.</p></div>'; return; } $messages = array( 'connector_secret_protection_unavailable' => 'Le stockage protégé du secret n’est pas disponible sur cette instance. La configuration n’a pas été enregistrée.', 'connector_secret_persistence_failed' => 'Le secret protégé ne peut pas être relu après enregistrement. La configuration précédente est conservée.', 'connector_secret_required' => 'Secret requis : collez le secret généré par le Core.', 'connector_core_url_invalid' => 'URL HTTPS du site Core invalide.', 'connector_client_invalid' => 'Identifiant client invalide.', 'connector_project_invalid' => 'Clé projet invalide.', 'subject_core_required' => 'Validez d’abord la connexion au Core.' ); if ( isset( $messages[ $notice ] ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>'; } elseif ( '' !== $notice && ! in_array( $notice, array( 'test_ok', 'test_failed', 'subject_tested' ), true ) ) { echo '<div class="notice notice-error"><p>La configuration ne peut pas être enregistrée.</p></div>'; } }
}
