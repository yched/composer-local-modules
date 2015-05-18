<?php

namespace yched\Composer\DrupalLocalModules;

use Composer\Installer\NoopInstaller;

/**
 * This is just a noop installer to enable packages of type "drupal-local"
 */
class LocalModuleInstaller extends NoopInstaller {
  
	/**
	 * {@inheritdoc}
	 */
	public function supports($packageType) {
		return $packageType == 'drupal-local';
	}

}
