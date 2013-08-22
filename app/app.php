<?php

 ini_set('display_errors', 1);

//TODO move image manipulation code to separate class

require_once __DIR__.'/bootstrap.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Aws\Common\Aws;
use Aws\S3\Enum\CannedAcl;
use Guzzle\Http\EntityBody;
use Zeega\ImagickService;

$app = new Silex\Application();
$app["debug"] = true;

/*
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../logs/dev.log',
));
*/

$app['imagick_service'] = function() {
    return new ImagickService();
};



$app->post("/image", function () use ($app) {

   
  // return var_dump($_FILES);
   if( isset($_FILES["file"])) $_FILES["imagefile"]=$_FILES["file"];
    // Create unique filename
    $filePrefix = md5( uniqid( rand(), true ));

    $aws = Aws::factory(array(
                    'key' => AWS_ACCESS_KEY,
                    'secret' => AWS_SECRET_KEY
                ));
    $client = $aws->get('s3');

    // Check sizes requested
    if( isset($_GET["sizes"]) ) {
        $sizeList = (string) $_GET["sizes"];
        for ( $i = 0; $i < strlen( $sizeList ); $i++ ) {
            $sizes[ substr($sizeList, $i, 1 )] = true;
        }
        for( $i = 0; $i < 8; $i++ ){
            if( !isset($sizes[ $i ])){
                $sizes[ $i ] = false;
            }
        }
    } else {
        $sizes[ 4 ] = $sizes [ 5 ] =  $sizes[ 6 ] = $sizes [ 7 ] = true;
        $sizes[ 0 ] = false;
    }

    if( isset($_FILES["imagefile"]["tmp_name"]) ) {
        if( strpos( $_FILES["imagefile"]["type"], "image/" ) === 0 ) {
            $fileType = $_FILES["imagefile"]["type"];
            $fileExt = substr( $fileType, 6 );
        } else if( $_FILES["imagefile"]["type"] == "application/octet-stream") {
            $fileExt = explode(".", $_FILES["imagefile"]["name"]);
            $fileExt = array_pop($fileExt);
            $fileExt = strtolower($fileExt);

            if(in_array( $fileExt, array("png", "jpeg", "gif", "jpg"))){
                $fileType = "image/" . $fileExt;
            }
        }

        if(isset($fileType)){
            $img = new Imagick($_FILES["imagefile"]["tmp_name"]);
//            $img =  $app['imagick_service']->autoRotateImage($img);
            
      $orientation = $img->getImageOrientation();

     switch($orientation) {
        case imagick::ORIENTATION_BOTTOMRIGHT:
            $img->rotateimage("#000", 180); // rotate 180 degrees 
        break;

        case imagick::ORIENTATION_RIGHTTOP:
            $img->rotateimage("#000", 90); // rotate 90 degrees CW 
        break;

        case imagick::ORIENTATION_LEFTBOTTOM:
            $img->rotateimage("#000", -90); // rotate 90 degrees CCW 
        break;
     }

      // Now that it's auto-rotated, make sure the EXIF data is correct in case the EXIF gets saved with the image! 
      $img->setImageOrientation(imagick::ORIENTATION_TOPLEFT);





// Save originals when file type is gif
            // TODO resize GIFs
            if( $fileType == "image/gif"){
                $sizes[ 0 ] = true;
                $sizes [ 7 ] = false;
            }

            // Save Originals
            if( $sizes [ 0 ] ){
                $files[ 0 ] = fopen( $_FILES["imagefile"]["tmp_name"], "r");
                $fileNames[ 0 ] = $filePrefix . "." . $fileExt;
            }
        }
    }
    
    if ( isset($img) ) {
        

        if( $fileType == "image/gif" ){

           
           
	  
         
        
            //move_uploaded_file($_FILES["imagefile"]["tmp_name"], "/tmp/media/" .  $fileNames[ 0 ]);   
            
            $frameRate = $img->getImageDelay();
          
	    $frameCount = $img->getNumberImages();
            
	    $metadata = $img->getImageWidth() . "_" . $img->getImageHeight() . "_" . $img->getNumberImages() . "_" .  $img->getImageDelay();
            //return new Response ($metadata);
            $fileNames[ 8 ] = "zga_" . $metadata . "_" . $filePrefix . ".jpg";
            $v = exec(  "montage " . $_FILES["imagefile"]["tmp_name"] . " -coalesce -tile 1x -frame 0 -geometry '+0+0' -quality 80 -colors 256 -background none -bordercolor none /tmp/media/".$fileNames[ 8 ]);           
            //return new Response("/tmp/media/". $fileNames[ 8 ] );
	    $montage = new Imagick( "/tmp/media/". $fileNames[ 8 ]);
	    $files [ 8 ] = $montage->getImageBlob();
            $montage->destroy();
            unlink ($_FILES["imagefile"]["tmp_name"]);
        }



        if( $fileExt == "png" ){
            $img->setImageFormat( "png" );
        } else {
            $fileExt = "jpg";
            $img->setImageFormat("jpg");
        }



        // Thumbnail Size (max dimension 200px)
        if( $sizes[ 5 ] ) {
            $img2 = clone $img;
            $img2->thumbnailImage(200,0);
            $files[ 5 ] = $img2->getImageBlob();
            $fileNames[ 5 ] = $filePrefix . "_5." . $fileExt;
            $img2->destroy();
        }

        // Large Size (max dimension 800px)
        if( $sizes[ 7 ] ) {

            if($img->getImageHeight() > 800 || $img->getImageWidth() > 800){
                $img->thumbnailImage( 800, 0 );
            }
            $files[ 7 ] = $img->getImageBlob();
            $fileNames[ 7 ] = $filePrefix."_7." . $fileExt;
        }

        if($sizes[ 6 ] || $sizes[ 4 ]){

            // Convert to Square
            if($img->getImageWidth() > $img->getImageHeight()){
                $x = (int) floor(( $img->getImageWidth()-$img->getImageHeight()) / 2 );
                $h = $img->getImageHeight();
                $img->chopImage( $x, 0, 0, 0 );
                $img->chopImage( $x, 0, $h, 0 );
            } else {
                $y = (int) floor(( $img->getImageHeight()-$img->getImageWidth()) / 2 );
                $w = $img->getImageWidth();
                $img->chopImage( 0, $y, 0, 0 );
                $img->chopImage( 0, $y, 0, $w );
            }
            // Medium Square Size (250px by 250px)
            if( $sizes[ 6 ]){

                $img->thumbnailImage( 250, 0);
                $files[ 6 ]    = $img->getImageBlob();
                $fileNames[ 6 ] = $filePrefix . "_6." . $fileExt;
            }

            // Small Square Size (150px by 150px)
            if( $sizes[ 4 ]){

                $img->thumbnailImage( 150, 0 );
                $files[ 4 ]    = $img->getImageBlob();
                $fileNames[ 4 ] = $filePrefix . "_4." . $fileExt;
            }
        }














	$img->destroy();

        // Upload files to S3, Original file is uploaded using putObjectFile instead of putObject

        for( $i = 0; $i < 9; $i++ ){
            if( isset($fileNames[ $i ]) ){
        //       echo $i;
                // Post to S3 server
                $res = $client->putObject(array(
                        "Bucket" => IMAGE_BUCKET,
                        "Key"    => $fileNames[ $i ],
                        "Body"  => EntityBody::factory($files[ $i ]),
                        "ACL" => CannedAcl::PUBLIC_READ,
                        "ContentType" => "image/jpg"
                    )
                );
                $response[ "image_url_" . $i ] = "http://" . IMAGE_BUCKET . ".s3.amazonaws.com/" . $fileNames[ $i ];
            }
        }
        $response[ "title" ] = $_FILES[ "imagefile" ][ "name" ];
       if( $sizes[ 7 ] ){

            $response[ "fullsize_url" ] = "http://" . IMAGE_BUCKET . ".s3.amazonaws.com/" . $fileNames[ 7 ];

        } else {

            $response[ "fullsize_url" ] = "http://" . IMAGE_BUCKET . ".s3.amazonaws.com/" . $fileNames[ 0 ];

        } 
        return json_encode($response);
    } else {

         return new Response("",500);
    }
});


