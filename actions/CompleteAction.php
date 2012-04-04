<?php
/**
 * This action does only work if SolR is a real SolR (ie. not mysqlindexer)
 */
class solrsearch_CompleteAction extends f_action_BaseAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$allowedFields = Framework::getConfiguration('modules/solrsearch/completion/allowedFields', false);
		if ($allowedFields === false)
		{
			$allowedFields = array('aggregateText');
		}
		$fieldName = $request->getParameter('fieldName', 'aggregateText');
		$limit = intval($request->getParameter('limit', 100));
		$lang = $request->getParameter('lang', RequestContext::getInstance()->getLang());
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$out = $request->getParameter('out', 'jquery-autocomplete');
		if (!in_array($fieldName, $allowedFields) || $lang === null || $out === null)
		{
			throw new Exception('Invalid request');
		}
		
		$q = $request->hasParameter('term') ? $request->getParameter('term') : $request->getParameter('q');
		$q = f_util_StringUtils::toLower(trim($q));
		$op = $request->getParameter('op', 'AND');
		$query = indexer_BooleanQuery::andInstance();
		// To save data transfert
		$query->setReturnedHitsCount(0);

		if (f_util_StringUtils::isNotEmpty($q) && $op == 'AND')
		{
			$textQuery = solrsearch_SolrsearchHelper::parseString($q, 'aggregateText');
			$lastTermQuery = f_util_ArrayUtils::lastElement($textQuery->getSubQueries());
			if ($lastTermQuery !== null) 
			{
				if (!f_util_StringUtils::endsWith($lastTermQuery->getValue(), '*'))
				{
					$lastTermQuery->add('*');
				}
				$lastTermQuery->setValue(f_util_StringUtils::strip_accents($lastTermQuery->getValue()));
				$query->add($textQuery);
			}
			else
			{
				$query->add(new indexer_TermQuery('*', '*'));
			}
		}
		else
		{
			$query->add(new indexer_TermQuery('*', '*'));
		}
		
		// Suggest only for the given lang
		$query->add(new indexer_TermQuery('lang', $lang));
		
		// Suggest only on current website terms
		$query->add(indexer_QueryHelper::websiteIdRestrictionInstance($website->getId()));
		$completeFieldName = ($request->hasNonEmptyParameter('completeFieldName')) ?
			$request->getParameter('completeFieldName') : $fieldName.'_complete';
		if ($op == 'AND')
		{
			$suggestFacet = new indexer_Facet($completeFieldName, $q);
			$resultPrefix = '';
		}
		else
		{
			$lastSpace = strrpos($q, ' ');
			$resultPrefix = ($lastSpace === false) ?  ' ' : substr($q, 0, $lastSpace).' ';
			 
			if (f_util_StringUtils::endsWith($q, ' '))
			{
				$facetPrefix = '';
			}
			else
			{
				$textQuery = solrsearch_SolrsearchHelper::parseString($q, 'aggregateText');
				$lastTermQuery = f_util_ArrayUtils::lastElement($textQuery->getSubQueries());
				$facetPrefix = $lastTermQuery->getValue();	
			}
			$suggestFacet = new indexer_Facet($completeFieldName, $facetPrefix);
		}
		
		$suggestFacet->method = indexer_Facet::METHOD_ENUM;
		$suggestFacet->limit = $limit;
		
		$query->addFacet($suggestFacet);
		
		// For sub-classes
		$this->completeQuery($context, $request, $query);
		
		$results = indexer_IndexService::getInstance()->search($query);
		if ($out == 'jquery-ui-autocomplete')
		{
			$this->setContentType('application/json');
			
			$data = array();
			foreach ($results->getFacetResult($fieldName.'_complete') as $facetCount)
			{
				$data[] = array(
					'label' => $facetCount->getValue() . ' (' . $facetCount->getCount() . ')',
					'value' => $resultPrefix.$facetCount->getValue()
				);
			}
			echo JsonService::getInstance()->encode($data);
		}
		elseif ($out == 'opensearch')
		{
			$this->setContentType('application/json');
				
			echo '["'.str_replace('"', '\"', $q).'",[';
			$i = 0;
			foreach ($results->getFacetResult($fieldName.'_complete') as $facetCount)
			{
				if ($i > 0)
				{
					echo ',';
				}
				echo '"';
				echo str_replace('"', '\"', $resultPrefix.$facetCount->getValue());
				echo '"';
				$i++;
			}
			echo '],[],[]]';
		}
		// @deprecated
		elseif ($out == 'jquery-autocomplete')
		{
			foreach ($results->getFacetResult($fieldName.'_complete') as $facetCount)
			{
				echo $resultPrefix.$facetCount->getValue().'|'.$facetCount->getCount()."\n";
			}
		}
	}
	
	/**
	 * @param Context $context
	 * @param Request $request
	 * @param indexer_BooleanQuery $query
	 */
	protected function completeQuery($context, $request, $query)
	{
		// empty: this is an extension point
	}

	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
}