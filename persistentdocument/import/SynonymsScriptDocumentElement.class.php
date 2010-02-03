<?php
class solrsearch_SynonymsScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return solrsearch_persistentdocument_synonyms
     */
    protected function initPersistentDocument()
    {
    	return solrsearch_SynonymsService::getInstance()->getNewDocumentInstance();
    }
}