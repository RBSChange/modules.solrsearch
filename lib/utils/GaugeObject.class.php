<?php
/**
 * @deprecated
 */
class GaugeObject extends solrsearch_GaugeObject
{
	/**
	 * @deprecated
	 * @param float $val
	 * @return solrsearch_GaugeObject
	 */
	public static function getNewInstance($val)
	{
		$instance = new GaugeObject($val);
		$instance->setGauge($val);
		return $instance;
	}
	
	public function increment()
	{
		$this->gauge++;
	}
}

class solrsearch_GaugeObject
{
	private $gauge = 0;
	
	private static $imageBaseName;
	private static $imageExtension;
	private static $maxScore;
	
	/**
	 * float
	 *
	 * @param unknown_type $value
	 */
	public function __construct($value)
	{
		if (self::$imageBaseName == null)
		{
			try
			{
				$delegateClassName = Framework::getConfiguration('modules/solrsearch/gaugeDelegate');
				self::setDelegate($delegateClassName);
			} 
			catch (ConfigurationException $e)
			{
				// Nothing to do here
			}
			
			if (self::$imageBaseName == null)
			{
				self::$imageBaseName = 'solrsearch-results-';
			}
			
			if (self::$imageExtension == null)
			{
				self::$imageExtension = 'png';
			}
			
			if (self::$maxScore == null)
			{
				self::$maxScore = 5;
			}
		}
		$this->gauge = round(self::$maxScore * $value);
	}
	
	/**
	 * @param Integer $value
	 */
	public function setGauge($value)
	{
		$this->gauge = $value;
	}
	
	/**
	 * @param String $className
	 */
	public static function setDelegate($className)
	{
		$classInstance = new $className();
		$reflectionClass = new ReflectionClass($className);
		
		if ($reflectionClass->hasMethod("getGaugeImageBaseName"))
		{
			self::$imageBaseName = $classInstance->getGaugeImageBaseName();
		}
		
		if ($reflectionClass->hasMethod("getGaugeImageExtension"))
		{
			self::$imageExtension = $classInstance->getGaugeImageExtension();
		}
		
		if ($reflectionClass->hasMethod("getGaugeMaxScore"))
		{
			self::$maxScore = $classInstance->getGaugeMaxScore();
		}
	}
	
	/**
	 * @return String
	 */
	public function getImageUrl()
	{
		return htmlentities(MediaHelper::getFrontofficeStaticUrl(self::$imageBaseName . strval($this->gauge)) . '.' . self::$imageExtension);
	}
	
	/**
	 * @return String
	 */
	public function getAltText()
	{
		return f_Locale::translate('&modules.solrsearch.frontoffice.Solrsearch-results-' . strval($this->gauge) . ";");
	}
	
	/**
	 * @return String
	 */
	public function getClassName()
	{
		return 'solrsearch-results-' . strval($this->gauge);
	}
}