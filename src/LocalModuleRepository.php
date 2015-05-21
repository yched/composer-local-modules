<?php

namespace yched\Composer\DrupalLocalModules;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\Loader\ArrayLoader;
use Composer\Repository\ArrayRepository;

class LocalModuleRepository extends ArrayRepository {

  /**
   * @var \Composer\Package\Loader\LoaderInterface
   */
  protected $loader;

  /**
   * @var array
   */
  protected $directories;

  protected $config;

  public function __construct(array $repoConfig, IOInterface $io, Config $config = null)
  {
    $this->loader = new ArrayLoader();
    $this->directories = $repoConfig['directories'];
    $this->io = $io;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  protected function initialize() {
    parent::initialize();

    foreach ($this->directories as $directory) {
      $this->scanDirectory($directory);
    }
  }

  protected function scanDirectory($path)
  {
    if (!file_exists($path)) {
      return;
    }

    $io = $this->io;

    $flags = \FilesystemIterator::UNIX_PATHS;
    $flags |= \FilesystemIterator::SKIP_DOTS;
    $flags |= \FilesystemIterator::FOLLOW_SYMLINKS;
    $directory = new \RecursiveDirectoryIterator($path, $flags);
    $iterator = new \RecursiveIteratorIterator($directory);
    $regex = new \RegexIterator($iterator, '|/composer.json$|i');
    foreach ($regex as $file) {
      /* @var $file \SplFileInfo */

      if (!$file->isFile()) {
        continue;
      }

      // Only bother if this is a drupal extension.
      $moduleInfo = glob($file->getPath() . '/*.info.yml');
      if (!$moduleInfo) {
        continue;
      }

      // Grab the module name from the info file.
      $name = basename(current($moduleInfo), '.info.yml');

      // Create the corresponding package.
      $package = $this->createPackage($file, $name);
      if (!$package) {
        if ($io->isVerbose()) {
          $io->writeError("File <comment>{$file->getPathname()}</comment> doesn't seem to hold a package");
        }
        continue;
      }

      if ($io->isVerbose()) {
        $template = 'Found package <info>%s</info> (<comment>%s</comment>) in file <info>%s</info>';
        $io->writeError(sprintf($template, $package->getName(), $package->getPrettyVersion(), $file->getBasename()));
      }

      $this->addPackage($package);
    }
  }

  /**
   * Not used atm - Alternate version copied from Drupal core for discovering info.yml
   */
  protected function drupalCoreScanDirectory($dir) {
    $files = array();

    // In order to scan top-level directories, absolute directory paths have to
    // be used (which also improves performance, since any configured PHP
    // include_paths will not be consulted). Retain the relative originating
    // directory being scanned, so relative paths can be reconstructed below
    // (all paths are expected to be relative to $this->root).
    $dir_prefix = ($dir == '' ? '' : "$dir/");
    $absolute_dir = ($dir == '' ? $this->root : $this->root . "/$dir");

    if (!is_dir($absolute_dir)) {
      return $files;
    }
    // Use Unix paths regardless of platform, skip dot directories, follow
    // symlinks (to allow extensions to be linked from elsewhere), and return
    // the RecursiveDirectoryIterator instance to have access to getSubPath(),
    // since SplFileInfo does not support relative paths.
    $flags = \FilesystemIterator::UNIX_PATHS;
    $flags |= \FilesystemIterator::SKIP_DOTS;
    $flags |= \FilesystemIterator::FOLLOW_SYMLINKS;
    $flags |= \FilesystemIterator::CURRENT_AS_SELF;
    $directory_iterator = new \RecursiveDirectoryIterator($absolute_dir, $flags);

    // Filter the recursive scan to discover extensions with a composer.json
    // only.
    $filter = new RecursiveExtensionFilterIterator($directory_iterator);

    // The actual recursive filesystem scan is only invoked by instantiating the
    // RecursiveIteratorIterator.
    $iterator = new \RecursiveIteratorIterator($filter,
      \RecursiveIteratorIterator::LEAVES_ONLY,
      // Suppress filesystem errors in case a directory cannot be accessed.
      \RecursiveIteratorIterator::CATCH_GET_CHILD
    );

    foreach ($iterator as $key => $fileinfo) {
      $name = $fileinfo->getBasename('.info.yml');
      // All extension names in Drupal have to be valid PHP function names due
      // to the module hook architecture.
      if (!preg_match(static::PHP_FUNCTION_PATTERN, $name)) {
        continue;
      }
      $pathname = $dir_prefix . $fileinfo->getSubPathname();

      $files[$key] = $pathname;
//      static::readJson($fileinfo->getPathName() . '/composer.json');
    }
    return $files;
  }

  /**
   * @return \Composer\Package\PackageInterface
   */
  protected function createPackage(\SplFileInfo $file, $name) {
    $filename = $file->getPathname();
    if (!is_readable($filename)) {
      throw new \RuntimeException(strtr('%filename is not readable.', array('%filename' => $filename)));
    }
    $json = file_get_contents($filename);
    if ($json === FALSE) {
      throw new \RuntimeException(strtr('Could not read %filename', array('%filename' => $filename)));
    }

    $packageData = JsonFile::parseJson($json);

    if (empty($packageData['name'])) {
      $packageData['name'] = 'drupal/' . $name;
    }
    // Force a high version number so that the local version takes precedency
    // if the package exists on packagist (e.g local fork).
    $packageData['version'] = '9999999999';
    $packageData['type'] = 'drupal-local';
    $packageData['dist'] = array(
      // @todo Not sure what this is used for...
      'type' => 'local',
      'url' => $file->getRealPath(),
      'shasum' => sha1_file($file->getRealPath())
    );


    return $this->loader->load($packageData);
  }

}
