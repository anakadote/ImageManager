<?php

namespace Anakadote\ImageManager;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ImageManager
{
    protected $file;
    protected $filePath;
    protected $urlPath;
    protected $filename;
    protected $errorFile;
    protected $image;
    protected $temp;
    protected $mode; // crop, crop-top, crop-bottom, fit, fit-x, fit-y
    protected $url;
    protected $width;
    protected $height;
    protected $quality;
    protected $imageInfo;
    protected $errors = [];
    
    /** 
     * Constructor.
     *
     * @param  string  $errorFilename
     * @throws \Exception  If the GD library is not available.
     */
    public function __construct($errorFilename = 'error.jpg')
    {        
        $this->errorFile = public_path() . '/vendor/anakadote/image-manager/' . $errorFilename;
        
        if (! function_exists('gd_info')) {
            throw new Exception('GD Library is required in package Anakadote\ImageManager.');
        }
        
        ini_set('memory_limit', '512M');
    }
    
    /** 
     * Resize image according to supplied parameters, and return its path.
     *
     * @param  string       $file  Path to the file.
     * @param  int          $width
     * @param  int          $height
     * @param  string       $mode
     * @param  int          $quality
     * @param  string|null  $format  Convert the image to the given format/extension i.e. "webp".
     * @return string
     */
    public function getImagePath($file, $width, $height, $mode, $quality = 90, $format = null)
    {
        // Separate file into name and paths.
        $this->parseFileName($file);
        
        $this->width = $width;
        $this->height = $height;
        $this->mode = $mode;
        $this->quality = $quality;
        
        // Use error image if file cannot be found.
        if (empty($file) || ! file_exists($file) || is_dir($file)) {
            return $this->errorHandler();
        }
        
        // File already there so don't bother creating it.
        if (file_exists($this->getPath(true, $format))) {
            return $this->getPath(false, $format);
        }

        // SVG? Simply return the URL path to the image.
        if ($this->getExtension($this->filename) === 'svg') {
            return $this->urlPath . $this->filename;
        }
        
        // Make sure file type is supported.
        $this->imageInfo = getimagesize($this->file);
        if (! $this->imageInfo || ! isset($this->imageInfo['mime'])) {
            $this->errors[] = 'Invalid file type';
            return $this->errorHandler();
        }
        
        switch ($this->imageInfo['mime']) {
            
            case 'image/gif':
                if (imagetypes() & IMG_GIF) {
                    $this->image = imagecreatefromgif ($this->file);
                } 
                else {
                    $this->errors[] = 'GIF images are not supported';
                    return $this->errorHandler();
                }
                break;
                
            case 'image/jpeg':
            case 'image/jpg':
                if (imagetypes() & IMG_JPG) {
                    $this->adjustImageOrientation();
                    $this->image = imagecreatefromjpeg($this->file);
                } 
                else {
                    $this->errors[] = 'JPG images are not supported';
                    return $this->errorHandler();
                }
                break;
                
            case 'image/png':
                if (imagetypes() & IMG_PNG) {
                    $this->image = imagecreatefrompng($this->file);
                } 
                else {
                    $this->errors[] = 'PNG images are not supported';
                    return $this->errorHandler();
                }
                break;
                
            case 'image/webp':
                if (imagetypes() & IMG_WEBP) {
                    $this->image = imagecreatefromwebp($this->file);
                } 
                else {
                    $this->errors[] = 'WEBP images are not supported';
                    return $this->errorHandler();
                }
                break;
                
            default:
                $this->errors[] = $this->imageInfo['mime'] . ' images are not supported';
                return $this->errorHandler();
        }

        $this->resize();

        if ($format) {
            $this->convertAndSave($format);
        } else {
            $this->save();
        }
        
        return $this->getPath(false, $format);
    }
    
    /** 
     * Get full image path including filename.
     *
     * @param  bool  $fromRoot  If true, return fully qualified path. If false, return public path to image.
     * @return string
     */
    public function getPath($fromRoot = false, $format = null)
    {
        $filename = $this->filename;
        if ($format) {
            $parts = explode('.', $this->filename);
            $filename = $parts[0] . '.' . $format;
        }

        return $this->getFolder($fromRoot) . $filename;
    }
    
