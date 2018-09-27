# PHP-CLI SHELL

This repository is the base of PHP Shell project. It contains base classes for create a PHP Shell.  
You must have a service which has an API and develop classes to use PHP-CLI Shell and your API.  
  
There is three projects which use PHP-CLI Shell:
- PHPIPAM: https://github.com/cloudwatt/php-cli-shell_phpipam
- PATCHMANAGER: https://github.com/cloudwatt/php-cli-shell_patchmanager
- FIREWALL: https://github.com/cloudwatt/php-cli-shell_firewall

PHPIPAM is an IPAM in PHP with REST API: https://phpipam.net/  
PATCHMANAGER is an DCIM in JAVA with SOAP and REST API: https://patchmanager.com/  
FIREWALL is an ACL manager with firewall appliance templating.  
*FIREWALL service can use PHPIPAM API for autocompletion objects.*  

You can use one of this three projects for help you to develop your own project.


# INSTALLATION

#### APT PHP
__*https://launchpad.net/~ondrej/+archive/ubuntu/php*__
* add-apt-repository ppa:ondrej/php
* apt-get update
* apt install php7.1-cli php7.1-mbstring php7.1-readline

#### REPOSITORY
* git clone https://github.com/cloudwatt/php-cli-shell_base
* git checkout tags/v1.1