$app->get("/image", function () use ($app) {
    
    // Create unique filename
    $filePrefix = md5( uniqid( rand(), true ));

    $aws = Aws::factory(array(
                    'key' => AWS_ACCESS_KEY,
                    'secret' => AWS_SECRET_KEY
                ));
    $client = $aws->get('s3');

    // Check sizes requested
    if( isset( $_GET["sizes"])){
        $sizeList = (string) $_GET["sizes"];
        for ( $i = 0; $i < strlen( $sizeList ); $i++ ) {
            $sizes[ substr($sizeList, $i, 1 )] = true;
        }
        for( $i = 0; $i < 8; $i++ ){
            if( !isset($sizes[ $i ])){
                $sizes[ $i ] = false;
            }
        }

    } else {
        $sizes[ 4 ] = $sizes [ 5 ] =  $sizes[ 6 ] =  true;
        $sizes[ 0 ] = $sizes[ 7 ] = false;
    }

    // Check for network media asset
    if( isset( $_GET["url"] )){
        $url = $_GET["url"];
        $url = str_replace(" ","%20",$url);
        $img = new Imagick($url);

        $img = $app['imagick_service']->coalesceIfAnimated($img);



        // Do not save Large Size for networked media assets
        // $sizes[ 7 ] = false;

        //Youtube image formatting hack to remove black bars
        if(strstr($url, "i.ytimg.com")){
            $img->cropImage($img->getImageWidth(), $img->getImageHeight()-90, 0, 45);
        }
    }
    
    if(isset($img)){
        

        $fileExt = explode(".", $_GET["url"]);
        $fileExt = array_pop($fileExt);
        $fileExt = strtolower($fileExt);

        if(in_array( $fileExt, array("png", "jpeg", "gif", "jpg"))){
            $fileType = "image/" . $fileExt;
        }

        if( $fileExt == "png"){
            $img->setImageFormat("png");
        } else {
            $fileExt = "jpg";
            $img->setImageFormat("jpg");
        }


        // Thumbnail Size (max dimension 200px)
        if( $sizes[ 5 ]){

            $img2 = clone $img;
            $img2->thumbnailImage(200,0);
            $files[ 5 ] = $img2->getImageBlob();
            $fileNames[ 5 ] = $filePrefix . "_5." . $fileExt;
        }

        // Large Size (max dimension 800px)
        if($sizes[ 7 ]){

            if($img->getImageHeight() > 800 || $img->getImageWidth() > 800){
                $img->thumbnailImage( 800, 0 );
            }
            $files[ 7 ] = $img->getImageBlob();
            $fileNames[ 7 ] = $filePrefix."_7." . $fileExt;
        }

        if($sizes[ 6 ] || $sizes[ 4 ]){

            // Convert to Square
            if($img->getImageWidth() > $img->getImageHeight()){
                $x = (int) floor(( $img->getImageWidth()-$img->getImageHeight()) / 2 );
                $h = $img->getImageHeight();
                $img->chopImage( $x, 0, 0, 0 );
                $img->chopImage( $x, 0, $h, 0 );
            } else {
                $y = (int) floor(( $img->getImageHeight()-$img->getImageWidth()) / 2 );
                $w = $img->getImageWidth();
                $img->chopImage( 0, $y, 0, 0 );
                $img->chopImage( 0, $y, 0, $w );
            }

            // Medium Square Size (250px by 250px)
            if( $sizes[ 6 ]){

                $img->thumbnailImage( 250, 0);
                $files[ 6 ]    = $img->getImageBlob();
                $fileNames[ 6 ] = $filePrefix . "_6." . $fileExt;
            }

            // Small Square Size (150px by 150px)
            if( $sizes[ 4 ]){

                $img->thumbnailImage( 150, 0 );
                $files[ 4 ]    = $img->getImageBlob();
                $fileNames[ 4 ] = $filePrefix . "_4." . $fileExt;
            }
        }

        // Upload files to S3, Original file is uploaded using putObjectFile instead of putObject
        for( $i = 0; $i < 8; $i++ ){
            if( isset($fileNames[ $i ])){

                // Post to S3 server
                $res = $client->putObject(array(
                        "Bucket" => IMAGE_BUCKET,
                        "Key"    => $fileNames[ $i ],
                        "Body"  => EntityBody::factory($files[ $i ]),
                        "ACL" => CannedAcl::PUBLIC_READ,
                        "ContentType" => "image/jpg"
                    )
                );
                $urls[ "image_url_" . $i ] = "http://" . IMAGE_BUCKET . ".s3.amazonaws.com/" . $fileNames[ $i ];
            }
        }
    }
    
    return $app->json($urls);
});


