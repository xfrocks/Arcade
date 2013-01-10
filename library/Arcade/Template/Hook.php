<?php

class Arcade_Template_Hook
{
	// preload the templates
	public static function templateCreate($templateName, array &$params, XenForo_Template_Abstract $template)
	{
			if ($templateName == 'message_user_info')
			{
				$template->preloadTemplate('arcade_post_champion_seal');
			}
	}
	
	
	/**
	 * Called whenever a template hook is encountered (via <xen:hook> tags)
	 *
	 * @param string $name		the name of the template hook being called
	 * @param string $contents	the contents of the template hook block. This content will be the final rendered output of the block. You should manipulate this, such as by adding additional output at the end.
	 * @param array $params		explicit key-value params that have been passed to the hook, enabling content-aware decisions. These will not be all the params that are available to the template
	 * @param XenForo_Template_Abstract $template	the raw template object that has called this hook. You can access the template name and full, raw set of parameters via this object.
	 * @return unknown
	 */
	public static function template($hookName, &$content, array $hookParams, XenForo_Template_Abstract $template)
	{
		switch ($hookName)
		{
			case 'message_user_info_extra':
			{
				self::addImageDisplayPost($content, $hookParams, $template);
				break;
			}
                        case 'footer_links_legal':
                        	$params = $template->getParams();
                        	$params['HTML'] = $content;
                        	$content .= $template->create('arcade_copyright', $params);
				break;

		}
	}

	private static function addImageDisplayPost(&$content, $hookParams, XenForo_Template_Abstract $template)
	{
		$content .= $template->create('arcade_post_champion_seal', $hookParams);
	}

	
}
