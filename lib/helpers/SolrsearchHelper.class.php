<?php

class solrsearch_SolrsearchHelper
{
	const DEFAULT_LOCALIZED_AGGREGRATE_BOOST = 4;
	const DEFAULT_EXACT_BOOST = 16;
	const DEFAULT_LABEL_BOOST = 8;
	const INVALID_QUERY_TERMS_REGEXP = '/([,;:\"\'&\.\^+\-(){}#~!?§=\/%]|\bAND\b|\bOR\b|\bNOT\b)+/u';
	
	const SORT_OPTIONS_LIST_ID = 'modules_solrsearch/sortoptions';
	const ITEM_PER_PAGE_LIST_ID = 'modules_solrsearch/itemperpage';
	
	/**
	 * Transforms a string in an array of individual terms:
	 * 
	 * @param String $string
	 * @return Array<String>
	 */
	public static function getValidTermsFromString($string)
	{
		$normalizedString = trim(preg_replace(solrsearch_SolrsearchHelper::INVALID_QUERY_TERMS_REGEXP, ' ', $string));
		return self::getTermsFromString($normalizedString);
	}
	
	/**
	 * @param String $value
	 * @return String
	 */
	public static function escapeString($value)
	{
		$pattern = '/(\+|-|;|\'|&&|\|\||!|\(|\)|\{|}|\[|]|\^|"|~|\*|\?|:|\\\)/u';
		$replace = '\\\$1';
		return preg_replace($pattern, $replace, $value);
	}
	
	/**
	 * @param String $value
	 * @return Array<String>
	 */
	public static function getTermsFromString($string)
	{
		$result = array();
		foreach (preg_split('/[\s]+/u', $string) as $term)
		{
			if ($term != "" && !preg_match('/^[*|?]/', $term) && $term != "AND" && $term != "OR" && $term != "NOT")
			{
				$result[] = $term;
			}
		}
		return array_filter($result);
	}
	
	/**
	 * Get an array of suggestions for the array $terms in the lang $lang
	 *
	 * @param Array $terms
	 * @param String $lang
	 * @return Array
	 */
	public static function getSuggestionsForTerms($terms, $lang)
	{
		if (f_util_ArrayUtils::isEmpty($terms))
		{
			return array();
		}
		$schemaVersion = indexer_SolrManager::getSchemaVersion();
		if ($schemaVersion == "2.0.4")
		{
			$res = array();
			foreach ($terms as $term)
			{
				$suggestion = indexer_IndexService::getInstance()->getSuggestionForWord(mb_strtolower($term), $lang);
				if (!is_null($suggestion))
				{
					$res[] = $suggestion;
				}
				else
				{
					$res[] = $term;
				}
			}
			if (count(array_diff($terms, $res)) == 0)
			{
				return array();
			}
			return $res;
		}
		else
		{
			$res = indexer_IndexService::getInstance()->getSuggestionForWords($terms, $lang);
			return explode(" ", $res);
		}
	}
	
	/**
	 * Return a filter on a specific document type. 
	 *
	 * @param String $type
	 * @return indexer_Query
	 */
	public static function mediaFilterInstance($type)
	{
		return indexer_QueryHelper::stringFieldInstance('mediaType', $type);
	}
	
	/**
	 * Given the array of terms $terms do a standard text search. 
	 *
	 * @param Array $terms
	 * @deprecated use standardTextQueryForQueryString
	 * @return indexer_Query
	 */
	public static function standardTextQueryForTerms($terms)
	{
		$masterQuery = indexer_QueryHelper::andInstance();
		foreach ($terms as $word)
		{
			$aggregateQuery = new indexer_TermQuery(RequestContext::getInstance()->getLang() . '_aggregateText', strtolower($word));
			$bool = indexer_QueryHelper::orInstance();
			$bool->add($aggregateQuery->setBoost(self::DEFAULT_LOCALIZED_AGGREGRATE_BOOST));
			$bool->add(indexer_QueryHelper::localizedFieldInstance('label', $word)->setBoost(self::DEFAULT_LABEL_BOOST));
			$bool->add(indexer_QueryHelper::localizedFieldInstance('text', $word));
			$masterQuery->add($bool);
		}
		return $masterQuery;
	}
	
	/**
	 * @param String $queryString
	 * @param String $lang default current lang
	 * @param Integer $labelBoost default DEFAULT_LABEL_BOOST
	 * @param Integer $localizedAggregateBoost default DEFAULT_LOCALIZED_AGGREGRATE_BOOST
	 * @param Integer $exactBoost default DEFAULT_EXACT_BOOST
	 * @return indexer_BooleanQuery
	 */
	public static function standardTextQueryForQueryString($queryString, $lang = null, $labelBoost = null, $localizedAggregateBoost = null, $exactBoost = null)
	{
		if ($lang === null)
		{
			$lang = RequestContext::getInstance()->getLang();
		}
		if ($labelBoost === null)
		{
			$labelBoost = self::DEFAULT_LABEL_BOOST;
		}
		if ($localizedAggregateBoost === null)
		{
			$localizedAggregateBoost = self::DEFAULT_LOCALIZED_AGGREGRATE_BOOST;
		}
		if ($exactBoost === null)
		{
			$exactBoost = self::DEFAULT_EXACT_BOOST;
		}
		
		$textQuery = indexer_BooleanQuery::orInstance();
		$parsedTextQuery = solrsearch_SolrsearchHelper::parseString($queryString, "text_".$lang);
		if ($parsedTextQuery->isEmpty())
		{
			return $textQuery;
		}
		$textQuery->add($parsedTextQuery);
		$textQuery->add(solrsearch_SolrsearchHelper::parseString($queryString, "label_".$lang, "AND", 
		 $labelBoost));
		$textQuery->add(solrsearch_SolrsearchHelper::parseString($queryString, $lang."_aggregateText", "AND",
		 $localizedAggregateBoost));
		if (indexer_SolrManager::hasAggregateText())
		{
			$textQuery->add(solrsearch_SolrsearchHelper::parseString($queryString, "aggregateText", "AND",
			 $exactBoost));
		}
		return $textQuery;
	}
	
