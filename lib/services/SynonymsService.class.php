<?php
/**
 * @date Wed, 02 Jul 2008 11:16:15 +0000
 * @author intstaufl
 * @package 
 */
class solrsearch_SynonymsService extends f_persistentdocument_DocumentService
{
	/**
	 * @var solrsearch_SynonymsService
	 */
	private static $instance;
	
	/**
	 * @return solrsearch_SynonymsService
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
	 * @return solrsearch_persistentdocument_synonyms
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_solrsearch/synonyms');
	}
	
	/**
	 * Create a query based on 'modules_solrsearch/synonyms' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_solrsearch/synonyms');
	}
	

	/**
	 * @param String $hash
	 * @return solrsearch_persistentdocument_synonyms or null
	 */
	public function getByHashmd5($hash)
	{
		return $this->createQuery()->add(Restrictions::eq('hashmd5', $hash))->findUnique();
	}
	
	/**
	 * @param solrsearch_persistentdocument_synonyms $document
	 * @param String $oldPublicationStatus
	 * @param array<"cause" => String, "modifiedPropertyNames" => array, "oldPropertyValues" => array> $params
	 * @return void
	 */
	protected function publicationStatusChanged($document, $oldPublicationStatus, $params)
	{
		$list = $document->getList();
		$list->setValue(solrsearch_SynonymslistService::getInstance()->synchronizeSynonymsList($list));
		$list->save();
	}
	
	/**
	 * @see f_persistentdocument_DocumentService::getResume()
	 *
	 * @param solrsearch_persistentdocument_synonyms $document
	 * @param string $forModuleName
	 * @param array $allowedSections
	 * @return array
	 */
	public function getResume($document, $forModuleName, $allowedSections = null)
	{
		$data = parent::getResume($document, $forModuleName, $allowedSections);
		if ($allowedSections === null || isset($allowedSections['properties']))
		{
			$data['properties']['value'] = $document->getValue();
		}
		return $data;
	}

}