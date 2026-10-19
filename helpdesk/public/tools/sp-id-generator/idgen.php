<?php
if (!defined('IDGEN_GENERATE_REQUEST')) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../../bootstrap/admin-tool-auth.php';
$idnumber = 0;
$iddir = 'cards';
$nickname = ucwords($_POST['nickname']); // Participant nickName
$text = ucwords($_POST['fullname']); // Participant Name
$position = ucwords($_POST['position']); // Position
$idnumber = strval($_POST['empnumber']);
//$address1 = ucwords($_POST['address1']); // ADDRESS
//$name2 = ucwords($_POST['name2']);
//$address2 = ucwords($_POST['address2']); // ADDRESS
//$number = strval($_POST['number']);
$fontsize = 70;
$fontsize2 = 35;
$fontsize = $_POST['fontsize'];
$fontsize2 = $_POST['fontsize2'];
$profileimage = $_FILES['image']['tmp_name']; // Use this request's upload temp file
$font_size = 13;               // Font size is in pixels.
$font_file = 'img/arialbd.ttf';         // path to your font file
$font_file_pant = 'img/PointPantherDEMO.otf';
$font_file_arial = 'img/ariblk.ttf';
$font_file_keep = 'img/KeepCalm-Medium.ttf';

$img = 'final.png';            // path to temporary image
$img4 = 'final2.png';            // path to temporary image
//$birthday = strtoupper($_POST['dateinput']);
//list($monthofdate, $dayofdate, $yearofdate) = explode(' ', $birthday);

$img2 = $iddir.'/'.strtolower(preg_replace('#[^0-9a-zA-z.]#','',$text)).'.png';
$img3 = $iddir.'/'.strtolower(preg_replace('#[^0-9a-zA-z.]#','',$text)).'back.png';

//creates 'cards' folder if not exists
if(!file_exists($iddir)){
	mkdir($iddir, 0777, true);
}

//generate virtual image from profile image
$virtualprofile = imagecreatefromjpeg($profileimage);

//returns profile image's width and height
list($profilewid, $profilehayt) = getimagesize($profileimage);

//initiate new width and height for profile image
$newprofilewid = 450;
$newprofilehayt = 450;

$destination = imagecreatetruecolor($newprofilewid, $newprofilehayt);
imagecopyresampled($destination, $virtualprofile, 0, 0, 0, 0, $newprofilewid, $newprofilehayt, $profilewid, $profilehayt);
imagejpeg($destination, 'tmp.jpg', 100);
imagedestroy($virtualprofile);
imagedestroy($destination);

$backgroundimage = imagecreatefrompng('img/id.png');// Load the stamp and the photo to apply the watermark to
$backgroundimage2 = imagecreatefrompng('img/id2.png');// Load the stamp and the photo to apply the watermark to
$profilestamp = imagecreatefromjpeg('tmp.jpg'); // First we create our stamp image manually from GD

// Set the margins for the stamp and get the height/width of the stamp image
$marge_right = 300;
$marge_bottom = 795;

// Get image Width and Height of Profile Image
$sx = imagesx($profilestamp);
$sy = imagesy($profilestamp);

//coordinates of destination point
$xcoordest = imagesx($backgroundimage) - $sx - $marge_right;
$ycoordest = imagesy($backgroundimage) - $sy - $marge_bottom;
$xcoordest2 = imagesx($backgroundimage2);
$ycoordest2 = imagesy($backgroundimage2);

//merges two images into one
imagecopymerge($backgroundimage, $profilestamp, $xcoordest, $ycoordest, 0, 0, $sx, $sy, 100);
imagecopymerge($backgroundimage2, $backgroundimage2, $xcoordest2, $ycoordest2, 0, 0, $sx, $sy, 100);
imagedestroy($profilestamp);

// Save the image to file and free memory
imagepng($backgroundimage, 'final.png');
imagepng($backgroundimage2, 'final2.png');
imagedestroy($backgroundimage);
imagedestroy($backgroundimage2);

$im = imagecreatefrompng($img); // get the image in php
$im2 = imagecreatefrompng($img4); // get the image in php
$textcolor = imagecolorallocate($im2, 0, 0, 0); // text color
$textcolor_white = imagecolorallocate($im, 255, 255, 255); // text color

// Get image Width and Height of Temporary Image
$image_width = imagesx($im);  
$image_height = imagesy($im);

$scale = 900 / $image_width;
$scale2 = 900 / $image_width;
$fontSize_name = $fontsize * $scale;
$fontSize_pos = $fontsize2 * $scale;
$fontSize_back = 35 * $scale2;

//to center text
$text_box_pos = imagettfbbox($fontSize_pos,0,$font_file_keep,$position);
$text_box_nick = imagettfbbox(150,0,$font_file_pant,$nickname);
$text_box_name = imagettfbbox($fontSize_name,0,$font_file_pant,$text);
//$text_box_back = imagettfbbox($fontSize_back,0,$font_file,$name2);
//$text_box_back2 = imagettfbbox($fontSize_back,0,$font_file,$address2);
//$text_box_back3 = imagettfbbox(30,0,$font_file,$number);
$text_width_pos = $text_box_pos[2]-$text_box_pos[0];
$text_width_nick = $text_box_nick[2]-$text_box_nick[0];
$text_width_name = $text_box_name[2]-$text_box_name[0];
//$text_width_back = $text_box_back[2]-$text_box_back[0];
//$text_width_back2 = $text_box_back2[2]-$text_box_back2[0];
//$text_width_back3 = $text_box_back3[2]-$text_box_back3[0];
$image_width = imagesx($im);
$x_pos = round(($image_width/2) - ($text_width_pos/2));
$x_nick = round(($image_width/2) - ($text_width_nick/2));
$x_name = round(($image_width/2) - ($text_width_name/2));
//$x_back = round(($image_width/2) - ($text_width_back/2));
//$x_back2 = round(($image_width/2) - ($text_width_back2/2));
//$x_back3 = round(($image_width/2) - ($text_width_back3/2));


imagettftext($im, 33, 0, 650, 1125, $textcolor_white, $font_file_arial, $idnumber); // Add ID Number to image:
imagettftext($im, 150, 0, $x_nick, 915, $textcolor_white, $font_file_pant, $nickname); // Add nickname to image:
imagettftext($im, $fontSize_name, 0, $x_name, 1000, $textcolor_white, $font_file_pant, $text); // Add name to image:
imagettftext($im, $fontSize_pos, 0, $x_pos, 1065, $textcolor_white, $font_file_keep, $position); // Add position to image:
//imagettftext($im2, 30, 0, 320, 140, $textcolor, $font_file, $birthday); // Add Birthday to image:
//imagettftext($im2, $fontSize_back, 0, 90, 280, $textcolor, $font_file, $address1);
//imagettftext($im2, $fontSize_back, 0, $x_back, 520, $textcolor, $font_file, $name2);
//imagettftext($im2, $fontSize_back, 0, $x_back2, 730, $textcolor, $font_file, $address2);
//imagettftext($im2, 30, 0, $x_back3, 880, $textcolor, $font_file, $number);

imagepng($im, $img2, 9); // save the image on server
//imagepng($im2, $img3, 9); // save the image on server
imagedestroy($im); // Destroy image in memory to free-up resources:
imagedestroy($im2);

unlink('final.png');//Remove Temporary Background Image
unlink('final2.png');
unlink('tmp.jpg');//Remove Temporary Profile Image
?>
