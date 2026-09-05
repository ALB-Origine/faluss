( function () {
    'use strict';

    function copyValue( button ) {
        var target = document.getElementById( button.getAttribute( 'data-copy-target' ) );
        if ( ! target ) {
            return;
        }
        var done = function () {
            button.textContent = 'Copiée';
            window.setTimeout( function () { button.textContent = 'Copier'; }, 1800 );
        };
        if ( navigator.clipboard && window.isSecureContext ) {
            navigator.clipboard.writeText( target.value ).then( done );
            return;
        }
        target.focus();
        target.select();
        if ( document.execCommand( 'copy' ) ) {
            done();
        }
    }

    document.addEventListener( 'click', function ( event ) {
        if ( event.target && event.target.classList.contains( 'token-engine-copy' ) ) {
            copyValue( event.target );
        }
    } );
}() );
