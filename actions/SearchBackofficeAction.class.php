<?php
/**
 * @date Fri, 18 Jul 2008 14:05:42 +0000
 * @author intstaufl
 * @package modules.uixul
 */
class solrsearch_SearchBackofficeAction extends change_Action
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$this->setContentType('text/plain');
		$terms = solrsearch_SolrsearchHelper::getTermsFromString(solrsearch_SolrsearchHelper::escapeString($request->getParameter("terms")) . '*');
		if (f_util_ArrayUtils::isNotEmpty($terms))
		{
			$query = indexer_QueryHelper::orInstance();
			$query->add(solrsearch_SolrsearchHelper::standardTextQueryForTerms($terms));
			$query->add(new indexer_TermQuery('id', str_replace('*', '', f_util_ArrayUtils::firstElement($terms) . "/" . RequestContext::getInstance()->getLang())));
			
			$filter = indexer_QueryHelper::andInstance();
			if ($request->hasParameter("parentId"))
			{
				$filter->add(indexer_QueryHelper::descendantOfInstance($request->getParameter("parentId")));
			}
			
			$baseModule = $request->getParameter("baseModule");
			if (f_util_StringUtils::isNotEmpty($baseModule))
			{
				$filter->add(indexer_QueryHelper::stringFieldInstance('module', $baseModule));
			}
			
			if ($filter->getSubqueryCount() > 0)
			{
				$query->setFilterQuery($filter);
			}
			$query->setSortOnField($this->getSortOnField($request), $this->sortDescending($request));
			$query->setReturnedHitsCount($request->getParameter("limit", 100));
			$this->setContentType("text/xml");
			$this->resultsToXml(indexer_IndexService::getInstance()->searchBackoffice($query));
		}
		else
		{
			$this->resultsToXml(array());
		}
		die();
	}
	
	private function getSortOnField($request)
	{
		$parameter = $request->getParameter('sortOnField', 'score');
		$suffix = '';
		switch ($parameter)
		{
			case 'author':
			case 'publicationstatus':
				$suffix = '_idx_str';
				break;
			case 'label':
				return RequestContext::getInstance()->getLang() . '_sortableLabel';
			case 'creationdate':
			case 'modificationdate':
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
	private function resultsToXml($searchResults)
	{
		$xmlWriter = new XMLWriter();
		$xmlWriter->openURI('php://output');
		$xmlWriter->startDocument('1.0', 'UTF-8');
		$xmlWriter->startElement("docs");
		if (f_util_ArrayUtils::isEmpty($searchResults))
		{
			$xmlWriter->writeAttribute("totalCount", 0);
			$xmlWriter->writeAttribute("count", 0);
			$xmlWriter->writeAttribute("offset", 0);
		}
		else
		{
			$xmlWriter->writeAttribute("totalCount", $searchResults->getTotalHitsCount());
			$xmlWriter->writeAttribute("count", $searchResults->getReturnedHitsCount());
			$xmlWriter->writeAttribute("offset", $searchResults->getFirstHitOffset());
			foreach ($searchResults as $result)
			{
				$xmlWriter->startElement("doc");
				$fields = $result->getFields();
				foreach ($fields as $key => $val)
				{
					switch ($key)
					{
						case "modificationdate":
						case "creationdate":
							$val = f_util_StringUtils::ucfirst(date_Formatter::toDefaultDateTimeBO(date_Converter::convertDateToLocal(indexer_Field::solrDateToDate($val))));
							break;
						case "publicationstatus":
							$val = strtolower($val);
							break;
						case "documentpath":
							$val = $this->processDocumentPath($val);
							break;
						case "normalizedScore":
							$val = round($val * 100, 2);
							break;
						case "label":
							$putativeKey = str_replace("&amp;", "&", $val);
							if (f_Locale::isLocaleKey($putativeKey))
							{
								$val = f_Locale::translate($putativeKey);
							}
						case "htmllink":
							$val = html_entity_decode($val);
							break;
						default:
							// Nothing
							break;
					}
					$xmlWriter->writeAttribute($key, $val);
				}
				$xmlWriter->endElement();
			}
		}
		$xmlWriter->endElement();
		$xmlWriter->endDocument();
		$xmlWriter->flush();
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