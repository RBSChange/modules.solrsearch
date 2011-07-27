<?php
/**
 * solrsearch_BlockFormAction
 * @package modules.solrsearch.lib.blocks
 */
class solrsearch_BlockFormAction extends website_BlockAction
{
	/**
	 * @see website_BlockAction::execute()
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	public function execute($request, $response)
	{
		$resultPage = $this->getResultPage();
		if ($resultPage === null)
		{
			return website_BlockView::NONE;
		}
		
		$cfg = $this->getConfiguration();
		$doCompletion = f_util_Convert::toBoolean(Framework::getConfigurationValue("modules/solrsearch/form-completion", $cfg->getComplete()));
		if ($doCompletion)
		{
			$this->getContext()->addScript("modules.solrsearch.lib.js.jquery-autocomplete");
			$this->getContext()->addStyle("modules.solrsearch.autocomplete");
		}
		$request->setAttribute("doCompletion", $doCompletion);
		
		$resultUrl = LinkHelper::getDocumentUrl($resultPage);
		$request->setAttribute('formAction', htmlentities($resultUrl));
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
		return $this->getConfiguration()->getConfigurationParameter('resultTag',
		 'contextual_website_website_modules_solrsearch_page-results');
	}
	
	/**
	 * @return website_persistentdocument_page
	 */
	protected final function getResultPage()
	{
		try
		{
			$currentWebsite = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
			return TagService::getInstance()->getDocumentByContextualTag($this->getResultPageTag(), $currentWebsite);
		}
		catch (TagException $e)
		{
			Framework::exception($e);
		}
		return null;
	}
}