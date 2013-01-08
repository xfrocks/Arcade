/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined) {
	XenForo.GameSystemOptions = function($element) { this.__construct($element); };
	XenForo.GameSystemOptions.prototype = {
		__construct: function($select) {
			this.$select = $select;
			this.url = $select.data('optionsUrl');
			this.$target = $($select.data('optionsTarget'));
			if (!this.url || !this.$target.length ) return;

			$select.bind({
				keyup: $.context(this, 'fetchDelayed'),
				change: $.context(this, 'fetch')
			});
		},

		fetchDelayed: function() {
			if (this.delayTimer) {
				clearTimeout(this.delayTimer);
			}

			this.delayTimer = setTimeout($.context(this, 'fetch'), 250);
		},

		fetch: function() {
			if (!this.$select.val().length) {
				this.$target.html('');
				return;
			}
			
			if ($('#systemOptionsLoaded').val() == this.$select.val()) {
				return;
			}

			if (this.xhr) {
				this.xhr.abort();
			}

			this.xhr = XenForo.ajax(
				this.url,
				{ 'system_id': this.$select.val(), 'game_id': $('#gameId').val() },
				$.context(this, 'ajaxSuccess'),
				{ error: false }
			);
		},

		ajaxSuccess: function(ajaxData) {
			if (XenForo.hasResponseError(ajaxData)) return false;
			
			if (ajaxData) {
				this.$target.html(ajaxData.templateHtml);
			} else {
				this.$target.html('');
			}
		}
	};
	
	XenForo.GameEditor = function($form) { this.__construct($form); };
	XenForo.GameEditor.prototype = {
		__construct: function($form) {
			this.useAjaxSave = true;
			this.$form = $form;
			this.$saveReloadButton = $('#saveReloadButton');
			this.$saveExitButton = $('#saveExitButton');
			this.$gameId    = $('#gameId');
			this.$imageButton = this.$form.find('.GameImageUploader');

			if (this.useAjaxSave && this.getSaveUrl('json')) {
				this.$saveReloadButton
					.val(this.$saveReloadButton.data('ajaxvalue'))
					.click($.context(this, 'saveAjax'));

				this.$saveExitButton
					.click($.context(this, 'saveExit'));
				
				this.$form.find('input:file').change($.context(this, 'uploadChange'));
			}
			
			this.initTitleAndSlug();
		},

		initTitleAndSlug: function() {
			var $title = $('#gameTitle');
			var $slug = $('#gameSlug');

/*
This function default the slug value to be a stripped out version of
the title when adding/editing a game.  Nice idea but caused too many
problems because the actual slug value is often different from the
name of the game.
			$title.keyup(function(e) {
				$slug.val($title.val().toLowerCase().replace(/[^a-zA-Z0-9 ]/g, '').trim().replace(/ +/g, '-'));
			});
*/
		},
		
		uploadChange: function(e) {
			var $uploader = $(e.target);
			var $uploaderOrig = $uploader.clone(true);
			var $form = this.$form;
			var realThis = this;
			
			if ($uploader.val() != '') {
				var $iframe, $hiddenInput;

				$iframe = $('<iframe src="about:blank" style="display:none; background-color: white" name="AutoGameUploader"></iframe>')
					.insertAfter($uploader)
					.load(function(e) {
						var $iframe = $(e.target),
							ajaxData = $iframe.contents().text(),
							eComplete = null;

						// Opera fires this function when it's not done with no data
						if (!ajaxData) {
							return false;
						}

						// alert the global progress indicator that the transfer is complete
						$(document).trigger('PseudoAjaxStop');

						$uploader = $uploaderOrig.clone(true).replaceAll($uploader);

						// removing the iframe after a delay to prevent Firefox' progress indicator staying active
						setTimeout(function() { $iframe.remove(); }, 500);

						try	{
							ajaxData = $.parseJSON(ajaxData);
							console.info('Inline file upload completed successfully. Data: %o', ajaxData);
						} catch(e) {
							console.error(ajaxData);
							return false;
						}

						if (XenForo.hasResponseError(ajaxData))	{
							return false;
						}

						$('input:submit', $form).removeAttr('disabled');

						realThis.ajaxSaveSuccess(ajaxData, 'success');
					});

				$hiddenInput = $('<span>'
					+ '<input type="hidden" name="_xfNoRedirect" value="1" />'
					+ '<input type="hidden" name="_xfResponseType" value="json-text" />'
					+ '<input type="hidden" name="_xfUploader" value="1" />'
					+ '</span>')
					.appendTo($form);

				$form.attr('target', 'AutoGameUploader')
					.submit()
					.attr('target', '');

				$hiddenInput.remove();

				// fire the event that will be caught by the global progress indicator
				$(document).trigger('PseudoAjaxStart');

				$form.find('input:submit').attr('disabled', 'disabled');
			}
		},

		saveAjax: function(e) {
			
			var hasFilePending = false;
			var $files = this.$form.find('input[type=file]') // do this everytime because the form element is changed
				.each(function() {
					var v = $(this).val();
					if (v && v.length > 0) {
						hasFilePending = true;
					}
				});
			if (hasFilePending) return true;
			
			var postParams;

			if (e) e.preventDefault();

			postParams = this.$form.serializeArray();

			XenForo.ajax(
				this.getSaveUrl('json'),
				postParams,
				$.context(this, 'ajaxSaveSuccess')
			);

			return true;
		},

		saveExit: function(e) {
			return true;
		},

		ajaxSaveSuccess: function(ajaxData, textStatus) {
			if (XenForo.hasResponseError(ajaxData)) return false;

			if (ajaxData.saveMessage) {
				XenForo.alert(ajaxData.saveMessage, '', 1000);
			}
			
			this.$gameId.val(ajaxData.gameId);
			
			if (ajaxData.updated) {
				for (var i in ajaxData.updated) {
					$('#' + i).html(ajaxData.updated[i]);
				}
			}
		},

		getSaveUrl: function(reqType) {
			return this.$form.attr('action') + (reqType ? ('.' + reqType) : '');
		}
	};

	XenForo.register('select.GameSystemOptions', 'XenForo.GameSystemOptions');
	XenForo.register('form.GameEditor', 'XenForo.GameEditor');

}
(jQuery, this, document);