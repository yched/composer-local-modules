<?php
/**
 * @file
 * Automatically adds modules present in a local directory as composer packages.
 */

namespace yched\Composer\DrupalLocalModules;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\LinkConstraint\VersionConstraint;
use Composer\Script\Event;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\ScriptEvents;

class Plugin implements PluginInterface, EventSubscriberInterface {

  /**
   * @var Composer
   */
  protected $composer;

  /**
   * @var IOInterface
   */
  protected $io;

  /**
   * @var array
   */
  protected $localDirectories = [];

  public function activate(Composer $composer, IOInterface $io) {
    $this->io = $io;
    $this->composer = $composer;

    // Register our "local_folders" repositorty type, and the associated (no-op) installer.
    $this->composer->getInstallationManager()->addInstaller(new LocalModuleInstaller());
    $repositoryManager = $this->composer->getRepositoryManager();
    $repositoryManager->setRepositoryClass('local_folders', 'yched\Composer\DrupalLocalModules\LocalModuleRepository');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ScriptEvents::PRE_INSTALL_CMD => "addLocalPackages",
      ScriptEvents::PRE_UPDATE_CMD => "addLocalPackages",
    ];
  }

  public function addLocalPackages(Event $event) {
    $extra = $this->composer->getPackage()->getExtra();
    if (isset($extra['local_directories'])) {
      // Add our "local_folders" repository.
      $repositoryManager = $this->composer->getRepositoryManager();
      $localRepo = $repositoryManager->createRepository('local_folders', [
        'directories' => $extra['local_directories'],
      ]);
      // @todo We should add the repo in first position so that it takes precedency.
      $repositoryManager->addRepository($localRepo);

      // Add all discovered local packages as requirements to the top-level composer.json
      $rootPackage = $this->composer->getPackage();
      $requires = $rootPackage->getRequires();
      foreach ($localRepo->getPackages() as $package) {
        /** @var $package \Composer\Package\PackageInterface */
        $contraint = new VersionConstraint('=', $package->getVersion());
        $requires[$package->getName()] = new Link($rootPackage->getName(), $package->getName(), $contraint, 'requires', $package->getPrettyVersion());
      }
      $rootPackage->setRequires($requires);
    }
  }

}
