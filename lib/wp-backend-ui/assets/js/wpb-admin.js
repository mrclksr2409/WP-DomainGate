/**
 * WP-Backend UI — shared admin behaviours.
 *
 * Everything is opt-in via data attributes, so plugins need no JS of their own
 * for the common cases:
 *
 *   data-wpb-tabs                 on a .nav-tab-wrapper → client-side tab panels
 *   data-wpb-modal-open="id"      opens <dialog id="id" class="wpb-modal">
 *   data-wpb-modal-close          closes the surrounding dialog
 *   data-wpb-reveal="input-id"    toggles a password field between hidden/visible
 *   data-wpb-copy="text"          copies the given text
 *   data-wpb-copy-target="id"     copies the value/text of an element
 *   data-wpb-confirm="message"    asks for confirmation on click/submit
 *   data-wpb-dismiss              removes the closest .wpb-alert
 *   data-wpb-sortable="group"     sortable list (lists of one group exchange items)
 *   data-wpb-combobox             on a <select> → search field with chips
 *   data-wpb-repeater             repeatable rows from a <template>
 *   .wpb-media                    media library picker
 *
 * Public API: window.wpbAdminUI.{ toast, openModal, closeModal, setLoading, initTabs, initCombobox, init }.
 * Events: wpb:sort, wpb:repeater-add, wpb:repeater-remove.
 *
 * @package WP_Backend_UI
 */
