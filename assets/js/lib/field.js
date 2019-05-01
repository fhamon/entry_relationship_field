/**
 * field.js
 * @author Deux Huit Huit
 * @license MIT
 */
(function ($, S) {

	'use strict';

	var notifier;
	var entryId = S.Context.get().env.entry_id;

	var identity = function (x) {
		return !!x;
	};

	var baseurl = function () {
		return S.Context.get('symphony');
	};

	var createPublishUrl = function (handle, action) {
		var url = baseurl() + '/publish/' + handle + '/';
		if (!!action) {
			url += action + '/';
		}
		url += '?no-lse-redirect';
		return url;
	};

	var CONTENTPAGES = '/extension/entry_relationship_field/';
	var RENDER = baseurl() + CONTENTPAGES +'render/';
	var SAVE = baseurl() + CONTENTPAGES +'save/';
	var DELETE = baseurl() + CONTENTPAGES +'delete/';
	var SEARCH = baseurl() + CONTENTPAGES +'search/';

	var TEMP_HEIGHT_ITEM = 45;

	var renderurl = function (value, fieldid, debug) {
		var url = RENDER + (value || 'null') + '/';
		url += fieldid + '/';
		if (!!debug) {
			url += '?debug';
		}
		return url;
	};

	var saveurl = function (value, fieldid, entryid) {
		var url = SAVE + (value || 'null') + '/';
		url += fieldid + '/';
		url += entryid + '/';
		return url;
	};

	var searchurl = function (section, entries) {
		var url = SEARCH + section + '/';
		if (entries) {
			url += entries + '/';
		}
		return url;
	};

	var postdata = function (timestamp) {
		return {
			timestamp: timestamp || $('input[name="action[timestamp]"]').val(),
			xsrf: S.Utilities.getXSRF()
		};
	};

	var updateTimestamp = function (t) {
		$('input[name="action[timestamp]"]').val(t || '');
	};

	var deleteurl = function (entrytodeleteid, fieldid, entryid) {
		var url = DELETE + entrytodeleteid + '/';
		url += fieldid + '/';
		url += entryid + '/';
		return url;
	};

	var gotourl = function (section, entry_id) {
		return baseurl() + '/publish/' + section + '/edit/' + entry_id + '/';
	};

	var openIframe = function (handle, action) {
		S.Extensions.EntryRelationship.show(createPublishUrl(handle, action));
	};

	var syncCurrent = function (self) {
		S.Extensions.EntryRelationship.current = self;
	};

	var link = function (val, entryId) {
		var found = false;

		for (var x = 0; x < val.length; x++) {
			if (!val[x]) {
				val.splice(x, 1);
			} else if (val[x] === entryId) {
				found = true;
				break;
			}
		}
		if (!found) {
			val.push(entryId);
		}
		val.changed = !found;
		return val;
	};

	var unlink = function (val, entryId) {
		var found = false;

		for (var x = 0; x < val.length; x++) {
			if (!val[x] || val[x] === entryId) {
				val.splice(x, 1);
				found = true;
			}
		}
		val.changed = found;
		return val;
	};

	var replace = function (val, entryId, replaceId) {
		var found = false;

		for (var x = 0; x < val.length; x++) {
			if (!val[x] || val[x] === replaceId) {
				val[x] = entryId;
				found = true;
			}
		}
		val.changed = found;
		return val;
	};

	var insert = function (val, insertPosition, entryId) {
		var found = false;
		for (var x = 0; x < val.length; x++) {
			if (!val[x] || val[x] === entryId) {
				found = true;
			}
		}
		if (!found) {
			if (insertPosition === undefined || insertPosition >= val.length) {
				val.push(entryId);
			} else {
				val.splice(insertPosition + 1, 0, entryId);
			}
		}
		val.changed = !found;
		return val;
	};

	var initOneEntryField = function (index, t) {
		t = $(t);
		var id = t.attr('id');
		var fieldId = t.attr('data-field-id');
		var label = t.attr('data-field-label');
		var debug = t.is('[data-debug]');
		var required = t.is('[data-required="yes"]');
		var minimum = parseInt(t.attr('data-min'), 10) || 0;
		var maximum = parseInt(t.attr('data-max'), 10) || 0;
		var sections = t.find('select.sections');
		var hidden = t.find('input[type="hidden"]');
		var frame = t.find('.frame');
		var list = frame.find('ul');
		var memento;
		var replaceId;
		var insertPosition;
		var isRendering = false;

		var storageKeys = {
			selection: 'symphony.ERF.section-selection-' + id,
			collapsible: 'symphony.collapsible.ERF.' + id + '.collasped'
		};

		var values = function () {
			var val = hidden.val() || '';
			return val.split(',').filter(identity);
		};

		var updateActionBar = function (li) {
			var actionBar = t.find('.action-bar');
			var maxReached = !!maximum && li.length >= maximum;
			actionBar[maxReached ? 'addClass' : 'removeClass']('max-reached');
		};

		var updateSearchUrl = function (section) {
			t.find('[data-search]').attr('data-url', searchurl(section, hidden.val()));
		};

		var render = function () {
			if (isRendering) {
				return;
			}
			if (!hidden.val()) {
				updateActionBar($());
				return;
			}
			isRendering = true;
			$.get(renderurl(hidden.val(), fieldId, debug)).done(function (data) {
				data = $(data);
				var error = data.find('error');
				var li = data.find('> li');
				var fx = !li.length ? 'addClass' : 'removeClass';

				if (!!error.length) {
					list.empty().append(
						$('<li />').text(
							S.Language.get('Error while rendering field “{$title}”: {$error}', {
								title: label,
								error: error.text()
							})
						).addClass('error invalid')
					);
					frame.addClass('empty');
				} else {
					list.empty().append(li);
					frame[fx]('empty');

					if (!list.hasClass('orderable') && !!list.find('[data-orderable-handle]').length) {
						list.symphonyOrderable({
							items: 'li:has([data-orderable-handle])',
							handles: '.grab-handle',
							ignore: '.ignore-orderable, .ignore',
						});
					}
					if (list.is('[data-collapsible]') && !!list.find('[data-collapsible-handle]').length) {
						if (!list.hasClass('collapsible')) {
							list.symphonyCollapsible({
								items: 'li:has([data-collapsible-content]):has([data-collapsible-handle])',
								handles: '[data-collapsible-handle]',
								content: '[data-collapsible-content]',
								ignore: '.ignore-collapsible, .ignore',
								save_state: false
							}).on('collapsestop.collapsible expandstop.collapsible', collapsingChanged);
						} else {
							list.find('li:has([data-collapsible-content]):has([data-collapsible-handle])')
								.addClass('instance')
								.trigger('updatesize.collapsible')
								.trigger('setsize.collapsible');
							list.trigger('restore.collapsible');
						}
						restoreCollapsing();
					}
					updateActionBar(li);
				}
			}).error(function (data) {
				notifier.trigger('attach.notify', [
					S.Language.get('Error while rendering field “{$title}”: {$error}', {
						title: label,
						error: data.statusText || ''
					}),
					'error'
				]);
			}).always(function () {
				isRendering = false;
			});
		};

		var saveValues = function (val) {
			var oldValue = hidden.val();
			if ($.isArray(val)) {
				val = val.join(',');
			}
			var count = !val ? 0 : val.split(',').length;
			var isDifferent = oldValue !== val;
			if (isDifferent) {
				memento = oldValue;
				hidden.val(val);
				// Only save when one of those criteria is true
				// 1. The field is required and the minimum is reached
				// 2. The field is optional and has the number of items is either 0 or >= minimum
				if ((!!required && count >= minimum) ||
					(!required && (count >= minimum || count === 0))) {
					ajaxSave();
				} else {
					render();
				}
			}
			return isDifferent;
		};

		var self = {
			link: function (entryId) {
				var val;
				if (!!replaceId) {
					val = replace(values(), entryId, replaceId);
				} else if (insertPosition !== undefined) {
					val = insert(values(), insertPosition, entryId);
				} else {
					val = link(values(), entryId);
				}

				if (!!val.changed) {
					saveValues(val);
				}
				replaceId = undefined;
				insertPosition = undefined;
			},
			unlink: function (entryId) {
				var val = unlink(values(), entryId);

				if (!!val.changed) {
					saveValues(val);
				}
			},
			values: values,
			render: render,
			cancel: function () {
				if (!!replaceId) {
					render();
					replaceId = undefined;
				}
				insertPosition = undefined;
			}
		};

		var unlinkAndUpdateUI = function (li, id) {
			if (!!id) {
				self.unlink(id);
			}
			li.empty().remove();
			if (!list.children().length) {
				frame.addClass('empty');
			}
		};

		var getInsertPosition = function (t) {
			// Has data insert attribute but no value in it
			if (!!t.filter('[data-insert]').length && !t.attr('data-insert')) {
				return t.closest('li').index();
			// data-insert is -1
			} else if (t.attr('data-insert') === '-1') {
				return t.closest('li').index() - 1;
			// data-insert has a value
			} else if (!!t.attr('data-insert')) {
				return Math.max(0, parseInt(t.attr('data-insert'), 10) || 0);
			}
			return undefined;
		};

		var btnCreateClick = function (e) {
			var t = $(this);
			syncCurrent(self);
			replaceId = undefined;
			insertPosition = getInsertPosition(t);
			openIframe(t.attr('data-section'), 'new');
			e.stopPropagation();
			e.preventDefault();
		};

		var btnLinkClick = function (e) {
			var t = $(this);
			syncCurrent(self);
			replaceId = undefined;
			insertPosition = getInsertPosition(t);
			openIframe(t.attr('data-section'));
			e.stopPropagation();
			e.preventDefault();
		};

		var btnUnlinkClick = function (e) {
			var t = $(this);
			syncCurrent(self);
			var li = t.closest('li');
			var id = t.attr('data-unlink') || li.attr('data-entry-id');
			unlinkAndUpdateUI(li, id);
			e.stopPropagation();
			e.preventDefault();
		};

		var btnEditClick = function (e) {
			var t = $(this);
			syncCurrent(self);
			var li = t.closest('li');
			var id = t.attr('data-edit') || li.attr('data-entry-id');
			var section = t.attr('data-section') || li.attr('data-section');
			replaceId = undefined;
			insertPosition = undefined;
			openIframe(section, 'edit/' + id);
			e.stopPropagation();
			e.preventDefault();
		};

		var btnReplaceClick = function (e) {
			var t = $(this);
			syncCurrent(self);
			var li = t.closest('li');
			var id = t.attr('data-replace') || li.attr('data-entry-id');
			insertPosition = undefined;
			if (!!unlink(values(), id).changed) {
				unlinkAndUpdateUI(li);
				replaceId = id;
				openIframe(t.attr('data-section'));
			}
			e.stopPropagation();
			e.preventDefault();
		};

		var btnDeleteClick = function (e) {
			var t = $(this);
			syncCurrent(self);
			var li = $(this).closest('li');
			var id = t.attr('data-delete') || li.attr('data-entry-id');
			var confirmMsg = t.attr('data-message') || S.Language.get('Are you sure you want to un-link AND delete this entry?');
			if (window.confirm(confirmMsg)) {
				ajaxDelete(id, function () {
					unlinkAndUpdateUI(li, id);
				});
			}
			e.stopPropagation();
			e.preventDefault();
		};

		var resetSearchState = function () {
			var ctn = t.find('.search-ctn');
			ctn.removeClass('is-open');
			ctn.removeClass('is-search-section');
			ctn.removeClass('is-search-results');
			ctn.find('input').val('');
		};

		var searchChange = function (e) {
			syncCurrent(self);
			var input = t.find('[data-search]');
			var id = input.attr('data-value');
			input.val('');
			resetSearchState();
			if (!!id) {
				replaceId = undefined;
				insertPosition = undefined;
				self.link(id);
			}
		};

		var btnSearchClick = function (e) {
			var t = $(this);
			var ctn = t.closest('.search-ctn');
			ctn.addClass('is-open is-search-section').removeClass('is-search-results');
			e.stopPropagation();
			e.preventDefault();
		};

		var btnSearchSectionClick = function (e) {
			var t = $(this);
			var ctn = t.closest('.search-ctn');
			var indicator = ctn.find('.search-indicator');

			indicator.text(indicator.attr('data-text') + t.attr('data-section-name'));

			ctn.removeClass('is-search-section');
			ctn.addClass('is-search-results');

			updateSearchUrl(t.attr('data-section'));

			setTimeout(function () {
				ctn.find('input').focus();
			}, 0);

			e.stopPropagation();
			e.preventDefault();
		};

		var onWindowClick = function (e) {
			var target = $(e.target);

			if (!target.is('.search-ctn') && !target.closest('.search-ctn').length) {
				resetSearchState();
			}
		};

		var saveToStorage = function (key, value) {
			if (!S.Support.localStorage) {
				return;
			}
			try {
				window.localStorage.setItem(key, value);
			} catch (ex) {
				console.error(ex);
			}
		};

		/* var sectionChanged = function (e) {
			saveToStorage(storageKeys.selection, sections.val());
		}; */

		var collapsingChanged = function (e) {
			var collapsed = [];
			list.filter('.orderable').find('.instance.collapsed').each(function (index, elem) {
				collapsed.push($(elem).attr('data-entry-id'));
			});
			saveToStorage(storageKeys.collapsible, collapsed.join(','));
		};

		var restoreCollapsing = function (e) {
			if (!S.Support.localStorage) {
				return;
			}
			var ids = (window.localStorage.getItem(storageKeys.collapsible) || '').split(',');
			$.each(ids, function (index, id) {
				list.find('.instance[data-entry-id="' + id + '"]').trigger('collapse.collapsible', [0]);
			});
		};

		var ajaxSaveTimeout = 0;
		var ajaxSave = function () {
			clearTimeout(ajaxSaveTimeout);
			ajaxSaveTimeout = setTimeout(function ajaxSaveTimer() {
				if (!entryId) {
					// entry is being created... we can't save right now...
					render();
					return;
				}
				$.post(saveurl(hidden.val(), fieldId, entryId), postdata())
				.done(function (data) {
					var hasError = !data || !data.ok || !!data.error;
					var msg = hasError ?
						S.Language.get('Error while saving field “{$title}”. {$error}', {
							title: label,
							error: data.error || ''
						}) :
						S.Language.get('The field “{$title}” has been saved', {
							title: label
						});
					notifier.trigger('attach.notify', [
						msg,
						hasError ? 'error' : 'success'
					]);
					if (hasError) {
						// restore old value
						hidden.val(memento);
					} else {
						updateTimestamp(data.timestamp);
					}
				}).error(function (data) {
					notifier.trigger('attach.notify', [
						S.Language.get('Server error, field “{$title}”. {$error}', {
							title: label,
							error: typeof data.error === 'string' ? data.error : data.statusText
						}),
						'error'
					]);
				})
				.always(function () {
					render();
				});
			}, 200);
		};

		var ajaxDelete = function (entryToDeleteId, success, noAssoc) {
			noAssoc = noAssoc === true ? '?no-assoc' : '';
			$.post(deleteurl(entryToDeleteId, fieldId, entryId) + noAssoc, postdata())
			.done(function (data) {
				var hasError = !data || !data.ok || !!data.error;
				var hasAssoc = hasError && data.assoc;
				if (hasAssoc) {
					if (window.confirm(data.error)) {
						ajaxDelete(entryToDeleteId, success, true);
					}
					return;
				}
				var msg = hasError ?
					S.Language.get('Error while deleting entry “{$id}”. {$error}', {
						id: entryToDeleteId,
						error: data.error || ''
					}) :
					S.Language.get('The entry “{$id}” has been deleted', {
						id: entryToDeleteId,
					});
				notifier.trigger('attach.notify', [
					msg,
					hasError ? 'error' : 'success'
				]);
				if (hasError) {
					// restore old value
					hidden.val(memento);
				}
				else {
					if (!!data.timestamp) {
						updateTimestamp(data.timestamp);
					}
					if ($.isFunction(success)) {
						success(entryToDeleteId);
					}
				}
			}).error(function (data) {
				notifier.trigger('attach.notify', [
					S.Language.get('Server error, field “{$title}”. {$error}', {
						title: label,
						error: typeof data.error === 'string' ? data.error : data.statusText
					}),
					'error'
				]);
			});
		};

		t.on('click', '[data-create]', btnCreateClick);
		t.on('click', '[data-link]', btnLinkClick);
		t.on('click', '[data-unlink]', btnUnlinkClick);
		t.on('click', '[data-edit]', btnEditClick);
		t.on('click', '[data-replace]', btnReplaceClick);
		t.on('click', '[data-delete]', btnDeleteClick);
		t.on('click', '[data-search-btn]', btnSearchSectionClick);

		t.on('click', '.search-trigger', btnSearchClick);
		$(window.document).on('click', onWindowClick);

		S.Interface.Suggestions.init(t, '[data-search]', {
			editSuggestion: function (suggestion, index, data, result) {
				var value = data.value.split(':');
				var id = value.shift();
				suggestion.attr('data-value', id).text(value.join(':'));
			}
		});

		t.on('mousedown.suggestions', '.suggestions li', searchChange);

		t.on('keydown.suggestions', '[data-search]', function (e) {
			if (e.which === 13) {
				searchChange(e);
			}
		});

		frame.on('orderstop.orderable', '*', function () {
			var val = [];
			list.find('li[data-entry-id]').each(function () {
				var id = $(this).attr('data-entry-id');
				if (!!id) {
					val.push(id);
				}
			});
			saveValues(val);
		});

		// Temp height before the render
		list.css({
			minHeight: values().length * TEMP_HEIGHT_ITEM
		});

		// render
		render();

		// export
		S.Extensions.EntryRelationship.instances[id] = self;
	};

	var initOneReverseField = function (index, t) {
		t = $(t);
		var id = t.attr('id');
		var fieldId = t.attr('data-field-id');
		var debug = t.is('[data-debug]');
		var entries = t.attr('data-entries') || '';
		var section = t.attr('data-linked-section');
		var linkedFieldId = t.attr('data-linked-field-id');
		var label = t.attr('data-field-label');
		var frame = t.find('.frame');
		var list = frame.find('ul');
		var isRendering = false;
		var dirty = false;
		var render = function () {
			if (isRendering || !entries || !entries.length) {
				return;
			}
			isRendering = true;
			$.get(renderurl(entries, fieldId, debug)).done(function (data) {
				data = $(data);
				var error = data.find('error');
				var li = data.find('li');
				var fx = !li.length ? 'addClass' : 'removeClass';

				if (!!error.length) {
					list.empty().append(
						$('<li />').text(
							S.Language.get('Error while rendering field “{$title}”: {$error}', {
								title: label,
								error: error.text()
							})
						).addClass('error invalid')
					);
					frame.addClass('empty');
				} else {
					list.empty().append(li);
					frame[fx]('empty');
				}
			}).error(function (data) {
				notifier.trigger('attach.notify', [
					S.Language.get('Error while rendering field “{$title}”: {$error}', {
						title: label,
						error: data.statusText || ''
					}),
					'error'
				]);
			}).always(function () {
				isRendering = false;
			});
		};

		var values = function () {
			if ($.isArray(entries)) {
				return entries;
			}
			return entries.split(',').filter(identity);
		};
		var memento = [].concat(values());

		var self = {
			link: function (entryId, timestamp) {
				entries = link(values(), entryId);
				ajaxSave('＋', entryId, timestamp);
			},
			unlink: function (entryId, timestamp) {
				entries = unlink(values(), entryId);
				ajaxSave('−', entryId, timestamp);
			},
			values: values,
			render: render,
			cancel: function () {
				if (dirty) {
					render();
				}
				dirty = false;
			}
		};

		var unlinkAndUpdateUI = function (li, id, timestamp) {
			if (!!id) {
				self.unlink(id, timestamp);
			}
			li.empty().remove();
			if (!list.children().length) {
				frame.addClass('empty');
			}
		};

		var btnGotoClick = function (e) {
			var t = $(this);
			window.location = gotourl(section, t.attr('data-goto'));
			e.stopPropagation();
			e.preventDefault();
		};

		var btnUnlinkClick = function (e) {
			var t = $(this);
			syncCurrent(self);
			var li = t.closest('li');
			var id = t.attr('data-unlink') || li.attr('data-entry-id');
			var timestamp = li.attr('data-timestamp');
			unlinkAndUpdateUI(li, id, timestamp);
			dirty = true;
			e.stopPropagation();
			e.preventDefault();
		};

		var btnAddClick = function (e) {
			var t = $(this);
			syncCurrent(self);
			openIframe(t.attr('data-add'));
			e.stopPropagation();
			e.preventDefault();
		};

		var ajaxSaveTimeout = 0;
		var ajaxSave = function (op, entryId, timestamp) {
			clearTimeout(ajaxSaveTimeout);
			ajaxSaveTimeout = setTimeout(function ajaxSaveTimer() {
				var eId = S.Context.get('env').entry_id;
				if (!eId) {
					return;
				}
				$.post(saveurl(encodeURIComponent(op) + eId, linkedFieldId, entryId), postdata(timestamp))
				.done(function (data) {
					var hasError = !data || !data.ok || !!data.error;
					var msg = hasError ?
						S.Language.get('Error while saving field “{$title}”. {$error}', {
							title: label,
							error: data.error || ''
						}) :
						S.Language.get('The field “{$title}” has been saved', {
							title: label
						});
					notifier.trigger('attach.notify', [
						msg,
						hasError ? 'error' : 'success'
					]);
					if (hasError) {
						entries = memento;
					} else {
						memento = [].concat(values());
					}
				}).error(function (data) {
					notifier.trigger('attach.notify', [
						S.Language.get('Server error, field “{$title}”. {$error}', {
							title: label,
							error: typeof data.error === 'string' ? data.error : data.statusText
						}),
						'error'
					]);
					memento = entries;
				})
				.always(function () {
					render();
				});
			}, 200);
		};

		t.on('click', '[data-goto]', btnGotoClick);
		t.on('click', '[data-unlink]', btnUnlinkClick);
		t.on('click', '[data-add]', btnAddClick);

		// render
		render();

		// export
		S.Extensions.EntryRelationship.instances[id] = self;
	};

	var init = function () {
		notifier = S.Elements.header.find('div.notifier');
		S.Elements.contents.find('.field.field-entry_relationship').each(initOneEntryField);
		S.Elements.contents.find('.field.field-reverse_relationship').each(initOneReverseField);
	};

	$(init);

})(jQuery, window.Symphony);