$app->get("/projects/{projectId}/frames/{frameId}", function ($projectId, $frameId) use ($app) {
    // Create unique filename
    $fileName = md5( uniqid( rand(), true )) . ".jpg";

    // Run cutycapt to create screencapture
    $url = ZEEGA_HOST . "projects/$projectId/frames/$frameId";
    exec( "/opt/webcapture/webpage_capture -t 80x60 -crop $url " . PATH, $output );
    $file=explode(":", $output[4] );
    
    // Test if screencapture successfule TODO create better test
    if(!is_null($file[1])){
        // Instantiate the S3 class
        $aws = Aws::factory(array(
            'key' => AWS_ACCESS_KEY,
            'secret' => AWS_SECRET_KEY
        ));

        $client = $aws->get('s3');
       
        $res = $client->putObject(array(
                "Bucket" => FRAME_BUCKET,
                "Key"    => $fileName,
                "Body"  => EntityBody::factory(fopen($file[ 1 ], 'r')),
                "ACL" => CannedAcl::PUBLIC_READ,
                "ContentType" => "image/jpg"
            )
        );

        $url= "http://" . FRAME_BUCKET . ".s3.amazonaws.com/" . $fileName;
        return new Response ($url, 200);
    } else {
        return new Response ("", 500);
    }
});

