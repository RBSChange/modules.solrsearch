<?php

class solrsearch_ImportSynonymsListInputView extends f_view_BaseView
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$this->setTemplateName('Solrsearch-ImportSynonymsList-Input', K::HTML);
		$synonymsList = DocumentHelper::getDocumentInstance($request->getParameter(K::COMPONENT_ID_ACCESSOR));
		$this->setAttribute("listid", $synonymsList->getId());
		$this->setAttribute("listname", f_Locale::translate($synonymsList->getLabel()));
		$this->setAttribute('styles', $this->getStyleService()->registerStyle('modules.uixul.dialog')->execute(K::HTML));
	}
}