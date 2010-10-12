<?php
/**
 * solrsearch_BlockResultsAction
 * @package modules.solrsearch.lib.blocks
 */
class solrsearch_BlockResultsAction extends website_BlockAction
{
	const DATE_FIELD_NAME = 'sortable_date_idx_dt';
	
	/**
	 * @see website_BlockAction::execute()
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	public function execute($request, $response)
	{
		if ($this->isInBackoffice())
		{
			return website_BlockView::NONE;
		}
		
		$queryString = trim($request->getParameter('terms'));
		// If a term starts with a wildcard * or ?, bail...
		if (preg_match('/(^| )[?*]/', $queryString) != 0)
		{
			return $this->handleBadQuery();
		}
		
		$textFieldQuery = solrsearch_SolrsearchHelper::parseString($queryString, "text");
		if ($textFieldQuery->isEmpty())
		{
			return $this->handleEmptyQuery();
		}
		
		$request->setAttribute('terms', htmlspecialchars($queryString));
		
		$currentPage = $this->getCurrentPageNumber();
		$itemsPerPage = $this->getNbItemsPerPage();
		$sort = $this->getSortingMode();
		$query = $this->getStandardQuery($queryString, $currentPage, $itemsPerPage, $sort);
		
		$cfg = $this->getConfiguration();
		$doSuggestion = $cfg->getEnableSuggestions();
		$schemaVersion = indexer_SolrManager::getSchemaVersion();
		
		$suggestionTerms = $doSuggestion ? $textFieldQuery->getTerms() : null;
		$searchResults = indexer_IndexService::getInstance()->search($query, $suggestionTerms);
		$this->completeSearchResults($searchResults);
				
		// Error during search...
		if ($searchResults === null)
		{
			$this->addError(f_Locale::translate("&modules.solrsearch.frontoffice.Error-during-search;"));
			return website_BlockView::ERROR;
		}
		
		if ($cfg->getDoDocumentModelFacet() && indexer_SolrManager::hasFacetAbility())
		{
			$request->setAttribute("hasFacet", true);
			$modelFacet = $searchResults->getFacetResult("documentModel");
			if ($modelFacet->count() > 1)
			{
				foreach ($modelFacet as $facetCount)
				{
					$modelName = $facetCount->getValue();
					$modelInfo = f_persistentdocument_PersistentDocumentModel::getModelInfo($modelName);
					$facetCount->setValue(f_Locale::translate("&modules.".$modelInfo["module"].".document.".$modelInfo["document"].".Document-name;"));
				}
				$request->setAttribute("documentModelFacet", $modelFacet);
			}
		}
		
		$totalHitsCount = $searchResults->getTotalHitsCount();
		$pageHitsCount = $searchResults->count();			
		$request->setAttribute('searchResults', $searchResults);
		$request->setAttribute('noHits', $pageHitsCount == 0);
		
		// Suggestions.
		if ($doSuggestion)
		{
			if (!indexer_SolrManager::hasSuggestionInOnRequest())
			{
				// This is the old way: multiple SolR requests (at least 2)
				$suggestions = solrsearch_SolrsearchHelper::getSuggestionsForTerms($suggestionTerms, $this->getLang());
				if (count($suggestions) > 0)
				{
					$params = array('solrsearchParam' => array('terms' => htmlspecialchars(join(' ', $suggestions))));
					$request->setAttribute('suggestionParams', $params);
				}
			}
			else
			{
				$suggestion = $searchResults->getSuggestion();
				if (f_util_StringUtils::isNotEmpty($suggestion))
				{
					$params = array('solrsearchParam' => array('terms' => htmlspecialchars($suggestion)));
					$request->setAttribute('suggestionParams', $params);
				}
			}
		}
		
		// Pagination.
		$paginator = new paginator_Paginator('solrsearch', $currentPage, array(), $itemsPerPage);
		$paginator->setPageCount((int) ceil($totalHitsCount / $itemsPerPage));
		$paginator->setCurrentPageNumber($currentPage);
		$paginator->setExtraParameters(array('terms' => htmlspecialchars($queryString), 'sort' => $sort));
		$request->setAttribute('paginator', $paginator);
		
		// Sort Parameters.
		$request->setAttribute('byScoreParams', array('solrsearchParam' => array('terms' => $queryString, 'sort' => 'score', 'page' => '1')));
		$request->setAttribute('byDateParams', array('solrsearchParam' => array('terms' => $queryString, 'sort' => 'date', 'page' => '1')));
		$request->setAttribute('byScore', $sort == 'score');
		
		$currentOffset = $searchResults->getFirstHitOffset() + 1;
		$header = f_Locale::translate('&modules.solrsearch.frontoffice.Search-results-header;', array('start' => $currentOffset, 'stop' => $currentOffset + $pageHitsCount - 1, 'total' => $totalHitsCount));
		$request->setAttribute('resultsHeader', $header);
		
		return website_BlockView::SUCCESS;
	}
	
	/**
	 * @return String
	 */
	protected function handleBadQuery()
	{
		$this->addError(f_Locale::translate("&modules.solrsearch.frontoffice.Search-error-wildcardstart;"));
		return website_BlockView::ERROR;
	}
	
