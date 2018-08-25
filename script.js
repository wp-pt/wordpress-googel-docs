'use strict';

(function( $ ) {

	window.bt_wpgd_get_files = function( sort_by, sort_direction ) {
		$( '.bt_wpgd_output tbody' ).html( '' );
		$( '.bt_wpgd_loader' ).show();
		var data = {
			'action': 'bt_wpgd_get_files',
			'id': window.bt_wpgd_folder,
			'sort_by': sort_by,
			'sort_direction': sort_direction
		};
		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: data,
			async: true,
			success: function( response ) {
				$( '.bt_wpgd_loader' ).hide();
				$( '.bt_wpgd_output tbody' ).html( response );
				$( '.bt_wpgd_output' ).show();
			}
		});
	}
	
	// import file
	window.bt_wpgd_import_file = function( type ) {
		
		var clear_style = $( '#bt_wpgd_clear_inline_style' ).prop( 'checked' );
		
		var selected = $( '.bt_wpgd_item_selected' );
		
		if ( selected.length > 0 ) {
			
			$( '.bt_wpgd_message' ).html( '<span class="bt_wpgd_blinker">' + $( '.bt_wpgd_wrap' ).data( 'label-importing' ) + '</span>' );
			
			var id = selected.data( 'id' );
			var name = selected.data( 'name' );
			
			var data = {
				'action': 'bt_wpgd_import_file',
				'id': id,
				'name': name,
				'type': type,
				'clear_style': clear_style
			};
			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: data,
				async: true,
				success: function( response ) {
					$( '.bt_wpgd_message' ).html( $( '.bt_wpgd_wrap' ).data( 'label-finished' ) );
				}
			});
			
		} else {
			$( '.bt_wpgd_message' ).html( $( '.bt_wpgd_wrap' ).data( 'label-not-selected' ) );
		}

	}
	
	$( document ).ready(function() {
		
		// item click
		$( '.bt_wpgd_output' ).on( 'click', '.bt_wpgd_item', function( e ) {
			var type = $( this ).data( 'type' );
			var id = $( this ).data( 'id' );
			var name = $( this ).data( 'name' );
			
			if ( type == 'folder' ) {
				window.bt_wpgd_folder = id;
				window.bt_wpgd_get_files( 'date', 'desc' );
			} else {
				$( this ).parent().find( 'tr' ).removeClass( 'bt_wpgd_item_selected' );
				$( this ).addClass( 'bt_wpgd_item_selected' );
			}
			
		});
		
		window.bt_wpgd_folder = 'root';
		
		window.bt_wpgd_get_files( 'date', 'desc' );
		
		// home
		$( '.bt_wpgd_home' ).on( 'click', function( e ) {
			window.bt_wpgd_folder = 'root';
			$( '.bt_wpgd_sort_name' ).find( 'span' ).html( '' );
			$( '.bt_wpgd_sort_name' ).data( 'sort', '' );
			$( '.bt_wpgd_sort_date' ).find( 'span' ).html( '<i class="fa fa-long-arrow-down"></i>' );
			$( '.bt_wpgd_sort_date' ).data( 'sort', 'desc' );			
			window.bt_wpgd_get_files( 'date', 'desc' );
		});
		
		// sort by name
		$( '.bt_wpgd_sort_name' ).on( 'click', function( e ) {
			var current_sort = $( this ).data( 'sort' );
			var sort = 'asc';
			if ( current_sort == 'asc' ) {
				sort = 'desc';
				$( this ).find( 'span' ).html( '<i class="fa fa-long-arrow-down"></i>' );
			} else {
				$( this ).find( 'span' ).html( '<i class="fa fa-long-arrow-up"></i>' );
			}
			$( '.bt_wpgd_sort_date' ).find( 'span' ).html( '' );
			$( '.bt_wpgd_sort_date' ).data( 'sort', '' );
			$( this ).data( 'sort', sort );
			window.bt_wpgd_get_files( 'name', sort );
		});

		// sort by date
		$( '.bt_wpgd_sort_date' ).on( 'click', function( e ) {
			var current_sort = $( this ).data( 'sort' );
			var sort = 'desc';
			if ( current_sort == 'desc' ) {
				sort = 'asc';
				$( this ).find( 'span' ).html( '<i class="fa fa-long-arrow-up"></i>' );
			} else {
				$( this ).find( 'span' ).html( '<i class="fa fa-long-arrow-down"></i>' );
			}
			$( '.bt_wpgd_sort_name' ).find( 'span' ).html( '' );
			$( '.bt_wpgd_sort_name' ).data( 'sort', '' );
			$( this ).data( 'sort', sort );
			window.bt_wpgd_get_files( 'date', sort );
		});
		
		// import page(s)
		$( '.bt_wpgd_import_page' ).on( 'click', function( e ) {
			window.bt_wpgd_import_file( 'page' );
		});
		
		// import post(s)
		$( '.bt_wpgd_import_post' ).on( 'click', function( e ) {
			window.bt_wpgd_import_file( 'post' );
		});		
		
	});
	
}( jQuery ));