<?php
class solrsearch_SearchBackofficeJSONAction extends change_JSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$textQuery = solrsearch_SolrsearchHelper::standardTextQueryForQueryString(f_util_StringUtils::stripAccents($request->getParameter("terms")));
		if (!$textQuery->isEmpty())
		{
			$query = indexer_QueryHelper::orInstance();
			$query->add($textQuery);
			$query->add(solrsearch_SolrsearchHelper::standardTextQueryForQueryString(f_util_StringUtils::stripAccents($request->getParameter("terms"))."*"));
			$firstTerm = f_util_ArrayUtils::firstElement(explode(" ", $request->getParameter("terms")));
			if (is_numeric($firstTerm))
			{
				$query->add(new indexer_TermQuery('id', $firstTerm . "/" . RequestContext::getInstance()->getLang()));
			}
			
			$filter = indexer_QueryHelper::andInstance();
			if ($request->hasParameter("parentId"))
			{
				$filter->add(indexer_QueryHelper::descendantOfInstance($request->getParameter("parentId")));
			}
			
			$baseModule = $request->getParameter("baseModule");
			if (f_util_StringUtils::isNotEmpty($baseModule))
			{
				$moduleFilter = indexer_QueryHelper::orInstance();
				$moduleFilter->add(indexer_QueryHelper::stringFieldInstance('module', $baseModule));
				$moduleFilter->add(indexer_QueryHelper::stringFieldInstance('editmodule', $baseModule));
				$filter->add($moduleFilter);
			}
			
			if ($filter->getSubqueryCount() > 0)
			{
				$query->setFilterQuery($filter);
			}
			$query->setSortOnField($this->getSortOnField($request), $this->sortDescending($request));
			$query->setReturnedHitsCount($request->getParameter("limit", 100));
			$this->resultsToJSON(indexer_IndexService::getInstance()->searchBackoffice($query), intval($request->getParameter('treetype')));
		}
		else
		{
			$this->resultsToJSON(array());
		}
	}
	
	private function getSortOnField($request)
	{
		$parameter = $request->getParameter('sortOnField', 'score');
		$suffix = '';
		switch ($parameter)
		{
			case 'author' :
			case 'publicationstatus' :
				$suffix = '_idx_str';
				break;
			case 'label' :
				return RequestContext::getInstance()->getLang() . '_sortableLabel';
			case 'creationdate' :
			case 'modificationdate' :
				$suffix = '_idx_dt';
				break;
		}
		return $parameter . $suffix;
	}
	
	private function sortDescending($request)
	{
		$parameter = $request->getParameter('sortDirection', 'sortDirection');
		if ($parameter == 'ascending')
		{
			return false;
		}
		return true;
	}
	
	/**
	 * @param indexer_SearchResults $searchResults
	 * @param integer $treetype
	 */
	private function resultsToJSON($searchResults, $treetype)
	{
		$result = array();
		
		if (f_util_ArrayUtils::isEmpty($searchResults))
		{
			$result["totalCount"] = 0;
			$result["count"] = 0;
			$result["offset"] = 0;
		}
		else
		{
			$result["totalCount"] = $searchResults->getTotalHitsCount();
			$result["count"] = $searchResults->getReturnedHitsCount();
			$result["offset"] = $searchResults->getFirstHitOffset();
			$result["nodes"] = array();
			
			$baseModule = $this->getContext()->getRequest()->getParameter("baseModule");
			$pp = $this->getPersistentProvider();
			$toIndex = array();
			foreach ($searchResults as $searchResult)
			{
				$fields = $searchResult->getFields();
				list($id,) = explode('/', $fields['id']);
				$modelName = $pp->getDocumentModelName($id);
				if (!$modelName)
				{
					$toIndex[] = $id;
					continue;
				}
				
				$doc = $pp->getDocumentInstance($id, $modelName);
				$node = array(
					'id' => $id,
					'status' => $doc->getPublicationstatus(),
					'normalizedScore' => round($fields['normalizedScore'] * 100, 2),
					'documentModel' => $modelName,
					'documentpath' => $this->processDocumentPath($fields['documentpath']),
					'creationdate' => date_Formatter::toDefaultDateTimeBO($doc->getUICreationdate()),
					'modificationdate' => date_Formatter::toDefaultDateTimeBO($doc->getUIModificationdate()),
					'editmodule' => isset($fields['editmodule']) ? $fields['editmodule'] : $fields['module']
				);
				
				DocumentHelper::completeBOAttributes($doc, $node, $treetype);
				$result["nodes"][] = $node;
			}
			
			if (count($toIndex) > 0)
			{
				$tm = $this->getTransactionManager();
				try 
				{
					$tm->beginTransaction();					
					foreach ($toIndex as $id)
					{
						$pp->setIndexingDocumentStatus($id, self::TO_INDEX);
					}
					$tm->commit();
				}
				catch (Exception $e)
				{
					$tm->rollback($e);
				}
			}
		}
		$this->sendJSON($result);
	}
	
	/**
	 * @param String $val
	 * @return String
	 */
	private function processDocumentPath($val)
	{
		if (f_util_StringUtils::isEmpty($val))
		{
			return "";
		}
		$val = str_replace(array("&gt;", "&#39;"), array(">", "'"), $val);
		$result = array();
		foreach (explode(" > ", $val) as $pathComponent)
		{
			$putativeKey = str_replace("&amp;", "&", $pathComponent);
			if (f_Locale::isLocaleKey($putativeKey))
			{
				$result[] = f_Locale::translate($putativeKey);
			}
			else
			{
				$result[] = $pathComponent;
			}
		}
		return implode(" > ", $result);
	}
}
