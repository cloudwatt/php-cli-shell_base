# PHP-CLI SHELL

__New release will be only available on (here)[https://github.com/Renji-FR/PhpCliShell]__

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
Ubuntu only, you can get last PHP version from this PPA:  
__*https://launchpad.net/~ondrej/+archive/ubuntu/php*__
* add-apt-repository ppa:ondrej/php
* apt update

You have to install a PHP version >= 7.1:
* apt install php7.3-cli php7.3-soap php7.3-mbstring php7.3-readline php7.3-curl

For MacOS users which use PHP 7.3, there is an issue with PCRE.
You have to add this configuration in your php.ini:
```ini
pcre.jit=0
```
*To locate your php.ini, use this command: php -i | grep "Configuration File"*

For more information about PCRE configuration:
- https://php.net/manual/en/pcre.configuration.php#ini.pcre.jit

#### REPOSITORY
* git clone https://github.com/cloudwatt/php-cli-shell_base
* git checkout tags/v2.1.2

#### ADDON / APPLICATION
*Be careful, you have to install the same version of the addon or application as base version*  
Follow the addon README to install the addon or application:
- PHPIPAM: https://github.com/cloudwatt/php-cli-shell_phpipam
- PATCHMANAGER: https://github.com/cloudwatt/php-cli-shell_patchmanager
- FIREWALL: https://github.com/cloudwatt/php-cli-shell_firewall
