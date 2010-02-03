<?php
/**
 * @date Wed, 16 Jul 2008 08:53:19 +0000
 * @author intstaufl
 * @package modules.ImportSynonymsList
 */
class solrsearch_ImportSynonymsListAction extends f_action_BaseAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		if ($request->hasFile("filename"))
		{
			$contents = file_get_contents($request->getFilePath('filename'));
			solrsearch_SynonymslistService::getInstance()->updateSynonymsList(DocumentHelper::getDocumentInstance(intval($request->getParameter(K::COMPONENT_ID_ACCESSOR))), $contents);
		}
		return 'Success';
	}
	
	public function getRequestMethods()
	{
		return Request::POST;
	}
}