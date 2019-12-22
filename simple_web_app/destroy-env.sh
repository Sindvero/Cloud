#!/bin/bash

echo "Destroy instances"
aws ec2 terminate-instances --instance-ids $(aws ec2 describe-instances --query 'Reservations[*].Instances[*].{Instance:InstanceId}')

echo "Destroy listener"
lb=$(aws elbv2 describe-load-balancers --query 'LoadBalancers[*].{Target:LoadBalancerArn}')
aws elbv2 delete-listener --listener-arn $(aws elbv2 describe-listeners --load-balancer-arn $lb --query 'Listeners[*].{Tlistener:ListenerArn}')

echo "Destroy Load Balancer"
aws elbv2 delete-load-balancer --load-balancer-arn $lb

echo "Destroy Target Group"
aws elbv2 delete-target-group --target-group-arn $(aws elbv2 describe-target-groups --query 'TargetGroups[*].{TTargetGroup:TargetGroupArn}')

echo "Destroy RDS Instances"
aws rds delete-db-instance --db-instance-identifier $(aws rds describe-db-instances --query 'DBInstances[*].{Target:DBInstanceIdentifier}') --skip-final-snapshot

echo "Destroy S3 Buckets"
Buckets=($(aws s3api list-buckets --query "Buckets[].Name"))
for i in "${Buckets[@]}"; do
aws s3 rm s3://$i --recursive
aws s3api delete-bucket --bucket $i
done