	/**
	 * @return String
	 */
	protected function handleEmptyQuery()
	{
		$this->addError(f_Locale::translate("&modules.solrsearch.frontoffice.Search-error-emptyquery;"));
		return website_BlockView::ERROR;
	}
	
	/**
	 * @param array $terms
	 * @param integer $currentPage
	 * @param integer $itemsPerPage
	 * @param string $sort
	 * @return indexer_Query
	 */
	protected function getStandardQuery($queryString, $currentPage, $itemsPerPage, $sort)
	{
		$cfg = $this->getConfiguration();
		
		$masterQuery = indexer_BooleanQuery::andInstance();
		$textQuery = solrsearch_SolrsearchHelper::standardTextQueryForQueryString($queryString, $this->getLang(),
		 $cfg->getConfigurationParameter("labelBoost", solrsearch_SolrsearchHelper::DEFAULT_LABEL_BOOST),
		 $cfg->getConfigurationParameter("localizedAggregateBoost", solrsearch_SolrsearchHelper::DEFAULT_LOCALIZED_AGGREGRATE_BOOST),
		 $cfg->getConfigurationParameter("exactBoost", solrsearch_SolrsearchHelper::DEFAULT_EXACT_BOOST));
		if ($cfg->getDoDocumentModelFacet() && indexer_SolrManager::hasFacetAbility())
		{
			$masterQuery->addFacet("documentModel");
		}
		
		$masterQuery->add($textQuery);
		$masterQuery->setLang($this->getLang());
		
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$filter = indexer_QueryHelper::andInstance();
		$filter->add(indexer_QueryHelper::websiteIdRestrictionInstance($website->getId()));
		$masterQuery->setFilterQuery($filter);
		if ($sort == 'date')
		{
			$masterQuery->setSortOnField(self::DATE_FIELD_NAME)->setSortOnField('id');
		}
		else
		{
			$masterQuery->setSortOnField('score')->setSortOnField('id');
		}
		
		$offset = $itemsPerPage * ($currentPage - 1);
		return $masterQuery->setHighlighting(true)->setFirstHitOffset($offset)->setReturnedHitsCount($itemsPerPage);
	}
	
	/**
	 * @return integer
	 */
	protected function getNbItemsPerPage()
	{
		return $this->getConfiguration()->getNbItemsPerPage();
	}
	
	/**
	 * @return integer
	 */
	protected function getCurrentPageNumber()
	{
		$number = $this->findLocalParameterValue('page');
		return ($number > 0) ? $number : 1;
	}
	
	/**
	 * @return integer
	 */
	protected function getSortingMode()
	{
		$sort = $this->findLocalParameterValue('sort');
		return ($sort == 'date') ? 'date' : 'score';
	}
	
	/**
	 * @deprecated use completeSearchResults
	 * @param array $searchResults
	 */
	protected function comleteSearchResults($searchResults)
	{
		return $this->completeSearchResults($searchResults);
	}
	
	/**
	 * @param array $searchResults
	 */
	protected function completeSearchResults($searchResults)
	{
		$count = $searchResults->count();
		for ($index = 0; $index <$count; $index++) 
		{
			$searchResult = $searchResults->offsetGet($index);
			try 
			{
				$document = $searchResult->getDocument();
				if ($document->isPublished())
				{
					$documentService = $document->getDocumentService();
					if (f_util_ClassUtils::methodExists($documentService, 'getSolrsearchResultItemTemplate'))
					{
						$template = $documentService->getSolrsearchResultItemTemplate($document, get_class());
						$searchResult->setProperty('__ITEM_MODULE', $template['module']);
						$searchResult->setProperty('__ITEM_TEMPLATE', $template['template']);
					}
					elseif (f_util_ClassUtils::methodExists($documentService, 'getSolrserachResultItemTemplate'))
					{
						// TODO: remove (bad syntax)
						$template = $documentService->getSolrserachResultItemTemplate($document, get_class());
						$searchResult->setProperty('__ITEM_MODULE', $template['module']);
						$searchResult->setProperty('__ITEM_TEMPLATE', $template['template']);
					}
					else
					{
						$searchResult->setProperty('__ITEM_MODULE', 'solrsearch');
						$searchResult->setProperty('__ITEM_TEMPLATE', 'Solrsearch-Inc-DefaultResultDetail');
					}
				}
				else
				{
					Framework::warn(__METHOD__ . " Unpublished document " . $document->__toString());
					$searchResults->offsetunset($index);
				}
			}
			catch (Exception $e)
			{
				Framework::exception($e);
				$searchResults->offsetunset($index);
			}
		}
	}
}