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
		$allowedFields = Framework::getConfiguration("modules/solrsearch/completion/allowedFields", false);
		if ($allowedFields === false)
		{
			$allowedFields = array("aggregateText");
		}
		$fieldName = $request->getParameter("fieldName", "aggregateText");
		$lang = $request->getParameter("lang");
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$out = $request->getParameter("out", "jquery-autocomplete");
		if (!in_array($fieldName, $allowedFields) || $lang === null || $out === null)
		{
			throw new Exception("Invalid request");
		}
		
		$q = $request->getParameter("q");
		$op = $request->getParameter("op", "AND");
		$query = indexer_BooleanQuery::andInstance();
		// To save data transfert
		$query->setReturnedHitsCount(0);

		if (f_util_StringUtils::isNotEmpty($q) && $op == "AND")
		{
			$textQuery = solrsearch_SolrsearchHelper::parseString($q, "aggregateText");
			$lastTermQuery = f_util_ArrayUtils::lastElement($textQuery->getSubQueries());
			if ($lastTermQuery !== null) 
			{
				if (!f_util_StringUtils::endsWith($lastTermQuery->getValue(), "*"))
				{
					$lastTermQuery->add('*');
				}
				// TODO: report BUG
				// Solr/Lucene has apparently a bug an prefixQuery. Ex : "text:é*" vs "text:e*" while text definition declares:
				//<fieldType name="text_normal" class="solr.TextField" positionIncrementGap="100">
				//<analyzer>
				//  <tokenizer class="solr.StandardTokenizerFactory" />
				//  <filter class="solr.LowerCaseFilterFactory" />
				//  <filter class="solr.ASCIIFoldingFilterFactory" />
				//</analyzer>
				//</fieldType>
				$lastTermQuery->setValue(f_util_StringUtils::strip_accents($lastTermQuery->getValue()));
				$query->add($textQuery);
			}
			else
			{
				$query->add(new indexer_TermQuery("*", "*"));
			}
		}
		else
		{
			$query->add(new indexer_TermQuery("*", "*"));
		}
		
		// Suggest only for the given lang
		$query->add(new indexer_TermQuery("lang", $lang));
		
		// Suggest only on current website terms
		$websiteQuery = indexer_BooleanQuery::orInstance();
		$parentWebsiteField = indexer_Field::PARENT_WEBSITE.indexer_Field::INTEGER;
		$websiteQuery->add(new indexer_TermQuery($parentWebsiteField, 0));
		$websiteQuery->add(new indexer_TermQuery($parentWebsiteField, $website->getId()));
		$query->add($websiteQuery);

		if ($op == "AND")
		{
			$suggestFacet = new indexer_Facet($fieldName."_complete", $q);
			$resultPrefix = "";
		}
		else
		{
			$lastSpace = strrpos($q, " ");
			$resultPrefix = ($lastSpace === false) ?  " " : substr($q, 0, $lastSpace)." ";
			 
			if (f_util_StringUtils::endsWith($q, " "))
			{
				$facetPrefix = "";
			}
			else
			{
				$textQuery = solrsearch_SolrsearchHelper::parseString($q, "aggregateText");
				$lastTermQuery = f_util_ArrayUtils::lastElement($textQuery->getSubQueries());
				$facetPrefix = $lastTermQuery->getValue();	
			}
			$suggestFacet = new indexer_Facet($fieldName."_complete", $facetPrefix);
		}
		
		$suggestFacet->method = indexer_Facet::METHOD_ENUM;
		$suggestFacet->limit = intval($request->getParameter("limit", "100"));
		
		$query->addFacet($suggestFacet);
		
		// For sub-classes
		$this->completeQuery($context, $request, $query);
		
		$results = indexer_IndexService::getInstance()->search($query);
		
		if ($out == "jquery-autocomplete")
		{
			foreach ($results->getFacetResult($fieldName."_complete") as $facetCount)
			{
				echo $resultPrefix.$facetCount->getValue()."|".$facetCount->getCount()."\n";
			}
		}
		elseif ($out == "opensearch")
		{
			$this->setContentType("application/json");
				
			echo "[\"".str_replace('"', '\"', $q)."\",[";
			$i = 0;
			foreach ($results->getFacetResult($fieldName."_complete") as $facetCount)
			{
				if ($i > 0)
				{
					echo ",";
				}
				echo '"';
				echo str_replace('"', '\"', $resultPrefix.$facetCount->getValue());
				echo '"';
				$i++;
			}
			echo "],[";
			/* This does not seems to be implemented by Firefox...
			$i = 0;
			foreach ($results->getFacetResult($fieldName."_suggest") as $facetCount)
			{
				if ($i > 0)
				{
					echo ",";
				}
				echo '"';
				echo str_replace('"', '\"', $facetCount->getCount()." résultats");
				echo '"';
				$i++;
			}
			*/
			echo "],[]]";
		}
		else
		{
			// TODO: throw ?
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

	function isSecure()
	{
		return false;
	}
}