	/**
	 * @param String $string
	 * @param String $fieldName
	 * @param String $op "AND" or "OR"
	 * @return indexer_BooleanQuery
	 */
	public static function parseString($string, $fieldName, $op = "AND", $defaultBoost = null)
	{
		$query = indexer_BooleanQuery::byStringInstance($op);
		$stringLen = strlen($string);
		$term = null;
		for ($i = 0; $i < $stringLen; $i++)
		{
			$c = $string[$i];
			if ($c == '"')
			{
				if ($term === null)
				{
					// begin new term
					$term = new indexer_PhraseQuery($fieldName);
				}
				elseif (!($term instanceof indexer_PhraseQuery))
				{
					// begin new term && end term
					self::addTerm($query, $term, $string, $stringLen, $i);
					$term = new indexer_PhraseQuery($fieldName);
				}
				else
				{
					// end term
					self::addTerm($query, $term, $string, $stringLen, $i);
				}
			}
			elseif ($c == ' ' || $c == '^')
			{
				if ($term instanceof indexer_PhraseQuery)
				{
					$term->add($c);
				}
				else
				{
					self::addTerm($query, $term, $string, $stringLen, $i);
				}
			}
			elseif ($c == '+')
			{
				if ($term === null)
				{
					$term = self::beginTerm($fieldName, $string, $stringLen, $i);
					$term->required();
				}
				else
				{
					$term->add($c);
				}
			}
			elseif ($c == '-')
			{
				if ($term === null)
				{
					$term = self::beginTerm($fieldName, $string, $stringLen, $i);
					$term->prohibited();
				}
				else
				{
					$term->add($c);
				}
			}
			else
			{
				if ($term === null)
				{
					$term = new indexer_TermQuery($fieldName);
				}
				$term->add($c);
			}
		}

		$wasAdded = self::addTerm($query, $term);
		/*
		 if ($wasAdded && $quoted)
		 {
			echo "Warning: malformed expression syntax";
			}
			*/

		if ($defaultBoost !== null)
		{
			foreach ($query->getSubqueries() as $termQuery)
			{
				$boost = $termQuery->getBoost();
				if ($boost === null)
				{
					$boost = 1;
				}
				$boost = $boost * $defaultBoost;
				$termQuery->setBoost($boost);
			}
		}

		return $query;
	}

	/**
	 * @param indexer_BooleanQuery $query
	 * @param String $term
	 * @return Boolean true if term was added
	 */

	private static function addTerm($query, &$term, $string = null, $stringLen = null, &$i = null)
	{
		$boost = null;
		$proximity = null;
		if ($string !== null)
		{
			if ($i < ($stringLen - 1) && '~' == $string[$i+1])
			{
				$j = $i+2;
				$proximity = "";
				while ($j < $stringLen && $string[$j] != ' ' && $string[$j] != '"' && $string[$j] != "^")
				{
					$string[$j];
					$proximity .= $string[$j];
					$j++;
				}
				$i = $j;
				$proximity = floatval($proximity);
			}
			if ($string[$i] == '^')
			{
				$i--;
			}
			if ($i < ($stringLen - 1) && '^' == $string[$i+1])
			{
				$j = $i+2;
				$boost = "";
				while ($j < $stringLen && $string[$j] != ' ' && $string[$j] != '"')
				{
					$boost .= $string[$j];
					$j++;
				}
				$i = $j;
				$boost = floatval($boost);
			}
		}
		if ($term !== null && !$term->isEmpty())
		{
			if ($boost !== null)
			{
				$term->setBoost($boost);
			}
			if ($term instanceof indexer_PhraseQuery && $proximity !== null)
			{
				$term->setProximity($proximity);
			}
			$query->add($term);
			$term = null;
			return true;
		}
		$term = null;
		return false;
	}

	/**
	 * @param String $string
	 * @param Integer $queryLen
	 * @param Integer $i
	 * @return indexer_TermQuery
	 */
	private static function beginTerm($fieldName, $string, $stringLen, &$i)
	{
		if ($i < ($stringLen-1) && $string[$i+1] == '"')
		{
			$term = new indexer_PhraseQuery($fieldName);
			$i++;
		}
		else
		{
			$term = new indexer_TermQuery($fieldName);
		}
		return $term;
	}
}