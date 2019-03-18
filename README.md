
Magento2 Delete Unused Product Images
=============================
Command Line module to validate database images and remove from pub/media/catalog/product which are not present in the db.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Add the following to your Magento2 composer.json repositories section:-

```
"repositories": [
   {
     "type": "vcs",
     "url": "https://github.com/cannycookie/Mage2UnusedImageRemover"
   }
 ],
```
Then run
```
composer require ekouk/imagecleaner "dev-master"
```

or add

```
"ekouk/imagecleaner": "dev-master"
```

to the require section of your `composer.json` file and run ``composer install``

Once the files have been installed to vendor/ekouk/imagecleaner

Enable the module:-

```
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Usage
-----

Run in check only mode
```
bin/magento ekouk:cleanimages
```

Run and delete images
```
bin/magento ekouk:cleanimages -d
```

