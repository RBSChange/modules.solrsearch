<?php
/**
 * solrsearch_GetSearchResultsAction
 * @package modules.solrsearch.actions
 */
class solrsearch_GetSearchResultsAction extends f_action_BaseAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$websiteInfo  = website_WebsiteModuleService::getInstance()->getWebsiteInfos($_SERVER['HTTP_HOST']);
		$website = DocumentHelper::getDocumentInstance($websiteInfo['id']);
		$tag = $request->getParameter('resultTag');

		$module = AG_ERROR_404_MODULE;
		$action = AG_ERROR_404_ACTION;
		if ($tag !== null)
		{
			$page = TagService::getInstance()->getDocumentByContextualTag($tag, $website);
			if ($page !== null)
			{
				$request->setParameter(K::PAGE_REF_ACCESSOR, $page->getId());
				$module = 'website';
				$action = 'Display';
			}
		}

		$context->getController()->forward($module, $action);
		return View::NONE;
	}

	/**
	 * @see f_action_BaseAction::isSecure()
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
}