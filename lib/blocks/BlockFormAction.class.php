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
		$request->setAttribute('formAction', htmlentities(LinkHelper::getDocumentUrl($resultPage)));
		$request->setAttribute('terms', htmlspecialchars($request->getParameter('terms')));
		
		// Open search.
		$website = website_WebsiteService::getInstance()->getCurrentWebsite();
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
		$currentWebsite = website_WebsiteService::getInstance()->getCurrentWebsite();
		return TagService::getInstance()->getDocumentByContextualTag($this->getResultPageTag(), $currentWebsite, false);
	}
}