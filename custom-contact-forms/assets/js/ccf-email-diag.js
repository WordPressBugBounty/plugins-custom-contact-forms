/**
 * Custom Contact Forms — Email Diagnostics
 * @since 7.9.0
 */
(function($) {
	'use strict';

	var l10n = window.ccfEmailDiagL10n || {};

	$('#ccf-send-test-email').on('click', function() {
		var $btn = $(this), $status = $('#ccf-test-email-status');
		$btn.prop('disabled', true);
		$status.text(l10n.sending || 'Sending...').css('color', '#666');

		$.post(ajaxurl, {
			action: 'ccf_send_test_email',
			nonce: l10n.nonce || ''
		}, function(response) {
			$btn.prop('disabled', false);
			if (response.success) {
				$status.text(response.data).css('color', '#0a6b0e');
			} else {
				$status.text(response.data).css('color', '#d63638');
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$status.text(l10n.failed || 'Request failed.').css('color', '#d63638');
		});
	});

	$('#ccf-clear-email-log').on('click', function() {
		$.post(ajaxurl, {
			action: 'ccf_send_test_email',
			nonce: l10n.nonce || '',
			clear_log: 1
		}, function() {
			location.reload();
		});
	});
})(jQuery);
