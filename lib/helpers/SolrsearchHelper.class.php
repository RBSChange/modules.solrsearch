<?php

class solrsearch_SolrsearchHelper
{
	const DEFAULT_AGGREGRATE_BOOST = 16;
	const DEFAULT_LABEL_BOOST = 4;
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
	 * @return indexer_Query
	 */
	public static function standardTextQueryForTerms($terms)
	{
		$masterQuery = indexer_QueryHelper::andInstance();
		foreach ($terms as $word)
		{
			$aggregateQuery = new indexer_TermQuery(RequestContext::getInstance()->getLang() . '_aggregateText', strtolower($word));
			$bool = indexer_QueryHelper::orInstance();
			$bool->add($aggregateQuery->setBoost(self::DEFAULT_AGGREGRATE_BOOST));
			$bool->add(indexer_QueryHelper::localizedFieldInstance('label', $word)->setBoost(self::DEFAULT_LABEL_BOOST));
			$bool->add(indexer_QueryHelper::localizedFieldInstance('text', $word));
			$masterQuery->add($bool);
		}
		return $masterQuery;
	}
}