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
		$resultUrl = LinkHelper::getDocumentUrl($resultPage);
		$request->setAttribute('formAction', htmlentities($resultUrl));
		$request->setAttribute('terms', htmlspecialchars($request->getParameter('terms')));
		
		// Open search.
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$parameters = array('submitUrl' => $resultUrl);
		$openSearchUrl = LinkHelper::getActionUrl('solrsearch', 'GetOpenSearch', $parameters);
		$this->getContext()->addLink('search', 'application/opensearchdescription+xml', $openSearchUrl, $website->getLabelAsHtml());
		
		return website_BlockView::SUCCESS;
	}
	
	/**
	 * @return website_persistentdocument_page
	 */
	protected function getResultPage()
	{
		try
		{
			$currentWebsite = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
			return TagService::getInstance()->getDocumentByContextualTag('contextual_website_website_modules_solrsearch_page-results', $currentWebsite);
		}
		catch (TagException $e)
		{
			Framework::exception($e);
		}
		return null;
	}
}