<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * A deliberately small, structured preset catalogue. It owns theme records,
 * but never member choices, entitlements, payments, subscriptions or tokens.
 */
final class Faluss_Catalog_Themes {
    const OPTION = 'faluss_catalog_card_themes';
    const VERSION_OPTION = 'faluss_catalog_version';
    const VERSION = '2';
    const SYSTEM_SLUG = 'faluss-default';
    const LINK_SCOPE = 'faluss-link';

    const NAME_COLORS = array(
        '#BE79FF' => 'Rose',
        '#FFFFFF' => 'Blanc',
        '#000000' => 'Noir',
        '#82206B' => 'Prune',
    );

    const ALIGNMENTS = array( 'left' => 'Gauche', 'center' => 'Centré' );
    const SOCIAL_VARIANTS = array( 'outline' => 'Icônes contour', 'full' => 'Logos pleins' );
    const LINK_STYLES = array( 'dark' => 'Sombre', 'light' => 'Clair', 'outline' => 'Contour' );

    public static function boot() {
        if ( ! is_admin() ) {
            return;
        }
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_post_faluss_catalog_create_theme', array( __CLASS__, 'create' ) );
        add_action( 'admin_post_faluss_catalog_update_theme', array( __CLASS__, 'update' ) );
        add_action( 'admin_post_faluss_catalog_delete_theme', array( __CLASS__, 'delete' ) );
    }

    public static function activate() {
        if ( false === get_option( self::OPTION, false ) ) {
            add_option( self::OPTION, array(), '', 'no' );
        }
        update_option( self::VERSION_OPTION, self::VERSION, false );
        return true;
    }

    /** The immutable current Faluss Link baseline. */
    public static function system_theme() {
        return array(
            'name' => 'Faluss par défaut',
            'slug' => self::SYSTEM_SLUG,
            'active' => 1,
            'sort_order' => 0,
            'preview_attachment_id' => 0,
            'scope' => self::LINK_SCOPE,
            'page_background' => '#FFFDF5',
            'hero_transition_color' => '#FFFDF5',
            'name_color' => '#000000',
            'alignment' => 'left',
            'social_variant' => 'outline',
            'link_style' => 'dark',
            'entitlement_code' => '',
            'system' => true,
        );
    }

    /** @return array<string, array<string, mixed>> */
    public static function all_for_scope( $scope = self::LINK_SCOPE ) {
        $themes = array( self::SYSTEM_SLUG => self::system_theme() );
        foreach ( (array) get_option( self::OPTION, array() ) as $slug => $stored ) {
            $theme = self::normalise_stored_theme( $stored, $slug );
            if ( ! $theme || $scope !== $theme['scope'] ) {
                continue;
            }
            $themes[ $theme['slug'] ] = $theme;
        }
        uasort(
            $themes,
            static function ( $first, $second ) {
                $order = (int) $first['sort_order'] <=> (int) $second['sort_order'];
                return 0 !== $order ? $order : strcasecmp( $first['name'], $second['name'] );
            }
        );
        return $themes;
    }

    /** Only active scoped themes may be selected or resolved by consuming plugins. */
    public static function active_for_scope( $scope = self::LINK_SCOPE ) {
        return array_filter(
            self::all_for_scope( $scope ),
            static function ( $theme ) {
                return ! empty( $theme['active'] );
            }
        );
    }

    public static function get_theme( $slug, $scope = self::LINK_SCOPE ) {
        $slug = sanitize_title( (string) $slug );
        foreach ( self::all_for_scope( $scope ) as $theme ) {
            if ( $slug === $theme['slug'] ) {
                return $theme;
            }
        }
        return false;
    }

    /** Return an exact active theme, never an archived or out-of-scope record. */
    public static function get_active_theme( $slug, $scope = self::LINK_SCOPE ) {
        $theme = self::get_theme( $slug, $scope );
        return is_array( $theme ) && ! empty( $theme['active'] ) ? $theme : false;
    }

    public static function menu() {
        add_menu_page( 'Catalogue Faluss', 'Catalogue Faluss', 'manage_options', 'faluss-catalog', array( __CLASS__, 'page' ), 'dashicons-art', 58 );
    }

