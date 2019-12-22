#!/bin/bash

#===========================
#   Create Instances
#===========================
sudo apt-get -y install php
echo -e "Creating the Two2 ec2 instances\n"
aws ec2 run-instances --image-id $1 --count $2 --instance-type $3 --key-name $4 --security-group-ids $5 --subnet-id $7 --user-data file://install-app-env.sh --iam-instance-profile Name=$6
Instances[0]=$(aws ec2 describe-instances --filters Name=instance-state-name,Values=pending --query 'Reservations[*].Instances[0].{Instance:InstanceId}')
Instances[1]=$(aws ec2 describe-instances --filters Name=instance-state-name,Values=pending --query 'Reservations[*].Instances[1].{Instance:InstanceId}')
echo -e "\nWaiting for the instances to be running ...\n"
aws ec2 wait instance-running --instance-ids ${Instances[@]}


#===========================
#   Create Load Balancer
#===========================
echo -e "\nCreating the Elastic Load Balancer V2 (elbv2)\n"
aws elbv2 create-load-balancer --name test-lb-mt --subnets $8 $7 --security-groups $5
lb=$(aws elbv2 describe-load-balancers --query 'LoadBalancers[*].{Target:LoadBalancerArn}')
echo -e "\nCreating target-group to attach the instances\n"
VPCIDS=$(aws ec2 describe-vpcs --query 'Vpcs[*].{VPCIDS:VpcId}')
aws elbv2 create-target-group --name target-gr-mt --protocol HTTP --port 80 --vpc-id $VPCIDS --target-type instance
TargetGrARN=$(aws elbv2 describe-target-groups --query 'TargetGroups[*].{TargetGrARN:TargetGroupArn}')
aws elbv2 modify-target-group-attributes --target-group-arn $TargetGrARN --attributes Key=stickiness.enabled,Value=true
aws elbv2 modify-target-group-attributes --target-group-arn $TargetGrARN --attributes Key=stickiness.type,Value=lb_cookie
aws elbv2 register-targets --target-group-arn $TargetGrARN --targets Id=${Instances[0]} Id=${Instances[1]}
echo -e "\nCreating the listener \n"
aws elbv2 create-listener --load-balancer-arn $lb --protocol HTTP --port 80 --default-actions Type=forward,TargetGroupArn=$TargetGrARN

echo -e "\nWaiting for the Load Balancer to be available ...\n"
aws elbv2 wait load-balancer-available --load-balancer-arns $lb

#===========================
#   Create RDS
#===========================
echo -e "\nCreating RDS instances\n"
DBName=dbmidtermab
DBID=midterm-db-ab
DBUser=abenoist
DBPass=abenoistpassdb

aws rds create-db-instance --db-name $DBName --allocated-storage 20 --db-instance-class db.t2.micro --db-instance-identifier $DBID --engine mysql --master-username $DBUser --master-user-password $DBPass
echo -e "\nWaiting for the RDS Instance to be available ...\n"
aws rds wait db-instance-available --db-instance-identifier $DBID
DBEndpoint=$(aws rds describe-db-instances --query 'DBInstances[*].Endpoint.Address')
DBPort=$(aws rds describe-db-instances --query 'DBInstances[*].Endpoint.Port')
echo -e "\nInitialising DataBase Table\n"
php config-db.php $DBEndpoint $DBUser $DBPass $DBName $DBPort

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


echo -e "\nThe environment has been created !! :)\n"