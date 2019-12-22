<?php

$servername = $argv[1];
$username = $argv[2];
$password = $argv[3];
$dbname = $argv[4];
$dbport = $argv[5];


// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname, $dbport);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "Connected Successfully\n";

$sql = "CREATE TABLE midterm(
    id INT NOT NULL AUTO_INCREMENT,
    email VARCHAR(200) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    s3rawurl VARCHAR(255) NOT NULL,
    s3finishedurl VARCHAR(255) NOT NULL,
    status INT NOT NULL,
    issubscribed INT NOT NULL,
    PRIMARY KEY(id)
)";

if(mysqli_query($conn, $sql)){
    echo "Table created successfully.\n";
} else{
    echo "ERROR: Could not able to execute $sql. " . mysqli_error($conn);
}

// Close connection
mysqli_close($conn);

?>