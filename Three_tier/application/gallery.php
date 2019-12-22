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
            $UUID = $_COOKIE["UUID"];
        ?>
        <h1>Hello <?php echo $name;?>, this is you gallery :)</h1>

        <?php

            //Query from DynamoDB
            require '/vendor/autoload.php';
            use Aws\DynamoDb\DynamoDbClient;

            $DynamoClient = new DynamoDbClient([
                'version' => 'latest',
                'region'  => 'us-east-1'
            ]);

            $result = $DynamoClient->query([
                'ExpressionAttributeValues' => [
                    ':v1' => ['S' => $email]
                ],
                'KeyConditionExpression' => 'Email = :v1',
                //'ProjectionExpression' => 'S3finishedurl, S3rawurl', 'Filename',
                'TableName' => 'RecordsAB',
            ]);

            foreach ($result['Items'] as $image) {
                echo "<figure><img src=" . $image['S3rawurl']['S'] . " alt='Unprocessed Image' /> <figcaption>" . $image['Filename']['S'] . " origin </figcaption></figure>";
                echo "<figure><img src=" . $image['S3finishedurl']['S'] . " alt='processed Image' /> <figcaption>" . $image['Filename']['S'] . " thumbnail </figcaption></figure>";
            }


        ?>

        <button onclick="location.href='index.html'" type="button">Return Home</button>


    </body>
</html>