    public static function assets( $hook ) {
        if ( 'toplevel_page_faluss-catalog' !== $hook ) {
            return;
        }
        wp_enqueue_media();
        wp_add_inline_style( 'wp-admin', '.faluss-catalog-theme{max-width:920px;margin:18px 0;padding:18px;background:#fff;border:1px solid #dcdcde;border-radius:12px}.faluss-catalog-theme__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}.faluss-catalog-theme label{display:grid;gap:6px;font-weight:600}.faluss-catalog-theme__preview{width:100%;max-width:180px;aspect-ratio:16/10;object-fit:cover;border-radius:8px}.faluss-catalog-theme__actions{display:flex;gap:8px;align-items:end;flex-wrap:wrap}' );
        wp_add_inline_script( 'media-editor', 'jQuery(function($){$(document).on("click",".faluss-catalog-media-picker",function(event){event.preventDefault();var button=$(this),field=button.siblings("input[type=hidden]"),preview=button.closest(".faluss-catalog-theme").find(".faluss-catalog-theme__preview");var frame=wp.media({title:"Image de prévisualisation",button:{text:"Utiliser cette image"},multiple:false,library:{type:"image"}});frame.on("select",function(){var item=frame.state().get("selection").first();field.val(item.get("id"));if(preview.length){preview.attr("src",item.get("url")).prop("hidden",false);}});frame.open();});});' );
    }

    public static function create() {
        self::guard( 'faluss_catalog_create_theme' );
        $themes = self::stored_themes();
        $name = self::text( $_POST['name'] ?? '', 80 );
        $theme = self::validated_input( $_POST, self::unique_slug( $name, $themes ) );
        if ( '' === $name || ! $theme ) {
            self::redirect( 'invalid' );
        }
        $themes[ $theme['slug'] ] = $theme;
        update_option( self::OPTION, $themes, false );
        self::redirect( 'created' );
    }

    public static function update() {
        self::guard( 'faluss_catalog_update_theme' );
        $themes = self::stored_themes();
        $slug = sanitize_title( wp_unslash( $_POST['theme_slug'] ?? '' ) );
        if ( self::SYSTEM_SLUG === $slug || ! isset( $themes[ $slug ] ) ) {
            self::redirect( 'invalid' );
        }
        $theme = self::validated_input( $_POST, $slug, self::entitlement_code( $themes[ $slug ]['entitlement_code'] ?? '' ) );
        if ( ! $theme ) {
            self::redirect( 'invalid' );
        }
        $was_active = ! empty( $themes[ $slug ]['active'] );
        $themes[ $slug ] = $theme;
        update_option( self::OPTION, $themes, false );
        if ( $was_active && empty( $theme['active'] ) ) {
            /** Consumers replace only references to this exact former active preset. */
            do_action( 'faluss_catalog_theme_deactivated', $slug );
        }
        self::redirect( 'updated' );
    }

    public static function delete() {
        self::guard( 'faluss_catalog_delete_theme' );
        $themes = self::stored_themes();
        $slug = sanitize_title( wp_unslash( $_POST['theme_slug'] ?? '' ) );
        if ( self::SYSTEM_SLUG !== $slug && isset( $themes[ $slug ] ) ) {
            unset( $themes[ $slug ] );
            update_option( self::OPTION, $themes, false );
            /** A deleted preset is unavailable just like a deactivated preset. */
            do_action( 'faluss_catalog_theme_deactivated', $slug );
            self::redirect( 'deleted' );
        }
        self::redirect( 'invalid' );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap faluss-catalog">
            <h1>Catalogue Faluss</h1>
            <p>Les thèmes sont des presets structurés pour les cartes Faluss Link. Ils ne portent aucun droit, prix, abonnement, token ou paiement.</p>
            <?php self::notice(); ?>
            <h2>Faluss par défaut</h2>
            <?php self::theme_summary( self::system_theme() ); ?>
            <h2>Thèmes de carte</h2>
            <?php foreach ( self::all_for_scope() as $theme ) : ?>
                <?php if ( ! empty( $theme['system'] ) ) { continue; } ?>
                <?php self::theme_form( $theme ); ?>
            <?php endforeach; ?>
            <h2>Créer un thème</h2>
            <?php self::theme_form( self::new_theme(), true ); ?>
        </div>
        <?php
    }

