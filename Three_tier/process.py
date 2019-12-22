from __future__ import print_function
from PIL import Image, ImageSequence
import boto3
import json

def process_handler(event, context):

    pre_proc_name = 'pre-proc-bucket-midterm-ab'
    post_proc_name = 'post-proc-bucket-midterm-ab'

    # Connection Dynamo

    dynamodb = boto3.resource('dynamodb')
    table = dynamodb.Table('RecordsAB')


    # Retrieve UUID from SNS

    msg = event['Records'][0]['Sns']['Message']
    UUID = msg.split('&')[0]
    email = msg.split('&')[1]

    # Retrieve filename from dynamo

    Item = table.get_item(
        Key={
            'UUID': UUID,
            'Email': email #Make a second queue
        }
    )

    # Process image

    image_name = Item['Item']['Filename']
    image_name_temp = Item['Item']['Filename']
    type_image = image_name_temp.split('.')[1]
    image2proc = '/tmp/image2proc' + image_name
    s3 = boto3.client('s3')
    s3.download_file(pre_proc_name, image_name, image2proc)

    im = Image.open(image2proc)
    size = (100, 100)
    if type_image == "gif" :
        frames = ImageSequence.Iterator(im)

        # Wrap on-the-fly thumbnail generator
        def thumbnails(frames):
            for frame in frames:
                thumbnail = frame.copy()
                thumbnail.thumbnail(size, Image.ANTIALIAS)
                yield thumbnail

        frames = thumbnails(frames)

        # Save output
        om = next(frames) # Handle first frame separately
        om.info = im.info # Copy sequence info
        image_processed = '/tmp/imageprocessed_' + image_name
        om.save(image_processed, save_all=True, append_images=list(frames))
    else :
        im.thumbnail(size, Image.ANTIALIAS)
        image_processed = '/tmp/imageprocessed_' + image_name
        im.save(image_processed)

    imagetoupload = 'processed_' + image_name
    
    s3.upload_file(image_processed, post_proc_name , imagetoupload)
    URL_post = "http://post-proc-bucket-midterm-ab.s3.amazonaws.com/" + imagetoupload

    # Update dynamo
    table.update_item(
        Key={
            'UUID': UUID,
            'Email': email
        },
        UpdateExpression='SET S3finishedurl = :val1',
        ExpressionAttributeValues={
            ':val1': URL_post
        }
    )

    # Send SMS

    sns = boto3.client('sns')
    msg2send = 'Your image is ready please refresh your gallery or go to ' + URL_post + ' to download it :)'
    sns.publish(PhoneNumber=Item['Item']['Phone'], Message=msg2send)
    

    return 0