    /**
     * Get the directory of the image if it exists, otherwise, create it and return it.
     *
     * @param  bool  $fromRoot  If true, returns fully qualified path. If false, returns public path to image.
     * @throws \Exception
     * @return string
     */
    protected function getFolder($fromRoot = false)
    {
        $foldername = $this->width . "-" . $this->height; // First make dimensions folder.
        
        if (! file_exists($this->filePath . "/" . $foldername)) {
            
            if (! mkdir($this->filePath . "/" . $foldername, 0777)) {
                throw new Exception('Error creating directory');
            }
        }
        
        $foldername = $foldername . "/" . $this->mode; // Then make mode folder.
        if (! file_exists($this->filePath . "/" . $foldername)) { 
            
            if (! mkdir($this->filePath . "/" . $foldername, 0777)) {
                throw new Exception('Error creating directory');
            }
        }
        
        if ($fromRoot) {
            return $this->filePath . "/" . $this->width . "-" . $this->height . "/" . $this->mode;
        }
        
        return $this->urlPath . "/" . $this->width . "-" . $this->height . "/" . $this->mode;
    }
    
    /** 
     * Get an array of errors.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * Separate file name into name and path.
     *
     * @param  string  $file
     */
    private function parseFileName($file)
    {
        $this->file     = str_replace('\\', '/', $file);
        $this->filePath = dirname($this->file);
        $this->urlPath  = str_replace(str_replace('\\', '/', public_path()), "", $this->filePath);
        $this->filename = str_replace($this->filePath, "", $this->file);
    }
    