    private static function theme_summary( $theme ) {
        ?><section class="faluss-catalog-theme"><p><strong><?php echo esc_html( $theme['name'] ); ?></strong> — thème système actif et immuable, utilisé comme repli technique.</p></section><?php
    }

    private static function theme_form( $theme, $create = false ) {
        $action = $create ? 'faluss_catalog_create_theme' : 'faluss_catalog_update_theme';
        ?>
        <form class="faluss-catalog-theme" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
            <?php if ( ! $create ) : ?><input type="hidden" name="theme_slug" value="<?php echo esc_attr( $theme['slug'] ); ?>"><?php endif; ?>
            <?php wp_nonce_field( $action, 'faluss_catalog_nonce' ); ?>
            <div class="faluss-catalog-theme__grid">
                <label>Nom<input name="name" maxlength="80" required value="<?php echo esc_attr( $theme['name'] ); ?>"></label>
                <?php if ( ! $create ) : ?><label>Identifiant stable<input readonly value="<?php echo esc_attr( $theme['slug'] ); ?>"></label><?php endif; ?>
                <label>État<select name="active"><option value="1" <?php selected( ! empty( $theme['active'] ) ); ?>>Actif</option><option value="0" <?php selected( empty( $theme['active'] ) ); ?>>Inactif</option></select></label>
                <label>Ordre d’affichage<input name="sort_order" type="number" min="1" max="9999" value="<?php echo (int) $theme['sort_order']; ?>"></label>
                <label>Fond de carte<input name="page_background" type="color" value="<?php echo esc_attr( $theme['page_background'] ); ?>"></label>
                <label>Transition du hero<input name="hero_transition_color" type="color" value="<?php echo esc_attr( $theme['hero_transition_color'] ); ?>"></label>
                <label>Couleur du nom<select name="name_color"><?php self::options( self::NAME_COLORS, $theme['name_color'] ); ?></select></label>
                <label>Alignement<select name="alignment"><?php self::options( self::ALIGNMENTS, $theme['alignment'] ); ?></select></label>
                <label>Réseaux<select name="social_variant"><?php self::options( self::SOCIAL_VARIANTS, $theme['social_variant'] ); ?></select></label>
                <label>Boutons de liens<select name="link_style"><?php self::options( self::LINK_STYLES, $theme['link_style'] ); ?></select></label>
                <?php self::entitlement_control( $theme, $create ); ?>
                <label>Image de prévisualisation<input type="hidden" name="preview_attachment_id" value="<?php echo (int) $theme['preview_attachment_id']; ?>"><button type="button" class="button faluss-catalog-media-picker">Choisir une image</button></label>
                <?php if ( (int) $theme['preview_attachment_id'] ) : ?><img class="faluss-catalog-theme__preview" src="<?php echo esc_url( wp_get_attachment_image_url( (int) $theme['preview_attachment_id'], 'medium' ) ); ?>" alt=""><?php else : ?><img class="faluss-catalog-theme__preview" hidden alt=""><?php endif; ?>
            </div>
            <div class="faluss-catalog-theme__actions"><?php submit_button( $create ? 'Créer le thème' : 'Enregistrer le thème', 'primary', 'submit', false ); ?></div>
        </form>
        <?php if ( ! $create ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="faluss_catalog_delete_theme"><input type="hidden" name="theme_slug" value="<?php echo esc_attr( $theme['slug'] ); ?>"><?php wp_nonce_field( 'faluss_catalog_delete_theme', 'faluss_catalog_nonce' ); ?><p><button class="button-link-delete" type="submit">Supprimer</button></p></form><?php endif; ?>
        <?php
    }

    private static function new_theme() {
        $theme = self::system_theme();
        $theme['name'] = '';
        $theme['slug'] = '';
        $theme['sort_order'] = 10;
        $theme['preview_attachment_id'] = 0;
        unset( $theme['system'] );
        return $theme;
    }

    private static function options( $options, $selected_value ) {
        foreach ( $options as $value => $label ) {
            ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_value, $value ); ?>><?php echo esc_html( $label ); ?></option><?php
        }
    }

    private static function stored_themes() {
        $themes = array();
        foreach ( (array) get_option( self::OPTION, array() ) as $slug => $stored ) {
            $theme = self::normalise_stored_theme( $stored, $slug );
            if ( $theme && self::SYSTEM_SLUG !== $theme['slug'] ) {
                $themes[ $theme['slug'] ] = $theme;
            }
        }
        return $themes;
    }

