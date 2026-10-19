<?php
require_once __DIR__ . '/../../../bootstrap/admin-tool-auth.php';
verify_id_generator_csrf((string) ($_GET['token'] ?? ''));
if(!empty($_GET['file1'] && $_GET['file2'])){
    $fileName = basename($_GET['file1']);
    $fileName2 = basename($_GET['file2']);
    $filePath = 'cards/'.$fileName;
    $filePath2 = 'cards/'.$fileName2;

    if(!empty($fileName) && file_exists($filePath)){
        list($img1_width, $img1_height) = getimagesize($filePath);
        list($img2_width, $img2_height) = getimagesize($filePath2);
    
        $merged_width  = $img1_width + $img2_width;
        //get highest
        $merged_height = $img1_height > $img2_height ? $img1_height : $img2_height;
    
        $merged_image = imagecreatetruecolor($merged_width, $merged_height);
    
        imagealphablending($merged_image, false);
        imagesavealpha($merged_image, true);
    
        $img1 = imagecreatefrompng($filePath);
        $img2 = imagecreatefrompng($filePath2);
    
        imagecopy($merged_image, $img1, 0, 0, 0, 0, $img1_width, $img1_height);
        //place at right side of $img1
        imagecopy($merged_image, $img2, $img1_width, 0, 0, 0, $img2_width, $img2_height);
    
        header('Content-Type: image/png');
        imagepng($merged_image,'cards/id.png');
        $file = 'cards/id.png';
        header('Content-type: octet/stream');
        header('Content-disposition: attachment; filename='.$file.';');
        header('Content-Length: '.filesize($file));
        readfile($file);
        //release memory
        imagedestroy($merged_image);
        unlink('cards/id.png');
        exit;
    }else{
        echo 'The File '.$fileName.' $ '.$fileName2.' does not exist.';
    }
}?>
