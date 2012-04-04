<?php
/**
 * solrsearch_BlockFormAction
 * @package modules.solrsearch.lib.blocks
 */
class solrsearch_BlockFormAction extends website_BlockAction
{
	/**
	 * @return array
	 */
	public function getRequestModuleNames()
	{
		$modules = parent::getRequestModuleNames();
		if (!in_array($this->getConfiguration()->getModule(), $modules))
		{
			$modules[] = $this->getConfiguration()->getModule();
		}
		return $modules;
	}
	
	/**
	 * @see website_BlockAction::execute()
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	public function execute($request, $response)
	{
		$resultPage = $this->getResultPage();
		if ($resultPage === null)
		{
			return website_BlockView::NONE;
		}
		
		$cfg = $this->getConfiguration();
		// @deprecated: this project configuration will be removed in 4.0 (use block configuration).
		if (Framework::getConfigurationValue('modules/solrsearch/form-completion') == 'true')
		{
			$cfg->setConfigurationParameter('complete', true);
		}
		// @deprecated: this attribute will be removed in 4.0 (use configuration/getComplete in your template).
		$request->setAttribute('doCompletion', $cfg->getComplete());
		
		$request->setAttribute('formAction', htmlentities(LinkHelper::getDocumentUrl($resultPage)));
		$request->setAttribute('terms', htmlspecialchars($request->getParameter('terms')));
		
		// Open search.
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$parameters = array('resultTag' => $this->getResultPageTag());
		$openSearchUrl = LinkHelper::getActionUrl('solrsearch', 'GetOpenSearch', $parameters);
		$this->getContext()->addLink('search', 'application/opensearchdescription+xml', $openSearchUrl, $website->getLabelAsHtml());
		
		return website_BlockView::SUCCESS;
	}
	
	/**
	 * @return string
	 */
	protected function getResultPageTag()
	{
		return $this->getConfiguration()->getConfigurationParameter('resultTag', 'contextual_website_website_modules_solrsearch_page-results');
	}
	
	/**
	 * @return website_persistentdocument_page
	 */
	protected final function getResultPage()
	{
		$currentWebsite = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		return TagService::getInstance()->getDocumentByContextualTag($this->getResultPageTag(), $currentWebsite, false);
	}
}