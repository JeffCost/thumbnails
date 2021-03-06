<?php

class TestThumb extends PHPUnit_Framework_TestCase
{
    public $thumb;

    public $config;

    public $imagePath;

    public $imagePublicPath;

    public function __construct()
    {
        File::rmdir('/tmp/public/thumbnails');
    }

    /**
     * Setup the test enviornment.
     * 
     * @return void
     */
    public function setUp()
    {
        if( !defined('ADM_URI')) define('ADM_URI', 'admin');
        
        \Bundle::start('themes');
        \Bundle::start('thumbnails');

        $this->imagePath = realpath(dirname(__FILE__)).DS.'img';

        $this->config = new MockConfig;

        $this->imagePublicPath = $this->config->get('thumbnails::options.image_path', 'public/thumbnails/cache');

        $this->thumb = new Thumbnails\Thumb($this->config);

        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    /**
    * Tear down the testing environment.
    */
    public function tearDown()
    {
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testStoragePath()
    {
        $this->assertSame('/tmp/public/thumbnails/cache', $this->thumb->storage_path);
    }

    public function testSanitazeOptions()
    {
        $expected = array(
            'mode'     => 'inset',
            'size'     => '150x150',
            'resource' => false,
        );
        $this->assertEquals($expected, $this->thumb->sanitazeOptions(array('mode' => 'inset')));
        
        $expected = array(
            'mode'     => 'outbound',
            'size'     => '150x150',
            'resource' => false,
        );
        
        $this->assertEquals($expected, $this->thumb->sanitazeOptions('somestring'));
        $this->assertEquals($expected, $this->thumb->sanitazeOptions(array()));
        $this->assertEquals($expected, $this->thumb->sanitazeOptions(array('mode' => 'xxx')));

        $expected = array(
            'mode'     => 'inset',
            'size'     => '200x220',
            'resource' => true,
        );
        $this->assertEquals($expected, $this->thumb->sanitazeOptions(array('mode' => 'inset', 'size' => '200x220', 'resource' => true)));
    }

    public function testSanitazeSize()
    {
        $size = $this->thumb->sanitazeSize('22');
        $this->assertEquals(array('w' => 22, 'h' => 22), $size);

        $size = $this->thumb->sanitazeSize('33x');
        $this->assertEquals(array('w' => 33, 'h' => 33), $size);

        $size = $this->thumb->sanitazeSize('212x321');
        $this->assertEquals(array('w' => 212, 'h' => 321), $size);
    }

    public function testExtractImageName()
    {
        $this->assertSame('logo.jpg', $this->thumb->extractImageName('/public/tmp/logo.jpg'));
        $this->assertSame('image.jpg', $this->thumb->extractImageName('image.jpg'));
        $this->assertSame('externalImage.jpg', $this->thumb->extractImageName('htt://somesite.com/externalImage.jpg'));
    }

    public function testBoxSize()
    {
        $box = $this->thumb->boxSize('50x50');

        $this->assertEquals(50 , $box->getWidth());

        $this->assertEquals(50, $box->getHeight());

        $box = $this->thumb->boxSize('124X127');

        $this->assertEquals(124 , $box->getWidth());

        $this->assertEquals(127, $box->getHeight());

        $this->assertInstanceOf('Imagine\Image\Box', $box);
    }

    public function testMode()
    {
        $this->thumb->mode();
        $this->assertSame('outbound', $this->thumb->mode);

        $this->thumb->mode('inset');
        $this->assertSame('inset', $this->thumb->mode);
        
        $this->thumb->mode('outbound');
        $this->assertSame('outbound', $this->thumb->mode);

        $this->thumb->mode('unknown');
        $this->assertSame('outbound', $this->thumb->mode);        
    }

    public function testIsValidExtension()
    {
        $this->assertTrue($this->thumb->isValidExtension('.jpg'));
        $this->assertTrue($this->thumb->isValidExtension('image.jpg'));

        $this->assertFalse($this->thumb->isValidExtension('bmp'));
        $this->assertFalse($this->thumb->isValidExtension('image.bmp'));
    }

    public function testThumbnail()
    {
        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'image.jpg');
        $this->assertSame($this->imagePublicPath.'/150x150/outbound-image.jpg', $thumbPath);
    }

    public function testInvalidImageExtension()
    {
        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'bmp24.bmp');
        $this->assertSame($this->imagePublicPath.'/150x150/not-found.jpg', $thumbPath);
    }