    private static function normalise_stored_theme( $stored, $fallback_slug ) {
        if ( ! is_array( $stored ) ) {
            return false;
        }
        $slug = sanitize_title( $stored['slug'] ?? $fallback_slug );
        $name = self::text( $stored['name'] ?? '', 80 );
        if ( '' === $slug || '' === $name || self::SYSTEM_SLUG === $slug ) {
            return false;
        }
        $fallback = self::system_theme();
        return array(
            'name' => $name,
            'slug' => $slug,
            'active' => empty( $stored['active'] ) ? 0 : 1,
            'sort_order' => min( 9999, max( 1, (int) ( $stored['sort_order'] ?? 10 ) ) ),
            'preview_attachment_id' => self::image_id( $stored['preview_attachment_id'] ?? 0 ),
            'scope' => self::LINK_SCOPE,
            'page_background' => self::hex( $stored['page_background'] ?? '' ) ?: $fallback['page_background'],
            'hero_transition_color' => self::hex( $stored['hero_transition_color'] ?? '' ) ?: $fallback['hero_transition_color'],
            'name_color' => self::name_color( $stored['name_color'] ?? $fallback['name_color'] ),
            'alignment' => self::enum( $stored['alignment'] ?? '', self::ALIGNMENTS, $fallback['alignment'] ),
            'social_variant' => self::enum( $stored['social_variant'] ?? '', self::SOCIAL_VARIANTS, $fallback['social_variant'] ),
            'link_style' => self::enum( $stored['link_style'] ?? '', self::LINK_STYLES, $fallback['link_style'] ),
            'entitlement_code' => self::entitlement_code( $stored['entitlement_code'] ?? '' ),
            'system' => false,
        );
    }

    private static function validated_input( $post, $slug, $existing_entitlement = '' ) {
        $name = self::text( wp_unslash( $post['name'] ?? '' ), 80 );
        $background = self::hex( wp_unslash( $post['page_background'] ?? '' ) );
        $hero = self::hex( wp_unslash( $post['hero_transition_color'] ?? '' ) );
        $name_color = self::name_color( wp_unslash( $post['name_color'] ?? '' ) );
        $alignment = self::enum( wp_unslash( $post['alignment'] ?? '' ), self::ALIGNMENTS, '' );
        $socials = self::enum( wp_unslash( $post['social_variant'] ?? '' ), self::SOCIAL_VARIANTS, '' );
        $links = self::enum( wp_unslash( $post['link_style'] ?? '' ), self::LINK_STYLES, '' );
        $entitlement = self::validated_entitlement( $post['entitlement_code'] ?? '', $existing_entitlement );
        if ( '' === $name || ! $background || ! $hero || ! isset( self::NAME_COLORS[ $name_color ] ) || '' === $alignment || '' === $socials || '' === $links || false === $entitlement ) {
            return false;
        }
        return array(
            'name' => $name,
            'slug' => $slug,
            'active' => '1' === (string) ( $post['active'] ?? '' ) ? 1 : 0,
            'sort_order' => min( 9999, max( 1, (int) ( $post['sort_order'] ?? 10 ) ) ),
            'preview_attachment_id' => self::image_id( $post['preview_attachment_id'] ?? 0 ),
            'scope' => self::LINK_SCOPE,
            'page_background' => $background,
            'hero_transition_color' => $hero,
            'name_color' => $name_color,
            'alignment' => $alignment,
            'social_variant' => $socials,
            'link_style' => $links,
            'entitlement_code' => $entitlement,
            'system' => false,
        );
    }

    /** Only active theme definitions received through the Connector may be attached to a preset. */
    private static function validated_entitlement( $value, $existing_entitlement ) {
        $requested = self::entitlement_code( wp_unslash( $value ) );
        $existing_entitlement = self::entitlement_code( $existing_entitlement );
        if ( '' === $requested ) {
            return '';
        }
        $definitions = self::connector_theme_entitlements();
        if ( false === $definitions ) {
            return $requested === $existing_entitlement ? $existing_entitlement : false;
        }
        return isset( $definitions[ $requested ] ) ? $requested : false;
    }

