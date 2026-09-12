<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Coordinates a deliberately narrow, one-time reset. This class never accepts
 * a member, table, role, amount, URL or target supplied by a browser or peer.
 */
class Faluss_Production_Reset {
    const VERSION = '0.1.3';
    const PROTOCOL = '1';
    const OPERATION = 'hub_member_reset_v1';
    const CONFIRMATION = 'METTRE FALUSS EN PRODUCTION';
    const ARM_CONFIRMATION = 'ARMER LE RESET FALUSS';
    const OPTION_ARMED = 'faluss_production_reset_armed';
    const OPTION_ARMED_VERSION = 'faluss_production_reset_armed_version';
    const OPTION_LOCKED = 'faluss_production_reset_locked';
    const OPTION_RECEIPT = 'faluss_production_reset_receipt';
    const NONCE_OPTION_PREFIX = 'faluss_production_reset_nonce_';
    const RECEIVER_NAMESPACE = 'faluss-production-reset/v1';
    const RECEIVER_PATH = '/hub-member-reset';
    const HUB_RECEIVER_URL = 'https://faluss.com/wp-json/faluss-production-reset/v1/hub-member-reset';
    const MAX_CLOCK_SKEW = 300;

    public static function boot() {
        self::invalidate_legacy_armament();
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_post_faluss_production_reset_arm', array( __CLASS__, 'post_arm' ) );
        add_action( 'admin_post_faluss_production_reset_preflight', array( __CLASS__, 'post_preflight' ) );
        add_action( 'admin_post_faluss_production_reset_execute', array( __CLASS__, 'post_execute' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_hub_receiver' ) );
    }

    public static function admin_menu() {
        if ( self::site_role() ) {
            add_management_page(
                'Mise en production Faluss',
                'Mise en production Faluss',
                'manage_options',
                'faluss-production-reset',
                array( __CLASS__, 'render_admin_page' )
            );
        }
    }

    public static function register_hub_receiver() {
        if ( 'hub' !== self::site_role() || ! self::is_armed() || self::is_locked() ) {
            return;
        }

        register_rest_route(
            self::RECEIVER_NAMESPACE,
            self::RECEIVER_PATH,
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'receive_hub_reset' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public static function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'faluss-production-reset' ), 403 );
        }

        $role = self::site_role();
        if ( ! $role ) {
            wp_die( esc_html__( 'Ce plugin est volontairement inactif hors de faluss.me et faluss.com.', 'faluss-production-reset' ), 403 );
        }

        $receipt = get_option( self::OPTION_RECEIPT, array() );
        $preview = get_transient( self::preview_key() );
        ?>
        <div class="wrap faluss-production-reset">
            <h1>Mise en production Faluss</h1>
            <p>Contrôle exceptionnel, réservé aux administrateurs WordPress. Les aperçus et reçus ne contiennent que des compteurs.</p>
            <?php self::render_notice(); ?>
            <h2>État local : <?php echo esc_html( self::is_locked() ? 'verrouillé' : ( self::is_armed() ? 'armé' : 'désarmé' ) ); ?></h2>
            <?php if ( self::is_locked() ) : ?>
                <p>Cette installation est verrouillée après une exécution. Un nouvel essai exige son réarmement explicite ici, ainsi que sur l’autre site.</p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'faluss_production_reset_arm' ); ?>
                <input type="hidden" name="action" value="faluss_production_reset_arm" />
                <p><label for="fpr-arm-phrase">Pour armer localement, saisissez <code>ARMER LE RESET FALUSS</code>.</label></p>
                <p><input id="fpr-arm-phrase" name="confirmation" type="text" autocomplete="off" required /></p>
                <p><button class="button" type="submit">Armer cette installation</button></p>
            </form>
            <?php if ( 'identity' === $role && self::is_armed() && ! self::is_locked() ) : ?>
                <hr />
                <h2>Préflight coordonné</h2>
                <p>Le préflight vérifie faluss.me et faluss.com avant toute suppression. Il échoue fermé si les médias, le ledger PF ou les préconditions ne sont pas vérifiables.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'faluss_production_reset_preflight' ); ?>
                    <input type="hidden" name="action" value="faluss_production_reset_preflight" />
                    <p><label for="fpr-preflight-phrase">Saisissez <code>METTRE FALUSS EN PRODUCTION</code> pour afficher les compteurs.</label></p>
                    <p><input id="fpr-preflight-phrase" name="confirmation" type="text" autocomplete="off" required /></p>
                    <p><button class="button" type="submit">Prévisualiser</button></p>
                </form>
                <?php if ( is_array( $preview ) ) : ?>
                    <h3>Prévisualisation en lecture seule</h3>
                    <?php self::render_counts( $preview['identity'] ?? array(), 'faluss.me' ); ?>
                    <?php self::render_counts( $preview['hub'] ?? array(), 'faluss.com' ); ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'faluss_production_reset_execute' ); ?>
                        <input type="hidden" name="action" value="faluss_production_reset_execute" />
                        <p><label for="fpr-execute-phrase">Confirmation finale : saisissez exactement <code>METTRE FALUSS EN PRODUCTION</code>.</label></p>
                        <p><input id="fpr-execute-phrase" name="confirmation" type="text" autocomplete="off" required /></p>
                        <p><button class="button button-primary" type="submit">Confirmer le reset coordonné</button></p>
                    </form>
                <?php endif; ?>
            <?php elseif ( 'hub' === $role ) : ?>
                <p>faluss.com ne propose aucune action de reset locale : il est seulement armé ici et reçoit l’opération fixe, signée, initiée depuis faluss.me.</p>
            <?php endif; ?>
            <?php if ( is_array( $receipt ) && ! empty( $receipt ) ) : ?>
                <hr />
                <h2>Reçu technique</h2>
                <p><strong>Run :</strong> <?php echo esc_html( $receipt['run_id'] ?? '' ); ?> — <strong>Statut :</strong> <?php echo esc_html( $receipt['status'] ?? '' ); ?> — <strong>Terminé :</strong> <?php echo esc_html( $receipt['finished_at'] ?? '' ); ?></p>
                <?php self::render_counts( $receipt['counts'] ?? array(), 'compteurs locaux' ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function post_arm() {
        self::require_administrator();
        check_admin_referer( 'faluss_production_reset_arm' );
        if ( ! isset( $_POST['confirmation'] ) || self::ARM_CONFIRMATION !== wp_unslash( $_POST['confirmation'] ) ) {
            self::redirect_notice( 'La phrase d’armement est invalide.', 'error' );
        }
        update_option( self::OPTION_ARMED, 1, false );
        update_option( self::OPTION_ARMED_VERSION, self::VERSION, false );
        delete_option( self::OPTION_LOCKED );
        self::redirect_notice( 'Installation armée explicitement.', 'success' );
    }

    public static function post_preflight() {
        self::require_identity_operator( 'faluss_production_reset_preflight' );
        if ( ! self::has_confirmation() ) {
            self::redirect_notice( 'La phrase de mise en production est invalide.', 'error' );
        }

        $identity = self::preflight_identity();
        if ( is_wp_error( $identity ) ) {
            self::redirect_notice( self::safe_error_message( $identity ), 'error' );
        }
        $hub = self::request_hub( 'preflight', wp_generate_uuid4() );
        if ( is_wp_error( $hub ) || 'preflight' !== ( $hub['status'] ?? '' ) ) {
            self::redirect_notice( self::safe_error_message( $hub ), 'error' );
        }

        set_transient( self::preview_key(), array(
            'created_at' => gmdate( 'c' ),
            'identity'   => self::public_counts( $identity ),
            'hub'        => self::public_counts( $hub['counts'] ),
        ), 10 * MINUTE_IN_SECONDS );
        self::redirect_notice( 'Préflight coordonné réussi. Vérifiez les compteurs avant confirmation finale.', 'success' );
    }

    public static function post_execute() {
        self::require_identity_operator( 'faluss_production_reset_execute' );
        if ( ! self::has_confirmation() || ! is_array( get_transient( self::preview_key() ) ) ) {
            self::redirect_notice( 'Une prévisualisation valide et la phrase exacte sont obligatoires.', 'error' );
        }

        $identity = self::preflight_identity();
        if ( is_wp_error( $identity ) ) {
            self::redirect_notice( self::safe_error_message( $identity ), 'error' );
        }
        $run_id = wp_generate_uuid4();
        $hub_preflight = self::request_hub( 'preflight', $run_id );
        if ( is_wp_error( $hub_preflight ) || 'preflight' !== ( $hub_preflight['status'] ?? '' ) ) {
            self::redirect_notice( self::safe_error_message( $hub_preflight ), 'error' );
        }
        $hub = self::request_hub( 'execute', $run_id );
        if ( is_wp_error( $hub ) || 'success' !== ( $hub['status'] ?? '' ) ) {
            self::lock_with_receipt( $run_id, 'partial', array( 'hub' => is_array( $hub ) ? self::public_counts( $hub['counts'] ) : array() ) );
            self::redirect_notice( 'État partiel : faluss.me est verrouillé. Aucun retry automatique n’est disponible.', 'error' );
        }

        $result = self::perform_identity_reset( $identity );
        if ( is_wp_error( $result ) ) {
            self::lock_with_receipt( $run_id, 'partial', self::public_counts( $identity ) );
            self::redirect_notice( 'État partiel : les deux installations sont verrouillées. Aucun retry automatique n’est disponible.', 'error' );
        }
        self::lock_with_receipt( $run_id, 'success', self::public_counts( $result ) );
        delete_transient( self::preview_key() );
        self::redirect_notice( 'Reset coordonné terminé et verrouillé.', 'success' );
    }

    public static function receive_hub_reset( $request ) {
        $validated = self::validate_hub_request( $request );
        if ( is_wp_error( $validated ) ) {
            return self::signed_response( 'unavailable', '', array(), 403 );
        }
        $phase = $validated['phase'];
        $run_id = $validated['run_id'];
        $preflight = self::preflight_hub();
        if ( is_wp_error( $preflight ) ) {
            return self::signed_response( 'unavailable', $run_id, array(), 409 );
        }
        if ( 'preflight' === $phase ) {
            return self::signed_response( 'preflight', $run_id, self::public_counts( $preflight ), 200 );
        }

        $result = self::perform_hub_reset( $preflight );
        if ( is_wp_error( $result ) ) {
            self::lock_with_receipt( $run_id, 'partial', self::public_counts( $preflight ) );
            return self::signed_response( 'partial', $run_id, self::public_counts( $preflight ), 500 );
        }
        self::lock_with_receipt( $run_id, 'success', self::public_counts( $result ) );
        return self::signed_response( 'success', $run_id, self::public_counts( $result ), 200 );
    }

    private static function site_role() {
        $host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
        if ( 'faluss.me' === $host ) {
            return 'identity';
        }
        if ( 'faluss.com' === $host ) {
            return 'hub';
        }
        return false;
    }

    private static function require_administrator() {
        if ( ! current_user_can( 'manage_options' ) || ! self::site_role() ) {
            wp_die( esc_html__( 'Accès refusé.', 'faluss-production-reset' ), 403 );
        }
    }

    private static function require_identity_operator( $nonce_action ) {
        self::require_administrator();
        check_admin_referer( $nonce_action );
        if ( 'identity' !== self::site_role() || ! self::is_armed() || self::is_locked() ) {
            self::redirect_notice( 'Le contrôle central faluss.me doit être armé et non verrouillé.', 'error' );
        }
    }

    private static function has_confirmation() {
        return isset( $_POST['confirmation'] ) && self::CONFIRMATION === wp_unslash( $_POST['confirmation'] );
    }

    private static function is_armed() {
        return self::VERSION === get_option( self::OPTION_ARMED_VERSION, '' ) && (bool) get_option( self::OPTION_ARMED, false );
    }

    private static function is_locked() {
        return (bool) get_option( self::OPTION_LOCKED, false );
    }

    /**
     * An armament is valid only for the plugin version that explicitly created
     * it. Upgrades always require a new local administrator action; a lock is
     * deliberately never removed or weakened here.
     */
    private static function invalidate_legacy_armament() {
        if ( self::VERSION === get_option( self::OPTION_ARMED_VERSION, '' ) ) {
            return;
        }
        update_option( self::OPTION_ARMED, 0, false );
        update_option( self::OPTION_ARMED_VERSION, self::VERSION, false );
    }

    private static function shared_secret() {
        if ( ! defined( 'FALUSS_PRODUCTION_RESET_SHARED_SECRET' ) ) {
            return false;
        }
        $secret = constant( 'FALUSS_PRODUCTION_RESET_SHARED_SECRET' );
        return is_string( $secret ) && strlen( $secret ) >= 32 ? $secret : false;
    }

    private static function preflight_identity() {
        $tables = self::required_tables( array(
            'faluss_identity_profiles',
            'faluss_identity_public_profiles',
            'faluss_identity_challenges',
            'faluss_identity_rate_limits',
            'faluss_identity_auth_codes',
            'faluss_identity_authorization_requests',
            'faluss_identity_audit',
            'faluss_link_cards',
            'faluss_link_blocks',
            'faluss_link_discoveries',
            'faluss_link_discovery_settings',
        ) );
        if ( is_wp_error( $tables ) ) {
            return $tables;
        }
        $profiles = self::identity_profiles( $tables['faluss_identity_profiles'] );
        if ( is_wp_error( $profiles ) ) {
            return $profiles;
        }
        $candidate_ids = self::non_privileged_candidates( wp_list_pluck( $profiles, 'wp_user_id' ) );
        $attachments = self::author_attachments( $candidate_ids );
        if ( is_wp_error( $attachments ) ) {
            return $attachments;
        }
        return self::plan( $tables, $profiles, $candidate_ids, $attachments );
    }

    private static function preflight_hub() {
        $tables = self::required_tables( array(
            'faluss_identity_links',
            'faluss_identity_client_state',
            'token_engine_pf_ledger',
        ) );
        if ( is_wp_error( $tables ) ) {
            return $tables;
        }
        global $wpdb;
        $linked = $wpdb->get_col( "SELECT DISTINCT wp_user_id FROM {$tables['faluss_identity_links']}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( null === $linked ) {
            return new WP_Error( 'fpr_hub_candidates_unreadable', 'Les candidats Hub ne sont pas vérifiables.' );
        }
        foreach ( $linked as $user_id ) {
            if ( ! get_user_by( 'id', absint( $user_id ) ) ) {
                return new WP_Error( 'fpr_hub_link_owner_missing', 'Une liaison Hub sans compte WordPress bloque le reset.' );
            }
        }
        $candidate_ids = self::non_privileged_candidates( $linked );
        $attachments = self::author_attachments( $candidate_ids );
        if ( is_wp_error( $attachments ) ) {
            return $attachments;
        }
        $pf_count = self::table_count( $tables['token_engine_pf_ledger'] );
        if ( is_wp_error( $pf_count ) || 0 !== $pf_count ) {
            return new WP_Error( 'fpr_pf_ledger_not_empty', 'Le ledger PF doit être présent et strictement vide avant un reset.' );
        }
        return self::plan( $tables, array(), $candidate_ids, $attachments );
    }

    private static function required_tables( $suffixes ) {
        global $wpdb;
        $tables = array();
        foreach ( $suffixes as $suffix ) {
            $table = $wpdb->prefix . $suffix;
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $table !== $found ) {
                return new WP_Error( 'fpr_required_table_missing', 'Une table requise est absente ; le reset est bloqué.' );
            }
            $tables[ $suffix ] = $table;
        }
        return $tables;
    }

    private static function identity_profiles( $table ) {
        global $wpdb;
        $rows = $wpdb->get_results( "SELECT faluss_id, wp_user_id FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( null === $rows ) {
            return new WP_Error( 'fpr_identity_profiles_unreadable', 'Les profils Identity ne sont pas vérifiables.' );
        }
        foreach ( $rows as $row ) {
            if ( empty( $row['faluss_id'] ) || empty( $row['wp_user_id'] ) || ! get_user_by( 'id', absint( $row['wp_user_id'] ) ) ) {
                return new WP_Error( 'fpr_identity_profile_invalid', 'Un profil Identity incomplet bloque le reset.' );
            }
        }
        return $rows;
    }

    private static function non_privileged_candidates( $linked_ids ) {
        $candidate_ids = array();
        foreach ( (array) $linked_ids as $user_id ) {
            $user_id = absint( $user_id );
            if ( $user_id && ! self::is_privileged_user( $user_id ) ) {
                $candidate_ids[] = $user_id;
            }
        }
        $subscribers = get_users( array( 'role' => 'subscriber', 'fields' => 'ids', 'number' => -1 ) );
        foreach ( $subscribers as $user_id ) {
            $user_id = absint( $user_id );
            if ( $user_id && ! self::is_privileged_user( $user_id ) ) {
                $candidate_ids[] = $user_id;
            }
        }
        return array_values( array_unique( $candidate_ids ) );
    }

    private static function is_privileged_user( $user_id ) {
        $user = get_user_by( 'id', $user_id );
        return $user instanceof WP_User && user_can( $user, 'manage_options' );
    }

    private static function author_attachments( $candidate_ids ) {
        if ( empty( $candidate_ids ) ) {
            return array();
        }
        global $wpdb;
        $ids = implode( ',', array_map( 'absint', $candidate_ids ) );
        $attachments = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_author IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( null === $attachments ) {
            return new WP_Error( 'fpr_attachments_unreadable', 'Les médias membres ne sont pas vérifiables.' );
        }
        return self::validate_attachments( $attachments, $candidate_ids );
    }

    private static function validate_attachments( $attachment_ids, $candidate_ids ) {
        $safe = array();
        foreach ( $attachment_ids as $attachment_id ) {
            $attachment_id = absint( $attachment_id );
            $attachment = get_post( $attachment_id );
            if ( ! $attachment || 'attachment' !== $attachment->post_type || ! in_array( absint( $attachment->post_author ), $candidate_ids, true ) ) {
                return new WP_Error( 'fpr_attachment_selection_unreadable', 'La sélection des médias membres n’est pas vérifiable.' );
            }
            $paths = self::attachment_paths( $attachment_id );
            if ( is_wp_error( $paths ) ) {
                return $paths;
            }
            $safe[] = $attachment_id;
        }
        return array_values( array_unique( $safe ) );
    }

    private static function plan( $tables, $profiles, $candidate_ids, $attachments ) {
        $counts = array();
        foreach ( $tables as $suffix => $table ) {
            $count = self::table_count( $table );
            if ( is_wp_error( $count ) ) {
                return $count;
            }
            $counts[ $suffix ] = $count;
        }
        $counts['member_users'] = count( $candidate_ids );
        $counts['member_attachments'] = count( $attachments );
        $counts['identity_profiles'] = count( $profiles );
        return array(
            'tables'      => $tables,
            'profiles'    => $profiles,
            'users'       => $candidate_ids,
            'attachments' => $attachments,
            'counts'      => $counts,
        );
    }

    private static function table_count( $table ) {
        global $wpdb;
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return null === $count ? new WP_Error( 'fpr_count_unreadable', 'Un compteur requis est illisible.' ) : (int) $count;
    }

    private static function perform_identity_reset( $plan ) {
        $deleted = self::delete_member_attachments( $plan['attachments'] );
        if ( is_wp_error( $deleted ) ) {
            return $deleted;
        }
        $users = self::delete_member_users( $plan['users'] );
        if ( is_wp_error( $users ) ) {
            return $users;
        }
        $suffixes = array(
            'faluss_identity_profiles',
            'faluss_identity_public_profiles',
            'faluss_identity_challenges',
            'faluss_identity_rate_limits',
            'faluss_identity_auth_codes',
            'faluss_identity_authorization_requests',
            'faluss_identity_audit',
            'faluss_link_cards',
            'faluss_link_blocks',
            'faluss_link_discoveries',
            'faluss_link_discovery_settings',
        );
        $cleared = self::clear_tables( $plan['tables'], $suffixes );
        if ( is_wp_error( $cleared ) ) {
            return $cleared;
        }
        self::purge_known_local_caches();
        $plan['counts']['deleted_member_users'] = $users;
        $plan['counts']['deleted_member_attachments'] = $deleted;
        return $plan;
    }

    private static function perform_hub_reset( $plan ) {
        $deleted = self::delete_member_attachments( $plan['attachments'] );
        if ( is_wp_error( $deleted ) ) {
            return $deleted;
        }
        $users = self::delete_member_users( $plan['users'] );
        if ( is_wp_error( $users ) ) {
            return $users;
        }
        $cleared = self::clear_tables( $plan['tables'], array( 'faluss_identity_links', 'faluss_identity_client_state' ) );
        if ( is_wp_error( $cleared ) ) {
            return $cleared;
        }
        self::purge_known_local_caches();
        $plan['counts']['deleted_member_users'] = $users;
        $plan['counts']['deleted_member_attachments'] = $deleted;
        return $plan;
    }

    private static function delete_member_attachments( $attachment_ids ) {
        $deleted = 0;
        foreach ( $attachment_ids as $attachment_id ) {
            $paths = self::attachment_paths( $attachment_id );
            if ( is_wp_error( $paths ) ) {
                return $paths;
            }
            if ( ! wp_delete_attachment( $attachment_id, true ) ) {
                return new WP_Error( 'fpr_attachment_delete_failed', 'La suppression WordPress d’un média membre a échoué.' );
            }
            if ( get_post( $attachment_id ) ) {
                return new WP_Error( 'fpr_attachment_remains', 'Un média supprimé reste présent dans WordPress.' );
            }
            foreach ( $paths as $path ) {
                if ( file_exists( $path ) ) {
                    return new WP_Error( 'fpr_attachment_file_remains', 'Un fichier média WordPress reste présent après suppression.' );
                }
            }
            $deleted++;
        }
        return $deleted;
    }

    private static function attachment_paths( $attachment_id ) {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
            return new WP_Error( 'fpr_uploads_unreadable', 'Le stockage WordPress des médias est illisible.' );
        }
        $base = wp_normalize_path( $uploads['basedir'] );
        $original = get_attached_file( $attachment_id );
        if ( ! is_string( $original ) || '' === $original ) {
            return new WP_Error( 'fpr_attachment_path_ambiguous', 'Le fichier original d’un média membre est inconnu.' );
        }
        $paths = array( $original );
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( is_array( $metadata ) && ! empty( $metadata['file'] ) && ! empty( $metadata['sizes'] ) ) {
            $directory = dirname( wp_normalize_path( path_join( $base, $metadata['file'] ) ) );
            foreach ( $metadata['sizes'] as $size ) {
                if ( empty( $size['file'] ) || ! is_string( $size['file'] ) ) {
                    return new WP_Error( 'fpr_attachment_metadata_ambiguous', 'Les métadonnées média sont ambiguës.' );
                }
                $paths[] = path_join( $directory, $size['file'] );
            }
        }
        $safe = array();
        foreach ( array_filter( $paths ) as $path ) {
            $normalized = wp_normalize_path( $path );
            if ( 0 !== strpos( $normalized, trailingslashit( $base ) ) && $normalized !== $base ) {
                return new WP_Error( 'fpr_attachment_path_ambiguous', 'Un chemin média hors stockage WordPress bloque le reset.' );
            }
            $safe[] = $normalized;
        }
        return array_values( array_unique( $safe ) );
    }

    private static function delete_member_users( $user_ids ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted = 0;
        foreach ( $user_ids as $user_id ) {
            if ( self::is_privileged_user( $user_id ) ) {
                continue;
            }
            if ( ! wp_delete_user( $user_id, null ) ) {
                return new WP_Error( 'fpr_user_delete_failed', 'La suppression WordPress d’un compte membre a échoué.' );
            }
            $deleted++;
        }
        return $deleted;
    }

    private static function clear_tables( $tables, $suffixes ) {
        global $wpdb;
        foreach ( $suffixes as $suffix ) {
            if ( empty( $tables[ $suffix ] ) || false === $wpdb->query( "DELETE FROM {$tables[$suffix]}" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                return new WP_Error( 'fpr_member_data_delete_failed', 'Une donnée membre requise n’a pas pu être supprimée.' );
            }
        }
        return true;
    }

    private static function purge_known_local_caches() {
        wp_cache_flush();
        do_action( 'litespeed_purge_all' );
    }

    private static function request_hub( $phase, $run_id ) {
        $secret = self::shared_secret();
        $nonce = self::random_nonce();
        if ( ! $secret || ! $nonce ) {
            return new WP_Error( 'fpr_shared_secret_missing', 'La constante secrète obligatoire est absente ou invalide.' );
        }
        $body = self::canonical_body( array(
            'protocol'  => self::PROTOCOL,
            'operation' => self::OPERATION,
            'phase'     => $phase,
            'run_id'    => $run_id,
            'issued_at' => time(),
            'nonce'     => $nonce,
        ) );
        $response = wp_safe_remote_post( self::HUB_RECEIVER_URL, array(
            'timeout'     => 20,
            'redirection' => 0,
            'headers'     => array(
                'Content-Type'                 => 'application/json',
                'X-Faluss-Production-Reset-Signature' => hash_hmac( 'sha256', $body, $secret ),
            ),
            'body'        => $body,
        ) );
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'fpr_hub_unavailable', 'Le Hub ne répond pas au contrôle signé.' );
        }
        $raw = wp_remote_retrieve_body( $response );
        $signature = wp_remote_retrieve_header( $response, 'x-fpr-response-signature' );
        if ( ! is_string( $signature ) || ! hash_equals( hash_hmac( 'sha256', $raw, $secret ), $signature ) ) {
            return new WP_Error( 'fpr_hub_signature_invalid', 'La réponse Hub n’est pas authentifiée.' );
        }
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) || self::PROTOCOL !== ( $decoded['protocol'] ?? '' ) || $run_id !== ( $decoded['run_id'] ?? '' ) || ! isset( $decoded['counts'] ) || ! is_array( $decoded['counts'] ) ) {
            return new WP_Error( 'fpr_hub_response_invalid', 'La réponse Hub est invalide.' );
        }
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return new WP_Error( 'fpr_hub_refused', 'Le Hub a refusé le reset sans exposer de détail.' );
        }
        return $decoded;
    }

    private static function validate_hub_request( $request ) {
        $secret = self::shared_secret();
        $raw = $request->get_body();
        $signature = $request->get_header( 'x-faluss-production-reset-signature' );
        if ( ! $secret || ! is_string( $signature ) || ! hash_equals( hash_hmac( 'sha256', $raw, $secret ), $signature ) ) {
            return new WP_Error( 'fpr_request_signature_invalid' );
        }
        $payload = json_decode( $raw, true );
        $keys = array( 'protocol', 'operation', 'phase', 'run_id', 'issued_at', 'nonce' );
        if ( ! is_array( $payload ) || $keys !== array_keys( $payload ) || self::canonical_body( $payload ) !== $raw || self::PROTOCOL !== $payload['protocol'] || self::OPERATION !== $payload['operation'] || ! in_array( $payload['phase'], array( 'preflight', 'execute' ), true ) || ! is_string( $payload['run_id'] ) || ! wp_is_uuid( $payload['run_id'] ) || ! is_numeric( $payload['issued_at'] ) || ! is_string( $payload['nonce'] ) || strlen( $payload['nonce'] ) < 32 ) {
            return new WP_Error( 'fpr_request_invalid' );
        }
        if ( abs( time() - (int) $payload['issued_at'] ) > self::MAX_CLOCK_SKEW || ! self::consume_nonce( $payload['nonce'] ) ) {
            return new WP_Error( 'fpr_request_expired_or_replayed' );
        }
        return $payload;
    }

    private static function signed_response( $status, $run_id, $counts, $http_status ) {
        $secret = self::shared_secret();
        $body = self::canonical_body( array(
            'protocol'  => self::PROTOCOL,
            'run_id'    => (string) $run_id,
            'status'    => $status,
            'issued_at' => time(),
            'counts'    => self::public_counts( $counts ),
        ) );
        $response = new WP_REST_Response( json_decode( $body, true ), $http_status );
        if ( $secret ) {
            $response->header( 'X-FPR-Response-Signature', hash_hmac( 'sha256', $body, $secret ) );
        }
        return $response;
    }

    private static function canonical_body( $payload ) {
        return wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
    }

    private static function random_nonce() {
        try {
            return bin2hex( random_bytes( 32 ) );
        } catch ( Exception $exception ) {
            return false;
        }
    }

    private static function consume_nonce( $nonce ) {
        $option = self::NONCE_OPTION_PREFIX . hash( 'sha256', $nonce );
        return add_option( $option, time(), '', 'no' );
    }

    private static function lock_with_receipt( $run_id, $status, $counts ) {
        update_option( self::OPTION_ARMED, 0, false );
        update_option( self::OPTION_LOCKED, 1, false );
        update_option( self::OPTION_RECEIPT, array(
            'run_id'      => (string) $run_id,
            'status'      => $status,
            'started_at'  => gmdate( 'c' ),
            'finished_at' => gmdate( 'c' ),
            'counts'      => self::public_counts( $counts ),
        ), false );
    }

    private static function public_counts( $value ) {
        $counts = isset( $value['counts'] ) && is_array( $value['counts'] ) ? $value['counts'] : (array) $value;
        $safe = array();
        foreach ( $counts as $key => $count ) {
            if ( in_array( $key, self::allowed_count_keys(), true ) && ( is_int( $count ) || ctype_digit( (string) $count ) ) ) {
                $safe[ $key ] = (int) $count;
            }
        }
        return $safe;
    }

    private static function allowed_count_keys() {
        return array(
            'faluss_identity_profiles',
            'faluss_identity_public_profiles',
            'faluss_identity_challenges',
            'faluss_identity_rate_limits',
            'faluss_identity_auth_codes',
            'faluss_identity_authorization_requests',
            'faluss_identity_audit',
            'faluss_link_cards',
            'faluss_link_blocks',
            'faluss_link_discoveries',
            'faluss_link_discovery_settings',
            'faluss_identity_links',
            'faluss_identity_client_state',
            'token_engine_pf_ledger',
            'member_users',
            'member_attachments',
            'identity_profiles',
            'deleted_member_users',
            'deleted_member_attachments',
        );
    }

    private static function preview_key() {
        return 'faluss_production_reset_preview_' . get_current_user_id();
    }

    private static function render_counts( $counts, $label ) {
        $counts = self::public_counts( $counts );
        echo '<h4>' . esc_html( $label ) . '</h4><ul>';
        foreach ( $counts as $key => $count ) {
            echo '<li>' . esc_html( $key ) . ' : ' . esc_html( (string) $count ) . '</li>';
        }
        echo '</ul>';
    }

    private static function render_notice() {
        if ( empty( $_GET['fpr_notice'] ) ) {
            return;
        }
        $type = isset( $_GET['fpr_type'] ) && 'success' === $_GET['fpr_type'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr( $type ) . '"><p>' . esc_html( wp_unslash( $_GET['fpr_notice'] ) ) . '</p></div>';
    }

    private static function redirect_notice( $message, $type ) {
        wp_safe_redirect( add_query_arg( array(
            'page'       => 'faluss-production-reset',
            'fpr_notice' => rawurlencode( $message ),
            'fpr_type'   => $type,
        ), admin_url( 'tools.php' ) ) );
        exit;
    }

    private static function safe_error_message( $error ) {
        return $error instanceof WP_Error ? $error->get_error_message() : 'Précondition non satisfaite.';
    }
}
