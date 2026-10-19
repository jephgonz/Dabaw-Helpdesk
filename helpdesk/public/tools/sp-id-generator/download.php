<?php
require_once __DIR__ . '/../../../bootstrap/admin-tool-auth.php';
verify_id_generator_csrf((string) ($_GET['token'] ?? ''));
if(!empty($_GET['file1'])){
    $fileName = basename($_GET['file1']);
    $filePath = 'cards/'.$fileName;
    if(!empty($fileName) && file_exists($filePath)){
        list($img1_width, $img1_height) = getimagesize($filePath);
    
        $merged_image = imagecreatetruecolor($img1_width, $img1_height);
    
        imagealphablending($merged_image, false);
        imagesavealpha($merged_image, true);
    
        $img1 = imagecreatefrompng($filePath);
    
        imagecopy($merged_image, $img1, 0, 0, 0, 0, $img1_width, $img1_height);
        
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
        unlink($filePath);
        exit;
    }else{
        echo "<script>
        alert('Please make sure to fill out all required fields in the ID generation form.');
        window.location.href = 'index.php';
        </script>";
    }
}
?>
