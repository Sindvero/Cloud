<?php

// Cookies with the index information

setcookie("name", $_POST["name"], time() + 3600 * 24 * 365);
setcookie("email", $_POST["email"], time() + 3600 * 24 * 365);
setcookie("phone", $_POST["phone"], time() + 3600 * 24 * 365);

if (!isset($_POST["name"]) || !isset($_POST["email"]) || !isset($_POST["phone"])) {
    echo "Formulaire not valid !";
    header("Location:index.html");
}

$name = $_POST["name"];
$email = $_POST["email"];
$phone = $_POST["phone"];

require '/vendor/autoload.php';
use Aws\S3\S3Client; //SDK AWS-php
use Aws\S3\Exception\S3Exception;
use Aws\Rds\RdsClient;

$S3Client = new S3Client([
    'version' => 'latest',
    'region' => 'us-east-1'
]);

$RDSClient = new RdsClient([
    'version' => 'latest',
    'region'  => 'us-east-1'
]);

#===========================
#   File Upload pre-process
#===========================

// DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
// Check MIME Type by yourself.
$finfo = new finfo(FILEINFO_MIME_TYPE); //source:https://www.php.net/manual/en/features.file-upload.php
if (false === $ext = array_search(
    $finfo->file($_FILES['file']['tmp_name']),
        array(
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ),
        true
    )) {
        throw new RuntimeException('Invalid file format.');
    }

$target_dir = "/var/www/html/uploads/";
$target_file = $target_dir . basename($_FILES["file"]["tmp_name"]);
$file_name = basename($_FILES["file"]["tmp_name"]). "." . $ext;
$uploadOk = 1;
// Check if image file is a actual image or fake image
if(isset($_POST["submit"])) {
    $check = getimagesize($_FILES["file"]["tmp_name"]);
    if($check !== false) {
        echo "File is an " . $check["mime"] . "<br />";
        $uploadOk = 1;
    } else {
        echo "File is not an image." . "<br />";
        $uploadOk = 0;
    }
}
// Check if file already exists
if (file_exists($target_file)) {
    echo "Sorry, file already exists." . "<br />";
    $uploadOk = 0;
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
    echo "Sorry, your file was not uploaded.";
// if everything is ok, try to upload file
} else {
    if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
        echo "The file ". basename( $_FILES["file"]["tmp_name"]). " has been uploaded.";
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
}

//Bucket list 
$Buckets = $S3Client->listBuckets();
$Bucket_pre_proc = "pre-proc-bucket-midterm-ab";
$Bucket_post_proc = "post-proc-bucket-midterm-ab";

//Upload pre-process image in the bucket

$S3Client->putObject(
    array(
        'Bucket' => $Bucket_pre_proc,
        'Key'    => $file_name,
        'ACL'    => 'public-read',
        'SourceFile' => $target_file
    )
);

//Create URL
$S3_url_pre_proc = "http://pre-proc-bucket-midterm-ab.s3.amazonaws.com/" . $file_name;

#===========================
#   Process the image
#===========================
use Imagine\Imagick\Imagine;
use Imagine\Image\Box;
$image = new Imagine();
$image = $image->open($target_file);
$image->layers()->coalesce();
foreach ($image->layers() as $frame) {
    $frame->resize(new Box(100, 100));
}
$image->save($target_file, array('animated' => true)); // Source: https://www.php.net/manual/en/imagick.examples-1.php

$S3Client->putObject(
    array(
        'Bucket' => $Bucket_post_proc,
        'Key'    => $file_name,
        'ACL'    => 'public-read',        'SourceFile' => $target_file
        )
    );
    
    //Create URL
    $S3_url_post_proc = "http://post-proc-bucket-midterm-ab.s3.amazonaws.com/" . $file_name;
    
    #===========================
    #   Add info to RDS
    #===========================
    
    //Connexion to RDS
    $Db_info = $RDSClient->describeDBInstances();
    $Username = $Db_info['DBInstances'][0]['MasterUsername'];
    $Password = 'abenoistpassdb';
    $DbName = $Db_info['DBInstances'][0]['DBName'];
    $DBEndpoint = $Db_info['DBInstances'][0]['Endpoint']['Address'];
    $DbPort = $Db_info['DBInstances'][0]['Endpoint']['Port'];
    
    $conn = mysqli_connect($DBEndpoint, $Username, $Password, $DbName, $DbPort);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    echo "Connected Successfully\n";
    
    //Add info to the database
    
    $query = "INSERT INTO `midterm` (`email`, `phone`, `filename`, `s3rawurl`, `s3finishedurl`, `status`, `issubscribed`) 
                VALUES ('%s','%s','%s','%s','%s',1,1)";
    
    $query = sprintf($query, $email, $phone, $_FILES['file']['name'], $S3_url_pre_proc, $S3_url_post_proc);
    
    if ($conn->query($query) === TRUE) {
        echo "The infomation have been uploaded to the DataBase";
    } else {
        echo "Error: " . $query . "<br>" . $conn->error;
    }
    
    $conn->close();

    header("Location:gallery.php");
    
?>    