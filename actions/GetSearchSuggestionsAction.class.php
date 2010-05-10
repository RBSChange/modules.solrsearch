<?php
/**
 * solrsearch_GetSearchSuggestionsAction
 * @package modules.solrsearch.actions
 */
class solrsearch_GetSearchSuggestionsAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$result = array();

		$terms = $request->getParameter('terms');
		
		// TODO.
		$result[$terms] = array('test');

		if (!headers_sent())
		{
			controller_ChangeController::setNoCache();
			header('Content-Type: application/json; charset=utf-8');
		}
		echo JsonService::getInstance()->encode($result);
	}
	
	/**
	 * @see f_action_BaseAction::isSecure()
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
}