<?php 

/**
* This file is part of the Thumbnails Mwi Module (https://github.com/Myopengrid/thumbnails).
*
*/

namespace Thumbnails;

use Laravel\Config;
use Laravel\Log;

/**
 * Thumb Class
 *
 * @author Jefferson A. Costella <jefersonc@gmail.com>
 */

class Thumb
{
    public $imagine;
    
    public $mode;
    
    public $config;
    
    public $image_path;

    public $storage_path;

     /**
     * Class constructor
     *
     * @return void
     */
    public function __construct(Config $config = null, $path = null)
    {
        $this->config = is_null($config) ? new Config : $config;

        $this->storage_path = is_null($path) ? $this->config->get('settings::core.thumbnails_storage_path', path('public').DS.'media'.DS.'thumbnails'.DS.'cached') : $path;

        $this->image_path = $this->config->get('thumbnails::options.image_path', 'media/thumbnails/cached');

        $lib = $this->config->get('settings::core.thumbnails_image_library', 'Gd');
        
        $Imagine = "Imagine\\{$lib}\\Imagine";
        
        $this->imagine = new $Imagine();
    }

    public function sanitazeOptions($options)
    {
        $result = array(
            'mode'     => 'outbound',
            'size'     => '150x150',
            'resource' => false,
        );

        if( !is_array($options)) return $result;
        
        if(isset($options['mode']) and !empty($options['mode']))
        {
            $result['mode'] = $options['mode'] == 'inset' ? 'inset' : 'outbound';
        }

        if(isset($options['size']) and !empty($options['size']))
        {
            $result['size'] = $options['size'];
        }

        if(isset($options['resource']) and $options['resource'] !== false)
        {
            $result['resource'] = true;
        }

        return $result;
    }

    public function sanitazeSize($size)
    {
        $result = array(
            'w' => 150,
            'h' => 150,
        );

        $sizeParts = explode('X', strtoupper($size));
        
        $result['w'] = (isset($sizeParts[0]) and is_numeric($sizeParts[0])) ? $sizeParts[0] : 100;
        $result['h'] = (isset($sizeParts[1]) and is_numeric($sizeParts[1])) ? $sizeParts[1] : $result['w'];

        return $result;
    }

    public function extractImageName($imagePath)
    {
        $parts = explode('/', $imagePath);

        return $parts[count($parts)-1];
    }

    /**
     * Get Box Size
     *
     * @param string $size image size
     *
     * @return Imagine\Image\Box
     */
    public function boxSize($size)
    {
        $size = explode('X', strtoupper($size));
        $w    = ( isset($size[0]) and is_numeric($size[0]) ) ? $size[0] : 100;
        $h    = ( isset($size[1]) and is_numeric($size[1]) ) ? $size[1] : $w;

        return new \Imagine\Image\Box($w, $h);
    }

    /**
     * Image mode
     *
     * @param string $mode mode
     *
     * @return Imagine\Image\Box
     */
    public function mode($mode = null)
    {
        if(is_null($mode))
        {
            $this->mode = $this->config->get('settings::core.thumbnails_image_mode', 'outbound');
        }
        else
        {
            $mode = strtolower($mode);
            $this->mode = $mode == 'inset' ? 'inset' : 'outbound';   
        }

        return $this->mode;
    }

    public function isValidExtension($url)
    {
        return in_array(strtolower(get_file_extension($url)), explode(',', $this->config->get('settings::core.thumbnails_allowed_image_extensions', 'png,jpg,jpeg,gif'))); 
    }

    public function isLocal($url)
    {
        return !(bool)preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
    }