$app->get("/projects/{projectId}/frames/{frameId}", function ($projectId, $frameId) use ($app) {
    // Create unique filename
    $fileName = md5( uniqid( rand(), true )) . ".png";

    // Run cutycapt to create screencapture
    $url = ZEEGA_HOST . "projects/$projectId/frames/$frameId";
    exec( "/opt/webcapture/webpage_capture -t 80x60 -crop $url " . PATH, $output );
    $file=explode(":", $output[4] );
    
    // Test if screencapture successfule TODO create better test
    if(!is_null($file[1])){
        // Instantiate the S3 class
        $aws = Aws::factory(array(
            'key' => AWS_ACCESS_KEY,
            'secret' => AWS_SECRET_KEY
        ));

        $client = $aws->get('s3');
       
        $res = $client->putObject(array(
                "Bucket" => FRAME_BUCKET,
                "Key"    => $fileName,
                "Body"  => EntityBody::factory(fopen($file[ 1 ], 'r')),
                "ACL" => CannedAcl::PUBLIC_READ,
                "ContentType" => "image/png"
            )
        );

        $url= "http://" . FRAME_BUCKET . ".s3.amazonaws.com/" . $fileName;
        return new Response ($url, 200);
    } else {
        return new Response ("", 500);
    }
});

return $app;
