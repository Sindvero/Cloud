<?php

    // Cookies with the index information

    setcookie("name", $_POST["name"], time() + 3600 * 24 * 365);
    setcookie("email", $_POST["email"], time() + 3600 * 24 * 365);
    setcookie("phone", $_POST["phone"], time() + 3600 * 24 * 365);
    $UUID = uniqid();
    setcookie("UUID", $UUID, time() + 3600 * 24 * 365);

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
    use Aws\DynamoDb\DynamoDbClient;
    use Aws\Sns\SnsClient; 

    $S3Client = new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1'
    ]);

    $DynamoClient = new DynamoDbClient([
        'version' => 'latest',
        'region'  => 'us-east-1'
    ]);

    $SNSClient = new SnsClient([
        'version' => 'latest',
        'region' => 'us-east-1'
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
            // echo "The file ". basename( $_FILES["file"]["tmp_name"]). " has been uploaded.";
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
            'Key'    => $_FILES['file']['name'],
            'ACL'    => 'public-read',
            'SourceFile' => $target_file
        )
    );

    //Create URL
    $S3_url_pre_proc = "http://pre-proc-bucket-midterm-ab.s3.amazonaws.com/" . $_FILES['file']['name'];

    #===========================
    #   SMS Subscription
    #===========================
    
    $ListTopicARN = $SNSClient->listTopics([
        // no need to call anything, as it will list all
     ]);

    $TopicARN = $ListTopicARN['Topics'][0]['TopicArn'];
    
    $protocol = 'sms';
    if (strpos($phone, '+') == false) {
        $endpoint = '+1' . $phone;
    } else {
        $endpoint = $phone;
    }

    $Subscription = $SNSClient->subscribe([
        'Protocol' => $protocol,
        'Endpoint' => $endpoint,
        'ReturnSubscriptionArn' => true,
        'TopicArn' => $TopicARN,
    ]);

    $SendMsg = $SNSClient->publish([
        'Message' => 'Your image is being processed and your receipt is:', // REQUIRED
        'PhoneNumber' => $endpoint,
        'Subject' => 'Processing',
    ]);

    $msg = $UUID . '&' . $email;
    $SendMsg2Lambda = $SNSClient->publish([
        'Message' => $msg, // REQUIRED
        'TopicArn' => $TopicARN,
    ]);

    #===========================
    #   Add info to DynamoDB
    #===========================

    $result = $DynamoClient->putItem([
        'TableName' => "RecordsAB", // REQUIRED
        'Item' => [ // REQUIRED
            'UUID' => ['S' => $UUID],
            'Email' => ['S' => $email],
            'Phone' => ['S' => $endpoint],
            'Filename' => ['S' => $_FILES['file']['name']],
            'S3rawurl' => ['S' => $S3_url_pre_proc],
            'S3finishedurl' => ['S' => "NULL"],     
            'Status' => ['BOOL' => false],
            'Issubscribed' => ['BOOL' => true]     
            ]   
        ]);
    

    header("Location:gallery.php");
?>    