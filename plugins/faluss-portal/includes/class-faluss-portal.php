<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AP-01 front-office boundary.
 *
 * This plugin deliberately owns neither identity nor subscription data. It
 * starts with the local, authenticated Identity Client link and projects only
 * safe read-time values from the existing Faluss services.
 */
final class Faluss_Portal {
    const SHORTCODE = 'faluss_portal';
    const STYLE = 'faluss-portal';
    const SCRIPT = 'faluss-portal';
    const PORTAL_ACTION = 'faluss_portal_open_customer_portal';
    const PORTAL_NONCE = 'faluss_portal_open_customer_portal';
    const NOTICE_PREFIX = 'faluss_portal_notice_';
    const NOTICE_TTL = 300;

    /** @var array<string,array<int,string>> */
    private const TABS = array(
        'home'         => array( 'view', 'activity', 'discover' ),
        'apps'         => array( 'my-apps', 'explore' ),
        'analytics'    => array( 'view', 'performance', 'revenue', 'sources' ),
        'subscription' => array( 'offer', 'compare' ),
        'billing'      => array( 'history', 'payment' ),
        'settings'     => array( 'general', 'notifications', 'preferences' ),
        'help'         => array( 'help', 'contact' ),
    );

    /** @var array<string,string> */
    private const SECTION_LABELS = array(
        'home'         => 'Accueil',
        'apps'         => 'Apps Faluss',
        'analytics'    => 'Analytics',
        'subscription' => 'Abonnement',
        'billing'      => 'Facturation',
        'settings'     => 'Paramètres',
        'help'         => 'Aide',
    );

    /** @var array<string,string> */
    private const TAB_LABELS = array(
        'view'          => 'Vue',
        'activity'      => 'Activité',
        'discover'      => 'Découvrir',
        'my-apps'       => 'Mes apps',
        'explore'       => 'Explorer',
        'performance'   => 'Performance',
        'revenue'       => 'Revenus',
        'sources'       => 'Sources',
        'offer'         => 'Mon offre',
        'compare'       => 'Comparer',
        'history'       => 'Historique',
        'payment'       => 'Paiement',
        'general'       => 'Général',
        'notifications' => 'Notifications',
        'preferences'   => 'Préférences',
        'help'          => 'Aide',
        'contact'       => 'Nous contacter',
    );

