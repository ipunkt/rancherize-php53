<?php namespace RancherizePhp53\PhpVersion;

use Rancherize\Blueprint\Infrastructure\Dockerfile\Dockerfile;
use Rancherize\Blueprint\Infrastructure\Infrastructure;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\Configurations\MailTarget;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\DebugImage;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\DefaultTimezone;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\MemoryLimit;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\PhpVersion;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\PostLimit;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\Traits\DebugImageTrait;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\Traits\DefaultTimezoneTrait;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\Traits\MailTargetTrait;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\Traits\MemoryLimitTrait;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\Traits\PostLimitTrait;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\Traits\UploadFileLimitTrait;
use Rancherize\Blueprint\Infrastructure\Service\NetworkMode\ShareNetworkMode;
use Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\UploadFileLimit;
use Rancherize\Blueprint\Infrastructure\Service\Service;
use Rancherize\Configuration\Configuration;

/**
 * Class PHP53
 * @package Rancherize\Blueprint\Infrastructure\Service\Maker\PhpFpm\PhpVersions
 */
class Php53 implements PhpVersion, MemoryLimit, PostLimit, UploadFileLimit, DefaultTimezone, MailTarget, DebugImage {

	const PHP_IMAGE = 'ipunktbs/php:5.3-fpm';

	/**
	 * @var string|Service
	 */
	protected $appTarget;

	use MemoryLimitTrait;
	use PostLimitTrait;
	use UploadFileLimitTrait;
	use DefaultTimezoneTrait;
	use MailTargetTrait;
	use DebugImageTrait;

	/**
	 * @param Configuration $config
	 * @param Service $mainService
	 * @param Infrastructure $infrastructure
	 */
	public function make(Configuration $config, Service $mainService, Infrastructure $infrastructure) {
		/**
		 * Disable internal fpm 7.0
		 */
		$mainService->setEnvironmentVariable('NO_FPM', 'true');
		$mainService->setEnvironmentVariable('BACKEND_HOST', '127.0.0.1:9000');

		$phpFpmService = new Service();
		$phpFpmService->setNetworkMode( new ShareNetworkMode($mainService) );
		$phpFpmService->setName( function() use ($mainService) {
			$name = 'PHP-FPM-'.$mainService->getName();
			return $name;
		});
		$this->setImage($phpFpmService);
		$phpFpmService->setRestart(Service::RESTART_UNLESS_STOPPED);

		$memoryLimit = $this->memoryLimit;
		if( $memoryLimit !== self::DEFAULT_MEMORY_LIMIT )
			$phpFpmService->setEnvironmentVariable('PHP_MEMORY_LIMIT', $memoryLimit);
		$postLimit = $this->postLimit;
		if( $postLimit !== self::DEFAULT_POST_LIMIT )
			$phpFpmService->setEnvironmentVariable('PHP_POST_MAX_SIZE', $postLimit);
		$uploadFileLimit = $this->uploadFileLimit;
		if( $uploadFileLimit !== self::DEFAULT_UPLOAD_FILE_LIMIT )
			$phpFpmService->setEnvironmentVariable('PHP_UPLOAD_MAX_FILESIZE', $uploadFileLimit);
		$defaultTimezone = $this->defaultTimezone;
		if( $defaultTimezone !== self::DEFAULT_TIMEZONE)
			$phpFpmService->setEnvironmentVariable('DEFAULT_TIMEZONE', $defaultTimezone);

		$mailHost = $this->mailHost;
		$mailPort = $this->mailPort;

		if($mailHost !== null && $mailPort !== null)
			$mailHost .= ':'.$mailPort;

		if($mailHost !== null)
			$phpFpmService->setEnvironmentVariable('SMTP_SERVER', $mailHost.':'.$mailPort);

		$mailAuth = $this->mailAuthentication;
		if($mailAuth !== null)
			$phpFpmService->setEnvironmentVariable('SMTP_AUTHENTICATION', $mailAuth);

		$mailUsername = $this->mailUsername;
		if($mailUsername !== null)
			$phpFpmService->setEnvironmentVariable('SMTP_USER', $mailUsername);

		$mailPassword = $this->mailPassword;
		if($mailPassword !== null)
			$phpFpmService->setEnvironmentVariable('SMTP_PASSWORD', $mailPassword);

		$this->addAppSource($phpFpmService);

		$phpFpmService->setEnvironmentVariablesCallback(function() use ($mainService) {
			return $mainService->getEnvironmentVariables();
		});

		$mainService->addSidekick($phpFpmService);
		$infrastructure->addService($phpFpmService);
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return '5.3';
	}

