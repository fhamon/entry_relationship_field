/**
 * publish.js
 * @author Deux Huit Huit
 * @license MIT
 */
(function ($, S) {

	'use strict';

	var win = $(window);
	var html = $('html');
	var body = $();
	var ctn = $();
	var loc = window.location.toString();
	var opacity = 0.7;
	var opacityFactor = 0.1;

	var updateOpacity = function(direction) {
		if (direction !== -1) {
			direction = 1;
		}
		opacity = opacity + (direction * opacityFactor);
		var color = ctn.css('background-color');
		color = color.replace(/rgba\((\d+,)\s*(\d+,)\s*(\d+,)\s*[^\)]+\)/i, 'rgba($1 $2 $3 ' + opacity + ')');
		ctn.css('background-color', color);
	};

	var removeUI = function () {
		var parent = window.parent.Symphony.Extensions.EntryRelationship;
		var saved = loc.indexOf('/saved/') !== -1;
		var created = loc.indexOf('/created/') !== -1;

		if (saved || created) {
			if (created) {
				parent.link(S.Context.get().env.entry_id);
			}
			parent.hide(true);
			return;
		}

		var form = S.Elements.contents.find('form');

		if (!!parent) {
			// block already link items
			$.each(parent.current.values(), function (index, value) {
				form.find('#id-' + value).addClass('inactive er-already-linked');
			});
		}

		body.addClass('entry_relationship');

		// remove everything in header, except notifier
		S.Elements.header.children().not('.notifier').remove();
		// Remove everything from the notifier except errors
		S.Elements.header.find('.notifier .notice:not(.error)').trigger('detach.notify');
		form.find('>table th:not([id])').remove();
		form.find('>table td:not([class]):not(:first-child)').each(function () {
			var td = $(this);
			td.find('input').appendTo(td.prev('td'));
			td.remove();
		});

		// Close support
		var btnClose = $('<button />').attr({type: 'button', class: 'close'}).append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="19.9px" height="19.9px" viewBox="0 0 19.9 19.9"><path fill="currentColor" d="M1,19.9c-0.3,0-0.5-0.1-0.7-0.3c-0.4-0.4-0.4-1,0-1.4L18.2,0.3c0.4-0.4,1-0.4,1.4,0s0.4,1,0,1.4L1.7,19.6C1.5,19.8,1.3,19.9,1,19.9z"/><path fill="currentColor" d="M18.9,19.9c-0.3,0-0.5-0.1-0.7-0.3L0.3,1.7c-0.4-0.4-0.4-1,0-1.4s1-0.4,1.4,0l17.9,17.9c0.4,0.4,0.4,1,0,1.4C19.4,19.8,19.2,19.9,18.9,19.9z"/></svg>').click(function (e) {
			parent.cancel();
			parent.hide();
		});

		S.Elements.tools.append(btnClose);

		$(document).on('keydown', function (e) {
			if (e.which === 27) {
				parent.cancel();
				parent.hide();
				e.preventDefault();
				return false;
			}
		});

		// makes all link open in new window/tab
		form.find('table tr td a').attr('target', '_blank');
		// disable breadcrumbs links
		S.Elements.context.find('#breadcrumbs nav a').attr('href', '#').click(function (e) {
			e.preventDefault();
			return false;
		});
		form.find('table tr td').css('cursor', 'pointer').click(function (e) {
			var t = $(this);
			var target = $(e.target);

			e.preventDefault();

			// click on a link, but not in the first td
			if (!!target.closest('a').length && !target.closest('tr td:first-child').length) {
				// bail out
				return true;
			}

			if (!t.closest('.er-already-linked').length) {
				var tr = t.closest('tr');
				var entryId = tr.attr('id').replace('id-', '');
				var timestamp = tr.find('input#entry-' + entryId).val();
				tr.addClass('selected');
				parent.link(entryId, timestamp);
				parent.hide();
			}

			return false;
		});
		win.focus();
	};

	var appendUI = function () {
		ctn = $('<div id="entry-relationship-ctn" />').append('<div class="iframe" />');
		body.append(ctn);
		ctn.on('click', function () {
			S.Extensions.EntryRelationship.cancel();
			S.Extensions.EntryRelationship.hide();
		});
	};

	var defineExternals = function () {
		var self = {
			hide: function (reRender) {
				ctn.removeClass('show').find('.iframe>iframe').fadeOut(1, function () {
					// raise unload events
					var iw = this.contentWindow;
					var i$ = iw.jQuery;
					if (!!i$) {
						i$(iw).trigger('beforeunload').trigger('unload');
					}
					// remove iframe
					$(this).empty().remove();
					html.removeClass('no-scroll');
					ctn.css('background-color', '');
				});
				if (window.parent !== window && window.parent.Symphony.Extensions.EntryRelationship) {
					window.parent.Symphony.Extensions.EntryRelationship.updateOpacity(-1);
				}
				if (reRender) {
					self.current.render();
				}
				self.current = null;
				win.focus();
			},
			show: function (url) {
				var iframe = $('<iframe />').attr('src', url);

				html.addClass('no-scroll');
				ctn.find('.iframe').append(iframe);

				S.Utilities.requestAnimationFrame(function () {
					ctn.addClass('show');

					if (window.parent !== window && window.parent.Symphony.Extensions.EntryRelationship) {
						window.parent.Symphony.Extensions.EntryRelationship.updateOpacity(1);
					}
				});
			},
			link: function (entryId, timestamp) {
				if (!self.current) {
					console.error('Parent not found.');
					return;
				}
				self.current.link(entryId, timestamp);
			},
			cancel: function () {
				if (!self.current) {
					console.error('Parent not found.');
					return;
				}
				self.current.cancel();
			},
			updateOpacity: updateOpacity,
			instances: {},
			current: null
		};

		// export
		S.Extensions.EntryRelationship = self;
	};

	var init = function () {
		body = $('body');
		if (body.is('#publish')) {
			var er = window.parent !== window && window.parent.Symphony &&
				window.parent.Symphony.Extensions.EntryRelationship;
			if (!!er && !!er.current) {
				// child (iframe)
				removeUI();
			}

			// parent (can always be parent)
			appendUI();
		}
	};

	defineExternals();

	$(init);

})(jQuery, Symphony);
