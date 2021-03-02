# drupal-config-importer
Utilities for importing Drupal configuration.

You may find other uses for this functionality, but it was initially developed for use in update hooks. It's generally assumed that during deployments you will want to run database updates before configuration import, because there is more flexibility in the update hooks. What this leads to is cases where a bunch of ugly code is needed to "rough-out" the necessary configuration that the update hooks depend on.

## Usage

For example, you may want to populate an `article_types` taxonomy vocabulary automatically upon deployment. This requires creating the taxonomy vocabulary prior to the term population, if it does not already exist.

Using this config importer, doing so is as easy as the following:

Copy the `taxonomy.vocabulary.article_types.yml` file into the `config/update/8003` folder within `my_module`. This allows for the vocabulary to be installed with exact/known values, not vulerable to future changes.

Then add the update hook and the population code:

```
function my_module_update_8003() {
  $path = drupal_get_path('module', 'my_module') . '/config/update/8003';
  $i = Kerasai\DrupalConfigImporter\ConfigImporter::create();
  $i->import('taxonomy.vocabulary.article_types', $path);
  _my_module_populate_article_types();
}

function _my_module_populate_article_types() {
  // Add article_types taxonomy terms here.
}
```

## Additional functionality

To obtain an importer ready to rock and roll, simply call the `Kerasai\DrupalConfigImporter\ConfigImporter::create` static method. This will create an importer using the necessary Drupal services. Then you may use the `::import` method to import configuration by its configuration ID (filename, minus the `.yml`).

The importer's `::import` method has logic built-in to determine if the config being imported is a first-class ConfigEntity or general "simple" configuration. Alternatively, you may call the importer's `::importConfig` or `::importConfigEntity` methods.
