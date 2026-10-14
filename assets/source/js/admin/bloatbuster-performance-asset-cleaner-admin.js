/**
 * Settings page: show child fields only while their parent toggle is on.
 *
 * Child field wrappers carry data-bbpac-parent="{parent_id}", parent toggles
 * use id="bbpac-{parent_id}". The row (tr) of each child gets bbpac-hidden.
 */
const toggleChildren = ( parent ) => {
	const parentId = parent.id.replace( 'bbpac-', '' );
	const children = document.querySelectorAll( `[data-bbpac-parent="${ parentId }"]` );

	children.forEach( ( child ) => {
		const row = child.closest( 'tr' );

		if ( row ) {
			row.classList.toggle( 'bbpac-hidden', ! parent.checked );
		}
	} );
};

document.addEventListener( 'DOMContentLoaded', () => {
	const form = document.querySelector( '#bloatbuster-performance-asset-cleaner-options form' );

	if ( ! form ) {
		return;
	}

	form.addEventListener( 'change', ( event ) => {
		if ( event.target.matches( 'input[type="checkbox"][id^="bbpac-"]' ) ) {
			toggleChildren( event.target );
		}
	} );
} );
