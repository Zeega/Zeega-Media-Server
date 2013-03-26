Zeega image management app. 

## Instalation instructions

Install Imagick. On Ubuntu:

`sudo apt-get install php5-imagic`

Install composer:

`curl -sS https://getcomposer.org/installer | php`

`sudo mv composer.phar /usr/local/bin/composer`

Change the log and web directory permissions (you may need to [enable ACL](https://help.ubuntu.com/community/FilePermissionsACLs)):

`sudo setfacl -dR -m u:www-data:rwx -m u:root:rwx logs/ web/`

`sudo setfacl -R -m u:www-data:rwX -m u:root:rwX logs/ web/`
