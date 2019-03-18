
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
php composer.phar require ekouk/imagecleaner "*"
```

or add

```
"ekouk/imagecleaner": "*"
```

to the require section of your `composer.json` file and run ``composer install``

Once the files have been installed to app/code/EkoUK/ImageCleaner

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

