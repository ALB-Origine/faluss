<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Faluss_Theme {
    const OPTION = 'faluss_theme_tokens';

    public static function defaults() {
        return array(
            'canvas' => '#FFFDF5', 'surface' => '#FFFFFF', 'ink' => '#080808', 'muted' => '#6F6A63', 'accent' => '#FF3D16',
            'action' => '#080808', 'action_text' => '#FFFFFF', 'action_hover' => '#28231F', 'action_active' => '#000000',
            'border_color' => '#080808', 'border_opacity' => '12', 'shadow' => 'soft',
            'card_radius' => '24px', 'control_radius' => '8px', 'pill_radius' => '999px',
        );
    }

    public static function shadow_presets() {
        return array(
            'none' => array( 'label' => 'Sans ombre', 'value' => 'none' ),
            'soft' => array( 'label' => 'Douce', 'value' => '0 12px 30px rgba(8, 8, 8, 0.06)' ),
            'lifted' => array( 'label' => 'Légèrement relevée', 'value' => '0 18px 38px rgba(8, 8, 8, 0.09)' ),
        );
    }

    public static function boot() {
        if ( ! self::is_faluss_site() ) { return; }
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'tokens' ) );
        if ( is_admin() ) {
            add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
            add_action( 'admin_init', array( __CLASS__, 'settings' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
        }
    }

    public static function activate() {
        if ( ! self::is_faluss_site() ) { wp_die( esc_html__( 'Faluss Theme est réservé aux sites Faluss.', 'faluss-theme' ) ); }
    }

    public static function get() {
        $stored = (array) get_option( self::OPTION, array() );
        if ( isset( $stored['border'] ) && ! isset( $stored['border_color'] ) ) {
            $stored['border_color'] = '#080808';
            $stored['border_opacity'] = '12';
        }
        return wp_parse_args( $stored, self::defaults() );
    }

    public static function tokens() {
        $tokens = self::get();
        $shadow = self::shadow_presets();
        $css = ':root{';
        foreach ( array( 'canvas', 'surface', 'ink', 'muted', 'accent', 'action', 'action_text', 'action_hover', 'action_active', 'card_radius', 'control_radius', 'pill_radius' ) as $key ) {
            $css .= '--faluss-' . $key . ':' . $tokens[ $key ] . ';';
        }
        $css .= '--faluss-action-text:' . $tokens['action_text'] . ';--faluss-action-hover:' . $tokens['action_hover'] . ';--faluss-action-active:' . $tokens['action_active'] . ';';
        $css .= '--faluss-border:rgba(' . self::rgb( $tokens['border_color'] ) . ',' . ( (int) $tokens['border_opacity'] / 100 ) . ');';
        $css .= '--faluss-shadow:' . $shadow[ $tokens['shadow'] ]['value'] . ';}';
        wp_register_style( 'faluss-theme-tokens', false, array(), null );
        wp_enqueue_style( 'faluss-theme-tokens' );
        wp_add_inline_style( 'faluss-theme-tokens', $css );
    }

    public static function menu() { add_options_page( 'Faluss Theme', 'Faluss Theme', 'manage_options', 'faluss-theme', array( __CLASS__, 'page' ) ); }
    public static function settings() { register_setting( 'faluss_theme', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) ); }

    public static function admin_assets( $hook ) {
        if ( 'settings_page_faluss-theme' !== $hook ) { return; }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){jQuery(".faluss-theme-color").wpColorPicker();});' );
    }

    public static function sanitize( $value ) {
        $defaults = self::defaults();
        $out = array();
        foreach ( array( 'canvas', 'surface', 'ink', 'muted', 'accent', 'action', 'action_text', 'action_hover', 'action_active', 'border_color' ) as $key ) {
            $candidate = isset( $value[ $key ] ) ? (string) $value[ $key ] : $defaults[ $key ];
            $out[ $key ] = preg_match( '/^#[A-Fa-f0-9]{6}$/', $candidate ) ? strtoupper( $candidate ) : $defaults[ $key ];
        }
        $opacity = isset( $value['border_opacity'] ) ? (int) $value['border_opacity'] : (int) $defaults['border_opacity'];
        $out['border_opacity'] = (string) max( 0, min( 100, $opacity ) );
        $out['shadow'] = isset( self::shadow_presets()[ $value['shadow'] ?? '' ] ) ? $value['shadow'] : $defaults['shadow'];
        foreach ( array( 'card_radius', 'control_radius', 'pill_radius' ) as $key ) {
            $candidate = isset( $value[ $key ] ) ? (string) $value[ $key ] : $defaults[ $key ];
            $out[ $key ] = preg_match( '/^[0-9]{1,3}px$/', $candidate ) ? $candidate : $defaults[ $key ];
        }
        return $out;
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $tokens = self::get();
        $shadows = self::shadow_presets();
        ?>
        <div class="wrap faluss-theme-admin">
            <h1>Faluss Theme</h1>
            <p>Les réglages ci-dessous alimentent les variables visuelles Faluss sur le front-end. Les widgets Elementor peuvent les surcharger localement.</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'faluss_theme' ); ?>
                <h2>Couleurs</h2><table class="form-table" role="presentation"><tbody>
                    <?php self::color_row( 'canvas', 'Fond de page', $tokens ); self::color_row( 'surface', 'Surface', $tokens ); self::color_row( 'ink', 'Texte principal', $tokens ); self::color_row( 'muted', 'Texte discret', $tokens ); self::color_row( 'accent', 'Accent de signal', $tokens ); ?>
                </tbody></table>
                <h2>Actions</h2><table class="form-table" role="presentation"><tbody>
                    <?php self::color_row( 'action', 'Bouton principal', $tokens ); self::color_row( 'action_text', 'Texte du bouton', $tokens ); self::color_row( 'action_hover', 'Bouton au survol', $tokens ); self::color_row( 'action_active', 'Bouton actif', $tokens ); ?>
                </tbody></table>
                <h2>Formes &amp; relief</h2><table class="form-table" role="presentation"><tbody>
                    <?php self::color_row( 'border_color', 'Couleur de bordure', $tokens ); ?>
                    <tr><th scope="row"><label for="faluss-theme-border-opacity">Opacité de bordure</label></th><td><input id="faluss-theme-border-opacity" name="<?php echo esc_attr( self::OPTION ); ?>[border_opacity]" type="number" min="0" max="100" value="<?php echo esc_attr( $tokens['border_opacity'] ); ?>"> <span>%</span></td></tr>
                    <tr><th scope="row"><label for="faluss-theme-shadow">Ombre de carte</label></th><td><select id="faluss-theme-shadow" name="<?php echo esc_attr( self::OPTION ); ?>[shadow]"><?php foreach ( $shadows as $key => $shadow ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $tokens['shadow'], $key ); ?>><?php echo esc_html( $shadow['label'] ); ?></option><?php endforeach; ?></select><p class="description">Des reliefs discrets, maintenus dans le langage Faluss.</p></td></tr>
                    <?php self::radius_row( 'card_radius', 'Arrondi des cartes', $tokens ); self::radius_row( 'control_radius', 'Arrondi des contrôles', $tokens ); self::radius_row( 'pill_radius', 'Arrondi pill', $tokens ); ?>
                </tbody></table>
                <?php submit_button( 'Enregistrer les réglages' ); ?>
            </form>
            <form method="post" action="options.php" class="faluss-theme-admin__reset"><?php settings_fields( 'faluss_theme' ); foreach ( self::defaults() as $key => $value ) : ?><input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>"><?php endforeach; ?><button class="button" type="submit">Restaurer les valeurs canoniques</button></form>
            <h2>Aperçu</h2><div class="faluss-theme-admin__preview" style="<?php echo esc_attr( self::preview_style( $tokens ) ); ?>"><div class="faluss-theme-admin__card"><strong>Une carte Faluss</strong><p>Une surface calme, lisible et cohérente.</p><button type="button">Action principale</button><button class="is-hover" type="button">Survol</button></div></div>
        </div>
        <style>.faluss-theme-admin__reset{margin:1rem 0 2rem}.faluss-theme-admin__preview{padding:24px;max-width:540px}.faluss-theme-admin__card{padding:24px;border:1px solid var(--ft-border);border-radius:var(--ft-card-radius);background:var(--ft-surface);box-shadow:var(--ft-shadow);color:var(--ft-ink)}.faluss-theme-admin__card p{color:var(--ft-muted)}.faluss-theme-admin__card button{margin-right:8px;padding:9px 15px;border:0;border-radius:999px;background:var(--ft-action);color:var(--ft-action-text)}.faluss-theme-admin__card .is-hover{background:var(--ft-action-hover)}</style>
        <?php
    }

    private static function color_row( $key, $label, $tokens ) { ?><tr><th scope="row"><label for="faluss-theme-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input id="faluss-theme-<?php echo esc_attr( $key ); ?>" class="faluss-theme-color" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $tokens[ $key ] ); ?>"></td></tr><?php }
    private static function radius_row( $key, $label, $tokens ) { ?><tr><th scope="row"><label for="faluss-theme-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input id="faluss-theme-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $tokens[ $key ] ); ?>" pattern="[0-9]{1,3}px"></td></tr><?php }
    private static function preview_style( $tokens ) { $shadow = self::shadow_presets()[ $tokens['shadow'] ]['value']; return '--ft-surface:' . $tokens['surface'] . ';--ft-ink:' . $tokens['ink'] . ';--ft-muted:' . $tokens['muted'] . ';--ft-action:' . $tokens['action'] . ';--ft-action-text:' . $tokens['action_text'] . ';--ft-action-hover:' . $tokens['action_hover'] . ';--ft-border:rgba(' . self::rgb( $tokens['border_color'] ) . ',' . ( (int) $tokens['border_opacity'] / 100 ) . ');--ft-card-radius:' . $tokens['card_radius'] . ';--ft-shadow:' . $shadow . ';background:' . $tokens['canvas'] . ';'; }
    private static function rgb( $hex ) { return implode( ',', array_map( 'hexdec', str_split( ltrim( $hex, '#' ), 2 ) ) ); }
    private static function is_faluss_site() { $host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ); return 1 === preg_match( '/(^|\\.)faluss\\.(me|com)$/i', $host ); }
}
