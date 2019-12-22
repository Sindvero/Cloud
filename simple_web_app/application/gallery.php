<!DOCTYPE html>
<html>
    <head>

        <meta charset="UTF-8">
	    <title>Gallery Page</title>

    </head>

    <body>

        <?php
            $name = $_COOKIE["name"];
            $email = $_COOKIE["email"];
            $phone = $_COOKIE["phone"];
        ?>
        <h1>Hello <?php echo $name;?>, this is you gallery :)</h1>

        <?php

            //Connexion to RDS
            require '/vendor/autoload.php';
            use Aws\Rds\RdsClient;
            $RDSClient = new RdsClient([
                'version' => 'latest',
                'region'  => 'us-east-1'
            ]);

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

            // $query = "SELECT `filename`, `s3rawurl`, `s3finishedurl` FROM `midterm` WHERE `phone` = '%s'";
            // $query = sprintf($query, $phone);
            $query = "SELECT filename,s3rawurl,s3finishedurl FROM midterm WHERE email='" . $email . "'";
            $result = $conn->query($query);

            while ($image = $result->fetch_assoc()) {
                echo "<figure><img src=" . $image['s3rawurl'] . " alt='Unprocess Image' /> <figcaption>" . $image['filename'] . " origin </figcaption></figure>";
                echo "<figure><img src=" . $image['s3finishedurl'] . " alt='Unprocess Image' /> <figcaption>" . $image['filename'] . " thumbnail </figcaption></figure>";
            }

            $conn->close();


        ?>

        <button onclick="location.href='index.html'" type="button">Return Home</button>


    </body>
</html>
