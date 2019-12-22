#!/bin/bash

sudo apt-get -y update
sudo apt-get -y install apache2 php php-gd mysql-server php7.2-xml php-curl mysql-client php-imagick php-mysql python3-pip python3-dev python3-setuptools

pip3 install boto3
pip3 install Pillow


EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)"

php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"


if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]

then

	>&2 echo 'ERROR: Invalid installer signature'
	
		rm composer-setup.php

   			 exit 1
fi

sudo php composer-setup.php --quiet

sudo php -d memory_limit=-1 composer.phar require aws/aws-sdk-php imagine/imagine



sudo systemctl enable apache2
sudo systemctl start apache2

git clone git@github.com:illinoistech-itm/abenoist.git
sudo cp -r /abenoist/ITMO544/mp2/application/* /var/www/html

sudo mkdir /var/www/html/uploads/
sudo chmod 777 /var/www/html/uploads


sudo systemctl restart apache2

