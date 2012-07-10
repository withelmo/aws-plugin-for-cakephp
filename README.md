# CAUTION!!!

This plugin has not been completed yet.
Keep in mind that you cannot use this.

# Requirements
* PHP 5.3.0 or greater.
* CakePHP 2.1.3 or greater

# AWS Plugin for CakePHP

This plugin is supporting following Amazon Web Services. 

* Simple Email Service(SES)
* Maybe someday
 
# Installation
## Simple Email Service(SES)
* Put the files into app/Plugin/Aws
* Load this plugin by calling `Cakeplugin::load('Aws');` in app/Config/bootstrap.php

#Configurations
```PHP
// SomeController.php
public $components = array('Aws.SimpleEmail' => array(
    'accessKey' => 'XXXXXXXXXXXXXXXXXXXX',
    'secretKey' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),
);

public function sendEmail() {
    $this->SimpleEmail->from('sender@example.co.jp');
    $this->SimpleEmail->to('receiver@example.co.jp');
    $this->SimpleEmail->subject('This is subject');
    $this->SimpleEmail->cakeText($content, 'element', 'layout');
    $res = $this->SimpleEmail->sendMail(true);
    
    if ($res['status'] == 200) {
        echo 'OK';
    } else {
        echo 'NG';
    }
}
```
