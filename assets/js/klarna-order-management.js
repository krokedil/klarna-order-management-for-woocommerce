jQuery( function ( $ ) {
	$( document ).ready( function () {
		const kom = {
			edit_button: $( ".kom_order_sync_edit" ),
			order_sync_box: $( ".kom_order_sync--box" ),
			toggle_button: $( ".kom_order_sync--toggle .woocommerce-input-toggle" ),
			submit_button: $( ".kom_order_sync--action > .submit_button" ),
			cancel_button: $( ".kom_order_sync--action > .cancel_button" ),
			sync_status: function () {
				return kom.toggle_button.hasClass( "woocommerce-input-toggle--enabled" ) ? "enabled" : "disabled"
			},
		}

		kom.edit_button.on( "click", function () {
			if ( "none" !== kom.edit_button.css( "display" ) ) {
				kom.order_sync_box.fadeIn()
				kom.edit_button.css( "display", "none" )
			} else {
				kom.edit_button.css( "display", "" )
				kom.order_sync_box.css( "display", "" )
			}
		} )

		kom.toggle_button.click( function () {
			const url = new URL( kom.submit_button.attr( "href" ), window.location )
			kom.toggle_button.toggleClass( "woocommerce-input-toggle--disabled woocommerce-input-toggle--enabled" )
			url.searchParams.set( "kom", kom.sync_status() )
			kom.submit_button.attr( "href", url.toString() )
		} )

		kom.cancel_button.on( "click", function () {
			kom.edit_button.click()
		} )
	} )
} )
