<?php
class solrsearch_SynonymslistScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return solrsearch_persistentdocument_synonymslist
     */
    protected function initPersistentDocument()
    {
    	return solrsearch_SynonymslistService::getInstance()->getNewDocumentInstance();
    }
}