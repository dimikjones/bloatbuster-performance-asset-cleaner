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

/**
 * Autoloaded Options tab: confirmations and the value dialog.
 *
 * Option names and values are only written with textContent.
 */
const bbpacData = window.bbpacAdmin || { i18n: {} };

const getBulkAction = ( form, submitter ) => {
	const name = submitter && 'doaction2' === submitter.id ? 'bbpac_bulk_action2' : 'bbpac_bulk_action';
	const select = form.querySelector( `select[name="${ name }"]` );

	return select ? select.value : '-1';
};

const confirmBulkAction = ( event ) => {
	const form = event.target;
	const action = getBulkAction( form, event.submitter );
	const selected = form.querySelectorAll( 'input[name="option_names[]"]:checked' ).length;

	if ( 0 === selected ) {
		return;
	}

	if ( 'delete' === action && ! window.confirm( `${ bbpacData.i18n.confirmDelete } (${ selected })` ) ) {
		event.preventDefault();
	}

	if ( 'disable' === action && ! window.confirm( `${ bbpacData.i18n.confirmDisable } (${ selected })` ) ) {
		event.preventDefault();
	}
};

const valueDialog = {
	element: null,
	name: '',

	init() {
		this.element = document.getElementById( 'bbpac-value-dialog' );

		if ( ! this.element ) {
			return;
		}

		this.element.querySelector( '.bbpac-value-close' ).addEventListener( 'click', () => this.element.close() );
		this.element.querySelector( '.bbpac-value-reveal' ).addEventListener( 'click', () => this.load( true ) );
		this.element.querySelector( '.bbpac-value-copy' ).addEventListener( 'click', () => this.copy() );
	},

	open( name ) {
		this.name = name;
		this.element.querySelector( '.bbpac-value-name' ).textContent = name;
		this.element.showModal();
		this.load( false );
	},

	setContent( { meta = '', notice = '', value = '', canReveal = false } ) {
		const noticeElement = this.element.querySelector( '.bbpac-value-notice' );

		this.element.querySelector( '.bbpac-value-meta' ).textContent = meta;
		this.element.querySelector( '.bbpac-value-content' ).textContent = value;
		this.element.querySelector( '.bbpac-value-reveal' ).hidden = ! canReveal;
		noticeElement.textContent = notice;
		noticeElement.hidden = '' === notice;
	},

	async load( reveal ) {
		const url = new URL( bbpacData.valueUrl );

		url.searchParams.set( 'name', this.name );

		if ( reveal ) {
			url.searchParams.set( 'reveal', '1' );
		}

		this.setContent( { value: bbpacData.i18n.loading } );

		try {
			const response = await fetch( url.toString(), {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': bbpacData.nonce },
			} );
			const data = await response.json();

			if ( ! response.ok ) {
				this.setContent( { notice: data.message || bbpacData.i18n.loadError } );
				return;
			}

			const notices = [];

			if ( data.masked ) {
				notices.push( bbpacData.i18n.masked );
			}

			if ( data.truncated ) {
				notices.push( bbpacData.i18n.truncated );
			}

			if ( data.binary ) {
				notices.push( bbpacData.i18n.binary );
			}

			this.setContent( {
				meta: `${ data.format } · ${ data.size } B · ${ data.autoload }`,
				notice: notices.join( ' ' ),
				value: data.value,
				canReveal: data.masked,
			} );
		} catch ( error ) {
			this.setContent( { notice: bbpacData.i18n.loadError } );
		}
	},

	async copy() {
		const button = this.element.querySelector( '.bbpac-value-copy' );
		const value = this.element.querySelector( '.bbpac-value-content' ).textContent;

		try {
			await navigator.clipboard.writeText( value );
			button.textContent = bbpacData.i18n.copied;
			window.setTimeout( () => {
				button.textContent = bbpacData.i18n.copy;
			}, 1500 );
		} catch ( error ) {
			// Clipboard not available, the value can still be selected by hand.
		}
	},
};

document.addEventListener( 'DOMContentLoaded', () => {
	const autoloadForm = document.getElementById( 'bbpac-autoload-form' );

	if ( ! autoloadForm ) {
		return;
	}

	valueDialog.init();

	autoloadForm.addEventListener( 'submit', confirmBulkAction );

	autoloadForm.addEventListener( 'click', ( event ) => {
		const viewButton = event.target.closest( '.bbpac-view-value' );
		const deleteLink = event.target.closest( '.bbpac-delete-option' );

		if ( viewButton && valueDialog.element ) {
			event.preventDefault();
			valueDialog.open( viewButton.dataset.option );
		}

		if ( deleteLink && ! window.confirm( bbpacData.i18n.confirmRow ) ) {
			event.preventDefault();
		}
	} );
} );
