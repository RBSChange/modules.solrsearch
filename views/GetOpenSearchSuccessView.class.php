<?php
/**
 * solrsearch_GetOpenSearchSuccessView
 * @package modules.solrsearch.views
 */
class solrsearch_GetOpenSearchSuccessView extends f_view_BaseView
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$this->setTemplateName('Solrsearch-Action-GetOpenSearch-Success', K::XML);
		
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$this->setAttribute('website', $website);
		$this->setAttribute('submitUrl', $request->getAttribute('submitUrl'));
		$this->setAttribute('selfUrl', LinkHelper::getCurrentUrl());
	}
}