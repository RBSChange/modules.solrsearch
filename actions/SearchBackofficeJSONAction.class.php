<?php
class solrsearch_SearchBackofficeJSONAction extends change_JSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$textQuery = solrsearch_SolrsearchHelper::standardTextQueryForQueryString(f_util_StringUtils::strip_accents($request->getParameter("terms")));
		if (!$textQuery->isEmpty())
		{
			$query = indexer_QueryHelper::orInstance();
			$query->add($textQuery);
			$query->add(solrsearch_SolrsearchHelper::standardTextQueryForQueryString(f_util_StringUtils::strip_accents($request->getParameter("terms"))."*"));
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
			$this->resultsToJSON(indexer_IndexService::getInstance()->searchBackoffice($query));
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
	 */
	private function resultsToJSON($searchResults)
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
			foreach ($searchResults as $searchResult)
			{
				$node = array();
				
				$fields = $searchResult->getFields();
				foreach ($fields as $key => $val)
				{
					switch ($key)
					{
						case "modificationdate" :
						case "creationdate" :
							$val = f_util_StringUtils::ucfirst(date_Formatter::toDefaultDateTimeBO(date_Converter::convertDateToLocal(indexer_Field::solrDateToDate($val))));
							break;
						case "publicationstatus" :
							$node['status'] = $val;
							$val = strtolower($val);
							break;
						case "documentpath" :
							$val = $this->processDocumentPath($val);
							break;
						case "normalizedScore" :
							$val = round($val * 100, 2);
							break;
						case "id" :
							$parts = explode('/', $val);
							$val = $parts[0];
							break;
						case "label" :
							$putativeKey = str_replace("&amp;", "&", $val);
							if (f_Locale::isLocaleKey($putativeKey))
							{
								$val = f_Locale::translate($putativeKey);
							}
						case "htmllink" :
							$val = str_replace(array("&gt;", "&#39;"), array(">", "'"), html_entity_decode($val));
							break;
						case "finalId" :
						case "score" :
						case "text" :
							$key = null;
							break;
						default :
							// Nothing
							break;
					}
					if ($key !== null)
					{
						$node[$key] = $val;
					}
				}
				
				if (isset($node['id']) && f_persistentdocument_PersistentProvider::getInstance()->getDocumentModelName($node['id']))
				{
					$doc = DocumentHelper::getDocumentInstance($node['id']);
					$attrs = array();
					$doc->buildTreeAttributes($baseModule, 'wlist', $attrs);
					if (isset($attrs['hasPreviewImage']))
					{
						$node['hasPreviewImage'] = $attrs['hasPreviewImage'];
					}				
					$result["nodes"][] = $node;
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
