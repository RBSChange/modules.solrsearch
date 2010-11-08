<?php
class solrsearch_Setup extends object_InitDataSetup
{
	public function install()
	{
		try
		{
			// Import the declared synonyms list
			$folderId = ModuleService::getInstance()->getRootFolderId('solrsearch');
			if (count($this->getPersistentProvider()->createQuery('modules_solrsearch/synonymslist')->find()) > 0)
			{
				$this->addWarning("Some synonyms list were found. Aborting automatic import!");
			}
			else
			{
				foreach (indexer_IndexService::getInstance()->getSynonymsLists() as $synonymsListName)
				{
					$synonymsList = solrsearch_SynonymslistService::getInstance()->getNewDocumentInstance();
					$synonymsList->setValue("");
					$synonymsList->setLabel(f_util_StringUtils::strtoupper($synonymsListName));
					$synonymsList->setListname($synonymsListName);
					$synonymsList->save($folderId);
				}
			}		
		}
		catch (Exception $e)
		{
			echo $e->getMessage();
		}
		
		try
		{
			$scriptReader = import_ScriptReader::getInstance();
			$scriptReader->executeModuleScript('solrsearch', 'init.xml');
		}
		catch (Exception $e)
		{
			Framework::exception($e);
		}
	}
}