( function () {
	'use strict';

	var i18n = Object.assign(
		{
			copied: 'Copied to clipboard.',
			copyFailed: 'Copy failed.',
			show: 'Show',
			hide: 'Hide',
			close: 'Close',
			remove: 'Remove',
			search: 'Search',
			searching: 'Searching…',
			noResults: 'No results found.',
			loadFailed: 'Could not load results.',
			reorder: 'Move (drag or use the arrow keys)',
			moved: 'Moved to position %1$d of %2$d.',
			mediaUnavailable: 'The media library is not available on this screen.',
		},
		window.wpbAdminUIL10n || {}
	);

	/* ------------------------------------------------------------------
	 * Toasts
	 * ------------------------------------------------------------------ */

	function getToastRegion() {
		var region = document.querySelector( '.wpb-toasts' );
		if ( ! region ) {
			region = document.createElement( 'div' );
			region.className = 'wpb-toasts';
			region.setAttribute( 'role', 'status' );
			region.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( region );
		}
		return region;
	}

	/**
	 * Show a short, auto-dismissing message.
	 *
	 * @param {string} message Plain text.
	 * @param {string} [type]  success|warning|error|info.
	 * @param {number} [ms]    Visible duration, 0 keeps it until clicked.
	 */
	function toast( message, type, ms ) {
		var el = document.createElement( 'div' );
		el.className = 'wpb-toast wpb-toast--' + ( type || 'info' );
		el.textContent = message;
		getToastRegion().appendChild( el );

		function remove() {
			el.classList.add( 'is-leaving' );
			setTimeout( function () {
				el.remove();
			}, 180 );
		}

		el.addEventListener( 'click', remove );
		if ( 0 !== ms ) {
			setTimeout( remove, ms || 4000 );
		}
		return el;
	}

	/* ------------------------------------------------------------------
	 * Modal
	 * ------------------------------------------------------------------ */

	function openModal( id ) {
		var dialog = document.getElementById( id );
		if ( dialog && 'function' === typeof dialog.showModal && ! dialog.open ) {
			dialog.showModal();
		}
		return dialog;
	}

	function closeModal( idOrEl ) {
		var dialog = 'string' === typeof idOrEl ? document.getElementById( idOrEl ) : idOrEl;
		if ( dialog && dialog.open ) {
			dialog.close();
		}
	}

	/* ------------------------------------------------------------------
	 * Loading state for buttons/containers
	 * ------------------------------------------------------------------ */

	function setLoading( el, isLoading ) {
		if ( ! el ) {
			return;
		}
		el.classList.toggle( 'is-loading', !! isLoading );
		el.setAttribute( 'aria-busy', isLoading ? 'true' : 'false' );
		if ( 'BUTTON' === el.tagName || 'INPUT' === el.tagName ) {
			el.disabled = !! isLoading;
		}
	}

	/* ------------------------------------------------------------------
	 * Client-side tabs
	 * ------------------------------------------------------------------ */

	function initTabs( nav ) {
		var tabs = Array.prototype.slice.call( nav.querySelectorAll( '.nav-tab[href^="#"]' ) );
		if ( ! tabs.length ) {
			return;
		}

		function activate( tab, updateHash ) {
			tabs.forEach( function ( t ) {
				var panel = document.getElementById( t.getAttribute( 'href' ).slice( 1 ) );
				var active = t === tab;
				t.classList.toggle( 'nav-tab-active', active );
				t.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				if ( panel ) {
					panel.hidden = ! active;
				}
			} );
			if ( updateHash && window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', tab.getAttribute( 'href' ) );
			}
		}

		nav.setAttribute( 'role', 'tablist' );
		tabs.forEach( function ( tab ) {
			tab.setAttribute( 'role', 'tab' );
			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				activate( tab, true );
			} );
		} );

		var fromHash = tabs.filter( function ( t ) {
			return t.getAttribute( 'href' ) === window.location.hash;
		} )[ 0 ];
		var preset = nav.querySelector( '.nav-tab-active[href^="#"]' );
		activate( fromHash || preset || tabs[ 0 ], false );
	}

	/* ------------------------------------------------------------------
	 * Clipboard
	 * ------------------------------------------------------------------ */

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			var area = document.createElement( 'textarea' );
			area.value = text;
			area.setAttribute( 'readonly', '' );
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild( area );
			area.select();
			var ok = document.execCommand( 'copy' );
			area.remove();
			( ok ? resolve : reject )();
		} );
	}

	/* ------------------------------------------------------------------
	 * Delegated handlers
	 * ------------------------------------------------------------------ */

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! ( target instanceof Element ) ) {
			return;
		}

		var confirmEl = target.closest( '[data-wpb-confirm]' );
		if ( confirmEl && 'FORM' !== confirmEl.tagName ) {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( confirmEl.getAttribute( 'data-wpb-confirm' ) ) ) {
				event.preventDefault();
				event.stopImmediatePropagation();
				return;
			}
		}

		var opener = target.closest( '[data-wpb-modal-open]' );
		if ( opener ) {
			event.preventDefault();
			openModal( opener.getAttribute( 'data-wpb-modal-open' ) );
			return;
		}

		var closer = target.closest( '[data-wpb-modal-close]' );
		if ( closer ) {
			event.preventDefault();
			closeModal( closer.closest( 'dialog' ) );
			return;
		}

		// Click on the backdrop (the dialog element itself) closes it.
		if ( 'DIALOG' === target.tagName && target.classList.contains( 'wpb-modal' ) ) {
			var rect = target.getBoundingClientRect();
			var inside = event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom;
			if ( ! inside ) {
				closeModal( target );
			}
			return;
		}

		var reveal = target.closest( '[data-wpb-reveal]' );
		if ( reveal ) {
			event.preventDefault();
			var input = document.getElementById( reveal.getAttribute( 'data-wpb-reveal' ) );
			if ( input ) {
				var hidden = 'password' === input.type;
				input.type = hidden ? 'text' : 'password';
				reveal.textContent = hidden ? i18n.hide : i18n.show;
				reveal.setAttribute( 'aria-pressed', hidden ? 'true' : 'false' );
			}
			return;
		}

		var copier = target.closest( '[data-wpb-copy], [data-wpb-copy-target]' );
		if ( copier ) {
			event.preventDefault();
			var text = copier.getAttribute( 'data-wpb-copy' );
			if ( null === text ) {
				var source = document.getElementById( copier.getAttribute( 'data-wpb-copy-target' ) );
				text = source ? ( 'value' in source ? source.value : source.textContent ) : '';
			}
			copyText( text ).then(
				function () {
					toast( i18n.copied, 'success', 2000 );
				},
				function () {
					toast( i18n.copyFailed, 'error' );
				}
			);
			return;
		}

		var dismiss = target.closest( '[data-wpb-dismiss]' );
		if ( dismiss ) {
			var alert = dismiss.closest( '.wpb-alert' );
			if ( alert ) {
				alert.remove();
			}
			return;
		}

		var repeaterAdd = target.closest( '[data-wpb-repeater-add]' );
		if ( repeaterAdd ) {
			event.preventDefault();
			addRepeaterRow( repeaterAdd.closest( '[data-wpb-repeater]' ) );
			return;
		}

		var repeaterRemove = target.closest( '[data-wpb-repeater-remove]' );
		if ( repeaterRemove ) {
			event.preventDefault();
			var row = repeaterRemove.closest( '.wpb-repeater__item' );
			var repeater = row.closest( '[data-wpb-repeater]' );
			row.remove();
			refreshRepeater( repeater );
			repeater.dispatchEvent( new CustomEvent( 'wpb:repeater-remove', { bubbles: true } ) );
			return;
		}

		var mediaSelect = target.closest( '[data-wpb-media-select]' );
		if ( mediaSelect ) {
			event.preventDefault();
			openMediaFrame( mediaSelect.closest( '.wpb-media' ) );
			return;
		}

		var mediaRemove = target.closest( '[data-wpb-media-remove]' );
		if ( mediaRemove ) {
			event.preventDefault();
			setMedia( mediaRemove.closest( '.wpb-media' ), null );
		}
	} );

	document.addEventListener(
		'submit',
		function ( event ) {
			var form = event.target;
			if ( form instanceof HTMLFormElement && form.hasAttribute( 'data-wpb-confirm' ) ) {
				// eslint-disable-next-line no-alert
				if ( ! window.confirm( form.getAttribute( 'data-wpb-confirm' ) ) ) {
					event.preventDefault();
				}
			}
		},
		true
	);

	/* ------------------------------------------------------------------
	 * Screen reader announcements
	 * ------------------------------------------------------------------ */

	function announce( message ) {
		var region = document.querySelector( '.wpb-live-region' );
		if ( ! region ) {
			region = document.createElement( 'div' );
			region.className = 'wpb-live-region wpb-visually-hidden';
			region.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( region );
		}
		region.textContent = message;
	}

	/* ------------------------------------------------------------------
	 * Sortable lists
	 * ------------------------------------------------------------------ */

	var SORT_ITEM = 'wpb-sortable__item';
	var sortDrag = null;

	function sortableItems( container ) {
		return Array.prototype.filter.call( container.children, function ( child ) {
			return child.classList.contains( SORT_ITEM );
		} );
	}

	function sortableZones( group ) {
		return Array.prototype.filter.call( document.querySelectorAll( '[data-wpb-sortable]' ), function ( zone ) {
			return zone.getAttribute( 'data-wpb-sortable' ) === group;
		} );
	}

	function sortableAccepts( container, drag ) {
		if ( ! container.hasAttribute( 'data-wpb-sortable' ) ) {
			return false;
		}
		return container === drag.from || ( '' !== drag.group && container.getAttribute( 'data-wpb-sortable' ) === drag.group );
	}

	function fireSort( item, from, oldIndex ) {
		var to = item.parentElement;
		var newIndex = sortableItems( to ).indexOf( item );
		if ( from === to && oldIndex === newIndex ) {
			return;
		}
		item.dispatchEvent(
			new CustomEvent( 'wpb:sort', {
				bubbles: true,
				detail: { item: item, from: from, to: to, oldIndex: oldIndex, newIndex: newIndex },
			} )
		);
	}

	function sortMoveTo( x, y ) {
		var drag = sortDrag;
		var under = document.elementFromPoint( x, y );
		if ( ! under ) {
			return;
		}

		// Hovering a collapsed section opens it, so items can be dropped inside.
		var closed = under.closest( 'details:not([open])' );
		if ( closed && '' !== drag.group && ! closed.contains( drag.item ) ) {
			var opens = sortableZones( drag.group ).some( function ( zone ) {
				return closed.contains( zone );
			} );
			if ( opens && drag.openTarget !== closed ) {
				clearTimeout( drag.openTimer );
				drag.openTarget = closed;
				drag.openTimer = setTimeout( function () {
					closed.open = true;
				}, 400 );
			}
		} else if ( drag.openTarget ) {
			clearTimeout( drag.openTimer );
			drag.openTarget = null;
		}

		var candidate = under.closest( '.' + SORT_ITEM );
		while ( candidate ) {
			if ( candidate === drag.item ) {
				return;
			}
			if ( sortableAccepts( candidate.parentElement, drag ) ) {
				break;
			}
			candidate = candidate.parentElement.closest( '.' + SORT_ITEM );
		}

		if ( candidate ) {
			var rect = candidate.getBoundingClientRect();
			candidate.parentElement.insertBefore( drag.item, y < rect.top + rect.height / 2 ? candidate : candidate.nextSibling );
			return;
		}

		// Empty lists have no item to hover, the list itself is the drop target.
		var zone = under.closest( '[data-wpb-sortable]' );
		while ( zone && ! sortableAccepts( zone, drag ) ) {
			zone = zone.parentElement.closest( '[data-wpb-sortable]' );
		}
		if ( zone && ! drag.item.contains( zone ) && ! sortableItems( zone ).length ) {
			zone.appendChild( drag.item );
		}
	}

	function sortAutoScroll() {
		if ( ! sortDrag ) {
			return;
		}
		var edge = 60;
		var step = 0;
		if ( sortDrag.y < edge ) {
			step = -12;
		} else if ( sortDrag.y > window.innerHeight - edge ) {
			step = 12;
		}
		if ( step ) {
			window.scrollBy( 0, step );
			sortMoveTo( sortDrag.x, sortDrag.y );
		}
		window.requestAnimationFrame( sortAutoScroll );
	}

	function sortFinish( cancelled ) {
		var drag = sortDrag;
		sortDrag = null;
		clearTimeout( drag.openTimer );
		document.body.classList.remove( 'wpb-is-sorting' );
		drag.item.classList.remove( 'is-dragging' );
		if ( cancelled ) {
			drag.from.insertBefore( drag.item, drag.next );
			return;
		}
		fireSort( drag.item, drag.from, drag.oldIndex );
	}

	document.addEventListener( 'pointerdown', function ( event ) {
		if ( 0 !== event.button || ! ( event.target instanceof Element ) ) {
			return;
		}
		var handle = event.target.closest( '.wpb-sortable__handle' );
		var item = handle ? handle.closest( '.' + SORT_ITEM ) : null;
		if ( ! item || ! item.parentElement.hasAttribute( 'data-wpb-sortable' ) ) {
			return;
		}
		event.preventDefault();
		sortDrag = {
			item: item,
			from: item.parentElement,
			next: item.nextSibling,
			group: item.parentElement.getAttribute( 'data-wpb-sortable' ),
			oldIndex: sortableItems( item.parentElement ).indexOf( item ),
			x: event.clientX,
			y: event.clientY,
			openTarget: null,
			openTimer: 0,
		};
		item.classList.add( 'is-dragging' );
		document.body.classList.add( 'wpb-is-sorting' );
		window.requestAnimationFrame( sortAutoScroll );
	} );

	document.addEventListener( 'pointermove', function ( event ) {
		if ( sortDrag ) {
			sortDrag.x = event.clientX;
			sortDrag.y = event.clientY;
			sortMoveTo( event.clientX, event.clientY );
		}
	} );

	document.addEventListener( 'pointerup', function () {
		if ( sortDrag ) {
			sortFinish( false );
		}
	} );

	document.addEventListener( 'pointercancel', function () {
		if ( sortDrag ) {
			sortFinish( true );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && sortDrag ) {
			event.preventDefault();
			sortFinish( true );
			return;
		}
		if ( ( 'ArrowUp' !== event.key && 'ArrowDown' !== event.key ) || ! ( event.target instanceof Element ) ) {
			return;
		}
		var handle = event.target.closest( '.wpb-sortable__handle' );
		var item = handle ? handle.closest( '.' + SORT_ITEM ) : null;
		if ( ! item || ! item.parentElement.hasAttribute( 'data-wpb-sortable' ) ) {
			return;
		}
		event.preventDefault();

		var from = item.parentElement;
		var items = sortableItems( from );
		var oldIndex = items.indexOf( item );
		var up = 'ArrowUp' === event.key;
		var neighbour = items[ oldIndex + ( up ? -1 : 1 ) ];

		if ( neighbour ) {
			from.insertBefore( item, up ? neighbour : neighbour.nextSibling );
		} else {
			// At the edge of a list, continue in the adjacent list of the same group.
			var group = from.getAttribute( 'data-wpb-sortable' );
			var zones = '' !== group ? sortableZones( group ) : [];
			var zone = zones[ zones.indexOf( from ) + ( up ? -1 : 1 ) ];
			if ( ! zone || item.contains( zone ) ) {
				return;
			}
			var details = zone.closest( 'details' );
			if ( details ) {
				details.open = true;
			}
			zone.insertBefore( item, up ? null : sortableItems( zone )[ 0 ] || null );
		}

		handle.focus();
		fireSort( item, from, oldIndex );
		announce(
			i18n.moved
				.replace( '%1$d', sortableItems( item.parentElement ).indexOf( item ) + 1 )
				.replace( '%2$d', sortableItems( item.parentElement ).length )
		);
	} );

	/* ------------------------------------------------------------------
	 * Combobox: enhances a native <select> with search and chips
	 * ------------------------------------------------------------------ */

	var comboboxCount = 0;

	function initCombobox( select ) {
		if ( select.closest( '.wpb-combobox' ) ) {
			return;
		}

		var multiple = select.multiple;
		var source = select.getAttribute( 'data-wpb-combobox-source' ) || '';
		var listId = 'wpb-combobox-list-' + ++comboboxCount;

		var wrap = document.createElement( 'div' );
		wrap.className = 'wpb-combobox';
		var control = document.createElement( 'div' );
		control.className = 'wpb-combobox__control';
		var input = document.createElement( 'input' );
		input.type = 'text';
		input.className = 'wpb-combobox__input';
		input.autocomplete = 'off';
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-controls', listId );
		input.setAttribute( 'aria-label', select.getAttribute( 'aria-label' ) || select.getAttribute( 'data-wpb-combobox-placeholder' ) || i18n.search );
		var list = document.createElement( 'ul' );
		list.className = 'wpb-combobox__list';
		list.id = listId;
		list.hidden = true;
		list.setAttribute( 'role', 'listbox' );
		if ( multiple ) {
			list.setAttribute( 'aria-multiselectable', 'true' );
		}

		select.parentNode.insertBefore( wrap, select );
		wrap.appendChild( select );
		wrap.appendChild( control );
		wrap.appendChild( list );
		control.appendChild( input );

		var results = [];
		var active = -1;
		var message = '';
		var timer = 0;
		var request = null;

		if ( select.disabled ) {
			wrap.classList.add( 'is-disabled' );
			input.disabled = true;
		}

		function selectedOptions() {
			return Array.prototype.filter.call( select.options, function ( option ) {
				return option.selected && '' !== option.value;
			} );
		}

		function renderChips() {
			Array.prototype.forEach.call( control.querySelectorAll( '.wpb-combobox__chip' ), function ( chip ) {
				chip.remove();
			} );
			var chosen = selectedOptions();
			chosen.forEach( function ( option ) {
				var chip = document.createElement( 'span' );
				chip.className = 'wpb-combobox__chip';
				var text = document.createElement( 'span' );
				text.textContent = option.textContent;
				chip.appendChild( text );
				if ( ! select.disabled ) {
					var remove = document.createElement( 'button' );
					remove.type = 'button';
					remove.className = 'wpb-combobox__remove';
					remove.setAttribute( 'aria-label', i18n.remove + ': ' + option.textContent );
					remove.addEventListener( 'click', function ( event ) {
						event.stopPropagation();
						choose( option.value, false );
						input.focus();
					} );
					chip.appendChild( remove );
				}
				control.insertBefore( chip, input );
			} );
			input.placeholder = chosen.length ? '' : select.getAttribute( 'data-wpb-combobox-placeholder' ) || '';
		}

		function renderList() {
			list.textContent = '';
			input.removeAttribute( 'aria-activedescendant' );
			if ( message ) {
				var note = document.createElement( 'li' );
				note.className = 'wpb-combobox__message';
				note.setAttribute( 'role', 'presentation' );
				note.textContent = message;
				list.appendChild( note );
				return;
			}
			var chosen = selectedOptions().map( function ( option ) {
				return option.value;
			} );
			results.forEach( function ( entry, index ) {
				var li = document.createElement( 'li' );
				var isChosen = chosen.indexOf( entry.value ) > -1;
				li.id = listId + '-' + index;
				li.className = 'wpb-combobox__option' + ( index === active ? ' is-active' : '' ) + ( isChosen ? ' is-selected' : '' );
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', isChosen ? 'true' : 'false' );
				li.textContent = entry.label;
				li.addEventListener( 'mousedown', function ( event ) {
					event.preventDefault();
				} );
				li.addEventListener( 'click', function () {
					choose( entry.value, ! isChosen, entry.label );
				} );
				list.appendChild( li );
				if ( index === active ) {
					input.setAttribute( 'aria-activedescendant', li.id );
					li.scrollIntoView( { block: 'nearest' } );
				}
			} );
		}

		function filterLocal() {
			var query = input.value.trim().toLowerCase();
			results = Array.prototype.filter
				.call( select.options, function ( option ) {
					return '' !== option.value && ! option.disabled && option.textContent.toLowerCase().indexOf( query ) > -1;
				} )
				.map( function ( option ) {
					return { value: option.value, label: option.textContent };
				} );
			message = results.length ? '' : i18n.noResults;
			active = results.length ? 0 : -1;
			renderList();
		}

		function fetchRemote() {
			if ( request ) {
				request.abort();
			}
			var current = new AbortController();
			request = current;
			results = [];
			active = -1;
			message = i18n.searching;
			renderList();

			fetch( source + ( source.indexOf( '?' ) > -1 ? '&' : '?' ) + 'search=' + encodeURIComponent( input.value.trim() ), {
				credentials: 'same-origin',
				signal: current.signal,
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( String( response.status ) );
					}
					return response.json();
				} )
				.then( function ( json ) {
					var rows = Array.isArray( json ) ? json : ( json && json.data ) || [];
					results = rows.map( function ( row ) {
						return { value: String( row.value ), label: String( row.label ) };
					} );
					message = results.length ? '' : i18n.noResults;
					active = results.length ? 0 : -1;
					renderList();
				} )
				.catch( function ( error ) {
					if ( 'AbortError' !== error.name ) {
						results = [];
						active = -1;
						message = i18n.loadFailed;
						renderList();
					}
				} );
		}

		function refresh() {
			if ( source ) {
				clearTimeout( timer );
				timer = setTimeout( fetchRemote, 250 );
			} else {
				filterLocal();
			}
		}

		function setOpen( open ) {
			if ( open === ! list.hidden ) {
				return;
			}
			list.hidden = ! open;
			wrap.classList.toggle( 'is-open', open );
			input.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			if ( open ) {
				if ( source ) {
					clearTimeout( timer );
					fetchRemote();
				} else {
					filterLocal();
				}
			} else {
				active = -1;
				input.value = '';
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		function choose( value, on, label ) {
			var option = Array.prototype.filter.call( select.options, function ( candidate ) {
				return candidate.value === value;
			} )[ 0 ];
			if ( ! option && on ) {
				option = new Option( label, value );
				select.appendChild( option );
			}
			if ( multiple ) {
				if ( option ) {
					option.selected = on;
				}
			} else if ( on ) {
				option.selected = true;
			} else if ( Array.prototype.some.call( select.options, function ( candidate ) {
				return '' === candidate.value;
			} ) ) {
				select.value = '';
			} else {
				select.selectedIndex = -1;
			}
			renderChips();
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			if ( multiple ) {
				input.value = '';
				if ( ! list.hidden ) {
					refresh();
				}
			} else {
				setOpen( false );
			}
		}

		control.addEventListener( 'mousedown', function ( event ) {
			if ( event.target !== input && ! select.disabled ) {
				event.preventDefault();
				input.focus();
				setOpen( true );
			}
		} );

		input.addEventListener( 'focus', function () {
			setOpen( true );
		} );

		input.addEventListener( 'click', function () {
			setOpen( true );
		} );

		input.addEventListener( 'blur', function () {
			setOpen( false );
		} );

		input.addEventListener( 'input', function () {
			setOpen( true );
			refresh();
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Backspace' === event.key && '' === input.value ) {
				var chosen = selectedOptions();
				if ( chosen.length ) {
					choose( chosen[ chosen.length - 1 ].value, false );
				}
				return;
			}
			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				event.preventDefault();
				if ( list.hidden ) {
					setOpen( true );
				} else if ( results.length ) {
					active = ( active + ( 'ArrowDown' === event.key ? 1 : -1 ) + results.length ) % results.length;
					renderList();
				}
				return;
			}
			if ( list.hidden ) {
				return;
			}
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				if ( results[ active ] ) {
					var isChosen = selectedOptions().some( function ( option ) {
						return option.value === results[ active ].value;
					} );
					choose( results[ active ].value, ! isChosen, results[ active ].label );
				}
			} else if ( 'Escape' === event.key ) {
				event.preventDefault();
				event.stopPropagation();
				setOpen( false );
			}
		} );

		renderChips();
	}

	/* ------------------------------------------------------------------
	 * Repeater
	 * ------------------------------------------------------------------ */

	function repeaterItems( repeater ) {
		var holder = repeater.querySelector( ':scope > .wpb-repeater__items' );
		return Array.prototype.filter.call( holder.children, function ( child ) {
			return child.classList.contains( 'wpb-repeater__item' );
		} );
	}

	function refreshRepeater( repeater ) {
		var count = repeaterItems( repeater ).length;
		var min = parseInt( repeater.getAttribute( 'data-wpb-repeater-min' ), 10 ) || 0;
		var max = parseInt( repeater.getAttribute( 'data-wpb-repeater-max' ), 10 ) || 0;
		var add = repeater.querySelector( ':scope > .wpb-repeater__footer [data-wpb-repeater-add]' );
		if ( add ) {
			add.disabled = max > 0 && count >= max;
		}
		repeaterItems( repeater ).forEach( function ( item ) {
			var remove = item.querySelector( ':scope > [data-wpb-repeater-remove]' );
			if ( remove ) {
				remove.disabled = count <= min;
			}
		} );
	}

	function addRepeaterRow( repeater ) {
		var template = repeater.querySelector( ':scope > template' );
		var holder = repeater.querySelector( ':scope > .wpb-repeater__items' );
		if ( ! template || ! holder ) {
			return null;
		}
		var index = parseInt( repeater.getAttribute( 'data-wpb-repeater-next' ), 10 );
		if ( isNaN( index ) ) {
			index = repeaterItems( repeater ).length;
		}
		repeater.setAttribute( 'data-wpb-repeater-next', index + 1 );

		// Rows are kept in DOM order by PHP, so indexes only need to be unique.
		var fragment = document.createElement( 'template' );
		fragment.innerHTML = template.innerHTML.replace( /__index__/g, String( index ) );
		var row = fragment.content.firstElementChild;
		holder.appendChild( fragment.content );
		initWithin( row );
		refreshRepeater( repeater );

		var focusable = row.querySelector( 'input:not([type="hidden"]):not(.wpb-combobox__input), select:not([data-wpb-combobox]), textarea' );
		if ( focusable ) {
			focusable.focus();
		}
		repeater.dispatchEvent( new CustomEvent( 'wpb:repeater-add', { bubbles: true, detail: { row: row, index: index } } ) );
		return row;
	}

	/* ------------------------------------------------------------------
	 * Media picker
	 * ------------------------------------------------------------------ */

	function setMedia( picker, attachment ) {
		var input = picker.querySelector( '.wpb-media__value' );
		var preview = picker.querySelector( '.wpb-media__preview' );
		var name = picker.querySelector( '.wpb-media__name' );
		var image = preview.querySelector( 'img' );

		input.value = attachment ? attachment.id : '';
		picker.classList.toggle( 'is-empty', ! attachment );

		var url = '';
		if ( attachment && 'image' === attachment.type ) {
			url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
		}
		image.hidden = ! url;
		if ( url ) {
			image.src = url;
		} else {
			image.removeAttribute( 'src' );
		}
		preview.hidden = ! attachment;
		name.textContent = attachment ? attachment.filename || attachment.title || '' : '';

		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function openMediaFrame( picker ) {
		if ( ! window.wp || ! window.wp.media ) {
			toast( i18n.mediaUnavailable, 'error' );
			return;
		}
		var type = picker.getAttribute( 'data-wpb-media-type' );
		var options = {
			title: picker.getAttribute( 'data-wpb-media-title' ) || '',
			multiple: false,
			button: { text: picker.getAttribute( 'data-wpb-media-button' ) || '' },
		};
		if ( type ) {
			options.library = { type: type };
		}
		var frame = window.wp.media( options );
		frame.on( 'select', function () {
			setMedia( picker, frame.state().get( 'selection' ).first().toJSON() );
		} );
		frame.open();
	}

	/* ------------------------------------------------------------------
	 * Setup
	 * ------------------------------------------------------------------ */

	function initWithin( root ) {
		Array.prototype.forEach.call( root.querySelectorAll( 'select[data-wpb-combobox]' ), initCombobox );
		Array.prototype.forEach.call( root.querySelectorAll( '.wpb-sortable__handle:not([aria-label])' ), function ( handle ) {
			handle.setAttribute( 'aria-label', i18n.reorder );
		} );
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-wpb-tabs]' ), initTabs );

		Array.prototype.forEach.call( document.querySelectorAll( '.wpb-modal__close:not([aria-label])' ), function ( btn ) {
			btn.setAttribute( 'aria-label', i18n.close );
		} );

		initWithin( document );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-wpb-repeater]' ), refreshRepeater );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	window.wpbAdminUI = {
		toast: toast,
		openModal: openModal,
		closeModal: closeModal,
		setLoading: setLoading,
		initTabs: initTabs,
		initCombobox: initCombobox,
		init: initWithin,
	};
} )();
