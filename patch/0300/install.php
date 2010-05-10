<?php
/**
 * solrsearch_patch_0300
 * @package modules.solrsearch
 */
class solrsearch_patch_0300 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->executeModuleScript('init.xml', 'solrsearch');
		$this->execChangeCommand('curl');
	}

	/**
	 * @return String
	 */
	protected final function getModuleName()
	{
		return 'solrsearch';
	}

	/**
	 * @return String
	 */
	protected final function getNumber()
	{
		return '0300';
	}
}