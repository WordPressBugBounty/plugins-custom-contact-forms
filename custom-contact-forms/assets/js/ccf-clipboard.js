/**
 * Custom Contact Forms — Clipboard copy for shortcodes
 * @since 7.9.0
 */
(function() {
	'use strict';

	function ccfCopyToClipboard(text, el) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function() {
				ccfShowCopied(el);
			});
		} else {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.left = '-9999px';
			document.body.appendChild(ta);
			ta.select();
			document.execCommand('copy');
			document.body.removeChild(ta);
			ccfShowCopied(el);
		}
	}

	function ccfShowCopied(el) {
		var orig = el.textContent;
		el.textContent = (window.ccfClipboardL10n && window.ccfClipboardL10n.copied) || 'Copied!';
		el.style.background = '#edfaef';
		el.style.borderColor = '#68de7c';
		setTimeout(function() {
			el.textContent = orig;
			el.style.background = '';
			el.style.borderColor = '';
		}, 1500);
	}

	document.addEventListener('click', function(e) {
		var link = e.target.closest('.ccf-copy-shortcode');
		if (link) {
			e.preventDefault();
			var sc = link.getAttribute('data-shortcode').replace(/&quot;/g, '"');
			ccfCopyToClipboard(sc, link);
		}

		var code = e.target.closest('.ccf-copy-target, .ccf-shortcode-display');
		if (code) {
			var text = code.getAttribute('data-copy') || code.textContent;
			text = text.replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
			ccfCopyToClipboard(text, code);
		}
	});
})();
