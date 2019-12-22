#!/bin/bash

#===========================
#   Create Load Balancer
#===========================
echo -e "\nCreating the Elastic Load Balancer V2 (elbv2)\n"
aws elbv2 create-load-balancer --name test-lb-mt --subnets $7 $8 --security-groups $5
lb=$(aws elbv2 describe-load-balancers --query 'LoadBalancers[*].{Target:LoadBalancerArn}')

echo -e "\nCreating target-group\n"
VPCIDS=$(aws ec2 describe-vpcs --query 'Vpcs[*].{VPCIDS:VpcId}')
aws elbv2 create-target-group --name target-gr-mt --protocol HTTP --port 80 --vpc-id $VPCIDS --target-type instance
TargetGrARN=$(aws elbv2 describe-target-groups --query 'TargetGroups[*].{TargetGrARN:TargetGroupArn}')
aws elbv2 modify-target-group-attributes --target-group-arn $TargetGrARN --attributes Key=stickiness.enabled,Value=true
aws elbv2 modify-target-group-attributes --target-group-arn $TargetGrARN --attributes Key=stickiness.type,Value=lb_cookie

echo -e "\nCreating the listener \n"
aws elbv2 create-listener --load-balancer-arn $lb --protocol HTTP --port 80 --default-actions Type=forward,TargetGroupArn=$TargetGrARN

echo -e "\nWaiting for the Load Balancer to be available ...\n"
aws elbv2 wait load-balancer-available --load-balancer-arns $lb


#===========================
#   Create Auto Scaling Group
#===========================

echo -e "\nCreating auto-scaling group\n"
ConfigName="configauto"
GroupName="autoname"
aws autoscaling create-launch-configuration --launch-configuration-name $ConfigName --image-id $1 --instance-type $3 --key-name $4 --security-groups $5 --user-data file://install-app-env.sh --iam-instance-profile $6
aws autoscaling create-auto-scaling-group --auto-scaling-group-name $GroupName --launch-configuration-name $ConfigName --min-size 2 --max-size 4 --desired-capacity 3 --vpc-zone-identifier $7

echo -e "\nAttach autoscaling group to Load Balancer\n"
aws autoscaling attach-load-balancer-target-groups --auto-scaling-group-name $GroupName --target-group-arns $TargetGrARN

#===========================
#   Create S3 Buckets
#===========================
echo -e "\nCreating the Two S3 Buckets\n"

BucketPreProc=pre-proc-bucket-midterm-ab
BucketPostProc=post-proc-bucket-midterm-ab

aws s3api create-bucket --bucket $BucketPreProc --region us-east-1
aws s3api create-bucket --bucket $BucketPostProc --region us-east-1

echo -e "\nWaiting for the buckets to exist ...\n"
aws s3api wait bucket-exists --bucket $BucketPreProc
aws s3api wait bucket-exists --bucket $BucketPostProc

aws s3api put-bucket-policy --bucket $BucketPreProc --policy file://policyS3Pre.json
aws s3api put-bucket-policy --bucket $BucketPostProc --policy file://policyS3Post.json


#===========================
#   Create DynamoDB
#===========================
echo -e "\nCreating the DynamoDB\n"

aws dynamodb create-table --cli-input-json file://dynamo_creation.json
echo -e "\nWaiting for the database to be available"

aws dynamodb wait table-exists --table-name RecordsAB


#===========================
#   Create SNS
#===========================
echo -e "\nCreating the SNS topic\n"

TopicUUID=$(aws sns create-topic --name mp2-abenoist-sns)

#===========================
#   Create Lambda Function
#===========================
echo -e "\nCreating the Lambda function\n"


aws lambda create-function --function-name lambda-function-mp2-ab --runtime python3.6 --zip-file fileb://process.zip --timeout 800 --handler process.process_handler --role $2
aws sns subscribe --protocol lambda --topic-arn $TopicUUID --notification-endpoint $(aws lambda list-functions --query 'Functions[*].FunctionArn')

# aws lambda create-event-source-mapping --function-name lambda-function-mp2-ab  --batch-size 5 --event-source-arn $TopicUUID --starting-position LATEST
# Doesn't work for sns trigger: https://docs.aws.amazon.com/cli/latest/reference/lambda/create-event-source-mapping.html
# So need to do it manually with the GUI :)

echo -e "\nThe environment has been created !! :)\n"
echo -e "\nPlease Refer to the README.md to see the next steps (activate SNS trigger manually) ;)\n"