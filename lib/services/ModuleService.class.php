<?php
/**
 * @package modules.solrsearch.lib.services
 */
class solrsearch_ModuleService extends ModuleBaseService
{
	/**
	 * Singleton
	 * @var solrsearch_ModuleService
	 */
	private static $instance = null;

	/**
	 * @return solrsearch_ModuleService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = new self();
		}
		return self::$instance;
	}
}