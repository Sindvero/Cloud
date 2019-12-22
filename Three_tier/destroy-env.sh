#!/bin/bash


ConfigName="configauto"
GroupName="autoname"
TargetGrARN=$(aws elbv2 describe-target-groups --query 'TargetGroups[*].{TargetGrARN:TargetGroupArn}')

echo "Destroy listener"
lb=$(aws elbv2 describe-load-balancers --query 'LoadBalancers[*].{Target:LoadBalancerArn}')
aws elbv2 delete-listener --listener-arn $(aws elbv2 describe-listeners --load-balancer-arn $lb --query 'Listeners[*].{Tlistener:ListenerArn}')

echo "Destroy Load Balancer"
aws autoscaling detach-load-balancer-target-groups --auto-scaling-group-name $GroupName --target-group-arns $TargetGrARN
aws elbv2 delete-load-balancer --load-balancer-arn $lb

echo "Destroy Target Group"
aws elbv2 delete-target-group --target-group-arn $(aws elbv2 describe-target-groups --query 'TargetGroups[*].{TargetGroup:TargetGroupArn}')

echo "Destroy autoscaling group"
aws autoscaling delete-auto-scaling-group --auto-scaling-group-name $GroupName --force-delete

echo "Destroy autoscaling configuration"
aws autoscaling delete-launch-configuration --launch-configuration-name $ConfigName

echo "Destroy instances"
aws ec2 terminate-instances --instance-ids $(aws ec2 describe-instances --query 'Reservations[*].Instances[*].{Instance:InstanceId}')

echo "Destroy DataBase"
aws dynamodb delete-table --table-name RecordsAB

echo "Destroy Topic"
aws sns delete-topic --topic-arn $(aws sns list-topics --query 'Topics[0].TopicArn')

echo "Destroy Lambda Function"

aws lambda delete-function --function-name lambda-function-mp2-ab