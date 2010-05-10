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
		if (preg_match('/^[?*]/', $queryString != 0 ))
		{
			return $this->handleBadQuery();
		}
		$terms = solrsearch_SolrsearchHelper::getValidTermsFromString($queryString);
		if (count($terms) == 0)
		{
			return $this->handleEmptyQuery();
		}
		$normalizedTerms = join(' ', $terms);
		$request->setAttribute('terms', htmlspecialchars($normalizedTerms));
		
		$currentPage = $this->getCurrentPageNumber();
		$itemsPerPage = $this->getNbItemsPerPage();
		$sort = $this->getSortingMode();
		$query = $this->getStandardQuery($terms, $currentPage, $itemsPerPage, $sort);
		$searchResults = indexer_IndexService::getInstance()->search($query);
		$this->comleteSearchResults($searchResults);
				
		// Error during search...
		if ($searchResults === null)
		{
			$this->addError(f_Locale::translate("&modules.solrsearch.frontoffice.Error-during-search;"));
			return website_BlockView::ERROR;
		}
		
		$totalHitsCount = $searchResults->getTotalHitsCount();
		$pageHitsCount = $searchResults->count();			
		$request->setAttribute('searchResults', $searchResults);
		$request->setAttribute('noHits', $pageHitsCount == 0);
		
		// Suggestions.
		if ($this->getConfiguration()->getEnableSuggestions())
		{
			$suggestions = solrsearch_SolrsearchHelper::getSuggestionsForTerms($terms, $this->getLang());
			if (count($suggestions) > 0)
			{
				$params = array('solrsearchParam' => array('terms' => htmlspecialchars(join(' ', $suggestions))));
				$request->setAttribute('suggestionParams', $params);
			}
		}
		
		// Pagination.
		$paginator = new paginator_Paginator('solrsearch', $currentPage, array(), $itemsPerPage);
		$paginator->setPageCount((int) ceil($totalHitsCount / $itemsPerPage));
		$paginator->setCurrentPageNumber($currentPage);
		$paginator->setExtraParameters(array('terms' => htmlspecialchars($normalizedTerms), 'sort' => $sort));
		$request->setAttribute('paginator', $paginator);
		
		// Sort Paramters.
		$request->setAttribute('byScoreParams', array('solrsearchParam' => array('terms' => $normalizedTerms, 'sort' => 'score')));
		$request->setAttribute('byDateParams', array('solrsearchParam' => array('terms' => $normalizedTerms, 'sort' => 'date')));
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
	protected function getStandardQuery($terms, $currentPage, $itemsPerPage, $sort)
	{
		$masterQuery = solrsearch_SolrsearchHelper::standardTextQueryForTerms($terms);
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
	 * @param array $searchResults
	 */
	protected function comleteSearchResults($searchResults)
	{
		foreach ($searchResults as $searchResult)
		{
			$document = $searchResult->getDocument();
			$documentService = $document->getDocumentService();
			if (f_util_ClassUtils::methodExists($documentService, 'getSolrserachResultItemTemplate'))
			{
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
	}
}