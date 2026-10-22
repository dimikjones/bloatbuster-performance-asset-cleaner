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

/**
 * Confirmation dialog for actions that change or delete data.
 *
 * ask() resolves to true when the user confirms. Escape, Cancel and a click
 * outside the dialog resolve to false. Falls back to window.confirm() when
 * the browser has no dialog element.
 */
const confirmDialog = {
	element: null,
	resolve: null,

	init() {
		this.element = document.getElementById( 'bbpac-confirm-dialog' );

		if ( ! this.element || 'function' !== typeof this.element.showModal ) {
			this.element = null;
			return;
		}

		this.element.querySelector( '.bbpac-confirm-cancel' ).addEventListener( 'click', () => this.element.close( 'cancel' ) );
		this.element.querySelector( '.bbpac-confirm-ok' ).addEventListener( 'click', () => this.element.close( 'confirm' ) );

		// The dialog has no padding, so a click on the dialog element itself is a click on the backdrop.
		this.element.addEventListener( 'click', ( event ) => {
			if ( event.target === this.element ) {
				this.element.close( 'cancel' );
			}
		} );

		this.element.addEventListener( 'close', () => {
			if ( this.resolve ) {
				this.resolve( 'confirm' === this.element.returnValue );
				this.resolve = null;
			}
		} );
	},

	ask( { title, message, confirmLabel, destructive = true } ) {
		if ( ! this.element ) {
			return Promise.resolve( window.confirm( `${ title }\n\n${ message }` ) );
		}

		const okButton = this.element.querySelector( '.bbpac-confirm-ok' );

		this.element.querySelector( '.bbpac-confirm-title' ).textContent = title;
		this.element.querySelector( '.bbpac-confirm-message' ).textContent = message;
		okButton.textContent = confirmLabel;
		okButton.classList.toggle( 'bbpac-button-danger', destructive );

		this.element.returnValue = '';
		this.element.showModal();

		// Cancel is the safe default for actions that can't be undone.
		this.element.querySelector( '.bbpac-confirm-cancel' ).focus();

		return new Promise( ( resolve ) => {
			this.resolve = resolve;
		} );
	},
};

/**
 * Submit a form again after confirmation, keeping the clicked button's name and value.
 *
 * @param {HTMLFormElement}        form      - Form to submit.
 * @param {HTMLButtonElement|null} submitter - Button that submitted the form.
 */
const submitConfirmed = ( form, submitter ) => {
	form.dataset.bbpacConfirmed = '1';

	if ( 'function' === typeof form.requestSubmit ) {
		form.requestSubmit( submitter || undefined );
		return;
	}

	// Older browsers: form.submit() doesn't send the button, add its value as a hidden field.
	if ( submitter && submitter.name ) {
		const input = document.createElement( 'input' );

		input.type = 'hidden';
		input.name = submitter.name;
		input.value = submitter.value;
		form.appendChild( input );
	}

	form.submit();
};

const confirmBulkAction = async ( event ) => {
	const form = event.target;

	if ( form.dataset.bbpacConfirmed ) {
		delete form.dataset.bbpacConfirmed;
		return;
	}

	const submitter = event.submitter;
	const action = getBulkAction( form, submitter );
	const selected = form.querySelectorAll( 'input[name="option_names[]"]:checked' ).length;

	if ( 0 === selected || ( 'delete' !== action && 'disable' !== action ) ) {
		return;
	}

	event.preventDefault();

	const isDelete = 'delete' === action;
	const count = bbpacData.i18n.selectedCount.replace( '%d', selected );

	const confirmed = await confirmDialog.ask( {
		title: isDelete ? bbpacData.i18n.bulkDeleteTitle : bbpacData.i18n.bulkDisableTitle,
		message: `${ count } ${ isDelete ? bbpacData.i18n.bulkDeleteMessage : bbpacData.i18n.bulkDisableMessage }`,
		confirmLabel: isDelete ? bbpacData.i18n.deleteLabel : bbpacData.i18n.disableLabel,
		destructive: isDelete,
	} );

	if ( confirmed ) {
		submitConfirmed( form, submitter );
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
	confirmDialog.init();

	const autoloadForm = document.getElementById( 'bbpac-autoload-form' );

	if ( ! autoloadForm ) {
		return;
	}

	valueDialog.init();

	autoloadForm.addEventListener( 'submit', confirmBulkAction );

	autoloadForm.addEventListener( 'click', async ( event ) => {
		const viewButton = event.target.closest( '.bbpac-view-value' );
		const deleteLink = event.target.closest( '.bbpac-delete-option' );

		if ( viewButton && valueDialog.element ) {
			event.preventDefault();
			valueDialog.open( viewButton.dataset.option );
		}

		if ( deleteLink ) {
			event.preventDefault();

			const row = deleteLink.closest( 'tr' );
			const nameElement = row ? row.querySelector( '.bbpac-option-name' ) : null;
			const name = nameElement ? nameElement.textContent : '';

			const confirmed = await confirmDialog.ask( {
				title: bbpacData.i18n.rowDeleteTitle,
				message: bbpacData.i18n.rowDeleteMessage.replace( '%s', name ),
				confirmLabel: bbpacData.i18n.deleteLabel,
			} );

			if ( confirmed ) {
				window.location.href = deleteLink.href;
			}
		}
	} );
} );

/**
 * Ask for confirmation before action buttons that delete data, e.g. in the Database tab.
 */
document.addEventListener( 'click', async ( event ) => {
	const button = event.target.closest( '[data-bbpac-confirm]' );

	if ( ! button || ! button.form ) {
		return;
	}

	event.preventDefault();

	const confirmed = await confirmDialog.ask( {
		title: button.dataset.bbpacConfirmTitle,
		message: button.dataset.bbpacConfirm,
		confirmLabel: button.dataset.bbpacConfirmLabel,
	} );

	if ( confirmed ) {
		submitConfirmed( button.form, button );
	}
} );
