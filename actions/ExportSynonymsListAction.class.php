<?php
/**
 * @date Wed, 16 Jul 2008 08:53:33 +0000
 * @author intstaufl
 * @package modules.ExportSynonymsList
 */
class solrsearch_ExportSynonymsListAction extends f_action_BaseAction 
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$synonymsList = DocumentHelper::getDocumentInstance(intval($request->getParameter(K::COMPONENT_ID_ACCESSOR)));
		header('Content-Disposition: attachment; filename="' . $synonymsList->getShortName() .'.txt";' );
		$this->setContentType("application/octet-stream");
		echo $synonymsList->getValue();
	}
}