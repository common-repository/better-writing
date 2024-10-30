jQuery(document).ready(function () {

	jQuery('#readability_method_flesch').click(function () {
		jQuery('#readability_target').val(50)
	});
	jQuery('#readability_method_coleman').click(function () {
		jQuery('#readability_target').val(9)
	});
	jQuery('#readability_method_gunning').click(function () {
		jQuery('#readability_target').val(9)
	});
	jQuery('#readability_method_smog').click(function () {
		jQuery('#readability_target').val(9)
	});
	jQuery('#readability_method_ari').click(function () {
		jQuery('#readability_target').val(9)
	});

	jQuery('#bw_readability_meta').on('click', ".bw_more_stats", function () {
		var panel = jQuery(this).children('.bw_toggle_panel');
		if (panel.is(':hidden')) {
			panel.slideDown('200');
			jQuery(this).children('.toggle').html('-');
		} else {
			panel.slideUp('200');
			jQuery(this).children('.toggle').html('+');
		}
	});

});