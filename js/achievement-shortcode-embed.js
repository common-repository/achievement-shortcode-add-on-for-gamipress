(function ($) {

	/* copied from gamipress-shortcode-embed.js */
	function gamipress_get_selected_shortcode() {
		return $( '#select_shortcode' ).val();
	}

	function gamipress_get_attributes( shortcode ) {
		var attrs = {};
		var inputs = gamipress_get_shortcode_inputs( shortcode );

		$.each( inputs, function( index, el ) {
			var key, field, value, fields, values, i;
			var row = $(el).closest('.cmb-row');

			// Skip repeatable fields pattern
			if( row.hasClass('empty-row') && row.hasClass('hidden') ) return true;

			// Turn array of repeatable field into a comma separated values
			if( row.hasClass('cmb-repeat-row') ) {
				// Repeatable

				key = el.name.replace( shortcode + '_', '').replace('[]', '');

				key = key.split('[')[0];

				// Skip empty shortcode keys
				if( key === '' ) return true;

				// Just continue if element has not set
				if( attrs[key] === undefined ) {

					var field_name = el.name.split('[')[0];

					// Look at all fields
					fields = $(el).closest('.cmb-tbody').find('[name^="' + field_name + '"]');
					values = [];

					// Loop all fields and make an array with all values
					// Note: loop max is set to length-1 to skip pattern field
					for( i=0; i < fields.length-1; i++ ) {
						field = $(fields[i]);

						if( field.val().length )
							values.push( field.val() );
					}

					// Setup a comma-separated list of values as attribute value
					if( values.length )
						attrs[key] = values.join(',');

				}
			} else if( row.data('fieldtype') === 'multicheck' ) {
				// Multicheck

				key = el.name.replace( shortcode + '_', '').replace('[]', '');

				// Skip empty shortcode keys
				if( key === '' ) return true;

				// Look at checked fields
				fields = $(el).closest('.cmb2-checkbox-list').find('[name^="' + el.name + '"]:checked');
				values = [];

				// Loop checked fields and make an array with all values
				for( i=0; i < fields.length; i++ ) {

					field = $(fields[i]);

					if( field.val().length )
						values.push( field.val() );
				}

				// Setup a comma-separated list of values as attribute value
				if( values.length )
					attrs[key] = values.join(',');

			} else {
				// Single

				// CMB2 adds a prefix on each field, so we need to remove it, also, wee need to remove array brace for multiple fields
				key = el.name.replace( shortcode + '_', '').replace('[]', '');

				// Skip empty shortcode keys
				if( key === '' ) return true;

				// Select2 values are only accessible through jQuery val()
				value = $(el).val();

				// Turn checked status into yes or no
				if( $(el).attr('type') === 'checkbox' ) {
					value = $(el).prop('checked') ? 'yes' : 'no';
				}

				// For radio inputs, just get checked input value
				if( $(el).attr('type') === 'radio' ) {
					value = $(el).closest('.cmb2-radio-list').find('input[type="radio"]:checked').val()
				}

				if( typeof value === 'string' ) {
					// Replaces " by ' on text fields
					value = value.replace(/"/g, "'");
				}

				if (value !== '' && value !== undefined && value !== null ) {
					attrs[key] = value;
				}

			}
		});

		// Allow external functions to add their own data to the array of attrs
		var args = { attributes: attrs, inputs: inputs };

		$('#' + shortcode + '_wrapper').trigger( 'gamipress_shortcode_attributes', [ args ] );

		// TODO: gamipress_get_shortcode_attributes is deprecated since 1.4.8, just keep for backward compatibility
		$('#' + shortcode + '_wrapper').trigger( 'gamipress_get_shortcode_attributes', [ args.attributes, args.inputs ] );

		return args.attributes;
	}

	function gamipress_get_shortcode_inputs( shortcode ) {
		// Look at .cmb2-wrap to prevent get cmb2 nonce fields
		return $( 'input, select, textarea', '#' + shortcode + '_wrapper .cmb2-wrap' );
	}

	function gamipress_construct_shortcode( shortcode, attributes ) {
		var output = '[';
		output += shortcode;

		$.each( attributes, function( key, value ) {
			output += ' ' + key + '="' + value + '"';
		});

		$.trim( output );
		output += ']';

		// Allow external functions to construct their own shortcode
		var args = { output: output, shortcode: shortcode, attributes: attributes };

		$('#' + shortcode + '_wrapper').trigger( 'gamipress_construct_shortcode', [ args ] );

		return args.output;
	}

	$( '#gamipress_insert' ).on( 'click', function( e ) {
		e.preventDefault();
		var shortcode = gamipress_get_selected_shortcode();

		if (shortcode != 'user_earned_achievement')
			return;

		e.stopImmediatePropagation();
		var attributes = gamipress_get_attributes( shortcode );
		var constructed = gamipress_construct_shortcode( shortcode, attributes );
		var editor = $('#' + wpActiveEditor);

		editor.surroundSelectedText(constructed, "[/" + shortcode + "]");
		tb_remove();
	});

}(jQuery));