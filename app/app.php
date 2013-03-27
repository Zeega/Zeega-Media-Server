<?php


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

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../logs/dev.log',
));

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
            $fileExt=strtolower($fileExt);

            if(in_array( $fileExt, array("png", "jpeg", "gif", "jpg"))){
                $fileType = "image/" . $fileExt;
            }
        }

        if(isset($fileType)){
            $img = new Imagick($_FILES["imagefile"]["tmp_name"]);

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
        $img->setImageFormat("png");

        // Thumbnail Size (max dimension 200px)
        if( $sizes[ 5 ] ) {
            $img2 = clone $img;
            $img2->thumbnailImage(200,0);
            $files[ 5 ] = $img2->getImageBlob();
            $fileNames[ 5 ] = $filePrefix . "_5.png";
        }

        // Large Size (max dimension 1280px)
        if( $sizes[ 7 ] ) {

            if($img->getImageHeight() > 1280 || $img->getImageWidth() > 1280){
                $img->thumbnailImage( 1280, 0 );
            }
            $files[ 7 ] = $img->getImageBlob();
            $fileNames[ 7 ] = $filePrefix."_7.png";
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
                $fileNames[ 6 ] = $filePrefix . "_6.png";
            }

            // Small Square Size (150px by 150px)
            if( $sizes[ 4 ]){

                $img->thumbnailImage( 150, 0 );
                $files[ 4 ]    = $img->getImageBlob();
                $fileNames[ 4 ] = $filePrefix . "_4.png";
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
                        "ContentType" => "image/png"
                    )
                );
                $response[ "image_url_" . $i ] = "http://" . IMAGE_BUCKET . ".s3.amazonaws.com/" . $fileNames[ $i ];
            }
        }
        $response[ "title" ] = $_FILES[ "imagefile" ][ "name" ];
        
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
        $sizes[ 4 ] = $sizes [ 5 ] =  $sizes[ 6 ] = $sizes [ 7 ] = true;
        $sizes[ 0 ] = false;
    }

    // Check for network media asset
    if( isset( $_GET["url"] )){
        $url = $_GET["url"];
        $url = str_replace(" ","%20",$url);
        $img = new Imagick($url);
        $img = $app['imagick_service']->coalesceIfAnimated($img);

        // Do not save Large Size for networked media assets
        $sizes[ 7 ] = false;

        //Youtube image formatting hack to remove black bars
        if(strstr($url, "i.ytimg.com")){
            $img->cropImage($img->getImageWidth(), $img->getImageHeight()-90, 0, 45);
        }
    } else if( isset( $_FILES["imagefile"]["tmp_name"])){

        if( strpos( $_FILES["imagefile"]["type"], "image/" ) === 0 ){
            $fileType = $_FILES["imagefile"]["type"];
            $fileExt = substr( $fileType, 6 );
        } else if( $_FILES["imagefile"]["type"] == "application/octet-stream"){
            $fileExt = explode(".", $_FILES["imagefile"]["name"]);
            $fileExt = array_pop($fileExt);
            $fileExt=strtolower($fileExt);


            if(in_array( $fileExt, array("png", "jpeg", "gif", "jpg"))){
                $fileType = "image/" . $fileExt;
            }
        }

        if(isset($fileType)){
            $img = new Imagick($_FILES["imagefile"]["tmp_name"]);

            // Save originals when file type is gif
            // TODO resize GIFs
            if( $fileType == "image/gif"){
                $sizes[ 0 ] = true;
                $sizes [ 7 ] = false;
            }

            // Save Originals
            if( $sizes [ 0 ] ){
                $files[ 0 ] = $s3->inputFile( $_FILES["imagefile"]["tmp_name"], false);
                $fileNames[ 0 ] = $filePrefix . "." . $fileExt;
            }
        }
    }
    
    if(isset($img)){
        $img->setImageFormat("png");

        // Thumbnail Size (max dimension 200px)
        if( $sizes[ 5 ]){

            $img2 = clone $img;
            $img2->thumbnailImage(200,0);
            $files[ 5 ] = $img2->getImageBlob();
            $fileNames[ 5 ] = $filePrefix . "_5.png";
        }

        // Large Size (max dimension 1280px)
        if($sizes[ 7 ]){

            if($img->getImageHeight() > 1280 || $img->getImageWidth() > 1280){
                $img->thumbnailImage( 1280, 0 );
            }
            $files[ 7 ] = $img->getImageBlob();
            $fileNames[ 7 ] = $filePrefix."_7.png";
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
                $fileNames[ 6 ] = $filePrefix . "_6.png";
            }

            // Small Square Size (150px by 150px)
            if( $sizes[ 4 ]){

                $img->thumbnailImage( 150, 0 );
                $files[ 4 ]    = $img->getImageBlob();
                $fileNames[ 4 ] = $filePrefix . "_4.png";
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
                        "ContentType" => "image/png"
                    )
                );
                $urls[ "image_url_" . $i ] = "http://" . IMAGE_BUCKET . ".s3.amazonaws.com/" . $fileNames[ $i ];
            }
        }
    }
    
    return $app->json($urls);
});


$app->get("/frame/{id}", function ($id) use ($app) {
    // Create unique filename
    $fileName = md5( uniqid( rand(), true )) . ".png";

    // Run cutycapt to create screencapture

    exec( "/opt/webcapture/webpage_capture -t 80x60 -crop ". FRAME_URL . $id . "/view " . PATH, $output );
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
