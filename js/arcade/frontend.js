(function($) {
	XenForo.GameVoteLink = function($link) {
		var $target = $('.game-vote-' + $link.data('gameId'));
		
		$link.click(function(e) {
			e.preventDefault();
			var $link = $(this);
			
			if ($link.hasClass('GameVoted')) {
				XenForo.alert($target.data('votedMessage'));
				return;
			}

			XenForo.ajax(this.href, {}, function(ajaxData, textStatus) {
				if (XenForo.hasResponseError(ajaxData)) {
					return false;
				}
				
				$link.stop(true, true);

				if (ajaxData.templateHtml === '') {
					$target.xfRemove();
				} else {
					$target.html($(ajaxData.templateHtml).html()).xfActivate();
				}
			});
		});
	};
	
	XenForo.register('a.GameVoteLink', 'XenForo.GameVoteLink');
})(jQuery);