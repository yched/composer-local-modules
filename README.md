This composer plugin allows your custom drupal modules and themes, locally committed to your site's repository, to befine their dependecies in their own separate composer.json in the module folder, just like contrib modules do.

The plugin discovers modules and themes in the folders you specify in your project's root composer.json (see [configuration](#user-content-configuration) below), and automatically enforces them as required packages with no download during `composer install` / `composer update`. The dependencies specified in their composer.json thus get resolved and downloaded along with the rest of your project's contrib modules.

## Installation
`composer require yched/composer-local-modules "1.*"`

## Configuration
You need to specify in your root compser.json which directories to scan for local custom modules and themes :
```json
  "extra": {
    "local_directories": [
      "web/modules/custom",
      "web/themes/custom"
    ]
  }
```
Paths are relative to the project root compooser.json file (the example paths above are for the project template suggested by https://github.com/drupal-composer/drupal-project)