    /**
     * Resize Image
     *
     * @param string $size image size
     * @param string $url  image url
     *
     * @return Response
     */
    public function thumbnail($path, $options = array())
    {
        $Imagine =& $this->imagine;

        // extract $mode, $size and $resource variables
        $opt      = $this->sanitazeOptions($options);
        $mode     = $opt['mode'];
        $size     = $opt['size'];
        $resource = $opt['resource'];
        // php 5.3 throw exception
        // extract($this->sanitazeOptions($options));
        
        $imageName = $mode.'-'.$this->extractImageName($path);

        if(isset($options['image_name']) and !empty($options['image_name']))
        {
            $imageName = $mode.'-'.$options['image_name'].'.'.get_file_extension($imageName);
        }

        // Extract the $w and $h variables
        $opt = $this->sanitazeSize($size);
        $w   = $opt['w'];
        $h   = $opt['h'];
        // php 5.3 throw exception
        //extract($this->sanitazeSize($size));

        $storage_path = $this->storage_path.DS.$w.'x'.$h;
        
        if($this->isValidExtension($imageName))
        {

            if(file_exists($storage_path.DS.$imageName))
            {
                $image     = $Imagine->open($storage_path.DS.$imageName);
                $imageTime = time()-filemtime($storage_path.DS.$imageName);
                $cacheTime = $this->config->get('settings::core.thumbnails_cache_time', 30) * 86400;

                if($imageTime > $cacheTime)
                {
                    Log::debug('Thumburl: Recaching old thumbnail. ['.$storage_path.DS.$imageName.']');
                    return $resource ? $this->cacheImage($imageName, $path, $size, $storage_path, $mode) : $this->image_path.'/'.$w.'x'.$h.'/'.$imageName;
                }
                else
                {
                    return $resource ? $Imagine->open($storage_path.DS.$imageName) : $this->image_path.'/'.$w.'x'.$h.'/'.$imageName;
                }
            }
            else
            {
                Log::debug('Thumburl: Generating new thumbnail. ['.$storage_path.DS.$imageName.']');
                $image = $this->cacheImage($imageName, $path, $size, $storage_path, $mode);
                return $resource ? $image : $this->image_path.'/'.$w.'x'.$h.'/'.$imageName;
            }
        }
        else
        {
            Log::debug('Thumburl: The image name ['.$imageName.'] is invalid.');
            $image = $this->cacheImage('not-found.jpg', $this->config->get('thumbnails::options.error_image'), $size, $storage_path, $mode);
            return $resource ? $image : $this->image_path.'/'.$w.'x'.$h.'/'.'not-found.jpg';
        }
    }

    public function cacheImage($name, $path, $size, $storage_path, $mode = 'outbound')
    {
        $Imagine =& $this->imagine;

        // Extract the $w and $h variables
        $opt = $this->sanitazeSize($size);
        $w   = $opt['w'];
        $h   = $opt['h'];
        // php 5.3 throw exception
        // extract($this->sanitazeSize($size));

        $thumb = null;

        if($this->isLocal($path))
        {
            $path = path('public').$path;
            if(file_exists($path))
            {
                $thumb = $Imagine->open($path)->thumbnail($this->boxSize($size), $this->mode($mode));        
            }
            else
            {
                Log::debug('Thumbnails: Image not found ['.$path.'] returning not found image.');
                // Return not-found image
                $thumb = $Imagine->open($this->config->get('thumbnails::options.error_image'))
                                 ->thumbnail($this->boxSize($size), $this->mode($mode));
            }
        }
        else
        {
            $thumb = $Imagine->open($path)->thumbnail($this->boxSize($size), $this->mode($mode));
        }

            
        $thumbSize = $thumb->getSize();
        
        $collage = $Imagine->create(new \Imagine\Image\Box($w, $h), new \Imagine\Image\Color('#fff', 100));
        
        $collage->paste($thumb, new \Imagine\Image\Point(($w-$thumbSize->getWidth())/2, ($h-$thumbSize->getHeight())/2));
        
        if( !file_exists($storage_path.DS.$name))
        {
            @mkdir($storage_path , 0777, true );
        }

        $collage->save($storage_path.DS.$name);
        
        return $collage;
    }
}
