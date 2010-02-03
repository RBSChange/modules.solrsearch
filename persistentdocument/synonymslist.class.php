<?php
/**
 * solrsearch_persistentdocument_synonymslist
 * @package solrsearch
 */
class solrsearch_persistentdocument_synonymslist extends solrsearch_persistentdocument_synonymslistbase {
	
	public function getShortName()
	{
		return strtolower(str_replace(';', '', f_util_ArrayUtils::lastElement(explode('.', $this->getLabel()))));
	}
}