    /** 
     * Resize an image using the provided mode and dimensions.
     *
     * @throws \Exception
     */
    protected function resize()
    {
        $width = $this->width;
        $height = $this->height;
        $origWidth = imagesx($this->image);
        $origHeight = imagesy($this->image);
        
        // Determine new image dimensions.
        if (in_array($this->mode, ['crop', 'crop-top', 'crop-bottom'])) { // Crop image
            
            $maxWidth = $cropWidth = $width;
            $maxHeight = $cropHeight = $height;
        
            $xRatio = $origWidth > 0 ? ($maxWidth / $origWidth) : 0;
            $yRatio = $origHeight > 0 ? ($maxHeight / $origHeight) : 0;
            
            if ($origWidth > $origHeight) { // Original is wide.
                $height = $maxHeight;
                $width = ceil($yRatio * $origWidth);
                
            } elseif ($origHeight > $origWidth) { // Original is tall.
                $width = $maxWidth;
                $height = ceil($xRatio * $origHeight);
                
            } else { // Original is square.
                $width = $maxWidth;
                $height = $maxWidth;
            }
            
            // Adjust if the crop width is less than the requested width to avoid black lines.
            if ($width < $cropWidth) {
                $width = $maxWidth;
                $height = ceil($xRatio * $origHeight);
            }
            
        } elseif ($this->mode === 'fit') { // Fits the image according to aspect ratio to within max height and width.
            $maxWidth = $width;
            $maxHeight = $height;
        
            $xRatio = $origWidth > 0 ? ($maxWidth / $origWidth) : 0;
            $yRatio = $origHeight > 0 ? ($maxHeight / $origHeight) : 0;
            
            if (($origWidth <= $maxWidth) && ($origHeight <= $maxHeight)) { // Image is smaller than max height and width so don't resize.
                $tnWidth = $origWidth;
                $tnHeight = $origHeight;
            
            } elseif (($xRatio * $origHeight) < $maxHeight) { // Wider rather than taller.
                $tnHeight = ceil($xRatio * $origHeight);
                $tnWidth = $maxWidth;
            
            } else { // Taller rather than wider
                $tnWidth = ceil($yRatio * $origWidth);
                $tnHeight = $maxHeight;
            }
            
            $width = $tnWidth;
            $height = $tnHeight;
            
        } elseif ($this->mode === 'fit-x') { // Sets the width to the max width and the height according to aspect ratio (will stretch if too small).
            $height = $origWidth > 0 ? round($origHeight * $width / $origWidth) : 0;
            
            if ($origHeight <= $height) { // Don't stretch if smaller.
                $width = $origWidth;
                $height = $origHeight;
            }
            
        } elseif ($this->mode === 'fit-y') { // Sets the height to the max height and the width according to aspect ratio (will stretch if too small).
            $width = $origHeight > 0 ? round($origWidth * $height / $origHeight) : 0;
            
            if ($origWidth <= $width) { // Don't stretch if smaller.
                $width = $origWidth;
                $height = $origHeight;
            }
        } else {
            throw new Exception('Invalid mode: ' . $this->mode);
        }
        

        // Resize.
        $this->temp = imagecreatetruecolor($width, $height);
        
        // Preserve transparency if a png.
        if ($this->imageInfo['mime'] == 'image/png') {
            imagealphablending($this->temp, false);
            imagesavealpha($this->temp, true);
        }
        
        imagecopyresampled($this->temp, $this->image, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
        $this->sync();
        
        
        // Cropping?
        if (in_array($this->mode, ['crop', 'crop-top', 'crop-bottom'])) {
            $origWidth  = imagesx($this->image);
            $origHeight = imagesy($this->image);

            // Crop from the horizontal middle.
            $xMid = $origWidth / 2;
            $srcX = ($xMid - ($cropWidth / 2));

            switch ($this->mode) {

                // Crop from the top.
                case 'crop-top':
                    $srcY = 0;
                    break;

                // Crop from the bottom.
                case 'crop-bottom':
                    $srcY = $origHeight - $cropHeight;
                    break;

                // Crop from the vertical middle.
                default:
                    $yMid = $origHeight / 2;
                    $srcY = ($yMid - ($cropHeight / 2));
            }
            
            $this->temp = imagecreatetruecolor($cropWidth, $cropHeight);
            
            // Preserve transparency if a png.
            if ($this->imageInfo['mime'] == 'image/png') {
                imagealphablending($this->temp, false);
                imagesavealpha($this->temp, true);
            }

            imagecopyresampled($this->temp, $this->image, 0, 0, $srcX, $srcY, $cropWidth, $cropHeight, $cropWidth, $cropHeight);
            $this->sync();
        }
    }
    
    /**
     * Correct the image's orientation (due to digital cameras).
     */
    protected function adjustImageOrientation()
    {        
        $exif = @exif_read_data($this->file);
        
        if ($exif && isset($exif['Orientation'])) {
            $orientation = $exif['Orientation'];
            
            if ($orientation != 1) {
                $img = imagecreatefromjpeg($this->file);
                
                $mirror = false;
                $deg    = 0;
                
                switch ($orientation) {
                    case 2:
                        $mirror = true;
                        break;
                    case 3:
                        $deg = 180;
                        break;
                    case 4:
                        $deg = 180;
                        $mirror = true;
                        break;
                    case 5:
                        $deg = 270;
                        $mirror = true;
                        break;
                    case 6:
                        $deg = 270;
                        break;
                    case 7:
                        $deg = 90;
                        $mirror = true;
                        break;
                    case 8:
                        $deg = 90;
                        break;
                }
                
                if ($deg)    $img = imagerotate($img, $deg, 0);
                if ($mirror) $img = $this->mirrorImage($img);
                
                $this->image = str_replace('.jpg', "-O{$orientation}.jpg", $this->file);
                imagejpeg($img, $this->file, $this->quality);
            }
        }
    }
    
    /**
     * Flip/mirror an image.
     *
     * @param  resource  $image
     * @return resource
     */
    protected function mirrorImage($image)
    {
        $width  = imagesx($image);
        $height = imagesy($image);
        
        $srcX = $width -1;
        $srcY = 0;
        $srcWidth  = -$width;
        $srcHeight = $height;
        
        $imgdest = imagecreatetruecolor($width, $height);
        
        if (imagecopyresampled($imgdest, $image, 0, 0, $srcX, $srcY, $width, $height, $srcWidth, $srcHeight)) {
            return $imgdest;
        }
        
        return $image;
    }
    
    /** 
     * Get a file name's extension.
     *
     * @param  string  $file
     * @return string
     */
    public function getExtension($filename)
    {    
        $parts = explode('.', $filename);
        return strtolower(array_pop($parts));
    }
    
    /** 
     * Generate a unique file name within a given destination.
     *
     * @param  string  $file
     * @param  string  $destination
     * @param  string
     */
    public function getUniqueFilename($filename, $destination)
    {    
        $filename = $this->slug($filename);
        
        if (! file_exists($destination . $filename)) {
            return $filename;
        }
        
        $parts = explode('.', $filename);
        $filename = $parts[0] .= '-' . uniqid() . '.' . $this->getExtension($filename);
        
        return $this->getUniqueFilename($filename, $destination);
    }
    
    /** 
     * Delete an image and all generated child images.
     *
     * @param  string  $file
     */
    public function deleteImage($file)
    {    
        // Separate file into name and paths
        $this->parseFileName($file);
        
        $dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->filePath));
        foreach ($dir as $dirFile) {
            if ($this->filename == '/' . basename($dirFile)) {
                unlink($dirFile);
            }
        }
    }
    
    /** 
     * Set the $image as an alias of $temp, then unset $temp.
     */
    protected function sync()
    {
        $this->image =& $this->temp;
        unset($this->temp);
    }
    
    /** 
     * Send image header.
     *
     * @param  string  $mime  Mime type of the image to be displayed.
     */
    protected function sendHeader($mime = 'jpeg')
    {
        header('Content-Type: image/' . $mime);
    }
    
    /** 
     * Display image to screen.
     */
    protected function show()
    {
        switch ($this->imageInfo['mime']) {
            case 'image/gif':
                $this->sendHeader('gif');
                imagegif($this->image, '');
                break;
            
            case 'image/jpeg':
                $this->sendHeader('jpg');
                imagejpeg($this->image, '', $this->quality);
                break;
            
            case 'image/jpg':
                $this->sendHeader('jpg');
                imagejpeg($this->image, '', $this->quality);
                break;
            
            case 'image/png':
                $this->sendHeader('png');
                imagepng($this->image, '', round($this->quality / 10));
                break;
            
            default:
                $this->errors[] = $this->imageInfo['mime'] . ' images are not supported';
                return $this->errorHandler();
        }
    }
    
    /** 
     * Save image to server.
     */
    protected function save()
    {
        switch ($this->imageInfo['mime']) {
            case 'image/gif':
                imagegif($this->image, $this->getPath(true));
                break;
            
            case 'image/jpeg':
            case 'image/jpg':
                imagejpeg($this->image, $this->getPath(true), $this->quality);
                break;
            
            case 'image/png':
                imagepng($this->image, $this->getPath(true), round($this->quality / 10));
                break;
            
            case 'image/webp':
                imagewebp($this->image, $this->getPath(true), $this->quality);
                break;
            
            default:
                $this->errors[] = $this->imageInfo['mime'] . ' images are not supported';
                return $this->errorHandler();
        }
        
        chmod($this->getPath(true), 0777);
    }
    
    /** 
     * Convert image and to server.
     *
     * @param  string  $format
     */
    protected function convertAndSave($format)
    {
        switch ($format) {
            case 'gif':
                imagegif($this->image, $this->getPath(true, $format));
                break;
            
            case 'jpeg':
            case 'jpg':
                imagejpeg($this->image, $this->getPath(true, $format), $this->quality);
                break;
            
            case 'png':
                imagepng($this->image, $this->getPath(true, $format), round($this->quality / 10));
                break;
            
            case 'webp':
                imagewebp($this->image, $this->getPath(true, $format), $this->quality);
                break;
            
            default:
                $this->errors[] = $format . ' images are not supported';
                return $this->errorHandler();
        }
        
        chmod($this->getPath(true, $format), 0777);
    }
    
    /** 
     * Display error image.
     */
    protected function errorHandler()
    {
        $this->file = $this->errorFile;
        
        if (file_exists($this->file)) {
            return $this->getImagePath($this->file, $this->width, $this->height, $this->mode, $this->quality);
        }
        
        $this->errors[] = 'Error image not found.';
    }
    
    /**
     * Generate a filename "slug".
     *
     * @param  string  $filename
     * @return string
     */
    private function slug($filename)
    {
        // Replace '_' with the word '-'
        $filename = preg_replace('![\_]+!u', '-', $filename);

        // Replace @ with the word 'at'
        $filename = str_replace('@', '-at-', $filename);

        // Remove all characters that are not the separator, letters, numbers, a period, or whitespace.
        $filename = preg_replace('![^\-\.\pL\pN\s]+!u', '', mb_strtolower($filename));

        // Replace all separator characters and whitespace by a single separator
        $filename = preg_replace('![\-\s]+!u', '-', $filename);

        return trim($filename, '-');
    }
    
    /** 
     * Destructor: Destroy image references from memory.
     */
    public function __destruct()
    {
        if (isset($this->image)) imageDestroy($this->image);
        if (isset($this->temp)) imageDestroy($this->temp);
    }
}
