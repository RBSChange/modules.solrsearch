<?php
/**
 * @date Wed, 16 Jul 2008 08:11:48 +0000
 * @author intstaufl
 * @package 
 */
class solrsearch_SynonymslistService extends f_persistentdocument_DocumentService
{
	/**
	 * @var solrsearch_SynonymslistService
	 */
	private static $instance;
	
	/**
	 * @return solrsearch_SynonymslistService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}
	
	/**
	 * @return solrsearch_persistentdocument_synonymslist
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_solrsearch/synonymslist');
	}
	
	/**
	 * Create a query based on 'modules_solrsearch/synonymslist' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_solrsearch/synonymslist');
	}

	/**
	 * @param solrsearch_persistentdocument_synonymslist $document
	 * @param unknown_type $content
	 */
	public function updateSynonymsList($document, $content)
	{
		$tm = $this->getTransactionManager();
		try
		{
			$tm->beginTransaction();
			foreach ($document->getSynonymsArrayInverse() as $synonym)
			{
				$synonym->delete();
			}
			foreach (explode(K::CRLF, $content) as $line)
			{
				if ($this->isEquivalentGroup($line))
				{					
					$this->processEquivalentGroup($document, $line);
				}
			}
			$document->setValue($this->synchronizeSynonymsList($document));
			$document->save();			
			$tm->commit();
		} catch (Exception $e)
		{
			$tm->rollBack($e);
		}	
	}
	
	/**
	 * @param solrsearch_persistentdocument_synonymslist $document
	 * @return String
	 */
	public function synchronizeSynonymsList($document)
	{
		$entries = array();
		foreach ($document->getPublishedSynonymsArrayInverse() as $synonym)
		{
			$entries[] = $synonym->getValue();
		}
		$value = implode(K::CRLF, $entries);
		indexer_IndexService::getInstance()->updateSynonymsList($document->getListname(), $value);		
		return $value;
	}
	
	private function isEquivalentGroup($line)
	{
		return strpos($line, ',') != false && strpos($line, '=>') == false;
	}
	
	/**
	 * @param solrsearch_persistentdocument_synonymslist $document
	 * @param String $line
	 */
	private function processEquivalentGroup($document, $line)
	{
		$synonymsService = solrsearch_SynonymsService::getInstance();
		$items = explode(",", $line);
		$trimmedItems = array();
		foreach ($items as $item)
		{
			$normalizedItem = trim($item);
			if (!f_util_StringUtils::isEmpty($normalizedItem))
			{
				$trimmedItems[] = $normalizedItem;
			}
		}
		
		if (f_util_ArrayUtils::isEmpty($trimmedItems))
		{
			return;
		}
		
		sort($trimmedItems);
		$hash = md5(serialize($trimmedItems).$document->getLabel());
		if ($synonymsService->getByHashmd5($hash) == null)
		{
			$synonyms = $synonymsService->getNewDocumentInstance();
			$synonyms->setValue(implode(", ", $trimmedItems));
			$synonyms->setLabel(f_util_ArrayUtils::firstElement($trimmedItems));
			$synonyms->setHashmd5($hash);
			$synonyms->setList($document);
			$synonyms->save($document->getId());
		}
	}
}