<?php
class solrsearch_Setup extends object_InitDataSetup
{
	public function install()
	{
		try
		{
			$scriptReader = import_ScriptReader::getInstance();
			$scriptReader->executeModuleScript('solrsearch', 'init.xml');
		}
		catch (Exception $e)
		{
			Framework::exception($e);
		}
	}
}