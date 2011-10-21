<?php
/**
 * solrsearch_GetOpenSearchSuccessView
 * @package modules.solrsearch.views
 */
class solrsearch_GetOpenSearchSuccessView extends change_View
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$this->setTemplateName('Solrsearch-Action-GetOpenSearch-Success', 'xml');
		
		$website = website_WebsiteService::getInstance()->getCurrentWebsite();
		$this->setAttribute('website', $website);
		$this->setAttribute('submitUrl', $request->getAttribute('submitUrl'));
		$this->setAttribute('selfUrl', LinkHelper::getCurrentUrl());
	}
}