<?php
/**
 * @file
 * Automatically adds modules present in a local directory as composer packages.
 */

namespace yched\Composer\DrupalLocalModules;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Package\LinkConstraint\VersionConstraint;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
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
   * @var LocalModuleRepository
   */
  protected $localRepo;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ScriptEvents::PRE_INSTALL_CMD => "addLocalRepo",
      ScriptEvents::PRE_UPDATE_CMD => "addLocalRepo",
      InstallerEvents::PRE_DEPENDENCIES_SOLVING => "addLocalPackages",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->io = $io;
    $this->composer = $composer;
  }

  /**
   * Adds the "local_folders" repository.
   *
   * @param Event $event
   */
  public function addLocalRepo(Event $event) {
    $extra = $this->composer->getPackage()->getExtra();
    if (!empty($extra['local_directories'])) {
      $repositoryManager = $this->composer->getRepositoryManager();
      $installationManager = $this->composer->getInstallationManager();

      // Register our "local_folders" repository type, and the associated (no-op) installer.
      // There is no way to add it first so that it take precedency on the remote ones,
      // but we enforce and then require a high version on the local packages, so that they
      // always win.
      $repositoryManager->setRepositoryClass('local_folders', 'yched\Composer\DrupalLocalModules\LocalModuleRepository');
      $this->localRepo = $repositoryManager->createRepository('local_folders', [
        'directories' => $extra['local_directories'],
      ]);
      $repositoryManager->addRepository($this->localRepo);
      $installationManager->addInstaller(new LocalModuleInstaller());
    }
  }

  /**
   * Adds all discovered local packages as requirements.
   *
   * @param InstallerEvent $event
   */
  public function addLocalPackages(InstallerEvent $event) {
    if (isset($this->localRepo)) {
      $request = $event->getRequest();
      foreach ($this->localRepo->getPackages() as $package) {
        /** @var $package \Composer\Package\PackageInterface */
        $request->install($package->getName(), new VersionConstraint('=', $package->getVersion()), TRUE);
      }
    }
  }

}
