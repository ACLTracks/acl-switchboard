/**
 * ACL Switchboard — Admin JavaScript
 *
 * Handles:
 *  1. AJAX connection tests for providers
 *  2. Provider type selector behavior on the "Add Provider" form
 */

(function ($) {
	'use strict';

	// Bail if our localized data isn't available.
	if (typeof aclSwitchboard === 'undefined') {
		return;
	}

	// -------------------------------------------------------------------------
	// Connection test buttons
	// -------------------------------------------------------------------------

	$(document).on('click', '.acl-test-connection-btn', function (e) {
		e.preventDefault();

		var $btn    = $(this);
		var slug    = $btn.data('provider');
		var $result = $('.acl-test-result[data-provider="' + slug + '"]');

		// Prevent double-clicks.
		if ($btn.prop('disabled')) {
			return;
		}

		$btn.prop('disabled', true);
		$result
			.text(aclSwitchboard.i18n.testing)
			.removeClass('acl-test--success acl-test--error')
			.addClass('acl-test--loading');

		$.ajax({
			url:  aclSwitchboard.ajaxUrl,
			type: 'POST',
			data: {
				action:   'acl_switchboard_test_connection',
				_ajax_nonce: aclSwitchboard.nonce,
				provider: slug
			},
			success: function (response) {
				var message = response.data && response.data.message ? response.data.message : '';

				if (response.success) {
					$result
						.text(message || aclSwitchboard.i18n.testSuccess)
						.removeClass('acl-test--loading acl-test--error')
						.addClass('acl-test--success');
				} else {
					$result
						.text(message || aclSwitchboard.i18n.testFailed)
						.removeClass('acl-test--loading acl-test--success')
						.addClass('acl-test--error');
				}
			},
			error: function () {
				$result
					.text(aclSwitchboard.i18n.testFailed)
					.removeClass('acl-test--loading acl-test--success')
					.addClass('acl-test--error');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	// -------------------------------------------------------------------------
	// Provider type selector (Add Provider page)
	// -------------------------------------------------------------------------

	$('.acl-provider-type-select').on('change', function () {
		var $select  = $(this);
		var $option  = $select.find('option:selected');
		var label    = $option.data('label') || '';
		var baseUrl  = $option.data('base-url') || '';
		var services = $option.data('services') || [];

		// Update label field if it's empty or matches the previous provider label.
		var $label = $('#label');
		if (!$label.val() || $label.data('auto-filled')) {
			$label.val(label).data('auto-filled', true);
		}

		// Track manual edits.
		$label.on('input', function () {
			$(this).data('auto-filled', false);
		});

		// Update base URL placeholder.
		$('#base_url').attr('placeholder', baseUrl || 'https://api.example.com/v1');

		// Update service checkboxes.
		if (services && services.length) {
			$('input[name="services[]"]').each(function () {
				var $cb = $(this);
				$cb.prop('checked', services.indexOf($cb.val()) !== -1);
			});
		}
	});

	// Trigger on page load to set initial state if a type is already selected.
	if ($('.acl-provider-type-select').length) {
		$('.acl-provider-type-select').trigger('change');
	}

})(jQuery);
