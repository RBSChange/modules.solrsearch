<?php
/**
 * solrsearch_GetOpenSearchAction
 * @package modules.solrsearch.actions
 */
class solrsearch_GetOpenSearchAction extends f_action_BaseAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		return 'Success';
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