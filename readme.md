# Image Resizing and Cropping Package for Laravel 4

This Laravel 4 package provides a convenient way of resizing and cropping images.


## Laravel 5

If you're using Laravel 5, then [use this package instead](https://github.com/anakadote/ImageManager-for-Laravel-5).


## Installation

Begin by installing this package through Composer. Edit your project's `composer.json` file to require `anakadote/image-manager`.

	"require-dev": {
		"anakadote/image-manager": "dev-master"
	}

Next, update Composer from the Terminal:

    composer update

Next, use artisan to move the package assets to the public directory, also from the Terminal:

    php artisan asset:publish anakadote/image-manager

The final step is to add the service provider. Open `config/app.php` and add a new item to the providers array.

    'Anakadote\ImageManager\ImageManagerServiceProvider'


## Usage

This package is accessible via a Laravel Facade so to use simply call its methods on the Facade "ImageManager".


### getImagePath($filename, $width, $height, $mode, $quality=90)

Resize or crop an image and get the newly generated image's web path.

```php
<img src="{{ ImageManager::getImagePath( public_path() . '/img/' . $image->filename, 250, 200, 'crop' ) }}" alt="">

```

The getImagePath() method has five parameters, the first four of which are required:

1. File Name *(string)* The fully qualified name of image file. The file must reside in your app's `public` directory. You'll need to grant write access by the web server to the `public` directory and its children.

2. Width *(integer)* Desired width of the image.

3. Height *(integer)* Desired height of the image.
4. Output Mode *(string)* The output mode. Options are:
  1. **crop**
  2. **fit** - Fit while maintaining aspect ratio
  3. **fit-x** - Fit to the given width while maintaining aspect ratio
  4. **fit-y** - Fit to the given height while maintaining aspect ratio

5. Image Quality - *(integer, 0 - 100)* Default value is 90


***


### deleteImage($filename)

Delete an image including all resized and/or cropped images generated from it.

```php
<?php

ImageManager::deleteImage( public_path() . '/img/' . $image->filename );

```

The deleteImage() method has a single parameter which is the fully qualified name of the original image file. This method will recursively delete all generated images from the original image file.