    public static function boot() {
        add_action( 'init', array( __CLASS__, 'register_assets' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_page_assets' ) );
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
        add_action( 'admin_post_' . self::PORTAL_ACTION, array( __CLASS__, 'open_customer_portal' ) );
        add_action( 'template_redirect', array( __CLASS__, 'intercept_customer_portal_return' ), -1 );
        add_filter( 'option_faluss_identity_client_settings', array( __CLASS__, 'include_portal_return' ) );
        add_filter( 'show_admin_bar', array( __CLASS__, 'filter_member_admin_bar' ), PHP_INT_MAX );
        add_filter( 'body_class', array( __CLASS__, 'filter_portal_body_class' ) );
    }

    /**
     * Makes the private portal a strict Identity Client return without writing
     * another plugin's option or weakening its same-site allowlist.
     *
     * @param mixed $settings Raw Identity Client option.
     * @return array<string,mixed>
     */
    public static function include_portal_return( $settings ) {
        $settings = is_array( $settings ) ? $settings : array();
        $returns = isset( $settings['return_urls'] ) ? (array) $settings['return_urls'] : array();
        $returns[] = self::portal_base_url();
        $settings['return_urls'] = array_values( array_unique( $returns ) );
        return $settings;
    }

    /** Keep wp-admin and privileged editorial/management sessions untouched. */
    public static function filter_member_admin_bar( $show ) {
        if ( is_admin() || ! is_user_logged_in() ) {
            return $show;
        }
        $user = wp_get_current_user();
        if ( ! $user instanceof WP_User || user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) ) {
            return $show;
        }
        return false;
    }

    /** Lock the host document only where the full-viewport portal is rendered. */
    public static function filter_portal_body_class( $classes ) {
        $classes = is_array( $classes ) ? $classes : array();
        if ( ! is_admin() && is_page( 'mon-faluss' ) ) {
            $classes[] = 'faluss-portal-page';
        }
        return array_values( array_unique( $classes ) );
    }

    public static function register_assets() {
        wp_register_style( self::STYLE, FALUSS_PORTAL_URL . 'assets/css/faluss-portal.css', array(), FALUSS_PORTAL_VERSION );
        wp_register_script( self::SCRIPT, FALUSS_PORTAL_URL . 'assets/js/faluss-portal.js', array(), FALUSS_PORTAL_VERSION, true );
    }

    public static function enqueue_page_assets() {
        if ( ! is_admin() && is_page( 'mon-faluss' ) ) {
            self::enqueue_assets();
        }
    }

    /** @return string */
    public static function shortcode( $attributes = array() ) {
        unset( $attributes );
        self::enqueue_assets();
        $member = self::current_member();
        if ( ! is_array( $member ) ) {
            return self::access_gate();
        }
        return self::shell( $member );
    }

    private static function enqueue_assets() {
        if ( ! wp_style_is( self::STYLE, 'registered' ) || ! wp_script_is( self::SCRIPT, 'registered' ) ) {
            self::register_assets();
        }
        wp_enqueue_style( self::STYLE );
        wp_enqueue_script( self::SCRIPT );
    }

    /**
     * Returns a safe subject only for a standard Faluss.com member session
     * created by the Identity Client. No browser-provided Faluss ID is read.
     *
     * @return array<string,mixed>|null
     */
    private static function current_member() {
        if ( ! is_user_logged_in() || ! class_exists( 'Faluss_Identity_Client_Schema' ) ) {
            return null;
        }
        $user = wp_get_current_user();
        if ( ! $user instanceof WP_User || array( 'subscriber' ) !== array_values( (array) $user->roles ) ) {
            return null;
        }
        $tables = Faluss_Identity_Client_Schema::tables();
        if ( empty( $tables['links'] ) ) {
            return null;
        }
        global $wpdb;
        $link = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT faluss_id, created_at, last_proved_at FROM ' . self::quote_identifier( $tables['links'] ) . ' WHERE wp_user_id = %d LIMIT 1',
                $user->ID
            ),
            ARRAY_A
        );
        if ( ! is_array( $link ) || ! self::valid_faluss_id( $link['faluss_id'] ?? '' ) ) {
            return null;
        }
        return array(
            'user'         => $user,
            'faluss_id'    => strtolower( $link['faluss_id'] ),
            'member_since' => self::valid_utc( $link['created_at'] ?? '' ) ? $link['created_at'] : '',
            'last_proved'  => self::valid_utc( $link['last_proved_at'] ?? '' ) ? $link['last_proved_at'] : '',
            'name'         => self::member_name( $user ),
            'handle'       => self::member_handle( $user ),
        );
    }

    /** The portal only projects whitelisted decision values, never sources or provider references. */
    private static function subscription_snapshot( $faluss_id ) {
        $empty = array(
            'available'        => false,
            'offer_name'       => 'Offre indisponible',
            'level'            => 'free',
            'state'            => 'unavailable',
            'expires_at'       => '',
            'billing_interval' => '',
            'portal_available' => false,
        );
        if ( ! self::valid_faluss_id( $faluss_id )
            || ! class_exists( 'Faluss_Subscriptions_Resolver' )
            || ! class_exists( 'Faluss_Subscriptions_Catalog' )
            || ! class_exists( 'Faluss_Subscriptions_Repository' ) ) {
            return $empty;
        }
        $decision = Faluss_Subscriptions_Resolver::resolve_for_faluss_id( $faluss_id );
        if ( ! is_array( $decision ) ) {
            return $empty;
        }
        $level = 'pro' === ( $decision['level'] ?? '' ) ? 'pro' : 'free';
        $state = in_array( $decision['state'] ?? '', array( 'free', 'trialing', 'active', 'canceling', 'past_due', 'suspended', 'expired', 'comped', 'revoked' ), true ) ? $decision['state'] : 'free';
        $plans = Faluss_Subscriptions_Catalog::plans();
        $plan = $plans[ $level ] ?? $plans['free'] ?? array();
        $interval = '';
        $reference = '';
        foreach ( (array) ( $decision['effective_sources'] ?? array() ) as $source ) {
            if ( is_array( $source ) && 'subscription' === ( $source['source'] ?? '' ) ) {
                $reference = (string) ( $source['reference'] ?? '' );
                break;
            }
        }
        if ( '' !== $reference ) {
            foreach ( Faluss_Subscriptions_Repository::subscriptions_for_faluss_id( $faluss_id ) as $subscription ) {
                if ( is_array( $subscription ) && $reference === ( $subscription['subscription_uuid'] ?? '' ) && in_array( $subscription['billing_interval'] ?? '', array( 'monthly', 'annual' ), true ) ) {
                    $interval = $subscription['billing_interval'];
                    break;
                }
            }
        }
        $portal_available = false;
        if ( class_exists( 'Faluss_Subscriptions_Stripe_Config' )
            && ! is_wp_error( Faluss_Subscriptions_Stripe_Config::portal_configuration_id() )
            && is_array( Faluss_Subscriptions_Repository::customer_for_faluss_id( $faluss_id, 'stripe' ) ) ) {
            $portal_available = true;
        }
        return array(
            'available'        => true,
            'offer_name'       => is_string( $plan['public_name'] ?? null ) ? $plan['public_name'] : 'Faluss Gratuit',
            'level'            => $level,
            'state'            => $state,
            'expires_at'       => self::valid_utc( $decision['expires_at'] ?? '' ) ? $decision['expires_at'] : '',
            'billing_interval' => $interval,
            'portal_available' => $portal_available,
        );
    }

    private static function access_gate() {
        $button = '';
        if ( class_exists( 'Faluss_Identity_Client' ) ) {
            $button = Faluss_Identity_Client::button( array(
                'label'        => 'Continuer avec Faluss',
                'redirect_url' => self::portal_base_url(),
                'accent_color' => '#FF3D16',
                'text_color'   => '#080808',
                'surface_color' => '#FFFFFF',
            ), false );
        }
        if ( '' === $button ) {
            $button = '<p class="faluss-portal__access-note">La connexion Faluss n’est pas disponible sur cette instance.</p>';
        }
        return '<section class="faluss-portal faluss-portal--access" data-faluss-portal="access" aria-labelledby="faluss-portal-access-title"><div class="faluss-portal__access-card"><p class="faluss-portal__wordmark" aria-hidden="true">faluss</p><h1 id="faluss-portal-access-title">Votre espace Faluss.</h1><p>Connectez-vous avec votre identité Faluss pour accéder à votre portail privé.</p>' . $button . '</div></section>';
    }

    private static function shell( $member ) {
        $route = self::route();
        $snapshot = self::subscription_snapshot( $member['faluss_id'] );
        $notice = self::consume_notice( $member['user']->ID );
        ob_start();
        ?>
        <section class="faluss-portal" data-faluss-portal="v1" data-section="<?php echo esc_attr( $route['section'] ); ?>" data-tab="<?php echo esc_attr( $route['tab'] ); ?>" aria-label="Portail Faluss">
            <aside class="faluss-portal__sidebar" id="faluss-portal-sidebar" aria-label="Navigation principale Faluss">
                <span class="faluss-portal__nav-indicator" aria-hidden="true"></span>
                <a class="faluss-portal__brand<?php echo 'home' === $route['section'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::portal_url( 'home', 'view' ) ); ?>" data-faluss-portal-nav="home" data-faluss-portal-tab="view" data-faluss-portal-sidebar-item<?php echo 'home' === $route['section'] ? ' aria-current="page"' : ''; ?> aria-label="Accueil Faluss"><span aria-hidden="true">faluss</span></a>
                <nav class="faluss-portal__nav" aria-label="Sections du portail">
                    <a class="faluss-portal__nav-link<?php echo 'apps' === $route['section'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::portal_url( 'apps', self::TABS['apps'][0] ) ); ?>" data-faluss-portal-nav="apps" data-faluss-portal-tab="<?php echo esc_attr( self::TABS['apps'][0] ); ?>" data-faluss-portal-sidebar-item<?php echo 'apps' === $route['section'] ? ' aria-current="page"' : ''; ?>><span class="faluss-portal__nav-icon" aria-hidden="true"><?php echo self::icon( 'apps' ); ?></span><span class="screen-reader-text">Apps Faluss</span></a>
                    <span class="faluss-portal__nav-separator" aria-hidden="true"></span>
                    <?php foreach ( array( 'analytics', 'subscription', 'billing', 'settings', 'help' ) as $section ) : ?>
                        <a class="faluss-portal__nav-link<?php echo $section === $route['section'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::portal_url( $section, self::TABS[ $section ][0] ) ); ?>" data-faluss-portal-nav="<?php echo esc_attr( $section ); ?>" data-faluss-portal-tab="<?php echo esc_attr( self::TABS[ $section ][0] ); ?>" data-faluss-portal-sidebar-item<?php echo $section === $route['section'] ? ' aria-current="page"' : ''; ?>><span class="faluss-portal__nav-icon" aria-hidden="true"><?php echo self::icon( $section ); ?></span><span class="screen-reader-text"><?php echo esc_html( self::SECTION_LABELS[ $section ] ); ?></span></a>
                    <?php endforeach; ?>
                </nav>
                <div class="faluss-portal__sidebar-avatar-cell" data-faluss-portal-control="sidebar-avatar">
                    <button class="faluss-portal__sidebar-avatar-trigger" type="button" data-faluss-portal-profile-open aria-haspopup="dialog" aria-controls="faluss-portal-master-profile" aria-label="Ouvrir le profil membre"><span class="faluss-portal__avatar faluss-portal__avatar--sidebar" aria-hidden="true"></span></button>
                </div>
            </aside>
            <main class="faluss-portal__main">
                <div class="faluss-portal__sidebar-chevron-cell" data-faluss-portal-control="sidebar-chevron">
                    <button class="faluss-portal__sidebar-chevron-control" type="button" data-faluss-portal-sidebar-toggle data-chevron-direction="left" aria-controls="faluss-portal-sidebar" aria-expanded="true"><?php echo self::chevron_icon(); ?><span class="screen-reader-text">Replier la navigation</span></button>
                </div>
                <?php foreach ( self::TABS as $section => $tabs ) : ?>
                    <?php $active_tab_index = $section === $route['section'] ? array_search( $route['tab'], $tabs, true ) : 0; ?>
                    <nav class="faluss-portal__tabs<?php echo $section === $route['section'] ? ' is-active' : ''; ?>" data-faluss-portal-tabs="<?php echo esc_attr( $section ); ?>" data-tab-count="<?php echo esc_attr( (string) count( $tabs ) ); ?>" data-active-index="<?php echo esc_attr( (string) ( false === $active_tab_index ? 0 : $active_tab_index ) ); ?>" aria-label="<?php echo esc_attr( 'Navigation ' . self::SECTION_LABELS[ $section ] ); ?>"<?php echo $section === $route['section'] ? '' : ' hidden'; ?>>
                        <span class="faluss-portal__tab-indicator" aria-hidden="true"></span>
                        <?php foreach ( $tabs as $tab ) : ?>
                            <a class="faluss-portal__tab<?php echo $section === $route['section'] && $tab === $route['tab'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::portal_url( $section, $tab ) ); ?>" data-faluss-portal-nav="<?php echo esc_attr( $section ); ?>" data-faluss-portal-tab="<?php echo esc_attr( $tab ); ?>"<?php echo $section === $route['section'] && $tab === $route['tab'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( self::TAB_LABELS[ $tab ] ); ?></a>
                        <?php endforeach; ?>
                    </nav>
                <?php endforeach; ?>
                <?php if ( is_string( $notice ) && '' !== $notice ) : ?><div class="faluss-portal__notice" role="status"><?php echo esc_html( $notice ); ?></div><?php endif; ?>
                <div class="faluss-portal__content" data-faluss-portal-content>
                    <?php self::render_panels( $route, $snapshot, $member ); ?>
                </div>
            </main>
            <?php self::master_profile( $member ); ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_panels( $route, $snapshot, $member ) {
        foreach ( self::TABS as $section => $tabs ) {
            foreach ( $tabs as $tab ) {
                $active = $section === $route['section'] && $tab === $route['tab'];
                echo '<section class="faluss-portal__panel' . ( $active ? ' is-active' : '' ) . '" data-faluss-portal-panel="' . esc_attr( $section . ':' . $tab ) . '"' . ( $active ? '' : ' hidden' ) . ' tabindex="-1" aria-label="' . esc_attr( self::SECTION_LABELS[ $section ] . ' — ' . self::TAB_LABELS[ $tab ] ) . '">';
                if ( 'home' === $section ) {
                    self::home_panel( $tab, $snapshot );
                } elseif ( 'apps' === $section ) {
                    self::apps_panel( $tab, $member['faluss_id'] );
                } elseif ( 'analytics' === $section ) {
                    self::analytics_panel( $tab );
                } elseif ( 'subscription' === $section ) {
                    self::subscription_panel( $tab, $snapshot );
                } elseif ( 'billing' === $section ) {
                    self::billing_panel( $tab, $snapshot );
                } elseif ( 'settings' === $section ) {
                    self::settings_panel( $tab );
                } else {
                    self::help_panel( $tab );
                }
                echo '</section>';
            }
        }
    }

    private static function apps_panel( $tab, $faluss_id ) {
        $apps = self::app_registry( $faluss_id );
        if ( 'explore' === $tab ) {
            echo '<div class="faluss-portal__apps faluss-portal__apps--explore" data-faluss-apps-view="explore">';
            foreach ( $apps as $app ) {
                self::render_app_card( $app, 'explore' );
            }
            echo '</div>';
            return;
        }

        echo '<div class="faluss-portal__apps faluss-portal__apps--owned" data-faluss-apps-view="my-apps">';
        foreach ( $apps as $app ) {
            if ( empty( $app['available'] ) || empty( $app['owned'] ) ) {
                continue;
            }
            self::render_app_card( $app, 'compact' );
        }
        echo '</div>';
    }

    /**
     * Single AP-01 application registry. Availability, ownership and the
     * currently open application deliberately remain separate facts.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function app_registry( $faluss_id ) {
        $assets = FALUSS_PORTAL_URL . 'assets/images/apps/';
        return array(
            array(
                'slug'        => 'hub',
                'name'        => 'Hub',
                'logo'        => $assets . 'faluss-hub.png',
                'accent'      => '#000000',
                'title_color' => '#FFFFFF',
                'url'         => 'https://faluss.com/',
                'available'   => true,
                'owned'       => self::valid_faluss_id( $faluss_id ),
                'active'      => true,
                'short_title' => 'Portail Central',
                'description' => 'Gérez vos applications et comptes Faluss depuis un espace unique. Retrouvez votre activité, vos préférences et les données que chaque service vous autorise à consulter.',
            ),
            array(
                'slug'        => 'me',
                'name'        => 'Me',
                'logo'        => $assets . 'faluss-me.png',
                'accent'      => '#EE4A4A',
                'title_color' => '#EE4A4A',
                'url'         => 'https://www.faluss.me/',
                'available'   => true,
                'owned'       => self::has_published_faluss_me_card( $faluss_id ),
                'active'      => false,
                'short_title' => 'Link Identité',
                'description' => 'La vitrine tout-en-un pensée pour votre bio. Partagez votre identité, vos liens et ce que vous proposez depuis un espace entièrement personnalisable et gratuit.',
            ),
            array(
                'slug'        => 'date',
                'name'        => 'Date',
                'logo'        => $assets . 'faluss-date.png',
                'accent'      => '#8649EF',
                'title_color' => '#8649EF',
                'url'         => '',
                'available'   => false,
                'owned'       => false,
                'active'      => false,
                'short_title' => 'Rencontre Notée',
                'description' => 'Pas de faux-semblants : découvrez, rencontrez, notez l’expérience et donnez une chance à l’inconnu.',
            ),
            array(
                'slug'        => 'fans',
                'name'        => 'Fans',
                'logo'        => '',
                'accent'      => '#51EEB7',
                'title_color' => '#51EEB7',
                'url'         => '',
                'available'   => false,
                'owned'       => false,
                'active'      => false,
                'short_title' => 'Visibilité Améliorée',
                'description' => 'Fidéliser une communauté et la faire grandir demande du travail. Faluss Fans réunira les outils pour la développer et la récompenser.',
            ),
            array(
                'slug'        => 'pro',
                'name'        => 'Pro',
                'logo'        => $assets . 'faluss-pro.png',
                'accent'      => '#EF8851',
                'title_color' => '#EF8851',
                'url'         => '',
                'available'   => false,
                'owned'       => false,
                'active'      => false,
                'short_title' => 'Ambition++',
                'description' => 'Un espace conçu pour apprendre, évoluer, créer et transformer cette ambition en une valeur réelle pour les autres.',
            ),
        );
    }

    /** A published Faluss.me card is accepted only from the local Identity API. */
    private static function has_published_faluss_me_card( $faluss_id ) {
        if ( ! self::valid_faluss_id( $faluss_id )
            || ! class_exists( 'Faluss_Identity_Public_Profile' )
            || ! method_exists( 'Faluss_Identity_Public_Profile', 'studio_profile' ) ) {
            return false;
        }
        $profile = Faluss_Identity_Public_Profile::studio_profile( $faluss_id );
        return is_array( $profile )
            && isset( $profile['faluss_id'] )
            && is_string( $profile['faluss_id'] )
            && hash_equals( strtolower( $faluss_id ), strtolower( $profile['faluss_id'] ) )
            && 'published' === ( $profile['publication_status'] ?? '' )
            && is_string( $profile['public_slug'] ?? null )
            && '' !== trim( $profile['public_slug'] );
    }

    /** @param array<string,mixed> $app */
    private static function render_app_card( $app, $variant ) {
        $compact = 'compact' === $variant;
        $url = self::validated_app_url( $app );
        $state = ! empty( $app['active'] ) ? 'active' : ( ! empty( $app['available'] ) && '' !== $url ? 'available' : 'unavailable' );
        $compact_link = $compact && '' !== $url;
        $classes = 'faluss-portal__app-card faluss-portal__app-card--' . ( $compact ? 'compact' : 'explore' );
        $style = '--faluss-app-accent:' . $app['accent'] . ';--faluss-app-title-accent:' . $app['title_color'] . ';';
        $card_label = 'Faluss ' . $app['name'];
        if ( $compact_link ) {
            echo '<a class="' . esc_attr( $classes ) . '" data-faluss-app-card data-faluss-app="' . esc_attr( $app['slug'] ) . '" data-faluss-app-state="' . esc_attr( $state ) . '" style="' . esc_attr( $style ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( 'Ouvrir ' . $card_label ) . '">';
        } else {
            echo '<article class="' . esc_attr( $classes ) . '" data-faluss-app-card data-faluss-app="' . esc_attr( $app['slug'] ) . '" data-faluss-app-state="' . esc_attr( $state ) . '" style="' . esc_attr( $style ) . '">';
        }

        echo '<div class="faluss-portal__app-head">';
        echo '<span class="faluss-portal__app-logo" role="img" aria-label="' . esc_attr( '' !== $app['logo'] ? 'Logo ' . $card_label : 'Logo ' . $card_label . ' non disponible' ) . '">';
        if ( '' !== $app['logo'] ) {
            echo '<img src="' . esc_url( $app['logo'] ) . '" alt="">';
        }
        echo '</span>';
        echo '<span class="faluss-portal__app-identity"><span class="faluss-portal__app-name"><span>Faluss</span> <span class="faluss-portal__app-derivative">' . esc_html( $app['name'] ) . '</span></span><span class="faluss-portal__app-subtitle">' . esc_html( $compact ? 'M’y rendre' : $app['short_title'] ) . '</span></span>';
        if ( $compact ) {
            echo '<span class="faluss-portal__app-open" aria-hidden="true">' . self::external_link_icon() . '</span>';
        }
        echo '</div>';

        if ( ! $compact ) {
            echo '<p class="faluss-portal__app-description">' . esc_html( $app['description'] ) . '</p><div class="faluss-portal__app-action-row">';
            if ( 'active' === $state ) {
                echo '<button class="faluss-portal__app-action" type="button" data-faluss-app-current aria-describedby="faluss-app-current-message">Vous êtes ici</button><span class="faluss-portal__app-current-message" id="faluss-app-current-message" data-faluss-app-current-message role="status" hidden>Impossible d’ouvrir, vous y êtes déjà</span>';
            } elseif ( 'available' === $state ) {
                echo '<a class="faluss-portal__app-action" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">Visiter</a>';
            } else {
                echo '<span class="faluss-portal__app-action faluss-portal__app-action--unavailable" aria-disabled="true">Bientôt disponible</span>';
            }
            echo '</div>';
        }

        echo $compact_link ? '</a>' : '</article>';
    }

    /** @param array<string,mixed> $app */
    private static function validated_app_url( $app ) {
        if ( empty( $app['available'] ) || ! is_string( $app['url'] ?? null ) || ! is_string( $app['slug'] ?? null ) ) {
            return '';
        }
        $allowed = array(
            'hub' => 'https://faluss.com/',
            'me'  => 'https://www.faluss.me/',
        );
        $expected = $allowed[ $app['slug'] ] ?? '';
        $parts = wp_parse_url( $app['url'] );
        if ( '' === $expected
            || ! hash_equals( $expected, $app['url'] )
            || ! is_array( $parts )
            || 'https' !== ( $parts['scheme'] ?? '' )
            || empty( $parts['host'] ) ) {
            return '';
        }
        return $app['url'];
    }

    private static function external_link_icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>';
    }

    private static function home_panel( $tab, $snapshot ) {
        if ( 'view' === $tab ) {
            echo '<div class="faluss-portal__intro"><p class="faluss-portal__eyebrow">Espace privé</p><h1>Bienvenue dans Faluss.</h1><p>Votre session Faluss est active. Les informations affichées ici proviennent uniquement des services déjà disponibles.</p></div>';
            echo '<div class="faluss-portal__facts"><article><span>Connexion</span><strong>Identité Faluss vérifiée</strong></article><article><span>Offre</span><strong>' . esc_html( $snapshot['available'] ? $snapshot['offer_name'] : 'Indisponible' ) . '</strong></article></div>';
        } elseif ( 'activity' === $tab ) {
            self::empty_state( 'Aucune activité consolidée', 'Les événements issus des applications Faluss apparaîtront ici lorsqu’un contrat de données commun sera actif.' );
        } else {
            self::empty_state( 'Découvrir Faluss', 'Les espaces effectivement activés par les applications Faluss apparaîtront ici. Aucun accès de démonstration n’est affiché.' );
        }
    }

    private static function analytics_panel( $tab ) {
        $labels = array( 'view' => 'Vue d’ensemble', 'performance' => 'Performance', 'revenue' => 'Revenus', 'sources' => 'Sources' );
        echo '<div class="faluss-portal__intro"><p class="faluss-portal__eyebrow">Analytics</p><h1>' . esc_html( $labels[ $tab ] ) . '</h1><p>Les composants KPI, graphiques, tableaux et filtres sont prêts à recevoir des événements consolidés, sans inventer de métrique.</p></div>';
        echo '<div class="faluss-portal__analytics-placeholder" aria-label="État sans données"><span></span><span></span><span></span></div>';
        self::empty_state( 'Aucune donnée consolidée', 'Les applications actives devront fournir des événements datés, une source, une métrique et une autorisation de lecture pour alimenter cette vue.' );
    }

    private static function subscription_panel( $tab, $snapshot ) {
        if ( 'compare' === $tab ) {
            self::comparison();
            return;
        }
        echo '<div class="faluss-portal__intro"><p class="faluss-portal__eyebrow">Abonnement</p><h1>Mon offre</h1><p>Cette décision est lue depuis Faluss Subscriptions pour votre identité Faluss courante.</p></div>';
        if ( ! $snapshot['available'] ) {
            self::empty_state( 'Offre indisponible', 'Faluss Subscriptions n’est pas prêt sur cette instance. Aucune information d’abonnement n’est estimée.' );
            return;
        }
        echo '<article class="faluss-portal__offer-card"><span class="faluss-portal__offer-kicker">Niveau effectif</span><h2>' . esc_html( $snapshot['offer_name'] ) . '</h2><dl><div><dt>État</dt><dd>' . esc_html( self::state_label( $snapshot['state'] ) ) . '</dd></div>';
        if ( '' !== $snapshot['billing_interval'] ) { echo '<div><dt>Périodicité</dt><dd>' . esc_html( 'annual' === $snapshot['billing_interval'] ? 'Annuelle' : 'Mensuelle' ) . '</dd></div>'; }
        if ( '' !== $snapshot['expires_at'] ) { echo '<div><dt>' . esc_html( 'trialing' === $snapshot['state'] ? 'Fin de l’essai' : 'Prochaine échéance' ) . '</dt><dd>' . esc_html( self::date_label( $snapshot['expires_at'] ) ) . '</dd></div>'; }
        echo '</dl><p class="faluss-portal__quiet">Les avantages applicatifs sont ajoutés par les produits Faluss qui les déclarent. Aucun avantage non configuré n’est supposé.</p>';
        if ( $snapshot['portal_available'] ) { self::customer_portal_form( 'Gérer dans Stripe' ); }
        echo '</article>';
    }

    private static function billing_panel( $tab, $snapshot ) {
        if ( 'payment' === $tab ) {
            echo '<div class="faluss-portal__intro"><p class="faluss-portal__eyebrow">Facturation</p><h1>Paiement</h1><p>Les moyens de paiement sont gérés exclusivement par Stripe. Faluss.com ne collecte ni n’affiche les cartes bancaires.</p></div>';
            if ( $snapshot['portal_available'] ) {
                echo '<article class="faluss-portal__surface"><h2>Gérer vos moyens de paiement</h2><p>Ouvrez le Customer Portal Stripe pour consulter ou modifier les informations disponibles pour votre abonnement.</p>';
                self::customer_portal_form( 'Ouvrir le Customer Portal' );
                echo '</article>';
            } else {
                self::empty_state( 'Aucun portail de paiement disponible', 'Un accès sécurisé à Stripe sera proposé lorsqu’un Customer Portal est réellement configuré pour votre identité.' );
            }
            return;
        }
        echo '<div class="faluss-portal__intro"><p class="faluss-portal__eyebrow">Facturation</p><h1>Historique</h1><p>Seuls les éléments de facturation canoniques et autorisés peuvent être affichés ici.</p></div>';
        self::empty_state( 'Aucune facture locale disponible', 'Les identifiants et données Stripe ne sont jamais affichés dans Faluss.com.' );
        if ( $snapshot['portal_available'] ) { self::customer_portal_form( 'Consulter via Stripe' ); }
    }

    private static function settings_panel( $tab ) {
        $title = self::TAB_LABELS[ $tab ];
        echo '<div class="faluss-portal__intro"><p class="faluss-portal__eyebrow">Paramètres</p><h1>' . esc_html( $title ) . '</h1><p>Cette section ne présente que des préférences propres à Faluss.com.</p></div>';
        self::empty_state( 'Aucune préférence Faluss.com', 'L’identité, le passwordless et les sessions restent gérés par Faluss Identity. Aucune copie n’est créée dans ce portail.' );
    }

    private static function help_panel( $tab ) {
        if ( 'contact' === $tab ) {
            echo '<div class="faluss-portal__intro"><p class="faluss-portal__eyebrow">Aide</p><h1>Nous contacter</h1><p>Le canal de support Faluss sera indiqué ici lorsqu’il sera configuré.</p></div>';
            self::empty_state( 'Contact non configuré', 'Aucune adresse ou formulaire de démonstration n’est affiché.' );
            return;
        }
        echo '<div class="faluss-portal__intro"><p class="faluss-portal__eyebrow">Aide</p><h1>Centre d’aide</h1><p>Retrouvez ici les ressources publiées par les produits Faluss.</p></div>';
        self::empty_state( 'Centre d’aide en préparation', 'Aucune FAQ ni statut fictif n’est présenté tant qu’un système d’aide n’est pas disponible.' );
    }

    private static function comparison() {
        $plans = class_exists( 'Faluss_Subscriptions_Catalog' ) ? Faluss_Subscriptions_Catalog::plans() : array();
        $free = $plans['free'] ?? array( 'public_name' => 'Faluss Gratuit' );
        $pro = $plans['pro'] ?? array( 'public_name' => 'Faluss Max', 'periods' => array() );
        echo '<div class="faluss-portal__intro"><p class="faluss-portal__eyebrow">Abonnement</p><h1>Comparer</h1><p>Le catalogue central Faluss Subscriptions est affiché sans ajout d’offre ou de fonctionnalité fictive.</p></div><div class="faluss-portal__comparison">';
        echo '<article><span>Offre</span><h2>' . esc_html( $free['public_name'] ?? 'Faluss Gratuit' ) . '</h2><strong>Gratuit</strong><p>Aucun avantage applicatif additionnel n’est déclaré.</p></article>';
        echo '<article><span>Offre</span><h2>' . esc_html( $pro['public_name'] ?? 'Faluss Max' ) . '</h2><strong>' . esc_html( self::price_label( $pro['periods']['monthly']['amount_cents'] ?? null ) ) . ' / mois</strong><p>' . esc_html( self::price_label( $pro['periods']['annual']['amount_cents'] ?? null ) ) . ' / an · essai de 15 jours · carte requise.</p></article></div>';
    }

    private static function empty_state( $title, $copy ) {
        echo '<div class="faluss-portal__empty"><span class="faluss-portal__empty-mark" aria-hidden="true">✦</span><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $copy ) . '</p></div>';
    }

    private static function customer_portal_form( $label ) {
        echo '<form class="faluss-portal__portal-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::PORTAL_ACTION ) . '">';
        wp_nonce_field( self::PORTAL_NONCE, 'faluss_portal_nonce' );
        echo '<button type="submit" class="faluss-portal__button">' . esc_html( $label ) . '<span aria-hidden="true">↗</span></button></form>';
    }

    private static function master_profile( $member ) {
        ?>
        <dialog class="faluss-portal__master" id="faluss-portal-master-profile" data-faluss-portal-master aria-labelledby="faluss-portal-master-title">
            <div class="faluss-portal__master-surface">
                <header class="faluss-portal__master-header">
                    <span class="faluss-portal__master-chevron-cell" data-faluss-portal-control="master-chevron">
                        <button class="faluss-portal__master-chevron-control" type="button" data-faluss-portal-profile-close data-chevron-direction="left" aria-label="Fermer mon profil"><?php echo self::chevron_icon(); ?></button>
                    </span>
                    <nav class="faluss-portal__master-tabs" aria-label="Profil membre" role="tablist">
                        <span class="faluss-portal__master-tab-indicator" aria-hidden="true"></span>
                        <button class="is-active" id="faluss-portal-master-tab-account" type="button" role="tab" data-faluss-portal-master-tab="account" aria-controls="faluss-portal-master-panel-account" aria-selected="true">Mon compte</button>
                        <button id="faluss-portal-master-tab-security" type="button" role="tab" data-faluss-portal-master-tab="security" aria-controls="faluss-portal-master-panel-security" aria-selected="false">Sécurité</button>
                        <button id="faluss-portal-master-tab-privacy" type="button" role="tab" data-faluss-portal-master-tab="privacy" aria-controls="faluss-portal-master-panel-privacy" aria-selected="false">Confidentialité</button>
                    </nav>
                    <span class="faluss-portal__master-header-spacer" aria-hidden="true"></span>
                </header>
                <section class="faluss-portal__master-panel is-active" id="faluss-portal-master-panel-account" role="tabpanel" aria-labelledby="faluss-portal-master-tab-account" data-faluss-portal-master-panel="account">
                    <div class="faluss-portal__master-identity"><span class="faluss-portal__avatar faluss-portal__avatar--master" aria-hidden="true"></span><div><h1 id="faluss-portal-master-title"><?php echo esc_html( $member['name'] ); ?></h1><p><?php echo '' !== $member['handle'] ? '@' . esc_html( $member['handle'] ) : 'Profil Faluss vérifié'; ?></p></div></div>
                    <button class="faluss-portal__profile-edit" type="button" data-faluss-portal-profile-unavailable>✎ Modifier mon profil</button>
                    <div class="faluss-portal__balance"><span>Points Faluss bientôt disponibles <small>PF</small></span></div>
                    <?php if ( '' !== $member['member_since'] ) : ?><p class="faluss-portal__member-since">Membre depuis <strong><?php echo esc_html( self::date_label( $member['member_since'] ) ); ?></strong></p><?php else : ?><p class="faluss-portal__member-since">Date d’adhésion indisponible.</p><?php endif; ?>
                </section>
                <section class="faluss-portal__master-panel" id="faluss-portal-master-panel-security" role="tabpanel" aria-labelledby="faluss-portal-master-tab-security" data-faluss-portal-master-panel="security" hidden><h2>Sécurité</h2><p>La sécurité de connexion, le passwordless et les sessions sont gérés par Faluss Identity. Ce portail ne les duplique pas.</p></section>
                <section class="faluss-portal__master-panel" id="faluss-portal-master-panel-privacy" role="tabpanel" aria-labelledby="faluss-portal-master-tab-privacy" data-faluss-portal-master-panel="privacy" hidden><h2>Confidentialité</h2><p>Les consentements et la visibilité du futur profil universel seront définis dans un lot dédié. Aucune préférence transversale n’est enregistrée ici.</p></section>
                <footer class="faluss-portal__master-footer" aria-label="Accès membre"><button type="button" data-faluss-portal-drawer="quests">✦<span>Quêtes</span></button><button type="button" data-faluss-portal-drawer="shop">▥<span>Boutique</span></button><button type="button" data-faluss-portal-drawer="premium">✹<span>Premium</span></button></footer>
                <div class="faluss-portal__drawer" data-faluss-portal-drawer-panel hidden role="status"><button type="button" data-faluss-portal-drawer-close aria-label="Fermer">×</button><h2 data-faluss-portal-drawer-title></h2><p data-faluss-portal-drawer-copy></p></div>
                <p class="faluss-portal__master-feedback" data-faluss-portal-profile-feedback hidden role="status"></p>
            </div>
        </dialog>
        <?php
    }

    /** The existing Billing service validates customer, configuration and the Stripe return URL. */
    public static function open_customer_portal() {
        $member = self::current_member();
        if ( ! is_array( $member ) || ! isset( $_POST['faluss_portal_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['faluss_portal_nonce'] ) ), self::PORTAL_NONCE ) ) {
            self::redirect_to_portal( 'Accès au portail de paiement indisponible.' );
        }
        if ( ! class_exists( 'Faluss_Subscriptions_Billing' ) ) {
            self::redirect_to_portal( 'Le portail de paiement n’est pas disponible.' );
        }
        $result = Faluss_Subscriptions_Billing::create_portal( $member['faluss_id'] );
        if ( is_wp_error( $result ) || ! is_array( $result ) || empty( $result['url'] ) || ! self::stripe_url( $result['url'] ) ) {
            self::redirect_to_portal( 'Le portail de paiement n’est pas disponible.' );
        }
        nocache_headers();
        wp_redirect( esc_url_raw( $result['url'] ), 302, 'Faluss Portal' );
        exit;
    }

    /**
     * The pre-existing Billing service returns Customer Portal users to home
     * with this neutral marker. Intercept it before SUB-01B’s Checkout-only
     * return controller, then keep the member inside the private portal.
     */
    public static function intercept_customer_portal_return() {
        $kind = isset( $_GET['faluss_subscriptions_return'] ) && is_string( $_GET['faluss_subscriptions_return'] ) ? sanitize_key( wp_unslash( $_GET['faluss_subscriptions_return'] ) ) : '';
        if ( 'portal' !== $kind ) {
            return;
        }
        nocache_headers();
        wp_safe_redirect( self::portal_url( 'billing', 'payment' ) );
        exit;
    }

    private static function route() {
        $section = isset( $_GET['faluss_portal'] ) && is_string( $_GET['faluss_portal'] ) ? sanitize_key( wp_unslash( $_GET['faluss_portal'] ) ) : 'home';
        if ( ! isset( self::TABS[ $section ] ) ) {
            $section = 'home';
        }
        $tab = isset( $_GET['faluss_portal_tab'] ) && is_string( $_GET['faluss_portal_tab'] ) ? sanitize_key( wp_unslash( $_GET['faluss_portal_tab'] ) ) : self::TABS[ $section ][0];
        if ( ! in_array( $tab, self::TABS[ $section ], true ) ) {
            $tab = self::TABS[ $section ][0];
        }
        return array( 'section' => $section, 'tab' => $tab );
    }

    private static function portal_url( $section = 'home', $tab = 'view' ) {
        return add_query_arg( array( 'faluss_portal' => sanitize_key( $section ), 'faluss_portal_tab' => sanitize_key( $tab ) ), self::portal_base_url() );
    }

    private static function portal_base_url() {
        return home_url( '/mon-faluss/' );
    }

    private static function redirect_to_portal( $notice ) {
        if ( is_user_logged_in() ) {
            set_transient( self::NOTICE_PREFIX . get_current_user_id(), sanitize_text_field( $notice ), self::NOTICE_TTL );
        }
        nocache_headers();
        wp_safe_redirect( self::portal_url( 'billing', 'payment' ) );
        exit;
    }

    private static function consume_notice( $user_id ) {
        $key = self::NOTICE_PREFIX . absint( $user_id );
        $notice = get_transient( $key );
        delete_transient( $key );
        return is_string( $notice ) ? $notice : '';
    }

    private static function member_name( $user ) {
        $name = is_object( $user ) ? trim( (string) $user->display_name ) : '';
        if ( '' === $name || 1 === preg_match( '/^faluss_[a-f0-9]{20}$/i', $name ) ) {
            return 'Membre Faluss';
        }
        return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 80 ) : substr( $name, 0, 80 );
    }

    private static function member_handle( $user ) {
        $handle = is_object( $user ) ? trim( (string) $user->user_nicename ) : '';
        if ( '' === $handle || 1 === preg_match( '/^faluss_[a-f0-9]{20}$/i', $handle ) ) {
            return '';
        }
        $handle = preg_replace( '/[^a-z0-9._-]/i', '', ltrim( $handle, '@' ) );
        return is_string( $handle ) ? substr( $handle, 0, 60 ) : '';
    }

    private static function date_label( $value ) {
        $time = strtotime( (string) $value . ' UTC' );
        return false === $time ? 'Indisponible' : wp_date( 'j F Y', $time );
    }

    private static function state_label( $state ) {
        $labels = array( 'free' => 'Gratuit', 'trialing' => 'Période d’essai', 'active' => 'Actif', 'canceling' => 'Résiliation à échéance', 'past_due' => 'Paiement à régulariser', 'suspended' => 'Suspendu', 'expired' => 'Expiré', 'comped' => 'Accès accordé', 'revoked' => 'Révoqué' );
        return $labels[ $state ] ?? 'Indisponible';
    }

    private static function price_label( $cents ) {
        return is_int( $cents ) || ctype_digit( (string) $cents ) ? number_format_i18n( (int) $cents / 100, 2 ) . ' € TTC' : 'Tarif indisponible';
    }

    private static function icon( $section ) {
        $paths = array(
            'apps' => '<circle cx="7" cy="7" r="1.35" fill="currentColor" stroke="none"/><circle cx="12" cy="7" r="1.35" fill="currentColor" stroke="none"/><circle cx="17" cy="7" r="1.35" fill="currentColor" stroke="none"/><circle cx="7" cy="12" r="1.35" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.35" fill="currentColor" stroke="none"/><circle cx="17" cy="12" r="1.35" fill="currentColor" stroke="none"/><circle cx="7" cy="17" r="1.35" fill="currentColor" stroke="none"/><circle cx="12" cy="17" r="1.35" fill="currentColor" stroke="none"/><circle cx="17" cy="17" r="1.35" fill="currentColor" stroke="none"/>',
            'analytics' => '<path d="M5 19V9m5 10V5m5 14v-7m5 7V3M4 21h17"/>',
            'subscription' => '<path d="M4 8.5A4.5 4.5 0 0 1 8.5 4h9A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-9A4.5 4.5 0 0 1 4 15.5z"/><path d="M4 9h16M16 15h4"/>',
            'billing' => '<path d="M6 4h12v16l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6m-6 3h6"/>',
            'settings' => '<path d="M5 5v14m7-14v14m7-14v14"/><circle cx="5" cy="9" r="2"/><circle cx="12" cy="15" r="2"/><circle cx="19" cy="8" r="2"/>',
            'help' => '<circle cx="12" cy="12" r="8"/><path d="M9.5 9a2.6 2.6 0 1 1 4.4 1.9c-1.3 1.2-1.9 1.7-1.9 3.1M12 17h.01"/>',
        );
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">' . ( $paths[ $section ] ?? '' ) . '</svg>';
    }

    /** Shared, non-rotating chevron used by the shell and Master Profile. */
    private static function chevron_icon() {
        return '<svg class="faluss-portal__chevron-icon" viewBox="0 0 30 30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path class="faluss-portal__chevron-path faluss-portal__chevron-path--left" d="M18 9l-6 6 6 6"/><path class="faluss-portal__chevron-path faluss-portal__chevron-path--right" d="M12 9l6 6-6 6"/></svg>';
    }

    private static function valid_faluss_id( $value ) {
        return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value );
    }

    private static function valid_utc( $value ) {
        return is_string( $value ) && false !== strtotime( $value . ' UTC' );
    }

    private static function stripe_url( $url ) {
        $parts = wp_parse_url( (string) $url );
        return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && isset( $parts['host'] ) && 1 === preg_match( '/(^|\\.)stripe\\.com$/', strtolower( $parts['host'] ) );
    }

    private static function quote_identifier( $identifier ) {
        return '`' . str_replace( '`', '', (string) $identifier ) . '`';
    }
}
