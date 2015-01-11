# Image Resizing and Cropping Package for Laravel

This Laravel package provides a convenient way of resizing and cropping images.

Begin by installing this package through Composer. Edit your project's `composer.json` file to require `anakadote/image-manager`.

	"require-dev": {
		"anakadote/image-manager": "dev-master"
	}

Next, update Composer from the Terminal:

    composer update

Next, use artisan move the package assets to the public directory, also from the Terminal:

    php artisan asset:publish anakadote/image-manager

The final step is to add the service provider. Open `config/app.php`, and add a new item to the providers array.

    'Anakadote\ImageManager\ImageManagerServiceProvider'


## Usage

This package is accessible via a Laravel Facade so to use simply call the getImagePath() method on the Facade:

```php
<img src="{{ ImageManager::getImagePath($image->filename, 250, 200, 'crop') }}" alt="">

```

The getImagePath() method has five parameters, the first four of which are required:

1. Filename *(string)* The fully qualified name of image file.

2. Width *(integer)* Desired width of the image.

3. Height *(integer)* Desired height of the image.
4. Output Mode *(string)* The output mode. Options are:
  1. **crop**
  2. **fit** - Fit while maintaining aspect ratio
  3. **fit-x** - Fit to the given width while maintaining aspect ratio
  4. **fit-y** - Fit to the given height while maintaining aspect ratio

5. Image Quality - *(integer, 0 - 100)* Default value is 90
