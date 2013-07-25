<?php

namespace Zeega;

use Silex\Application;
use Silex\ServiceProviderInterface;

class ImagickService
{

    public function isAnimatedImage ( $image ) {
        $frame = 0;
        foreach($image->deconstructImages() as $i) {
            $frame++;
            if ($frame > 1) {
                return true;
            }
        }
        return false;
    }

    public function coalesceIfAnimated ( $image ) {
        if (self::isAnimatedImage($image)) {
            return $image->coalesceImages();
        }

        return $image;
    }
    
}
