/* globals jQuery:true, ajaxurl:true, cp_ddp:true  */
jQuery(document).ready(function ($) {

	var startTime;
	var interval;
	var redirTable = null;
	var selectedRedirectIds = {};
	var redirectSelectionLock = false;

	function redirectIdCount() {
		return Object.keys(selectedRedirectIds).length;
	}

	function updateRedirectSelectionUi() {
		var count = redirectIdCount();
		$('#ddp-delete-selected-redirects').prop('disabled', count === 0);
		$('#ddp-redirect-selection-count').text(
			count ? cp_ddp.redirectSelectedCount.replace('%d', String(count)) : ''
		);

		if (!redirTable) {
			return;
		}

		var pageIds = [];
		var pageSelected = 0;
		redirTable.rows({ page: 'current' }).every(function () {
			var id = parseInt(this.data().ID, 10);
			var selected = !!(id && selectedRedirectIds[id]);
			pageIds.push(id);
			if (selected) {
				pageSelected += 1;
			}
			jQuery(this.node())
				.attr('aria-selected', selected ? 'true' : 'false')
				.find('.ddp-row-checkbox')
				.prop('checked', selected);
		});

		var $pageToggle = $('#ddp-redirect-select-page');
		if ($pageToggle.length) {
			$pageToggle.prop('checked', pageIds.length > 0 && pageSelected === pageIds.length);
			$pageToggle.prop('indeterminate', pageSelected > 0 && pageSelected < pageIds.length);
		}
	}

	function applyRedirectSelectionToPage() {
		if (!redirTable || redirectSelectionLock) {
			return;
		}
		redirectSelectionLock = true;
		redirTable.rows({ page: 'current' }).every(function () {
			var id = parseInt(this.data().ID, 10);
			var shouldSelect = !!(id && selectedRedirectIds[id]);
			if (shouldSelect === this.selected()) {
				return;
			}
			if (shouldSelect) {
				this.select();
			} else {
				this.deselect();
			}
		});
		redirectSelectionLock = false;
		updateRedirectSelectionUi();
	}

	function clearRedirectSelection() {
		selectedRedirectIds = {};
		if (redirTable) {
			redirectSelectionLock = true;
			redirTable.rows().deselect();
			redirectSelectionLock = false;
		}
		updateRedirectSelectionUi();
	}
	var deleteDialogLastFocus = null;

	// Persist dismissal of plugin admin notices when the user clicks the native WP close button.
	$(document).on('click', '.ddp-dismissible-notice .notice-dismiss', function () {
		var key = $(this).closest('.ddp-dismissible-notice').data('ddp-dismiss');
		if (!key) {
			return;
		}
		$.post(ajaxurl, {
			action: 'ddp_dismiss_notice',
			notice: key,
			_ajax_nonce: cp_ddp.dismiss_notice_nonce
		});
	});

	ddp_refresh_log();

	$(document).on('submit', '#ddp_reactivate', function (e) {
		var $submit = $('#ddp_reactivate_submit');
		var message = $submit.data('confirm') || '';
		if (message && !window.confirm(message)) {
			e.preventDefault();
			return false;
		}
		announceStatus(message);
		return true;
	});

	$(document).on('submit', '#ddp_clearlog', function (e) {
		var message = $(this).find('[data-confirm]').data('confirm') || '';
		if (message && !window.confirm(message)) {
			e.preventDefault();
			return false;
		}
		return true;
	});

	/**
	 * Lazy-init redirects DataTable only when the premium table exists.
	 */
	function initRedirectsTable() {
		if (redirTable || !$('#ddp_redirtable').length) {
			return;
		}

		redirTable = jQuery('#ddp_redirtable').DataTable({
			"processing": true,
			"serverSide": true,
			"autoWidth": false,
			"ordering": true,
			"ajax": {
				"url": ajaxurl,
				"type": "POST",
				"data": function(d) {
					return jQuery.extend({}, d, {
						"action": "ddp_get_redirects",
						"_ajax_nonce": cp_ddp.nonce
					});
				},
				"dataSrc": function (json) {
					return json.data;
				}
			},
			"columns": [
				{
					"data": null,
					"title": '<input type="checkbox" id="ddp-redirect-select-page" aria-label="' + String(cp_ddp.redirectSelectPage).replace(/"/g, '&quot;') + '">',
					"defaultContent": '',
					"className": 'ddp-select-cell',
					"orderable": false,
					"searchable": false,
					"render": function (data, type, row) {
						if (type !== 'display') {
							return '';
						}
						var label = row.from_url ? String(row.from_url) : String(row.ID || '');
						return jQuery('<input/>', {
							type: 'checkbox',
							class: 'ddp-row-checkbox',
							'aria-label': cp_ddp.redirectSelectRedirect.replace('%s', label)
						})[0].outerHTML;
					}
				},
				{ "data": "ID", "visible": false },
				{ "data": "from_url", "title": cp_ddp.fromUrlTitle },
				{ "data": "target_url", "title": cp_ddp.targetUrlTitle }
			],
			"select": {
				style: 'multi',
				selector: 'td.ddp-select-cell',
				info: false
			},
			"dom": '<"tablenav top ddp-table-toolbar"<"alignleft actions"lf><"alignright"B>>rt<"tablenav bottom ddp-table-footer"ip>',
			"buttons": [
				{
					text: cp_ddp.refreshText,
					action: function ( e, dt, node ) {
						var $button = $(node);
						$button.prop('disabled', true).text(cp_ddp.refreshingText);
						dt.ajax.reload(function() {
							$button.prop('disabled', false).text(cp_ddp.refreshText);
						});
					},
					className: 'button button-secondary button-small'
				}
			],
			"pageLength": 10,
			"rowCallback": function (row) {
				var $row = jQuery(row);
				var $cells = $row.children('td');
				$row
					.addClass('wp-list-table widefat fixed striped table-view-list')
					.attr('aria-selected', 'false');
				$cells.eq(0).attr('data-label', cp_ddp.redirectSelectPage);
				$cells.eq(1).attr('data-label', cp_ddp.fromUrlTitle);
				$cells.eq(2).attr('data-label', cp_ddp.targetUrlTitle);
			},
			"lengthMenu": [[10, 25, 50, 100, 250, 500], [10, 25, 50, 100, 250, 500]]
		});

		redirTable.on('error.dt', function (e, settings, techNote, message) {
			e.preventDefault();
			var errorDetails = cp_ddp.errorDetailsText + message + (techNote ? ' (Tech note: ' + techNote + ')' : '');
			setRedirectFeedback(cp_ddp.redirectsErrorText + errorDetails, true);
		});

		redirTable.on('select', function (e, dt, type, indexes) {
			if (redirectSelectionLock || type !== 'row') {
				return;
			}
			dt.rows(indexes).every(function () {
				var id = parseInt(this.data().ID, 10);
				if (id) {
					selectedRedirectIds[id] = true;
				}
			});
			updateRedirectSelectionUi();
		});

		redirTable.on('deselect', function (e, dt, type, indexes) {
			if (redirectSelectionLock || type !== 'row') {
				return;
			}
			dt.rows(indexes).every(function () {
				var id = parseInt(this.data().ID, 10);
				if (id) {
					delete selectedRedirectIds[id];
				}
			});
			updateRedirectSelectionUi();
		});

		redirTable.on('draw', function () {
			if (redirectSelectionLock) {
				return;
			}
			applyRedirectSelectionToPage();
		});

	}

	if ($('#ddp_redirtable').length && $('#redirects-tab').is(':visible')) {
		initRedirectsTable();
	}

	$(document).on('click', 'a[href="#redirects-tab"]', function () {
		window.setTimeout(initRedirectsTable, 0);
	});

	$(document).on('click', '.ddp-tab-link', function (e) {
		var target = $(this).attr('href');
		var $tab = $('.nav-tab-wrapper a[href="' + target + '"]');
		if (!$tab.length) {
			return;
		}

		e.preventDefault();
		$tab.trigger('click');
		document.querySelector('.nav-tab-wrapper').scrollIntoView({ behavior: 'smooth', block: 'start' });
	});

	function setRedirectFeedback(message, isError) {
		var $feedback = $('#ddp-redirect-action-feedback');
		if (!$feedback.length) {
			announceStatus(message);
			return;
		}
		$feedback
			.toggleClass('is-error', !!isError)
			.toggleClass('is-success', !isError && !!message)
			.text(message || '');
		announceStatus(message || '');
	}

	function getSelectedRedirectIds() {
		return Object.keys(selectedRedirectIds).map(function (id) {
			return parseInt(id, 10);
		}).filter(function (id) {
			return id > 0;
		});
	}

	function setRedirectActionBusy($button, busy, busyText) {
		if (!$button || !$button.length) {
			return;
		}
		if (busy) {
			if (!$button.data('ddp-label')) {
				$button.data('ddp-label', $button.text());
			}
			$button.prop('disabled', true).attr('aria-busy', 'true').text(busyText || $button.text());
		} else {
			$button.prop('disabled', false).attr('aria-busy', 'false');
			if ($button.data('ddp-label')) {
				$button.text($button.data('ddp-label'));
			}
		}
	}

	$(document).on('change', '#ddp_redirects', function () {
		var enabled = $(this).is(':checked');
		$('#ddp-redirect-provider-wrap').prop('hidden', !enabled);
	});

	function activateSettingsPanel($button, moveFocus) {
		var panelId = $button.data('ddp-settings-panel');
		var $buttons = $('.ddp-settings-nav [role="tab"]');

		$buttons
			.removeClass('is-active')
			.attr({ 'aria-selected': 'false', 'tabindex': '-1' });
		$button
			.addClass('is-active')
			.attr({ 'aria-selected': 'true', 'tabindex': '0' });
		$('.ddp-settings-panel').prop('hidden', true);
		$('#' + panelId).prop('hidden', false);
		$('#delete_duplicate_posts_options').prop('hidden', panelId === 'ddp-settings-maintenance');

		if (moveFocus) {
			$button.trigger('focus');
		}
	}

	$(document).on('click', '.ddp-settings-nav [role="tab"]', function () {
		activateSettingsPanel($(this), false);
	});

	$(document).on('keydown', '.ddp-settings-nav [role="tab"]', function (event) {
		if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) === -1) {
			return;
		}

		event.preventDefault();
		var $buttons = $('.ddp-settings-nav [role="tab"]');
		var currentIndex = $buttons.index(this);
		var nextIndex = currentIndex;
		if (event.key === 'Home') {
			nextIndex = 0;
		} else if (event.key === 'End') {
			nextIndex = $buttons.length - 1;
		} else if (event.key === 'ArrowRight') {
			nextIndex = (currentIndex + 1) % $buttons.length;
		} else {
			nextIndex = (currentIndex - 1 + $buttons.length) % $buttons.length;
		}
		activateSettingsPanel($buttons.eq(nextIndex), true);
	});

	function updateAutomationFields() {
		var scheduleEnabled = $('#ddp_enabled').is(':checked');
		var emailEnabled = scheduleEnabled && $('#ddp_statusmail').is(':checked');

		$('.ddp-schedule-dependent').prop('hidden', !scheduleEnabled);
		$('.ddp-email-dependent').prop('hidden', !emailEnabled);
	}

	$(document).on('change', '#ddp_enabled, #ddp_statusmail', updateAutomationFields);
	updateAutomationFields();

	$(document).on('change input', '#delete_duplicate_posts_options :input', function () {
		$('.ddp-settings-dirty').text(cp_ddp.unsavedSettingsText);
	});

	$(document).on('submit', '#delete_duplicate_posts_options', function () {
		$('.ddp-settings-dirty').text('');
	});

	$(document).on('click', '#ddp-redirect-select-page', function (event) {
		event.stopPropagation();
	});

	$(document).on('change', '#ddp-redirect-select-page', function () {
		if (!redirTable) {
			return;
		}
		var checked = $(this).is(':checked');
		redirTable.rows({ page: 'current' }).every(function () {
			var id = parseInt(this.data().ID, 10);
			if (!id) {
				return;
			}
			if (checked) {
				selectedRedirectIds[id] = true;
			} else {
				delete selectedRedirectIds[id];
			}
		});
		applyRedirectSelectionToPage();
	});

	$(document).on('click', '#ddp-select-none-redirects', function (event) {
		event.preventDefault();
		clearRedirectSelection();
	});

	$(document).on('click', '#ddp-select-all-redirects', function (event) {
		event.preventDefault();
		if (!redirTable) {
			initRedirectsTable();
		}
		if (!redirTable) {
			return;
		}

		var $button = $(this);
		var search = redirTable.search();
		setRedirectActionBusy($button, true, cp_ddp.redirectSelectAllWorking);

		$.post(ajaxurl, {
			action: 'ddp_list_redirect_ids',
			_ajax_nonce: cp_ddp.nonce,
			search: search
		}).done(function (response) {
			var data = response && response.success ? response.data : null;
			if (!data || !data.ids) {
				setRedirectFeedback(cp_ddp.redirectSelectAllFailed, true);
				return;
			}
			selectedRedirectIds = {};
			data.ids.forEach(function (id) {
				id = parseInt(id, 10);
				if (id) {
					selectedRedirectIds[id] = true;
				}
			});
			applyRedirectSelectionToPage();
			if (data.truncated) {
				setRedirectFeedback(cp_ddp.redirectSelectAllTruncated.replace('%d', String(data.ids.length)), true);
			} else {
				setRedirectFeedback('', false);
			}
		}).fail(function () {
			setRedirectFeedback(cp_ddp.redirectSelectAllFailed, true);
		}).always(function () {
			setRedirectActionBusy($button, false);
		});
	});

	$(document).on('click', '#ddp-delete-selected-redirects', function (e) {
		e.preventDefault();
		var $button = $(this);
		var ids = getSelectedRedirectIds();
		var lastFocus = document.activeElement;

		if (!ids.length) {
			setRedirectFeedback(cp_ddp.redirectSelectNone, true);
			return;
		}

		if (!window.confirm(cp_ddp.redirectSelectedCount.replace('%d', String(ids.length)) + '\n\n' + cp_ddp.redirectDeleteConfirm)) {
			if (lastFocus && lastFocus.focus) {
				lastFocus.focus();
			}
			return;
		}

		setRedirectActionBusy($button, true, cp_ddp.redirectDeleteWorking);
		setRedirectFeedback(cp_ddp.redirectDeleteWorking, false);

		$.post(ajaxurl, {
			action: 'ddp_delete_redirects',
			_ajax_nonce: cp_ddp.nonce,
			checked_posts: ids
		}).done(function (response) {
			var deleted = response && response.data && typeof response.data.deleted !== 'undefined'
				? parseInt(response.data.deleted, 10)
				: 0;
			if (response && response.success) {
				clearRedirectSelection();
				setRedirectFeedback(cp_ddp.redirectDeleteDone.replace('%d', String(deleted)), false);
				if (redirTable) {
					redirTable.ajax.reload(null, false);
				}
			} else {
				var errorMessage = (response && response.data) ? response.data : cp_ddp.redirectActionFailed;
				setRedirectFeedback(errorMessage, true);
			}
		}).fail(function () {
			setRedirectFeedback(cp_ddp.redirectActionFailed, true);
		}).always(function () {
			setRedirectActionBusy($button, false);
			if (lastFocus && lastFocus.focus) {
				lastFocus.focus();
			}
		});
	});

	$(document).on('click', '#ddp-move-builtin-redirects', function (e) {
		e.preventDefault();
		var $button = $(this);
		var nonce = $button.data('nonce') || cp_ddp.redirectMoveNonce;
		var lastFocus = document.activeElement;

		setRedirectActionBusy($button, true, cp_ddp.redirectMoveWorking);
		setRedirectFeedback(cp_ddp.redirectMoveWorking, false);

		$.post(ajaxurl, {
			action: 'ddp_preview_move_redirects',
			nonce: nonce
		}).done(function (previewResponse) {
			var preview = previewResponse && previewResponse.success ? previewResponse.data : null;
			var confirmText = cp_ddp.redirectMoveConfirm;

			if (preview) {
				confirmText = cp_ddp.redirectMovePreview
					.replace('%1$d', String(preview.would_move || 0))
					.replace('%2$d', String(preview.would_remove || 0));
			}

			if (!window.confirm(confirmText)) {
				setRedirectFeedback('', false);
				setRedirectActionBusy($button, false);
				if (lastFocus && lastFocus.focus) {
					lastFocus.focus();
				}
				return;
			}

			$.post(ajaxurl, {
				action: 'ddp_move_builtin_redirects',
				nonce: nonce
			}).done(function (moveResponse) {
				var result = moveResponse && moveResponse.success ? moveResponse.data : null;
				if (!result) {
					setRedirectFeedback(cp_ddp.redirectActionFailed, true);
					return;
				}
				var remaining = parseInt(result.remaining, 10) || 0;
				setRedirectFeedback(
					cp_ddp.redirectMoveDone
						.replace('%1$d', String(result.moved || 0))
						.replace('%2$d', String(result.removed || 0))
						.replace('%3$d', String(result.failed || 0)),
					!!(result.failed && result.failed > 0)
				);
				if (remaining > 0) {
					var label = cp_ddp.redirectMoveButton.replace('%d', String(remaining));
					$button.data('ddp-label', label).text(label);
				} else {
					$button.remove();
					$('#ddp-builtin-leftovers').remove();
				}
				if (redirTable) {
					redirTable.ajax.reload(function () {
						var info = redirTable.page.info();
						var $count = $('#ddp-managed-records-count');
						if ($count.length) {
							$count.text(String(info.recordsTotal));
						}
					}, false);
				}
			}).fail(function () {
				setRedirectFeedback(cp_ddp.redirectActionFailed, true);
			}).always(function () {
				setRedirectActionBusy($button, false);
				if (lastFocus && lastFocus.focus) {
					lastFocus.focus();
				}
			});
		}).fail(function () {
			setRedirectFeedback(cp_ddp.redirectActionFailed, true);
			setRedirectActionBusy($button, false);
			if (lastFocus && lastFocus.focus) {
				lastFocus.focus();
			}
		});
	});

	var table = jQuery('#ddp_dupetable').DataTable({
		"select": {
			style: 'multi',
			selector: 'td.ddp-select-cell'
		},
		"autoWidth": true,
		"processing": true,
		language: {
			processing: '<div id="processingMessage">' + cp_ddp.processingMessage + '</div>'
		},
		"serverSide": true,
		"searching": false,
		"ordering": false,
		"dom": '<"tablenav top ddp-table-toolbar"<"alignleft actions ddp-table-length"l><"alignleft actions ddp-table-actions">>rt<"tablenav bottom ddp-table-footer"ip>',
		"ajax": {
			"url": ajaxurl,
			"type": "POST",
			"data": function (d) {
				return jQuery.extend({}, d, {
					"action": "ddp_get_duplicates",
					"_ajax_nonce": cp_ddp.nonce
				});
			},
			"dataSrc": function (json) {
				if (json.error) {
					jQuery("#ddp-dashboard .errormessage").html(json.error).show();
					return [];
				}
				return json.data;
			},
			"beforeSend": function () {
				startTime = new Date().getTime();
				jQuery('#requestTime').text(cp_ddp.requestTimeText);
				interval = setInterval(updateTime, 1000);
				jQuery("#ddp_dupetable_wrapper .ddp-table-buttons .button").prop('disabled', true);
				jQuery('#ddp_dupetable tbody').css('opacity', '0.5');
			},
			"complete": function () {
				var elapsedTime = (new Date().getTime() - startTime) / 1000;
				clearInterval(interval);
				jQuery('#requestTime').text(cp_ddp.requestTimeText + elapsedTime.toFixed(1) + ' sec.');
				jQuery('#ddp_dupetable tbody').css('opacity', '1');
				jQuery("#ddp_dupetable_wrapper .ddp-table-buttons .button:not(.ddp-delete-selected)").prop('disabled', false);
				ddp_refresh_log();
			},
			"error": function (jqXHR, textStatus, errorThrown) {
				console.error('AJAX error:', textStatus, errorThrown);
				jQuery("#ddp-dashboard .errormessage").text(cp_ddp.failedToLoadDataText).show();
				return [];
			}
		},
		"columns": [
			{
				"data": null,
				"title": '',
				"defaultContent": '',
				"className": 'ddp-select-cell',
				"orderable": false,
				"render": function (data, type, row) {
					if (type !== 'display') {
						return '';
					}
					var title = row.title ? stripHtml(row.title) : stripHtml(row.duplicate);
					return jQuery('<input/>', {
						type: 'checkbox',
						class: 'ddp-row-checkbox',
						'aria-label': cp_ddp.selectDuplicateText.replace('%s', title)
					})[0].outerHTML;
				}
			},
			{ "data": "ID", "visible": false },
			{ "data": "orgID", "visible": false },
			{ "data": "duplicate", "title": cp_ddp.duplicateTitle, "className": 'ddp-remove-cell', "orderable": false },
			{ "data": "original", "title": cp_ddp.originalTitle, "className": 'ddp-keep-cell', "orderable": false }
		],
		"rowCallback": function (row) {
			jQuery(row)
				.addClass('wp-list-table widefat fixed striped table-view-list')
				.attr('aria-selected', 'false');
		},
		"lengthMenu": [[10, 25, 50, 100, 250, 500], [10, 25, 50, 100, 250, 500]]
	});

	table.on('error.dt', function (e, settings, techNote, message) {
		e.preventDefault();
		var errorDetails = cp_ddp.errorDetailsText + message + (techNote ? ' (Tech note: ' + techNote + ')' : '');
		jQuery("#ddp-dashboard .errormessage").html(cp_ddp.dataTablesErrorText + errorDetails).show();
	});

	$.fn.dataTable.ext.errMode = 'none';

	var buttonsDiv = createButtons();
	buttonsDiv.appendTo('#ddp_dupetable_wrapper .ddp-table-actions');

	function refreshTable() {
		table.rows().deselect();
		table.ajax.reload();
	}

	function stripHtml(html) {
		var tmp = document.createElement('div');
		tmp.innerHTML = html || '';
		var text = tmp.textContent || tmp.innerText || '';
		tmp.innerHTML = text;
		return (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
	}

	function announceStatus(message, type) {
		$('#ddp-action-status').text(message || '');
		$('#ddp-operation-feedback')
			.prop('hidden', !message)
			.toggleClass('is-success', type === 'success')
			.toggleClass('is-error', type === 'error')
			.text(message || '');
	}

	function updateSelectionState() {
		var count = table.rows({ selected: true }).count();
		var message = (count === 1 ? cp_ddp.selectedSingularText : cp_ddp.selectedPluralText)
			.replace('%d', String(count));

		$('#ddp-selection-count').text(message);
		$('#ddp_dupetable_wrapper .ddp-delete-selected').prop('disabled', count === 0);

		table.rows({ page: 'current' }).every(function () {
			var selected = this.selected();
			jQuery(this.node())
				.attr('aria-selected', selected ? 'true' : 'false')
				.find('.ddp-row-checkbox')
				.prop('checked', selected);
		});
	}

	function closeDeleteDialog() {
		var $dialog = $('#ddp-delete-dialog');
		if ($dialog.prop('hidden')) {
			return;
		}
		$dialog.prop('hidden', true);
		$('body').removeClass('ddp-modal-open');
		$(document).off('keydown.ddpDeleteDialog');
		if (deleteDialogLastFocus && deleteDialogLastFocus.focus) {
			deleteDialogLastFocus.focus();
		}
	}

	function openDeleteDialog(selectedRows) {
		var count = selectedRows.length;
		var $dialog = $('#ddp-delete-dialog');
		var previewLimit = 5;
		var $list = $('#ddp-delete-dialog-list').empty();
		var i;
		var row;
		var title;
		var orgTitle;
		var label;

		deleteDialogLastFocus = document.activeElement;

		$('#ddp-delete-dialog-title').text(cp_ddp.deleteModalTitle);
		$('#ddp-delete-dialog-count').text(
			(count === 1 ? cp_ddp.deleteModalCountSingular : cp_ddp.deleteModalCountPlural)
				.replace('%d', String(count))
		);

		if (cp_ddp.deleteMode === 'permanent') {
			$('#ddp-delete-dialog-method').text(cp_ddp.deleteModalPermanent);
		} else {
			$('#ddp-delete-dialog-method').text(cp_ddp.deleteModalTrash);
		}

		if (cp_ddp.keepPreference === 'latest') {
			$('#ddp-delete-dialog-keep').text(cp_ddp.deleteModalKeepLatest);
		} else {
			$('#ddp-delete-dialog-keep').text(cp_ddp.deleteModalKeepOldest);
		}

		$('#ddp-delete-dialog-preview-label').text(cp_ddp.deleteModalPreview);

		for (i = 0; i < Math.min(count, previewLimit); i++) {
			row = selectedRows[i];
			title = row.title ? stripHtml(row.title) : stripHtml(row.duplicate);
			orgTitle = row.orgtitle ? stripHtml(row.orgtitle) : stripHtml(row.original);
			label = title + ' ' + cp_ddp.deleteModalArrow + ' ' + orgTitle;
			$list.append($('<li/>').text(label));
		}

		if (count > previewLimit) {
			$('#ddp-delete-dialog-more')
				.text(cp_ddp.deleteModalMore.replace('%d', String(count - previewLimit)))
				.prop('hidden', false);
		} else {
			$('#ddp-delete-dialog-more').prop('hidden', true).text('');
		}

		$('#ddp-delete-dialog-confirm').text(cp_ddp.deleteModalConfirm).prop('disabled', false);
		$('#ddp-delete-dialog-cancel').text(cp_ddp.deleteModalCancel);

		$dialog.prop('hidden', false);
		$('body').addClass('ddp-modal-open');
		$('#ddp-delete-dialog-cancel').trigger('focus');

		$(document).on('keydown.ddpDeleteDialog', function (event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				closeDeleteDialog();
				return;
			}
			if (event.key !== 'Tab') {
				return;
			}
			var $focusable = $dialog.find('button:visible').filter(':not([disabled])');
			var first = $focusable.first()[0];
			var last = $focusable.last()[0];
			if (!first || !last) {
				return;
			}
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});
	}

	function runDelete(selectedRows) {
		var checked_posts = [];
		var selectedCount = selectedRows.length;
		jQuery.each(selectedRows, function(index, value) {
			checked_posts.push({
				'ID': value.ID,
				'orgID': value.orgID
			});
		});

		announceStatus(cp_ddp.deletingText);
		$('#ddp-delete-dialog-confirm').prop('disabled', true).text(cp_ddp.deletingText);

		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				'_ajax_nonce': cp_ddp.deletedupes_nonce,
				'action': 'ddp_delete_duplicates',
				'checked_posts': checked_posts
			},
			success: function (response) {
				closeDeleteDialog();
				if (response.success) {
					table.ajax.reload(null, false);
					ddp_refresh_log();
					var successTemplate;
					if (cp_ddp.deleteMode === 'permanent') {
						successTemplate = selectedCount === 1 ? cp_ddp.deleteSuccessPermanentSingular : cp_ddp.deleteSuccessPermanentPlural;
					} else {
						successTemplate = selectedCount === 1 ? cp_ddp.deleteSuccessTrashSingular : cp_ddp.deleteSuccessTrashPlural;
					}
					announceStatus(successTemplate.replace('%d', String(selectedCount)), 'success');
				} else {
					var errorMessage = response.data && response.data.message ? response.data.message : cp_ddp.unknownErrorText;
					alert(cp_ddp.serverResponseText + errorMessage);
					announceStatus(errorMessage, 'error');
				}
			},
			error: function (jqXHR, textStatus) {
				closeDeleteDialog();
				alert(cp_ddp.errorOccurredText + ' ' + textStatus);
				announceStatus(cp_ddp.errorOccurredText + ' ' + textStatus, 'error');
			}
		});
	}

	function deleteSelected() {
		var selectedRows = table.rows({ selected: true }).data();

		if (selectedRows.length === 0) {
			alert(cp_ddp.selectRowAlert);
			return;
		}

		if (!$('#ddp-delete-dialog').length) {
			if (!confirm(cp_ddp.text_areyousure)) {
				return;
			}
			runDelete(selectedRows);
			return;
		}

		openDeleteDialog(selectedRows);
	}

	function selectVisible() {
		table.rows({ page: 'current' }).select();
	}

	function selectNone() {
		table.rows().deselect();
	}

	function createButtons() {
		var buttonsDiv = jQuery('<div/>', { class: 'ddp-table-buttons' });

		buttonsDiv.append(jQuery('<span/>', {
			id: 'ddp-selection-count',
			class: 'ddp-selection-count',
			role: 'status',
			'aria-live': 'polite',
			text: cp_ddp.selectedPluralText.replace('%d', '0')
		}));

		buttonsDiv.append(jQuery('<button/>', {
			text: cp_ddp.deleteSelectedText,
			click: deleteSelected,
			class: 'button button-secondary ddp-delete-selected',
			disabled: true
		}));

		buttonsDiv.append(jQuery('<button/>', {
			text: cp_ddp.selectVisibleText,
			click: selectVisible,
			class: 'button button-secondary'
		}));

		buttonsDiv.append(jQuery('<button/>', {
			text: cp_ddp.selectNoneText,
			click: selectNone,
			class: 'button button-secondary'
		}));

		buttonsDiv.append(jQuery('<button/>', {
			text: cp_ddp.refreshText,
			click: refreshTable,
			class: 'button button-secondary ddp-refresh-table'
		}));

		return buttonsDiv;
	}

	table.on('select deselect draw', updateSelectionState);
	updateSelectionState();

	$('#ddp-delete-dialog-cancel, #ddp-delete-dialog [data-ddp-modal-close]').on('click', function (event) {
		event.preventDefault();
		closeDeleteDialog();
	});

	$('#ddp-delete-dialog-confirm').on('click', function (event) {
		event.preventDefault();
		var selectedRows = table.rows({ selected: true }).data();
		if (selectedRows.length === 0) {
			closeDeleteDialog();
			alert(cp_ddp.selectRowAlert);
			return;
		}
		runDelete(selectedRows);
	});

	function updateTime() {
		var currentTime = new Date().getTime();
		var elapsedTime = (currentTime - startTime) / 1000;
		jQuery('#requestTime').html(cp_ddp.requestTimeText + elapsedTime + " sec.");
		jQuery('#processingMessage').html(cp_ddp.processingMessage + elapsedTime + " sec.");
	}

	function ddp_refresh_log() {
		jQuery('#ddp_log tbody').empty();
		jQuery('#ddp_log').prop('hidden', false);
		jQuery('#ddp-log-empty').prop('hidden', true);
		jQuery('#log .spinner').addClass('is-active');
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				'_ajax_nonce': cp_ddp.loglines_nonce,
				'action': 'ddp_get_loglines'
			},
			dataType: "json",
			success: function (response) {
				var loglines = response && response.success && response.data ? response.data.results : null;
				if (loglines && loglines.length) {
					jQuery.each(loglines, function (key, value) {
						var datime = jQuery('<textarea/>').html(value.datime || '').text();
						var note = jQuery('<textarea/>').html(value.note || '').text();
						var $row = jQuery('<tr/>');
						$row.append(jQuery('<td/>', { class: 'ddp-log-date' }).append(jQuery('<time/>').text(datime)));
						$row.append(jQuery('<td/>').text(note));
						jQuery('#ddp_log tbody').append($row);
					});
				} else {
					jQuery('#ddp_log').prop('hidden', true);
					jQuery('#ddp-log-empty').prop('hidden', false);
				}
				jQuery('#log .spinner').removeClass('is-active');
			}
		}).fail(function () {
			jQuery('#log .spinner').removeClass('is-active');
			jQuery('#ddp_log').prop('hidden', true);
			jQuery('#ddp-log-empty').prop('hidden', false).text(cp_ddp.logLoadFailedText);
		});
	}

	jQuery(document).on('click', '.ddpcomparemethod li', function () {
		jQuery(".ddpcomparemethod input:radio").each(function () {
			if (this.checked) {
				jQuery(this).closest('li').find('.ddp-compare-details').show();
			} else {
				jQuery(this).closest('li').find('.ddp-compare-details').hide();
			}
		});
	});

	jQuery('.ddpcomparemethod li').trigger('click');
});
