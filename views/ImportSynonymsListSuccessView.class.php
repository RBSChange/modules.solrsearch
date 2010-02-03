<?php

class solrsearch_ImportSynonymsListSuccessView extends f_view_BaseView 
{
 	/**
	 * @param Context $context
	 * @param Request $request
	 */
    public function _execute($context, $request)
    {
    	$this->setTemplateName('Solrsearch-ImportSynonymsList-Success', K::HTML);
    	$this->setAttribute('styles', $this->getStyleService()->registerStyle('modules.uixul.dialog')->execute(K::HTML));
    	
    }
}