    /** @return array<string, array<string, string>>|false */
    private static function connector_theme_entitlements() {
        if ( ! class_exists( 'Token_Engine_Connector_Service' ) || ! method_exists( 'Token_Engine_Connector_Service', 'entitlement_definitions' ) ) {
            return false;
        }
        $definitions = Token_Engine_Connector_Service::entitlement_definitions();
        if ( is_wp_error( $definitions ) || ! is_array( $definitions ) ) {
            return false;
        }
        $out = array();
        foreach ( $definitions as $definition ) {
            $code = self::entitlement_code( $definition['code'] ?? '' );
            if ( '' !== $code && 'theme' === (string) ( $definition['type'] ?? '' ) ) {
                $out[ $code ] = array( 'code' => $code, 'label' => self::text( $definition['label'] ?? '', 120 ) ?: $code );
            }
        }
        return $out;
    }

    private static function entitlement_control( $theme, $create ) {
        $current = self::entitlement_code( $theme['entitlement_code'] ?? '' );
        $definitions = self::connector_theme_entitlements();
        ?><label>Droit requis<?php if ( false === $definitions ) : ?><input type="hidden" name="entitlement_code" value="<?php echo esc_attr( $current ); ?>"><span class="description"><?php echo $current ? 'Le droit actuellement associé reste verrouillé tant que le Core est indisponible.' : 'Core/Connector indisponible : aucun nouveau droit ne peut être associé.'; ?></span><?php else : ?><select name="entitlement_code"><option value="">Inclus — aucun droit requis</option><?php foreach ( $definitions as $code => $definition ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current, $code ); ?>><?php echo esc_html( $definition['label'] ); ?></option><?php endforeach; ?></select><span class="description">Les droits actifs de type thème sont lus depuis le Core. Aucun code libre n’est accepté.</span><?php endif; ?></label><?php
    }

    private static function unique_slug( $name, $themes ) {
        $base = sanitize_title( $name );
        $base = '' === $base ? 'theme-faluss' : $base;
        $slug = $base;
        $number = 2;
        while ( self::SYSTEM_SLUG === $slug || isset( $themes[ $slug ] ) ) {
            $slug = $base . '-' . $number;
            ++$number;
        }
        return $slug;
    }

    private static function guard( $action ) {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['faluss_catalog_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['faluss_catalog_nonce'] ) ), $action ) ) {
            wp_die( 'Accès refusé.' );
        }
    }

    private static function redirect( $notice ) {
        wp_safe_redirect( add_query_arg( 'faluss_catalog_notice', sanitize_key( $notice ), admin_url( 'admin.php?page=faluss-catalog' ) ) );
        exit;
    }

    private static function notice() {
        $notice = sanitize_key( wp_unslash( $_GET['faluss_catalog_notice'] ?? '' ) );
        $messages = array( 'created' => 'Thème créé.', 'updated' => 'Thème enregistré.', 'deleted' => 'Thème supprimé.', 'invalid' => 'Le thème ne peut pas être enregistré.' );
        if ( isset( $messages[ $notice ] ) ) {
            ?><div class="notice notice-<?php echo 'invalid' === $notice ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( $messages[ $notice ] ); ?></p></div><?php
        }
    }

    private static function image_id( $value ) {
        $attachment_id = absint( $value );
        return $attachment_id && wp_attachment_is_image( $attachment_id ) ? $attachment_id : 0;
    }

    private static function name_color( $value ) {
        $color = self::hex( $value );
        return isset( self::NAME_COLORS[ $color ] ) ? $color : '';
    }

    private static function entitlement_code( $value ) {
        $code = is_string( $value ) ? strtolower( trim( $value ) ) : '';
        return 1 === preg_match( '/^[a-z][a-z0-9_.-]{1,118}$/', $code ) ? $code : '';
    }

    private static function hex( $value ) {
        $color = is_string( $value ) ? strtoupper( (string) sanitize_hex_color( $value ) ) : '';
        return preg_match( '/^#[0-9A-F]{6}$/', $color ) ? $color : '';
    }

    private static function enum( $value, $allowed, $fallback ) {
        return is_string( $value ) && isset( $allowed[ $value ] ) ? $value : $fallback;
    }

    private static function text( $value, $length ) {
        $value = is_string( $value ) ? sanitize_text_field( $value ) : '';
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
    }
}