	/**
	 * @param string $hostDirectory
	 * @param string $containerDirectory
	 * @return $this
	 */
	public function setAppMount(string $hostDirectory, string $containerDirectory) {
		$this->appTarget = [$hostDirectory, $containerDirectory];
		return $this;
	}

	/**
	 * @param Service $appService
	 * @return $this
	 */
	public function setAppService(Service $appService) {
		$this->appTarget = $appService;
		return $this;
	}

	/**
	 * @param $phpFpmService
	 */
	protected function addAppSource(Service $phpFpmService) {
		$appTarget = $this->appTarget;

		if ($appTarget instanceof Service) {
			$phpFpmService->addVolumeFrom($appTarget);
			return;
		}

		list($hostDirectory, $containerDirectory) = $appTarget;
		$phpFpmService->addVolume($hostDirectory, $containerDirectory);
	}

	/**
	 * @param $commandName
	 * @param Service $command
	 * @param Service $mainService
	 * @return Service
	 */
	public function makeCommand( $commandName, $command, Service $mainService ) {

		$phpCommandService = new Service();
		$phpCommandService->setCommand($command);
		$phpCommandService->setName('PHP-'.$commandName);
		$phpCommandService->setName( function() use ($mainService, $commandName) {
			return 'PHP-'.$commandName.'-'.$mainService->getName() ;
		});
		$phpCommandService->setImage( self::PHP_IMAGE );
		$phpCommandService->setRestart(Service::RESTART_START_ONCE);
		$this->addAppSource($phpCommandService);

		$phpCommandService->setEnvironmentVariablesCallback(function() use ($mainService) {
			return $mainService->getEnvironmentVariables();
		});

		$mainService->addSidekick($phpCommandService);
		return $phpCommandService;
	}

	/**
	 * @param Service $service
	 */
	public function setImage( Service $service ) {
		$service->setImage( self::PHP_IMAGE );

		if( $this->isDebug() ) {
			$dockerfile = new Dockerfile();
			$dockerfile->setFrom( self::PHP_IMAGE );
			$dockerfile->addInlineFile('/etc/confd/conf.d/xdebug.ini.toml',
				'[template]
src = "xdebug.ini.tpl"
dest = "/usr/local/etc/php/conf.d/30-xdebug.ini"
');
			$dockerfile->addInlineFile('/etc/confd/templates/xdebug.ini.tpl',
				'[xdebug]
xdebug.remote_enable=On
{{ if getenv "XDEBUG_REMOTE_HOST" }}
xdebug.remote_host={{ getenv "XDEBUG_REMOTE_HOST" }}
{{ else }}
xdebug.remote_connect_back=On
{{ end }}
xdebug.profiler_enable_trigger=On
xdebug.profiler_output_dir=/opt/profiling
xdebug.profiler_output_name=cachegrind.out.%t
');
			$dockerfile->run('apt-get update && apt-get -y install $PHPIZE_DEPS');
			$dockerfile->run('docker-php-source extract');

			$dockerfile->run('curl https://pecl.php.net/get/xdebug-2.1.4.tgz -o /tmp/xdebug.tgz ');
			$dockerfile->run('cd /usr/src && tar -xzf /tmp/xdebug.tgz && cd xdebug-2.1.4 && phpize && ./configure && make && make install && docker-php-ext-enable xdebug');

			$dockerfile->run('docker-php-source delete');
			$dockerfile->run('apt-get -y remove $PHPIZE_DEPS');
			$dockerfile->run('rm -rf /var/lib/apt/lists/*');

			$service->setImage( $dockerfile );
			$service->setEnvironmentVariable('XDEBUG_REMOTE_HOST', gethostname());
			if($this->debugListener !== null)
				$service->setEnvironmentVariable('XDEBUG_REMOTE_HOST', $this->debugListener);
		}
	}
}