    public function testThumbnailFromUrl()
    {
        $parameters = 'thumb/500x500/outbound/http://www.google.com/images/srpr/logo4w.png';
        // Since we are calling the
        // controller directlly here
        // we need to process logic
        // existent on the route
        $route = explode('/', $parameters);
        $method = isset($route[0]) ? $route[0] : 'thumb';
        array_shift($route);
        
        $response = Controller::call('thumbnails::frontend.thumbnails@thumb', $route);
        $this->assertInstanceOf('Laravel\\Response', $response);
        
        // testing with mode and zize
        $parameters = 'thumb/230x230/inbound/'.$this->imagePath;
        
        // Route logic
        $route = explode('/', $parameters);
        $method = isset($route[0]) ? $route[0] : 'thumb';
        array_shift($route);
        
        $response = Controller::call('thumbnails::frontend.thumbnails@thumb', $route);
        $this->assertInstanceOf('Laravel\\Response', $response);
    }

    public function testImageWithCustomName()
    {
        $options = array(
            'image_name' => 'new_image_name',
        );

        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'image.jpg', $options);
        $this->assertSame($this->imagePublicPath.'/150x150/outbound-new_image_name.jpg', $thumbPath);
    }

    public function testImageHaveRightSize()
    {
        $options = array(
            'mode'       => 'outbound',
            'size'       => '117x24',
            'resource'   => false,
        );

        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'image.jpg', $options);
        $this->assertSame($this->imagePublicPath.'/117x24/outbound-image.jpg', $thumbPath);

        list($width, $height) = getimagesize('/tmp/public/thumbnails/cache/117x24/outbound-image.jpg');
        $this->assertEquals(117, $width);
        $this->assertEquals(24, $height);
    }

    public function testIfThumbReturnPath()
    {
        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'image.jpg');
        $this->assertSame($this->imagePublicPath.'/150x150/outbound-image.jpg', $thumbPath);
    }

    public function testIfReturnResource()
    {
        $options = array(
            'resource'   => true,
        );

        $thumbPath = $this->thumb->thumbnail($this->imagePath, $options);
        $this->assertInstanceOf('Imagine\Gd\Image', $thumbPath);
    }

    public function testExternalImage()
    {
        $thumbPath = $this->thumb->thumbnail('http://www.google.com/images/srpr/logo4w.png');
        $this->assertSame($this->imagePublicPath.'/150x150/outbound-logo4w.png', $thumbPath);
    }

    public function testImageWithOptions()
    {
        $options = array(
            'mode'       => 'outbound',
            'size'       => '200x220',
            'resource'   => false,
            'image_name' => 'new_image',
        );

        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'image.jpg', $options);
        $this->assertSame($this->imagePublicPath.'/200x220/outbound-new_image.jpg', $thumbPath);

        // Set only one value to be used as
        // width and height is allowed
        $options = array(
            'mode'       => 'inset',
            'size'       => '180',
            'resource'   => false,
        );

        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'image.jpg', $options);
        $this->assertSame($this->imagePublicPath.'/180x180/inset-image.jpg', $thumbPath);

        list($width, $height) = getimagesize('/tmp/public/thumbnails/cache/180x180/inset-image.jpg');
        $this->assertEquals(180, $width);
        $this->assertEquals(180, $height);
    }

    public function testCacheImage()
    {
        $imagePath = realpath(dirname(__FILE__)).DS.'img'.DS.'image.jpg';
        
        $image = $this->thumb->cacheImage('image.jpg', $imagePath, '100x100', '/tmp/public/thumbnails/custom', 'outbound', false);
        $this->assertInstanceOf('Imagine\Gd\Image', $image);

        $image = $this->thumb->cacheImage('image.jpg', $imagePath, '200x200', '/tmp/public/thumbnails/new_folder', 'outbound', false);
        $this->assertInstanceOf('Imagine\Gd\Image', $image);
    }

    public function testIfPathIsLocal()
    {
        $this->assertTrue($this->thumb->isLocal($this->imagePath));
        $this->assertFalse($this->thumb->isLocal('http://google.com/outbound-image.jpg'));
    }

    public function testRegenerateCacheImage()
    {
        // This test must be the last test to
        // run so it will setup the cache time
        // correctly
        $this->config->set('settings::core.thumbnails_cache_time', 0);
        
        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'image.jpg');
        
        $this->assertSame($this->imagePublicPath.'/150x150/outbound-image.jpg', $thumbPath);
    }

    public function testDeleteImage()
    {
        $options = array(
            'mode'       => 'outbound',
            'size'       => '203x223',
            'resource'   => false,
            'image_name' => 'new_image',
        );

        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'image.jpg', $options);
        $this->assertSame($this->imagePublicPath.'/203x223/outbound-new_image.jpg', $thumbPath);


        $imageAbsolutePath = $this->thumb->storage_path.DS.'203x223'.DS.'outbound-new_image.jpg';
        
        Event::fire('thumbnails.delete', $imageAbsolutePath);
        
        $this->assertFalse(file_exists($imageAbsolutePath));
    }

    public function testDeleteMultipleImages()
    {
        $options = array(
            'mode'       => 'outbound',
            'size'       => '203x222',
            'resource'   => false,
            'image_name' => 'new_image',
        );

        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'image.jpg', $options);
        $this->assertSame($this->imagePublicPath.'/203x222/outbound-new_image.jpg', $thumbPath);
        $imageAbsolutePath  = $this->thumb->storage_path.DS.'203x222'.DS.'outbound-new_image.jpg';

        $options = array(
            'mode'       => 'outbound',
            'size'       => '203x224',
            'resource'   => false,
            'image_name' => 'new_image2',
        );

        $thumbPath = $this->thumb->thumbnail($this->imagePath.DS.'image.jpg', $options);
        $this->assertSame($this->imagePublicPath.'/203x224/outbound-new_image2.jpg', $thumbPath);
        $imageAbsolutePath1 = $this->thumb->storage_path.DS.'203x224'.DS.'outbound-new_image2.jpg';

        $imagePaths = array(
            $imageAbsolutePath,
            $imageAbsolutePath1,
        );
        
        Event::fire('thumbnails.delete', array($imagePaths));
        
        $this->assertFalse(file_exists($imageAbsolutePath));
        $this->assertFalse(file_exists($imageAbsolutePath1));
    }
}

class MockConfig extends \Laravel\Config {

    public static function get($key, $default = null)
    {
        switch ($key) {
            case 'settings::core.thumbnails_image_library':
                return 'Gd';
                break;
            case 'settings::core.thumbnails_storage_path';
                return '/tmp/public/thumbnails/cache';
                break;
            case 'thumbnails::options.image_path';
                return 'public/thumbnails/cache';
                break;

            case 'settings::core.thumbnails_allowed_image_extensions';
                return 'jpg,png';
                break;
            case 'thumbnails::options.error_image';
                return \Bundle::path('thumbnails').'images'.DS.'error'.DS.'not-found.jpg';
                break;
            case 'settings::core.thumbnails_cache_time':
                return parent::get($key, $default);
                break;
            
            default:
                return $default;
                break;
        }
        return $default;
    }
}
