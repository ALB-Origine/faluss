<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Faluss_Link {
    const STYLE = 'faluss-link-card';
    const IMMERSIVE_STYLE = 'faluss-link-immersive';
    const STUDIO_STYLE = 'faluss-link-studio';
    const SCRIPT = 'faluss-link-editor';
    const CARD_SCRIPT = 'faluss-link-card';
    const IMMERSIVE_SCRIPT = 'faluss-link-immersive';
    const NETWORKS = array( 'instagram', 'tiktok', 'youtube', 'x', 'linkedin', 'github' );
    const ANNOUNCEMENTS = array( 'accent' => 'Accent / ink', 'ink' => 'Ink / white' );
    const NAME_TREATMENTS = array( 'editorial' => 'Éditorial', 'strong' => 'Fort' );
    const LAYOUTS = array( 'bubbles' => 'Bulles', 'inline' => 'Ligne' );
    const LINK_STYLES = array( 'solid' => 'Plein', 'outline' => 'Contour' );
    const NAME_COLORS = array( '#BE79FF' => 'Rose', '#FFFFFF' => 'Blanc', '#000000' => 'Noir', '#82206B' => 'Prune' );
    const SOCIAL_VARIANTS = array( 'outline' => 'Icônes contour', 'full' => 'Logos pleins' );
    const BLOCK_TYPES = array( 'section_title' => 'Titre de section', 'text' => 'Texte', 'link' => 'Lien', 'media_teaser' => 'Teaser média' );
    const TEASER_FORMATS = array( 'landscape' => 'Paysage', 'portrait' => 'Portrait', 'square' => 'Carré' );

    public static function boot() {
        add_action( 'plugins_loaded', array( 'Faluss_Link_Schema', 'maybe_install' ), 1 );
        add_shortcode( 'faluss_link_card', array( __CLASS__, 'card_shortcode' ) );
        add_shortcode( 'faluss_link_appearance', array( __CLASS__, 'appearance_shortcode' ) );
        add_shortcode( 'faluss_link_studio', array( __CLASS__, 'studio_shortcode' ) );
        add_action( 'admin_post_faluss_link_save', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_faluss_link_save_studio', array( __CLASS__, 'save_studio' ) );
        add_action( 'wp_ajax_faluss_link_upload_cover', array( __CLASS__, 'upload_cover' ) );
        add_action( 'wp_ajax_faluss_link_upload_teaser', array( __CLASS__, 'upload_teaser' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 5 );
        add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'assets' ), 5 );
        add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'assets' ), 5 );
        add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ) );
    }

    public static function activate() { return Faluss_Link_Schema::install(); }
    public static function card_shortcode( $attributes = array() ) { return self::render_card( (array) $attributes ); }
    public static function appearance_shortcode() { return self::render_editor(); }
    public static function studio_shortcode() { return self::render_studio(); }

    public static function assets() {
        wp_register_style( self::STYLE, plugins_url( 'assets/css/faluss-link.css', FALUSS_LINK_FILE ), array(), FALUSS_LINK_VERSION );
        wp_register_style( self::IMMERSIVE_STYLE, plugins_url( 'assets/css/faluss-link-immersive.css', FALUSS_LINK_FILE ), array( self::STYLE ), FALUSS_LINK_VERSION );
        wp_register_style( self::STUDIO_STYLE, plugins_url( 'assets/css/faluss-link-studio.css', FALUSS_LINK_FILE ), array( self::STYLE, self::IMMERSIVE_STYLE ), FALUSS_LINK_VERSION );
        wp_register_script( self::CARD_SCRIPT, plugins_url( 'assets/js/faluss-link-card.js', FALUSS_LINK_FILE ), array(), FALUSS_LINK_VERSION, true );
        wp_register_script( self::SCRIPT, plugins_url( 'assets/js/faluss-link-editor.js', FALUSS_LINK_FILE ), array( 'jquery', self::CARD_SCRIPT ), FALUSS_LINK_VERSION, true );
        wp_register_script( self::IMMERSIVE_SCRIPT, plugins_url( 'assets/js/faluss-link-immersive.js', FALUSS_LINK_FILE ), array(), FALUSS_LINK_VERSION, true );
        wp_localize_script( self::SCRIPT, 'falussLinkCover', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'faluss_link_upload_cover' ), 'teaserNonce' => wp_create_nonce( 'faluss_link_upload_teaser' ), 'networks' => self::network_catalog_for_client() ) );
    }

    public static function render_card( $attributes = array() ) {
        if ( ! self::identity_ready() ) { return self::empty_card( __( 'Carte Faluss indisponible.', 'faluss-link' ) ); }
        $attributes = wp_parse_args( $attributes, array( 'identifier' => '', 'align' => '', 'presentation' => 'compact' ) );
        $slug = '' !== $attributes['identifier'] ? sanitize_title( $attributes['identifier'] ) : (string) get_query_var( 'faluss_public_profile' );
        $profile = self::published_profile( $slug );
        if ( ! $profile ) { return self::empty_card( __( 'Cette carte Faluss n’est pas disponible.', 'faluss-link' ) ); }
        $preferences = self::prefs( $profile['faluss_id'] );
        $alignment = '' === (string) $attributes['align'] ? $preferences['alignment'] : self::align( $attributes['align'] );
        $blocks = self::content_blocks( $profile['faluss_id'], $profile['links'] );
        self::enqueue_assets();
        $markup = self::card_markup( $profile, $preferences, $alignment, false, $blocks );
        if ( 'immersive' === $attributes['presentation'] ) {
            wp_enqueue_script( self::IMMERSIVE_SCRIPT );
            return str_replace( 'faluss-link-card ', 'faluss-link-card faluss-link-card--presentation-immersive ', $markup );
        }
        return $markup;
    }

    public static function render_editor() {
        if ( ! is_user_logged_in() || ! self::identity_ready() ) { return self::empty_card( __( 'Connectez-vous pour personnaliser votre carte.', 'faluss-link' ) ); }
        $faluss_id = Faluss_Identity_Registry::get_active_for_wp_user( get_current_user_id() );
        if ( ! $faluss_id ) { return self::empty_card( __( 'Votre identité Faluss est indisponible.', 'faluss-link' ) ); }
        self::editor_assets(); $preferences = self::valid_prefs( $faluss_id ); ob_start();
        ?><section class="faluss-link-editor"><h2><?php esc_html_e( 'Apparence de ma carte Faluss', 'faluss-link' ); ?></h2><?php echo self::notice( 'faluss_link_notice' ); ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="faluss_link_save"><?php wp_nonce_field( 'faluss_link_save', 'faluss_link_nonce' ); self::page_background_field( $preferences, false ); self::preference_fields( $preferences, false ); self::social_editor( $preferences ); ?><button class="faluss-link-action" type="submit"><?php esc_html_e( 'Enregistrer', 'faluss-link' ); ?></button></form></section><?php
        return (string) ob_get_clean();
    }

    public static function render_studio() {
        if ( ! is_user_logged_in() || ! self::identity_ready() || ! class_exists( 'Faluss_Identity_Public_Profile' ) ) { return self::empty_card( __( 'Connectez-vous pour ouvrir Studio Faluss.', 'faluss-link' ) ); }
        $faluss_id = Faluss_Identity_Registry::get_active_for_wp_user( get_current_user_id() );
        if ( ! $faluss_id ) { return self::empty_card( __( 'Votre identité Faluss est indisponible.', 'faluss-link' ) ); }
        self::editor_assets(); $profile = Faluss_Identity_Public_Profile::studio_profile( $faluss_id ); $preferences = self::valid_prefs( $faluss_id ); $blocks = self::content_blocks( $faluss_id, $profile['links'], true ); $active_tab = self::studio_tab( $_GET['faluss_studio_tab'] ?? 'profile' ); ob_start();
        ?>
        <section class="faluss-link-studio" data-faluss-studio-tab="<?php echo esc_attr( $active_tab ); ?>">
            <header class="faluss-link-studio__header"><div class="faluss-link-studio__member"><?php if ( (int) $profile['avatar_attachment_id'] ) { echo wp_get_attachment_image( (int) $profile['avatar_attachment_id'], 'thumbnail', false, array( 'alt' => '' ) ); } ?><strong data-studio-member-name><?php echo esc_html( $profile['display_name'] ?: __( 'Mon Faluss', 'faluss-link' ) ); ?></strong><span data-studio-member-handle><?php echo '' === $profile['public_slug'] ? '@—' : '@' . esc_html( $profile['public_slug'] ); ?></span></div><?php if ( '' !== $profile['public_slug'] && 'published' === $profile['publication_status'] ) : ?><a href="<?php echo esc_url( home_url( '/' . $profile['public_slug'] ) ); ?>"><?php esc_html_e( 'Voir mon Faluss', 'faluss-link' ); ?></a><?php endif; ?></header>
            <form class="faluss-link-studio__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="faluss_link_save_studio"><input type="hidden" name="faluss_studio_tab" data-fl-active-tab value="<?php echo esc_attr( $active_tab ); ?>"><?php wp_nonce_field( 'faluss_link_save_studio', 'faluss_link_studio_nonce' ); ?><div class="faluss-link-studio__notice" role="status" aria-live="polite"><?php echo self::notice( 'faluss_studio_notice' ); ?></div>
                <nav class="faluss-link-studio__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Sections du Studio Faluss', 'faluss-link' ); ?>"><button id="faluss-studio-tab-profile" type="button" role="tab" aria-selected="<?php echo 'profile' === $active_tab ? 'true' : 'false'; ?>" aria-controls="faluss-studio-panel-profile" tabindex="<?php echo 'profile' === $active_tab ? '0' : '-1'; ?>" data-fl-tab="profile">Profil</button><button id="faluss-studio-tab-links" type="button" role="tab" aria-selected="<?php echo 'links' === $active_tab ? 'true' : 'false'; ?>" aria-controls="faluss-studio-panel-links" tabindex="<?php echo 'links' === $active_tab ? '0' : '-1'; ?>" data-fl-tab="links">Liens</button><button id="faluss-studio-tab-style" type="button" role="tab" aria-selected="<?php echo 'style' === $active_tab ? 'true' : 'false'; ?>" aria-controls="faluss-studio-panel-style" tabindex="<?php echo 'style' === $active_tab ? '0' : '-1'; ?>" data-fl-tab="style">Style</button></nav>
                <div class="faluss-link-studio__workspace">
                    <section id="faluss-studio-panel-profile" role="tabpanel" aria-labelledby="faluss-studio-tab-profile" data-fl-panel="profile"<?php echo 'profile' === $active_tab ? '' : ' hidden'; ?>><?php self::identity_fields( $profile ); ?><label class="faluss-link-studio__check"><input name="available" type="checkbox" value="1" <?php checked( $preferences['available'] ); ?>> <?php esc_html_e( 'Afficher Disponible', 'faluss-link' ); ?></label></section>
                    <section id="faluss-studio-panel-links" role="tabpanel" aria-labelledby="faluss-studio-tab-links" data-fl-panel="links"<?php echo 'links' === $active_tab ? '' : ' hidden'; ?>><?php self::content_composer( $blocks ); self::social_editor( $preferences ); ?><label for="faluss-studio-social-layout"><?php esc_html_e( 'Affichage des réseaux', 'faluss-link' ); ?></label><select id="faluss-studio-social-layout" name="social_layout"><?php self::options( self::LAYOUTS, $preferences['social_layout'] ); ?></select></section>
                    <section id="faluss-studio-panel-style" role="tabpanel" aria-labelledby="faluss-studio-tab-style" data-fl-panel="style"<?php echo 'style' === $active_tab ? '' : ' hidden'; ?>><?php self::page_background_field( $preferences, true ); self::name_color_field( $preferences ); self::preference_fields( $preferences, true ); ?></section>
                    <aside class="faluss-link-studio__preview" aria-label="<?php esc_attr_e( 'Aperçu vivant de ma carte Faluss', 'faluss-link' ); ?>"><h2><?php esc_html_e( 'Aperçu', 'faluss-link' ); ?></h2><?php echo self::card_markup( $profile, $preferences, $preferences['alignment'], true, $blocks ); ?></aside>
                </div><button class="faluss-link-action faluss-link-studio__submit" type="submit"><?php esc_html_e( 'Enregistrer le Studio', 'faluss-link' ); ?></button>
            </form>
        </section>
        <?php return (string) ob_get_clean();
    }

    public static function save() {
        if ( ! self::verify( 'faluss_link_nonce', 'faluss_link_save' ) ) { wp_die( 'Accès refusé.' ); }
        $faluss_id = Faluss_Identity_Registry::get_active_for_wp_user( get_current_user_id() );
        self::redirect( 'faluss_link_notice', $faluss_id && self::save_preferences( $faluss_id, $_POST ) ? 'saved' : 'invalid' );
    }

    public static function save_studio() {
        if ( ! self::verify( 'faluss_link_studio_nonce', 'faluss_link_save_studio' ) ) { wp_die( 'Accès refusé.' ); }
        $faluss_id = Faluss_Identity_Registry::get_active_for_wp_user( get_current_user_id() );
        $blocks = self::normalise_blocks( $_POST['content_blocks'] ?? array() ); $identity_post = $_POST; $identity_post['links'] = self::identity_links( $blocks );
        $identity = $faluss_id && class_exists( 'Faluss_Identity_Public_Profile' ) ? Faluss_Identity_Public_Profile::save_studio_profile( $faluss_id, $identity_post, $_FILES ) : 'invalid';
        self::redirect( 'faluss_studio_notice', 'saved' === $identity && self::save_preferences( $faluss_id, $_POST ) && self::save_blocks( $faluss_id, $blocks ) ? 'saved' : ( 'taken' === $identity ? 'taken' : 'invalid' ), self::studio_tab( $_POST['faluss_studio_tab'] ?? 'profile' ) );
    }

    public static function upload_cover() {
        self::upload_member_image( 'cover', 'faluss_link_upload_cover' );
    }

    public static function upload_teaser() {
        self::upload_member_image( 'teaser', 'faluss_link_upload_teaser' );
    }

    private static function upload_member_image( $field, $nonce_action ) {
        if ( ! is_user_logged_in() || ! check_ajax_referer( $nonce_action, 'nonce', false ) || ! self::identity_ready() || ! Faluss_Identity_Registry::get_active_for_wp_user( get_current_user_id() ) ) { wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 ); }
        if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) { wp_send_json_error( array( 'message' => 'Image requise.' ), 400 ); }
        require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
        $file = $_FILES[ $field ]; $type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        if ( empty( $type['type'] ) || 0 !== strpos( $type['type'], 'image/' ) ) { wp_send_json_error( array( 'message' => 'Image invalide.' ), 400 ); }
        $upload = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp' ) ) );
        if ( ! empty( $upload['error'] ) ) { wp_send_json_error( array( 'message' => 'Envoi impossible.' ), 400 ); }
        $attachment_id = wp_insert_attachment( array( 'post_mime_type' => $upload['type'], 'post_title' => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ), 'post_status' => 'inherit', 'post_author' => get_current_user_id() ), $upload['file'] );
        if ( is_wp_error( $attachment_id ) || ! self::owned_image( (int) $attachment_id, get_current_user_id() ) ) { wp_send_json_error( array( 'message' => 'Image invalide.' ), 400 ); }
        wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
        wp_send_json_success( array( 'id' => (int) $attachment_id, 'url' => wp_get_attachment_image_url( $attachment_id, 'medium' ) ) );
    }

    public static function widgets( $manager ) {
        if ( ! class_exists( 'Elementor\\Widget_Base' ) || ! is_object( $manager ) || ! method_exists( $manager, 'register' ) ) { return; }
        require_once FALUSS_LINK_DIR . 'includes/class-faluss-link-widgets.php';
        $manager->register( new Faluss_Link_Card_Widget() ); $manager->register( new Faluss_Link_Appearance_Widget() ); $manager->register( new Faluss_Link_Studio_Widget() );
    }

    public static function socials( $value ) {
        $decoded = is_string( $value ) ? json_decode( $value, true ) : null;
        $items = is_array( $decoded ) ? ( isset( $decoded['networks'] ) ? $decoded['networks'] : $decoded ) : $value;
        if ( is_string( $items ) ) { $items = preg_split( '/\r\n|\r|\n/', $items ); }
        $allowed = array_keys( self::active_network_catalog() ); $out = array(); $seen = array();
        foreach ( (array) $items as $item ) {
            if ( is_array( $item ) ) { $network = $item['network'] ?? ''; $url = $item['url'] ?? ''; } else { list( $network, $url ) = array_pad( explode( '|', (string) $item, 2 ), 2, '' ); }
            $network = strtolower( trim( (string) $network ) ); $url = trim( (string) $url ); $parts = wp_parse_url( $url );
            if ( ! in_array( $network, $allowed, true ) || ! filter_var( $url, FILTER_VALIDATE_URL ) || ! is_array( $parts ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) || isset( $seen[ $network . '|' . $url ] ) ) { continue; }
            $seen[ $network . '|' . $url ] = true; $out[] = array( 'network' => $network, 'url' => $url );
        }
        return $out;
    }

    private static function save_preferences( $faluss_id, $post ) {
        global $wpdb; $table = Faluss_Link_Schema::table(); if ( '' === $table ) { return false; }
        $pick = static function( $value, $allowed, $fallback ) { return in_array( $value, $allowed, true ) ? $value : $fallback; };
        $cover = max( 0, (int) ( $post['cover_attachment_id'] ?? 0 ) ); if ( $cover && ! self::owned_image( $cover, get_current_user_id() ) ) { $cover = 0; }
        $color = sanitize_hex_color( wp_unslash( $post['hero_transition_color'] ?? '' ) );
        $page_background = sanitize_hex_color( wp_unslash( $post['page_background'] ?? '' ) );
        $old = self::prefs( $faluss_id ); $posted_name_color = $post['name_color'] ?? null;
        $name_color = is_string( $posted_name_color ) ? self::name_color( wp_unslash( $posted_name_color ) ) : $old['name_color'];
        $social_variant = self::social_variant( wp_unslash( $post['social_variant'] ?? $old['social_variant'] ) );
        $payload = array( 'networks' => self::socials( wp_unslash( $post['social_networks'] ?? $post['social_links'] ?? array() ) ), 'alignment' => $pick( wp_unslash( $post['alignment'] ?? '' ), array( 'left', 'center' ), 'left' ), 'page_background' => $page_background ? $page_background : '#FFFDF5', 'hero_transition_color' => $color ? $color : '#FFFDF5', 'hero_transition_intensity' => min( 100, max( 0, (int) ( $post['hero_transition_intensity'] ?? 82 ) ) ), 'hero_transition_position' => min( 100, max( 35, (int) ( $post['hero_transition_position'] ?? 72 ) ) ), 'name_color' => $name_color, 'social_variant' => $social_variant );
        $values = array( 'cover_attachment_id' => $cover, 'avatar_visible' => ! empty( $post['avatar_visible'] ) ? 1 : 0, 'name_weight' => 'bold', 'name_treatment' => $pick( wp_unslash( $post['name_treatment'] ?? '' ), array_keys( self::NAME_TREATMENTS ), 'strong' ), 'available' => ! empty( $post['available'] ) ? 1 : 0, 'bio_mode' => $pick( wp_unslash( $post['bio_mode'] ?? '' ), array( 'editorial', 'announcement' ), 'editorial' ), 'announcement' => sanitize_text_field( wp_unslash( $post['announcement'] ?? '' ) ), 'announcement_variant' => $pick( wp_unslash( $post['announcement_variant'] ?? '' ), array_keys( self::ANNOUNCEMENTS ), 'accent' ), 'social_links' => wp_json_encode( $payload ), 'social_layout' => $pick( wp_unslash( $post['social_layout'] ?? '' ), array_keys( self::LAYOUTS ), 'bubbles' ), 'link_style' => $pick( wp_unslash( $post['link_style'] ?? '' ), array_keys( self::LINK_STYLES ), 'solid' ) );
        $now = gmdate( 'Y-m-d H:i:s' );
        if ( $old['faluss_id'] !== $faluss_id ) { return false !== $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . $table . ' (faluss_id,cover_attachment_id,avatar_visible,name_weight,name_treatment,available,bio_mode,announcement,announcement_variant,social_links,social_layout,link_style,created_at,updated_at) VALUES (%s,%d,%d,%s,%s,%d,%s,%s,%s,%s,%s,%s,%s,%s)', ...array_merge( array( $faluss_id ), array_values( $values ), array( $now, $now ) ) ) ); }
        return false !== $wpdb->update( $table, $values + array( 'updated_at' => $now ), array( 'faluss_id' => $faluss_id ) );
    }

    private static function identity_fields( $profile ) {
        ?><label for="faluss-studio-slug"><?php esc_html_e( 'Identifiant public', 'faluss-link' ); ?></label><input id="faluss-studio-slug" name="public_slug" type="text" value="<?php echo esc_attr( $profile['public_slug'] ); ?>" pattern="[a-z0-9][a-z0-9-]{1,39}" maxlength="40" <?php echo '' !== $profile['public_slug'] ? 'readonly' : ''; ?> required><p class="faluss-link-studio__hint"><?php esc_html_e( 'Il reste stable après sa création.', 'faluss-link' ); ?></p><label for="faluss-studio-name"><?php esc_html_e( 'Nom affiché', 'faluss-link' ); ?></label><input id="faluss-studio-name" name="display_name" type="text" maxlength="80" value="<?php echo esc_attr( $profile['display_name'] ); ?>" required><label for="faluss-studio-bio"><?php esc_html_e( 'Bio courte', 'faluss-link' ); ?></label><textarea id="faluss-studio-bio" name="bio" maxlength="280" rows="4"><?php echo esc_textarea( $profile['bio'] ); ?></textarea><label for="faluss-studio-avatar"><?php esc_html_e( 'Avatar', 'faluss-link' ); ?></label><input id="faluss-studio-avatar" name="faluss_identity_avatar" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><label class="faluss-link-studio__check"><input name="publication_status" type="checkbox" value="published" <?php checked( 'published' === $profile['publication_status'] ); ?>> <?php esc_html_e( 'Publier mon profil', 'faluss-link' ); ?></label><?php
    }

    private static function preference_fields( $preferences, $studio = false ) {
        $prefix = $studio ? 'faluss-studio-' : 'faluss-link-editor-';
        ?>
        <div class="faluss-link-preferences">
            <div class="faluss-link-editor__media">
                <label><?php esc_html_e( 'Photo de couverture', 'faluss-link' ); ?></label>
                <input class="faluss-link-editor__cover-id" name="cover_attachment_id" type="hidden" value="<?php echo (int) $preferences['cover_attachment_id']; ?>">
                <button class="faluss-link-editor__select-cover" type="button"><?php esc_html_e( 'Choisir une image', 'faluss-link' ); ?></button>
                <button class="faluss-link-editor__remove-cover" type="button"><?php esc_html_e( 'Retirer', 'faluss-link' ); ?></button>
                <div class="faluss-link-editor__cover-preview"><?php if ( (int) $preferences['cover_attachment_id'] ) { echo wp_get_attachment_image( (int) $preferences['cover_attachment_id'], 'medium', false, array( 'alt' => '' ) ); } ?></div>
            </div>
            <label class="faluss-link-studio__check"><input name="avatar_visible" type="checkbox" value="1" <?php checked( $preferences['avatar_visible'] ); ?>> <?php esc_html_e( 'Afficher l’avatar', 'faluss-link' ); ?></label>
            <?php if ( ! $studio ) : ?><label class="faluss-link-studio__check"><input name="available" type="checkbox" value="1" <?php checked( $preferences['available'] ); ?>> <?php esc_html_e( 'Afficher Disponible', 'faluss-link' ); ?></label><?php endif; ?>
            <label for="<?php echo esc_attr( $prefix ); ?>name"><?php esc_html_e( 'Traitement du nom', 'faluss-link' ); ?></label>
            <select id="<?php echo esc_attr( $prefix ); ?>name" name="name_treatment"><?php self::options( self::NAME_TREATMENTS, $preferences['name_treatment'] ); ?></select>
            <label for="<?php echo esc_attr( $prefix ); ?>bio"><?php esc_html_e( 'Bio', 'faluss-link' ); ?></label>
            <select id="<?php echo esc_attr( $prefix ); ?>bio" name="bio_mode"><option value="editorial" <?php selected( $preferences['bio_mode'], 'editorial' ); ?>><?php esc_html_e( 'Texte éditorial', 'faluss-link' ); ?></option><option value="announcement" <?php selected( $preferences['bio_mode'], 'announcement' ); ?>><?php esc_html_e( 'Annonce', 'faluss-link' ); ?></option></select>
            <label for="<?php echo esc_attr( $prefix ); ?>announcement"><?php esc_html_e( 'Texte de l’annonce', 'faluss-link' ); ?></label>
            <input id="<?php echo esc_attr( $prefix ); ?>announcement" name="announcement" maxlength="120" value="<?php echo esc_attr( $preferences['announcement'] ); ?>">
            <label for="<?php echo esc_attr( $prefix ); ?>announcement-variant"><?php esc_html_e( 'Couleur de l’annonce', 'faluss-link' ); ?></label>
            <select id="<?php echo esc_attr( $prefix ); ?>announcement-variant" name="announcement_variant"><?php self::options( self::ANNOUNCEMENTS, $preferences['announcement_variant'] ); ?></select>
            <label for="<?php echo esc_attr( $prefix ); ?>alignment"><?php esc_html_e( 'Alignement du profil', 'faluss-link' ); ?></label>
            <select id="<?php echo esc_attr( $prefix ); ?>alignment" name="alignment"><option value="left" <?php selected( $preferences['alignment'], 'left' ); ?>><?php esc_html_e( 'Gauche', 'faluss-link' ); ?></option><option value="center" <?php selected( $preferences['alignment'], 'center' ); ?>><?php esc_html_e( 'Centre', 'faluss-link' ); ?></option></select>
            <label for="<?php echo esc_attr( $prefix ); ?>transition-color"><?php esc_html_e( 'Couleur de transition de la couverture', 'faluss-link' ); ?></label>
            <input id="<?php echo esc_attr( $prefix ); ?>transition-color" name="hero_transition_color" type="color" value="<?php echo esc_attr( $preferences['hero_transition_color'] ); ?>">
            <label for="<?php echo esc_attr( $prefix ); ?>transition-intensity"><?php esc_html_e( 'Intensité de transition', 'faluss-link' ); ?></label>
            <input id="<?php echo esc_attr( $prefix ); ?>transition-intensity" name="hero_transition_intensity" type="range" min="0" max="100" value="<?php echo (int) $preferences['hero_transition_intensity']; ?>">
            <label for="<?php echo esc_attr( $prefix ); ?>transition-position"><?php esc_html_e( 'Position de transition', 'faluss-link' ); ?></label>
            <input id="<?php echo esc_attr( $prefix ); ?>transition-position" name="hero_transition_position" type="range" min="35" max="100" value="<?php echo (int) $preferences['hero_transition_position']; ?>">
            <label for="<?php echo esc_attr( $prefix ); ?>links"><?php esc_html_e( 'Boutons de liens', 'faluss-link' ); ?></label>
            <select id="<?php echo esc_attr( $prefix ); ?>links" name="link_style"><?php self::options( self::LINK_STYLES, $preferences['link_style'] ); ?></select>
            <label for="<?php echo esc_attr( $prefix ); ?>social-variant"><?php esc_html_e( 'Style des réseaux', 'faluss-link' ); ?></label>
            <select id="<?php echo esc_attr( $prefix ); ?>social-variant" name="social_variant"><?php self::options( self::SOCIAL_VARIANTS, $preferences['social_variant'] ); ?></select>
        </div>
        <?php
    }

    private static function content_composer( $blocks ) {
        ?><fieldset class="faluss-link-content-composer"><legend><?php esc_html_e( 'Contenu de ma carte', 'faluss-link' ); ?></legend><p class="faluss-link-content-composer__hint"><?php esc_html_e( 'Composez vos sections dans l’ordre de lecture.', 'faluss-link' ); ?></p><div class="faluss-link-content-composer__list"><?php foreach ( $blocks as $index => $block ) { self::content_block_fields( $block, $index, count( $blocks ) ); if ( 'media_teaser' === $block['type'] ) { ?><input class="faluss-link-content-block__format-source" type="hidden" value="<?php echo esc_attr( self::teaser_format( $block['format'] ?? 'landscape' ) ); ?>"><?php } } ?></div><div class="faluss-link-content-composer__add"><label for="faluss-link-content-type"><?php esc_html_e( 'Type d’élément', 'faluss-link' ); ?></label><select id="faluss-link-content-type" class="faluss-link-content-composer__type"><?php self::options( self::BLOCK_TYPES, 'section_title' ); ?></select><button class="faluss-link-content-composer__add-button" type="button"><?php esc_html_e( 'Ajouter un élément', 'faluss-link' ); ?></button></div></fieldset><?php
    }

    private static function content_block_fields( $block, $index, $count ) {
        $type = $block['type']; $label = self::BLOCK_TYPES[ $type ]; $prefix = 'content_blocks[' . (int) $index . ']';
        ?><article class="faluss-link-content-block" data-block-type="<?php echo esc_attr( $type ); ?>"><input data-fl-block-field="block_id" name="<?php echo esc_attr( $prefix ); ?>[block_id]" type="hidden" value="<?php echo esc_attr( $block['block_id'] ); ?>"><input data-fl-block-field="type" name="<?php echo esc_attr( $prefix ); ?>[type]" type="hidden" value="<?php echo esc_attr( $type ); ?>"><header class="faluss-link-content-block__header"><strong><?php echo esc_html( $label ); ?></strong><div class="faluss-link-content-block__actions"><button data-fl-block-action="up" type="button" aria-label="<?php echo esc_attr( sprintf( __( 'Monter %s', 'faluss-link' ), $label ) ); ?>" <?php disabled( 0 === (int) $index ); ?>><?php esc_html_e( 'Monter', 'faluss-link' ); ?></button><button data-fl-block-action="down" type="button" aria-label="<?php echo esc_attr( sprintf( __( 'Descendre %s', 'faluss-link' ), $label ) ); ?>" <?php disabled( (int) $index === (int) $count - 1 ); ?>><?php esc_html_e( 'Descendre', 'faluss-link' ); ?></button><button data-fl-block-action="remove" type="button" aria-label="<?php echo esc_attr( sprintf( __( 'Supprimer %s', 'faluss-link' ), $label ) ); ?>"><?php esc_html_e( 'Supprimer', 'faluss-link' ); ?></button></div></header><?php if ( 'section_title' === $type ) : ?><label><?php esc_html_e( 'Titre', 'faluss-link' ); ?><input data-fl-block-field="value" name="<?php echo esc_attr( $prefix ); ?>[value]" type="text" maxlength="80" value="<?php echo esc_attr( $block['value'] ); ?>"></label><?php elseif ( 'text' === $type ) : ?><label><?php esc_html_e( 'Texte', 'faluss-link' ); ?><textarea data-fl-block-field="value" name="<?php echo esc_attr( $prefix ); ?>[value]" maxlength="480" rows="3"><?php echo esc_textarea( $block['value'] ); ?></textarea></label><?php elseif ( 'media_teaser' === $type ) : ?><div class="faluss-link-content-block__media"><input data-fl-block-field="attachment_id" name="<?php echo esc_attr( $prefix ); ?>[attachment_id]" type="hidden" value="<?php echo (int) $block['attachment_id']; ?>"><button class="faluss-link-content-block__select-teaser" type="button"><?php esc_html_e( 'Choisir une image', 'faluss-link' ); ?></button><button class="faluss-link-content-block__remove-teaser" type="button"><?php esc_html_e( 'Retirer', 'faluss-link' ); ?></button><div class="faluss-link-content-block__media-preview"><?php echo self::teaser_image_markup( (int) $block['attachment_id'], $block['title'] ); ?></div></div><label><?php esc_html_e( 'Titre facultatif', 'faluss-link' ); ?><input data-fl-block-field="title" name="<?php echo esc_attr( $prefix ); ?>[title]" type="text" maxlength="80" value="<?php echo esc_attr( $block['title'] ); ?>"></label><label><?php esc_html_e( 'Texte facultatif', 'faluss-link' ); ?><textarea data-fl-block-field="text" name="<?php echo esc_attr( $prefix ); ?>[text]" maxlength="240" rows="3"><?php echo esc_textarea( $block['text'] ); ?></textarea></label><?php else : ?><label><?php esc_html_e( 'Libellé', 'faluss-link' ); ?><input data-fl-block-field="label" name="<?php echo esc_attr( $prefix ); ?>[label]" type="text" maxlength="80" value="<?php echo esc_attr( $block['label'] ); ?>"></label><label><?php esc_html_e( 'URL HTTPS', 'faluss-link' ); ?><input data-fl-block-field="url" name="<?php echo esc_attr( $prefix ); ?>[url]" type="url" maxlength="2048" value="<?php echo esc_attr( $block['url'] ); ?>" placeholder="https://"></label><?php endif; ?></article><?php
    }

    private static function page_background_field( $preferences, $studio ) {
        $id = $studio ? 'faluss-studio-page-background' : 'faluss-link-editor-page-background';
        ?><label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Fond de page', 'faluss-link' ); ?></label><input id="<?php echo esc_attr( $id ); ?>" name="page_background" type="color" value="<?php echo esc_attr( $preferences['page_background'] ); ?>"><?php
    }

    private static function name_color_field( $preferences ) {
        ?><fieldset class="faluss-link-name-colors"><legend><?php esc_html_e( 'Couleur du nom', 'faluss-link' ); ?></legend><p class="faluss-link-name-colors__hint"><?php esc_html_e( 'Choisissez une couleur Faluss pour votre nom affiché.', 'faluss-link' ); ?></p><div class="faluss-link-name-colors__options"><?php foreach ( self::NAME_COLORS as $color => $label ) : ?><label class="faluss-link-name-color"><input name="name_color" type="radio" value="<?php echo esc_attr( $color ); ?>" <?php checked( $preferences['name_color'], $color ); ?>><span class="faluss-link-name-color__swatch" aria-hidden="true" style="--fl-name-color-swatch:<?php echo esc_attr( $color ); ?>"></span><span class="faluss-link-name-color__label"><?php echo esc_html( $label ); ?></span><span class="faluss-link-name-color__state"><?php esc_html_e( 'Sélectionnée', 'faluss-link' ); ?></span></label><?php endforeach; ?></div></fieldset><?php
    }

    private static function social_editor( $preferences ) {
        $catalog = self::active_network_catalog(); $socials = self::socials( $preferences['social_links'] );
        ?><fieldset class="faluss-link-studio__social-fields"><legend><?php esc_html_e( 'Réseaux sociaux', 'faluss-link' ); ?></legend><div class="faluss-link-studio__network-list"><?php foreach ( $socials as $index => $social ) : ?><div class="faluss-link-studio__network-row"><select name="social_networks[<?php echo (int) $index; ?>][network]" aria-label="<?php esc_attr_e( 'Réseau', 'faluss-link' ); ?>"><?php self::options( wp_list_pluck( $catalog, 'label' ), $social['network'] ); ?></select><input name="social_networks[<?php echo (int) $index; ?>][url]" type="url" maxlength="2048" value="<?php echo esc_attr( $social['url'] ); ?>" placeholder="https://" aria-label="<?php esc_attr_e( 'URL HTTPS', 'faluss-link' ); ?>"><button type="button" class="faluss-link-studio__remove-row"><?php esc_html_e( 'Supprimer', 'faluss-link' ); ?></button></div><?php endforeach; ?></div><button type="button" class="faluss-link-studio__add-network"><?php esc_html_e( 'Ajouter un réseau', 'faluss-link' ); ?></button></fieldset><?php
    }

    private static function card_markup( $profile, $preferences, $alignment = 'left', $preview = false, $blocks = array() ) {
        $cover = (int) $preferences['cover_attachment_id']; $avatar = (int) $profile['avatar_attachment_id']; $has_cover = $cover > 0; $has_avatar = (int) $preferences['avatar_visible'] && $avatar > 0;
        $styles = self::resolve_card_styles( $preferences );
        $variant = self::social_variant( $preferences['social_variant'] ?? 'outline' );
        $social_markup = self::social_markup( $preferences['social_links'], $variant );
        $style = sprintf( '--fl-page-background:%s;--fl-hero-transition-color:%s;--fl-hero-transition-intensity:%d%%;--fl-hero-transition-position:%d%%;--fl-name-color:%s;', $preferences['page_background'], $preferences['hero_transition_color'], (int) $preferences['hero_transition_intensity'], (int) $preferences['hero_transition_position'], $styles['name_color'] );
        ob_start();
        ?>
        <article class="faluss-link-card faluss-link-card--align-<?php echo esc_attr( self::align( $alignment ) ); ?> faluss-link-card--links-<?php echo esc_attr( $preferences['link_style'] ); ?> faluss-link-card--cover-<?php echo $has_cover ? 'yes' : 'no'; ?> faluss-link-card--avatar-<?php echo $has_avatar ? 'yes' : 'no'; ?>" style="<?php echo esc_attr( $style ); ?>">
            <div class="faluss-link-card__cover" <?php echo $has_cover ? '' : 'hidden'; ?>><?php if ( $has_cover ) { echo wp_get_attachment_image( $cover, 'large', false, array( 'alt' => '' ) ); } ?></div>
            <div class="faluss-link-card__body">
                <div class="faluss-link-card__avatar" <?php echo $has_avatar ? '' : 'hidden'; ?>><?php if ( $has_avatar ) { echo wp_get_attachment_image( $avatar, 'medium', false, array( 'alt' => '' ) ); } ?></div>
                <h2 class="faluss-link-card__name faluss-link-card__name--<?php echo esc_attr( $preferences['name_treatment'] ); ?>"><?php echo esc_html( $profile['display_name'] ?: __( 'Mon Faluss', 'faluss-link' ) ); ?></h2>
                <p class="faluss-link-card__handle"><?php echo '' === $profile['public_slug'] ? '@—' : '@' . esc_html( $profile['public_slug'] ); ?></p>
                <span class="faluss-link-card__availability" <?php echo (int) $preferences['available'] ? '' : 'hidden'; ?>><i></i><?php esc_html_e( 'Disponible', 'faluss-link' ); ?></span>
                <p class="faluss-link-card__announcement faluss-link-card__announcement--<?php echo esc_attr( $preferences['announcement_variant'] ); ?>" <?php echo 'announcement' === $preferences['bio_mode'] && '' !== $preferences['announcement'] ? '' : 'hidden'; ?>><?php echo esc_html( $preferences['announcement'] ); ?></p>
                <p class="faluss-link-card__bio" <?php echo 'announcement' === $preferences['bio_mode'] ? 'hidden' : ''; ?>><?php echo esc_html( $profile['bio'] ); ?></p>
                <nav class="faluss-link-card__social faluss-link-card__social--<?php echo esc_attr( $preferences['social_layout'] ); ?> faluss-link-card__social--variant-<?php echo esc_attr( $variant ); ?>" data-faluss-social-variant="<?php echo esc_attr( $variant ); ?>" aria-label="<?php esc_attr_e( 'Réseaux sociaux', 'faluss-link' ); ?>"<?php echo '' === $social_markup ? ' hidden' : ''; ?>><?php echo $social_markup; ?></nav>
                <?php echo self::public_blocks_markup( $blocks ); ?>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    private static function public_blocks_markup( $blocks ) {
        if ( ! $blocks ) { return ''; }
        ob_start(); ?><div class="faluss-link-card__content-blocks faluss-link-card__links"><?php foreach ( $blocks as $block ) { echo self::public_block_markup( $block ); } ?></div><?php return (string) ob_get_clean();
    }

    /* All current and future strictly validated blocks use this single rendering registry. */
    private static function public_block_markup( $block ) {
        if ( ! is_array( $block ) || empty( $block['type'] ) ) { return ''; }
        ob_start();
        if ( 'section_title' === $block['type'] ) : ?><h3 class="faluss-link-card__section-title"><?php echo esc_html( $block['value'] ); ?></h3><?php endif;
        if ( 'text' === $block['type'] ) : ?><p class="faluss-link-card__content-text"><?php echo esc_html( $block['value'] ); ?></p><?php endif;
        if ( 'link' === $block['type'] ) : ?><a class="faluss-link-card__link" href="<?php echo esc_url( $block['url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo esc_html( $block['label'] ); ?></a><?php endif;
        if ( 'media_teaser' === $block['type'] ) : $has_copy = '' !== $block['title'] || '' !== $block['text']; ?><section class="faluss-link-card__media-teaser faluss-link-card__media-teaser--format-<?php echo esc_attr( self::teaser_format( $block['format'] ?? 'landscape' ) ); ?><?php echo $has_copy ? '' : ' faluss-link-card__media-teaser--image-only'; ?>"><?php echo self::teaser_image_markup( (int) $block['attachment_id'], $block['title'] ); ?><?php if ( $has_copy ) : ?><div class="faluss-link-card__media-teaser-copy"><?php if ( '' !== $block['title'] ) : ?><h3><?php echo esc_html( $block['title'] ); ?></h3><?php endif; ?><?php if ( '' !== $block['text'] ) : ?><p><?php echo esc_html( $block['text'] ); ?></p><?php endif; ?></div><?php endif; ?></section><?php endif;
        return (string) ob_get_clean();
    }

    private static function teaser_image_markup( $attachment_id, $alt = '' ) {
        return $attachment_id && wp_attachment_is_image( $attachment_id ) ? wp_get_attachment_image( $attachment_id, 'large', false, array( 'alt' => $alt, 'loading' => 'lazy' ) ) : '';
    }

    private static function published_profile( $slug ) {
        global $wpdb; if ( '' === (string) $slug ) { return null; } $table = Faluss_Identity_Schema::get_public_profiles_table();
        $profile = $wpdb->get_row( $wpdb->prepare( 'SELECT faluss_id,public_slug,display_name,bio,avatar_attachment_id,external_links FROM ' . $table . ' WHERE public_slug=%s AND publication_status=%s', sanitize_title( $slug ), 'published' ), ARRAY_A );
        if ( ! is_array( $profile ) ) { return null; } $profile['links'] = json_decode( $profile['external_links'], true ); return $profile;
    }

    private static function prefs( $faluss_id ) {
        global $wpdb; $defaults = array( 'faluss_id' => '', 'cover_attachment_id' => 0, 'avatar_visible' => 1, 'name_weight' => 'bold', 'name_treatment' => 'strong', 'available' => 0, 'bio_mode' => 'editorial', 'announcement' => '', 'announcement_variant' => 'accent', 'social_links' => '[]', 'social_layout' => 'bubbles', 'link_style' => 'solid', 'alignment' => 'left', 'page_background' => '#FFFDF5', 'hero_transition_color' => '#FFFDF5', 'hero_transition_intensity' => 82, 'hero_transition_position' => 72, 'name_color' => '#000000', 'social_variant' => 'outline' );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Faluss_Link_Schema::table() . ' WHERE faluss_id=%s', $faluss_id ), ARRAY_A ); $preferences = is_array( $row ) ? array_merge( $defaults, $row ) : $defaults; $payload = json_decode( $preferences['social_links'], true );
        if ( is_array( $payload ) ) { $preferences['alignment'] = self::align( $payload['alignment'] ?? $preferences['alignment'] ); $preferences['page_background'] = sanitize_hex_color( $payload['page_background'] ?? '' ) ?: $preferences['page_background']; $preferences['hero_transition_color'] = sanitize_hex_color( $payload['hero_transition_color'] ?? '' ) ?: $preferences['hero_transition_color']; $preferences['hero_transition_intensity'] = min( 100, max( 0, (int) ( $payload['hero_transition_intensity'] ?? $preferences['hero_transition_intensity'] ) ) ); $preferences['hero_transition_position'] = min( 100, max( 35, (int) ( $payload['hero_transition_position'] ?? $preferences['hero_transition_position'] ) ) ); $preferences['name_color'] = self::name_color( $payload['name_color'] ?? $preferences['name_color'] ); $preferences['social_variant'] = self::social_variant( $payload['social_variant'] ?? $payload['social_appearance'] ?? $preferences['social_variant'] ); }
        return $preferences;
    }

    private static function name_color( $value ) {
        $color = is_string( $value ) ? strtoupper( (string) sanitize_hex_color( $value ) ) : '';
        return isset( self::NAME_COLORS[ $color ] ) ? $color : '#000000';
    }

    private static function social_variant( $value ) { return 'full' === $value ? 'full' : 'outline'; }

    private static function resolve_card_styles( $preferences, $theme = array() ) {
        $tokens = array( 'name_color' => '#000000' );
        $member = array( 'name_color' => self::name_color( $preferences['name_color'] ?? $tokens['name_color'] ) );
        $theme = is_array( $theme ) ? array( 'name_color' => self::name_color( $theme['name_color'] ?? $member['name_color'] ) ) : array();
        return array_merge( $tokens, $member, $theme );
    }

    /* Studio and public cards always consume this same normalized source. */
    private static function content_blocks( $faluss_id, $legacy_links, $migrate = false ) {
        $stored = self::stored_blocks( $faluss_id );
        if ( $stored['has_rows'] ) { return $stored['blocks']; }
        $legacy = self::legacy_blocks( $faluss_id, $legacy_links );
        if ( $migrate && $legacy && self::migrate_legacy_links( $faluss_id, $legacy ) ) { $stored = self::stored_blocks( $faluss_id ); return $stored['blocks'] ?: $legacy; }
        return $legacy;
    }

    private static function stored_blocks( $faluss_id ) {
        global $wpdb; $table = Faluss_Link_Schema::blocks_table();
        if ( '' === $table ) { return array( 'blocks' => array(), 'has_rows' => false ); }
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT block_id,block_type,payload FROM ' . $table . ' WHERE faluss_id=%s ORDER BY sort_order ASC,id ASC', $faluss_id ), ARRAY_A );
        $blocks = array(); $has_rows = false; $seen = array();
        foreach ( (array) $rows as $row ) { $has_rows = true; $block = self::hydrate_stored_block( $row ); if ( $block && ! isset( $seen[ $block['block_id'] ] ) ) { $seen[ $block['block_id'] ] = true; $blocks[] = $block; } }
        return array( 'blocks' => $blocks, 'has_rows' => $has_rows );
    }

    private static function migrate_legacy_links( $faluss_id, $legacy ) {
        $stored = self::stored_blocks( $faluss_id );
        return $stored['blocks'] ? true : self::save_blocks( $faluss_id, $legacy );
    }

    private static function legacy_blocks( $faluss_id, $links ) {
        $raw = array();
        foreach ( array_values( is_array( $links ) ? $links : array() ) as $index => $link ) { $raw[] = array( 'block_id' => self::legacy_block_id( $faluss_id, $index, $link ), 'type' => 'link', 'label' => $link['label'] ?? '', 'url' => $link['url'] ?? '' ); }
        return self::normalise_blocks( $raw );
    }

    private static function legacy_block_id( $faluss_id, $index, $link ) {
        $hash = hash( 'sha256', $faluss_id . '|' . (int) $index . '|' . (string) ( $link['label'] ?? '' ) . '|' . (string) ( $link['url'] ?? '' ) );
        return substr( $hash, 0, 8 ) . '-' . substr( $hash, 8, 4 ) . '-' . substr( $hash, 12, 4 ) . '-' . substr( $hash, 16, 4 ) . '-' . substr( $hash, 20, 12 );
    }

    private static function normalise_blocks( $raw, $require_owned_media = true ) {
        $blocks = array(); $seen = array();
        foreach ( array_slice( is_array( $raw ) ? $raw : array(), 0, 32 ) as $block ) { $clean = self::normalise_block( $block, false, $require_owned_media ); if ( $clean && ! isset( $seen[ $clean['block_id'] ] ) ) { $seen[ $clean['block_id'] ] = true; $blocks[] = $clean; } }
        return $blocks;
    }

    private static function hydrate_stored_block( $row ) {
        if ( ! is_array( $row ) || ! self::valid_block_id( $row['block_id'] ?? '' ) ) { return null; }
        $payload = json_decode( $row['payload'] ?? '', true );
        return is_array( $payload ) ? self::normalise_block( array_merge( $payload, array( 'block_id' => $row['block_id'], 'type' => $row['block_type'] ?? '' ) ), true, false ) : null;
    }

    private static function normalise_block( $block, $stored = false, $require_owned_media = true ) {
        if ( ! is_array( $block ) ) { return null; }
        $type = sanitize_key( (string) ( $block['type'] ?? '' ) ); $block_id = self::block_id( $block['block_id'] ?? '', $stored );
        if ( '' === $block_id ) { return null; }
        if ( 'section_title' === $type ) { $value = self::block_text( $block['value'] ?? '', 80, false ); return '' === $value ? null : array( 'block_id' => $block_id, 'type' => $type, 'value' => $value ); }
        if ( 'text' === $type ) { $value = self::block_text( $block['value'] ?? '', 480, true ); return '' === $value ? null : array( 'block_id' => $block_id, 'type' => $type, 'value' => $value ); }
        if ( 'link' === $type ) { $label = self::block_text( $block['label'] ?? '', 80, false ); $url = self::block_url( $block['url'] ?? '' ); return '' === $label || '' === $url ? null : array( 'block_id' => $block_id, 'type' => $type, 'label' => $label, 'url' => $url ); }
        if ( 'media_teaser' === $type ) { $attachment_id = self::media_attachment_id( $block['attachment_id'] ?? 0, $require_owned_media ); if ( ! $attachment_id ) { return null; } return array( 'block_id' => $block_id, 'type' => $type, 'attachment_id' => $attachment_id, 'title' => self::block_text( $block['title'] ?? '', 80, false ), 'text' => self::block_text( $block['text'] ?? '', 240, true ), 'format' => self::teaser_format( $block['format'] ?? 'landscape' ) ); }
        return null;
    }

    private static function valid_block_id( $value ) { return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', strtolower( (string) $value ) ); }
    private static function teaser_format( $value ) { return is_string( $value ) && isset( self::TEASER_FORMATS[ $value ] ) ? $value : 'landscape'; }
    private static function block_id( $value, $strict = false ) { $value = strtolower( (string) $value ); return self::valid_block_id( $value ) ? $value : ( $strict ? '' : wp_generate_uuid4() ); }
    private static function block_text( $value, $length, $multiline ) { if ( ! is_string( $value ) ) { return ''; } $value = trim( $multiline ? sanitize_textarea_field( wp_unslash( $value ) ) : sanitize_text_field( wp_unslash( $value ) ) ); return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length ); }
    private static function block_url( $value ) { $url = is_string( $value ) ? esc_url_raw( trim( wp_unslash( $value ) ), array( 'https' ) ) : ''; $parts = wp_parse_url( $url ); return '' !== $url && is_array( $parts ) && 'https' === strtolower( $parts['scheme'] ?? '' ) && ! empty( $parts['host'] ) && ! isset( $parts['user'], $parts['pass'] ) ? $url : ''; }
    private static function media_attachment_id( $value, $require_owned ) { $attachment_id = absint( $value ); if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) { return 0; } return ! $require_owned || self::owned_image( $attachment_id, get_current_user_id() ) ? $attachment_id : 0; }

    private static function identity_links( $blocks ) {
        $links = array(); foreach ( $blocks as $block ) { if ( 'link' === $block['type'] && count( $links ) < 8 ) { $links[] = array( 'label' => $block['label'], 'url' => $block['url'], 'position' => count( $links ) + 1 ); } } return $links;
    }

    private static function save_blocks( $faluss_id, $blocks ) {
        global $wpdb; $table = Faluss_Link_Schema::blocks_table(); $blocks = self::normalise_blocks( $blocks );
        if ( '' === $table || false === $wpdb->query( 'START TRANSACTION' ) ) { return false; }
        try {
            if ( false === $wpdb->delete( $table, array( 'faluss_id' => $faluss_id ), array( '%s' ) ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
            $now = current_time( 'mysql', true );
            foreach ( $blocks as $order => $block ) { $payload = $block; unset( $payload['block_id'], $payload['type'] ); if ( false === $wpdb->insert( $table, array( 'faluss_id' => $faluss_id, 'block_id' => $block['block_id'], 'sort_order' => $order + 1, 'block_type' => $block['type'], 'payload' => wp_json_encode( $payload ), 'created_at' => $now, 'updated_at' => $now ), array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) ) ) { $wpdb->query( 'ROLLBACK' ); return false; } }
            if ( false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
        } catch ( Exception $exception ) { $wpdb->query( 'ROLLBACK' ); return false; }
        return true;
    }

    private static function valid_prefs( $faluss_id ) { $preferences = self::prefs( $faluss_id ); if ( (int) $preferences['cover_attachment_id'] && ! self::owned_image( (int) $preferences['cover_attachment_id'], get_current_user_id() ) ) { $preferences['cover_attachment_id'] = 0; } return $preferences; }
    private static function verify( $field, $action ) { return is_user_logged_in() && isset( $_POST[ $field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ); }
    private static function studio_tab( $value ) { $value = sanitize_key( (string) wp_unslash( $value ) ); return in_array( $value, array( 'profile', 'links', 'style' ), true ) ? $value : 'profile'; }
    private static function redirect( $key, $notice, $studio_tab = '' ) { $url = wp_validate_redirect( wp_get_referer(), home_url( '/' ) ); $args = array( $key => $notice ); if ( '' !== $studio_tab ) { $args['faluss_studio_tab'] = self::studio_tab( $studio_tab ); } wp_safe_redirect( add_query_arg( $args, $url ) ); exit; }
    private static function notice( $key ) { $value = isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : ''; $messages = array( 'saved' => __( 'Studio enregistré.', 'faluss-link' ), 'taken' => __( 'Cet identifiant public n’est pas disponible.', 'faluss-link' ), 'invalid' => __( 'Nous ne pouvons pas enregistrer le Studio.', 'faluss-link' ) ); return isset( $messages[ $value ] ) ? '<p class="faluss-link-notice">' . esc_html( $messages[ $value ] ) . '</p>' : ''; }
    private static function options( $options, $selected ) { foreach ( $options as $key => $label ) { ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected, $key ); ?>><?php echo esc_html( $label ); ?></option><?php } }
    private static function align( $value ) { return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : 'left'; }
    private static function identity_ready() { return class_exists( 'Faluss_Identity_Schema' ) && class_exists( 'Faluss_Identity_Registry' ) && ! empty( Faluss_Identity_Schema::get_status()['ready'] ); }
    private static function enqueue_assets() { if ( ! wp_style_is( self::STYLE, 'registered' ) ) { self::assets(); } wp_enqueue_style( self::STYLE ); wp_enqueue_style( self::IMMERSIVE_STYLE ); wp_enqueue_style( self::STUDIO_STYLE ); wp_enqueue_script( self::CARD_SCRIPT ); }
    private static function editor_assets() { self::enqueue_assets(); wp_enqueue_script( self::SCRIPT ); }
    private static function owned_image( $attachment_id, $user_id ) { $attachment = get_post( (int) $attachment_id ); return $attachment instanceof WP_Post && (int) $attachment->post_author === (int) $user_id && 0 === strpos( (string) $attachment->post_mime_type, 'image/' ); }
    private static function empty_card( $message ) { return '<div class="faluss-link-card faluss-link-card--empty" role="status">' . esc_html( $message ) . '</div>'; }
    private static function network_catalog() { return class_exists( 'Faluss_Link_Admin' ) ? Faluss_Link_Admin::catalog() : array_combine( self::NETWORKS, array_map( static function( $network ) { return array( 'label' => ucfirst( $network ), 'active' => 1, 'outline_icon' => 0, 'full_logo' => 0 ); }, self::NETWORKS ) ); }
    private static function network_catalog_for_client() { $catalog = self::network_catalog(); foreach ( $catalog as $network => $settings ) { $catalog[ $network ]['outline'] = self::network_asset_url( $settings['outline_icon'] ?? 0 ); $catalog[ $network ]['full'] = self::network_asset_url( $settings['full_logo'] ?? 0 ); } return $catalog; }
    private static function active_network_catalog() { return class_exists( 'Faluss_Link_Admin' ) ? Faluss_Link_Admin::active_catalog() : self::network_catalog(); }
    private static function network_label( $network ) { $catalog = self::network_catalog(); return sanitize_text_field( $catalog[ $network ]['label'] ?? ucfirst( $network ) ); }
    private static function network_asset_url( $attachment_id ) { $attachment_id = (int) $attachment_id; return $attachment_id && wp_attachment_is_image( $attachment_id ) ? (string) wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : ''; }
    private static function social_markup( $links, $variant ) { $markup = ''; foreach ( self::socials( $links ) as $social ) { $asset = self::social_asset_markup( $social['network'], $variant ); if ( '' !== $asset ) { $markup .= '<a href="' . esc_url( $social['url'] ) . '" target="_blank" rel="noopener noreferrer nofollow" aria-label="' . esc_attr( self::network_label( $social['network'] ) ) . '" data-faluss-network="' . esc_attr( $social['network'] ) . '">' . $asset . '</a>'; } } return $markup; }
    private static function social_asset_markup( $network, $variant ) { $catalog = self::network_catalog(); $settings = $catalog[ $network ] ?? array(); $fields = 'full' === self::social_variant( $variant ) ? array( 'full_logo', 'outline_icon' ) : array( 'outline_icon', 'full_logo' ); foreach ( $fields as $field ) { $attachment_id = (int) ( $settings[ $field ] ?? 0 ); if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) { return wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => self::network_label( $network ), 'class' => 'faluss-link-card__network-asset', 'loading' => 'lazy' ) ); } } return ''; }
}
