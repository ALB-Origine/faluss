<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Native administration only; there is no public wallet or frontend surface in this core. */
final class Token_Engine_Admin {
    const PAGE = 'token-engine';
    const CAPABILITY = 'manage_options';

    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_token_engine_save_configuration', array( __CLASS__, 'save_configuration' ) );
        add_action( 'admin_post_token_engine_create_project', array( __CLASS__, 'create_project' ) );
        add_action( 'admin_post_token_engine_update_project', array( __CLASS__, 'update_project' ) );
        add_action( 'admin_post_token_engine_create_rule', array( __CLASS__, 'create_rule' ) );
        add_action( 'admin_post_token_engine_update_rule', array( __CLASS__, 'update_rule' ) );
        add_action( 'admin_post_token_engine_adjust', array( __CLASS__, 'adjust' ) );
    }

    public static function menu() {
        add_menu_page( 'Token Engine', 'Token Engine', self::CAPABILITY, self::PAGE, array( __CLASS__, 'page' ), 'dashicons-database', 59 );
    }

    public static function page() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }
        $tab = self::tab( $_GET['tab'] ?? '' );
        ?>
        <div class="wrap token-engine-admin">
            <h1>Token Engine</h1>
            <p>Core générique : les écritures sont immuables et les soldes sont des projections du ledger.</p>
            <nav class="nav-tab-wrapper" aria-label="Token Engine">
                <?php foreach ( self::tabs() as $key => $label ) : ?><a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::url( $key ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
            </nav>
            <?php self::notice(); ?>
            <?php if ( 'configuration' === $tab ) { self::configuration_page(); } ?>
            <?php if ( 'projects' === $tab ) { self::projects_page(); } ?>
            <?php if ( 'rules' === $tab ) { self::rules_page(); } ?>
            <?php if ( 'ledger' === $tab ) { self::ledger_page(); } ?>
            <?php if ( 'adjustment' === $tab ) { self::adjustment_page(); } ?>
        </div>
        <?php
    }

    public static function save_configuration() {
        self::guard( 'token_engine_save_configuration' );
        $result = Token_Engine_Service::save_configuration( $_POST );
        self::redirect( 'configuration', is_wp_error( $result ) ? $result->get_error_code() : 'saved' );
    }

    public static function create_project() {
        self::guard( 'token_engine_create_project' );
        $result = Token_Engine_Service::create_project( $_POST );
        self::redirect( 'projects', is_wp_error( $result ) ? $result->get_error_code() : 'project_created' );
    }

    public static function update_project() {
        self::guard( 'token_engine_update_project' );
        $result = Token_Engine_Service::update_project( $_POST['project_id'] ?? 0, $_POST );
        self::redirect( 'projects', is_wp_error( $result ) ? $result->get_error_code() : 'project_saved' );
    }

    public static function create_rule() {
        self::guard( 'token_engine_create_rule' );
        $result = Token_Engine_Service::create_rule( $_POST );
        self::redirect( 'rules', is_wp_error( $result ) ? $result->get_error_code() : 'rule_created' );
    }

    public static function update_rule() {
        self::guard( 'token_engine_update_rule' );
        $result = Token_Engine_Service::update_rule( $_POST['rule_id'] ?? 0, $_POST );
        self::redirect( 'rules', is_wp_error( $result ) ? $result->get_error_code() : 'rule_saved' );
    }

    public static function adjust() {
        self::guard( 'token_engine_adjust' );
        $result = Token_Engine_Service::write_transaction( array(
            'transaction_uuid' => wp_unslash( $_POST['operation_uuid'] ?? '' ),
            'idempotency_key' => wp_unslash( $_POST['operation_uuid'] ?? '' ),
            'subject_id' => wp_unslash( $_POST['subject_id'] ?? '' ),
            'project_key' => wp_unslash( $_POST['project_key'] ?? '' ),
            'direction' => wp_unslash( $_POST['direction'] ?? '' ),
            'amount' => wp_unslash( $_POST['amount'] ?? '' ),
            'source_reference' => wp_unslash( $_POST['source_reference'] ?? '' ),
            'metadata' => array( 'manual_adjustment' => true ),
        ) );
        if ( is_wp_error( $result ) ) {
            self::redirect( 'adjustment', $result->get_error_code() );
        }
        set_transient( self::adjustment_transient_key(), array( 'balance' => (int) $result['balance'], 'idempotent' => ! empty( $result['idempotent'] ) ), MINUTE_IN_SECONDS );
        self::redirect( 'adjustment', 'adjusted' );
    }

    private static function configuration_page() {
        $settings = Token_Engine_Service::configuration();
        ?>
        <h2>Configuration</h2>
        <p>Aucune unité n’est active tant que ces quatre champs ne sont pas valides. Le code devient immuable après la première écriture.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="token_engine_save_configuration">
            <?php wp_nonce_field( 'token_engine_save_configuration', 'token_engine_nonce' ); ?>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><label for="token-engine-unit-code">Code de l’unité</label></th><td><input id="token-engine-unit-code" name="unit_code" class="regular-text" maxlength="16" value="<?php echo esc_attr( $settings['unit_code'] ); ?>" <?php disabled( Token_Engine_Service::has_transactions() ); ?>><p class="description">Lettres capitales, chiffres, tiret ou souligné.</p></td></tr>
                <tr><th scope="row"><label for="token-engine-unit-singular">Libellé singulier</label></th><td><input id="token-engine-unit-singular" name="unit_singular" class="regular-text" maxlength="80" value="<?php echo esc_attr( $settings['unit_singular'] ); ?>"></td></tr>
                <tr><th scope="row"><label for="token-engine-unit-plural">Libellé pluriel</label></th><td><input id="token-engine-unit-plural" name="unit_plural" class="regular-text" maxlength="80" value="<?php echo esc_attr( $settings['unit_plural'] ); ?>"></td></tr>
                <tr><th scope="row"><label for="token-engine-timezone">Fuseau horaire de référence</label></th><td><select id="token-engine-timezone" name="reference_timezone"><option value="">Choisir un fuseau</option><?php foreach ( timezone_identifiers_list() as $timezone ) : ?><option value="<?php echo esc_attr( $timezone ); ?>" <?php selected( $settings['reference_timezone'], $timezone ); ?>><?php echo esc_html( $timezone ); ?></option><?php endforeach; ?></select></td></tr>
            </tbody></table>
            <?php submit_button( 'Enregistrer la configuration' ); ?>
        </form>
        <?php
    }

    private static function projects_page() {
        global $wpdb;
        $projects = (array) $wpdb->get_results( 'SELECT * FROM ' . Token_Engine_Schema::projects_table() . ' ORDER BY name ASC, id ASC', ARRAY_A );
        ?>
        <h2>Projets</h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="token_engine_create_project"><?php wp_nonce_field( 'token_engine_create_project', 'token_engine_nonce' ); ?>
            <table class="form-table" role="presentation"><tbody><tr><th><label for="token-engine-project-key">Clé stable</label></th><td><input id="token-engine-project-key" name="project_key" required maxlength="64" pattern="[a-z0-9][a-z0-9_-]{1,63}"></td></tr><tr><th><label for="token-engine-project-name">Nom</label></th><td><input id="token-engine-project-name" name="name" required maxlength="120" class="regular-text"></td></tr><tr><th>État</th><td><label><input name="active" type="checkbox" value="1" checked> Actif</label></td></tr></tbody></table><?php submit_button( 'Créer le projet', 'secondary' ); ?>
        </form>
        <table class="widefat striped"><thead><tr><th>Clé</th><th>Nom</th><th>État</th><th>Enregistrer</th></tr></thead><tbody>
        <?php foreach ( $projects as $project ) : ?><tr><td colspan="4"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><code><?php echo esc_html( $project['project_key'] ); ?></code><input type="hidden" name="action" value="token_engine_update_project"><input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>"><?php wp_nonce_field( 'token_engine_update_project', 'token_engine_nonce' ); ?><label>Nom <input name="name" maxlength="120" value="<?php echo esc_attr( $project['name'] ); ?>"></label><label><input name="active" type="checkbox" value="1" <?php checked( ! empty( $project['active'] ) ); ?>> Actif</label><button class="button" type="submit">Enregistrer</button></form></td></tr><?php endforeach; ?>
        <?php if ( ! $projects ) : ?><tr><td colspan="4">Aucun projet.</td></tr><?php endif; ?></tbody></table>
        <?php
    }

    private static function rules_page() {
        $projects = Token_Engine_Service::active_projects();
        $rules = Token_Engine_Service::rules();
        ?>
        <h2>Règles</h2><p>Les règles sont administrées ici, mais TE-01 ne les exécute jamais automatiquement.</p>
        <?php self::rule_form( array(), $projects, 'token_engine_create_rule', 'Créer la règle' ); ?>
        <table class="widefat striped"><thead><tr><th>Clé</th><th>Portée</th><th>Déclencheur</th><th>Périodicité</th><th>Montant</th><th>État</th><th>Enregistrer</th></tr></thead><tbody>
        <?php foreach ( $rules as $rule ) : ?><tr><td colspan="7"><?php self::rule_form( $rule, $projects, 'token_engine_update_rule', 'Enregistrer la règle' ); ?></td></tr><?php endforeach; ?>
        <?php if ( ! $rules ) : ?><tr><td colspan="7">Aucune règle.</td></tr><?php endif; ?></tbody></table>
        <?php
    }

    private static function rule_form( $rule, $projects, $action, $submit ) {
        $is_update = 'token_engine_update_rule' === $action;
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
            <input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>"><?php if ( $is_update ) : ?><input type="hidden" name="rule_id" value="<?php echo (int) $rule['id']; ?>"><?php endif; ?><?php wp_nonce_field( $action, 'token_engine_nonce' ); ?>
            <label>Clé<br><input name="rule_key" maxlength="96" required value="<?php echo esc_attr( $rule['rule_key'] ?? '' ); ?>" <?php echo $is_update ? 'readonly' : ''; ?>></label>
            <label>Portée<br><select name="scope"><option value="global" <?php selected( $rule['scope'] ?? 'global', 'global' ); ?>>Globale</option><option value="project" <?php selected( $rule['scope'] ?? '', 'project' ); ?>>Projet</option></select></label>
            <label>Projet<br><select name="project_id"><option value="0">Aucun</option><?php foreach ( $projects as $project ) : ?><option value="<?php echo (int) $project['id']; ?>" <?php selected( (int) ( $rule['project_id'] ?? 0 ), (int) $project['id'] ); ?>><?php echo esc_html( $project['name'] ); ?></option><?php endforeach; ?></select></label>
            <label>Déclencheur<br><select name="trigger_type"><option value="event" <?php selected( $rule['trigger_type'] ?? 'event', 'event' ); ?>>Événement</option><option value="claim" <?php selected( $rule['trigger_type'] ?? '', 'claim' ); ?>>Réclamation</option></select></label>
            <label>Périodicité<br><select name="periodicity"><option value="none" <?php selected( $rule['periodicity'] ?? 'none', 'none' ); ?>>Aucune</option><option value="once" <?php selected( $rule['periodicity'] ?? '', 'once' ); ?>>Une fois</option><option value="daily" <?php selected( $rule['periodicity'] ?? '', 'daily' ); ?>>Quotidienne</option><option value="cooldown" <?php selected( $rule['periodicity'] ?? '', 'cooldown' ); ?>>Cooldown</option></select></label>
            <label>Cooldown (s)<br><input name="cooldown_seconds" type="number" min="1" value="<?php echo (int) ( $rule['cooldown_seconds'] ?? 0 ); ?>"></label>
            <label>Montant<br><input name="amount" type="number" min="1" required value="<?php echo (int) ( $rule['amount'] ?? 1 ); ?>"></label>
            <label><input name="active" type="checkbox" value="1" <?php checked( ! isset( $rule['active'] ) || ! empty( $rule['active'] ) ); ?>> Active</label><button class="button" type="submit"><?php echo esc_html( $submit ); ?></button>
        </form>
        <?php
    }

    private static function ledger_page() {
        $entries = Token_Engine_Service::ledger_entries();
        ?><h2>Ledger</h2><table class="widefat striped"><thead><tr><th>Date</th><th>UUID</th><th>Sujet</th><th>Projet</th><th>Règle</th><th>Sens</th><th>Montant</th><th>Référence</th></tr></thead><tbody><?php foreach ( $entries as $entry ) : ?><tr><td><?php echo esc_html( $entry['created_at'] ); ?></td><td><code><?php echo esc_html( $entry['transaction_uuid'] ); ?></code></td><td><?php echo esc_html( $entry['subject_id'] ); ?></td><td><?php echo esc_html( $entry['project_key'] ); ?></td><td><?php echo esc_html( $entry['rule_key'] ?: '—' ); ?></td><td><?php echo esc_html( $entry['direction'] ); ?></td><td><?php echo (int) $entry['amount']; ?></td><td><?php echo esc_html( $entry['source_reference'] ?: '—' ); ?></td></tr><?php endforeach; ?><?php if ( ! $entries ) : ?><tr><td colspan="8">Aucune transaction.</td></tr><?php endif; ?></tbody></table><?php
    }

    private static function adjustment_page() {
        $projects = Token_Engine_Service::active_projects();
        $result = get_transient( self::adjustment_transient_key() );
        if ( is_array( $result ) ) { delete_transient( self::adjustment_transient_key() ); }
        ?><h2>Ajustement manuel</h2><p>Chaque soumission porte un UUID d’opération : un renvoi du même formulaire retrouve l’écriture existante.</p><?php if ( is_array( $result ) ) : ?><div class="notice notice-success"><p><?php echo ! empty( $result['idempotent'] ) ? 'Opération déjà inscrite.' : 'Ajustement inscrit.'; ?> Solde projeté : <strong><?php echo (int) $result['balance']; ?></strong></p></div><?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="token_engine_adjust"><input type="hidden" name="operation_uuid" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>"><?php wp_nonce_field( 'token_engine_adjust', 'token_engine_nonce' ); ?>
        <table class="form-table" role="presentation"><tbody><tr><th><label for="token-engine-adjust-project">Projet</label></th><td><select id="token-engine-adjust-project" name="project_key" required><option value="">Choisir un projet</option><?php foreach ( $projects as $project ) : ?><option value="<?php echo esc_attr( $project['project_key'] ); ?>"><?php echo esc_html( $project['name'] ); ?></option><?php endforeach; ?></select></td></tr><tr><th><label for="token-engine-adjust-subject">Subject ID</label></th><td><input id="token-engine-adjust-subject" name="subject_id" maxlength="191" required class="regular-text"></td></tr><tr><th>Sens</th><td><label><input name="direction" type="radio" value="credit" checked> Crédit</label> <label><input name="direction" type="radio" value="debit"> Débit</label></td></tr><tr><th><label for="token-engine-adjust-amount">Montant</label></th><td><input id="token-engine-adjust-amount" name="amount" type="number" min="1" required></td></tr><tr><th><label for="token-engine-adjust-reference">Motif / référence</label></th><td><input id="token-engine-adjust-reference" name="source_reference" maxlength="191" class="regular-text"></td></tr></tbody></table><?php submit_button( 'Inscrire l’ajustement' ); ?></form><?php
    }

    private static function guard( $action ) {
        if ( ! current_user_can( self::CAPABILITY ) || ! isset( $_POST['token_engine_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['token_engine_nonce'] ) ), $action ) ) {
            wp_die( 'Accès refusé.' );
        }
    }

    private static function tabs() { return array( 'configuration' => 'Configuration', 'projects' => 'Projets', 'rules' => 'Règles', 'ledger' => 'Ledger', 'adjustment' => 'Ajustement manuel' ); }
    private static function tab( $value ) { $value = sanitize_key( wp_unslash( $value ) ); return isset( self::tabs()[ $value ] ) ? $value : 'configuration'; }
    private static function url( $tab ) { return add_query_arg( array( 'page' => self::PAGE, 'tab' => $tab ), admin_url( 'admin.php' ) ); }
    private static function redirect( $tab, $notice ) { wp_safe_redirect( add_query_arg( 'token_engine_notice', sanitize_key( $notice ), self::url( $tab ) ) ); exit; }
    private static function adjustment_transient_key() { return 'token_engine_adjustment_' . get_current_user_id(); }
    private static function notice() { $notice = sanitize_key( wp_unslash( $_GET['token_engine_notice'] ?? '' ) ); $messages = array( 'saved' => 'Configuration enregistrée.', 'project_created' => 'Projet créé.', 'project_saved' => 'Projet enregistré.', 'rule_created' => 'Règle créée.', 'rule_saved' => 'Règle enregistrée.' ); if ( isset( $messages[ $notice ] ) ) { echo '<div class="notice notice-success"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>'; } elseif ( '' !== $notice && 'adjusted' !== $notice ) { echo '<div class="notice notice-error"><p>' . esc_html( 'L’opération ne peut pas être enregistrée : ' . $notice ) . '</p></div>'; } }
}
