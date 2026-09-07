<?php

function studio_v1_interactions_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/plugins/faluss-link/faluss-link.php' );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$studio = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$card = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );

studio_v1_interactions_assert( false !== strpos( $bootstrap, 'Version: 0.3.10' ) && false !== strpos( $bootstrap, "FALUSS_LINK_VERSION','0.3.10'" ), 'The interaction assets must use the 0.3.10 cache key.' );

foreach ( array( 'grid-template-columns: repeat(4, minmax(0, 1fr));', 'align-items: stretch;', 'justify-items: stretch;', '.faluss-link-studio__dock-tabs > button', 'width: 100%;', 'place-items: center;', 'padding: .45rem 0;', '.faluss-link-studio__dock-indicator', 'left: 0;' ) as $needle ) {
    studio_v1_interactions_assert( false !== strpos( $studio, $needle ), 'Root tabs must use equal, centered grid cells: ' . $needle );
}
studio_v1_interactions_assert( 2 === substr_count( $link, 'type="button" disabled aria-disabled="true"' ), 'Shop and Profil must share the same inert button geometry.' );

foreach ( array( 'data-fl-open-social-manager', 'Ajouter un réseau social', 'data-fl-social-manager', 'function openSocialManager', "activate(studio, 'style', false)", "activateSection(studio, 'header', false)" ) as $needle ) {
    studio_v1_interactions_assert( false !== strpos( $link . $editor, $needle ), 'The social-add shortcut must mount and reach the canonical Header social manager: ' . $needle );
}
studio_v1_interactions_assert( false !== strpos( $studio, 'width: 44px; height: 44px') && false !== strpos( $studio, 'width: 16px; height: 16px'), 'The social add control must keep a 44px target around a 16px visual bubble.' );

foreach ( array( 'data-faluss-studio="v1"', 'data-fl-studio-back', 'function closeTransient', "screen === 'create-link'", "section: 'all'", "screen === 'create-collection'", "section: 'collections'", "screen === 'ecosystem'", 'restoreStudioState', 'function safeHistoryBack' ) as $needle ) {
    studio_v1_interactions_assert( false !== strpos( $link . $editor, $needle ), 'Back must be wired to the real V1 renderer and restore its transient state: ' . $needle );
}

foreach ( array( 'data-fl-studio-share', 'data-public-url=', 'function sharePublicURL', 'navigator.share', 'navigator.clipboard.writeText', '/\\/mon-faluss\\/?$/' ) as $needle ) {
    studio_v1_interactions_assert( false !== strpos( $link . $editor, $needle ), 'Share must use the public V1 button and a mobile/clipboard fallback: ' . $needle );
}
studio_v1_interactions_assert( false === strpos( $editor, 'url.origin !== window.location.origin' ), 'Share must not reject a server-canonical www/non-www public URL.' );

foreach ( array( "'button_color' => '#080808'", "'button_color' => \$button_color ? \$button_color : '#080808'", "['button_color'] = self::valid_hex", "studio_color_palette( 'button_color'", 'data-fl-color-palette=', '--fl-action:%s', "setProperty('--fl-action'" ) as $needle ) {
    studio_v1_interactions_assert( false !== strpos( $link . $editor, $needle ), 'Button colour must be one persistent shared presentation preference: ' . $needle );
}
studio_v1_interactions_assert( false !== strpos( $card, '.faluss-link-card--links-solid .faluss-link-card__link{border-color:transparent;border-radius:1px' ), 'Visuel must remain low-radius.' );
studio_v1_interactions_assert( false !== strpos( $card, '.faluss-link-card--links-light .faluss-link-card__link{border:1px solid #fff;border-radius:100px' ), 'Minutieux must be exactly a 100px pill.' );
studio_v1_interactions_assert( false !== strpos( $card, '.faluss-link-card--links-outline .faluss-link-card__link{border-color:transparent;border-radius:1px' ), 'Formel must remain rectangular.' );

foreach ( array( 'data-fl-studio-ecosystem', 'data-fl-studio-screen="ecosystem"', 'Faluss, c’est un écosystème complet.', 'Visitez nos autres produits !', 'https://www.faluss.me/', 'https://www.faluss.fans/', 'https://www.pro.faluss.com/', 'https://www.faluss.com/', 'Vous êtes ici', "home_url( '/mon-faluss/' )", "home_url( '/list/' )", 'Faluss_Identity_Navigation::actions()', 'function openEcosystem' ) as $needle ) {
    studio_v1_interactions_assert( false !== strpos( $link . $editor, $needle ), 'The Ecosystem secondary screen is incomplete: ' . $needle );
}
studio_v1_interactions_assert( false === strpos( $link, 'Plus d’options, bientôt disponible' ), 'More must not remain a disabled placeholder.' );
studio_v1_interactions_assert( false !== strpos( $studio, 'grid-template-columns: repeat(2, minmax(0, 1fr));' ) && false !== strpos( $studio, 'faluss-link-studio__ecosystem-actions' ), 'Ecosystem cards and authenticated actions must use the responsive two-column layout.' );

echo "Faluss Link Studio V1 interactions contract: OK\n";
