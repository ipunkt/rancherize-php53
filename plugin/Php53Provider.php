<?php namespace RancherizePhp53;

use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\AlpineDebugImageBuilder;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\PhpFpmMaker;
use Rancherize\Plugin\Provider;
use Rancherize\Plugin\ProviderTrait;
use RancherizePhp53\PhpVersion\Php53;

/**
 * Class Php53Provider
 * @package RancherizePhp53
 */
class Php53Provider implements Provider {

	use ProviderTrait;

	/**
	 */
	public function register() {
		$this->container[Php53::class] = function() {
			return new Php53( );
		};
	}

	/**
	 */
	public function boot() {
		/**
		 * @var PhpFpmMaker $fpmMaker
		 */
		$fpmMaker = $this->container[PhpFpmMaker::class];

		$fpmMaker->addVersion( $this->container[Php53::class] );
	}
}