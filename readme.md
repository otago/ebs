# EBS - SilverStripe interface

This extension allows a SilverStripe instance communicate with Tribals EBS4 
Student Management System via a web service. You can customise your own read only 
web service queries, giving you full access to the student database. 


![Tribal logo](images/tribal_logo.jpg)

###### Note: Otago Polytechnic maintains this module, Tribal is not affiliated with the maintenance of it.

# Installation

Use composer to install the module:

```
$composer require otago/ebs
```

Then create an EBS user:

### 1. Open 'EBS Central' (client)
### 2. Access user management 
![Access user management](images/ebs3.png)
### 3. Create a new role
![Create a role with at least these permissions](images/ebs4.png)

If you do not have permission to do this, you may need to seek your SMS administrator.

After create a **mysite/_config/ebs.yml** file with your EBS web service user details in it:

```php
---
Name: EBSWebservice
---
EBSWebservice:
    authentication:
        username: webservicename
        password: webservicepassword
        locationTest: https://ebs-test.organisation.domain/Rest/
        locationDev:  https://ebs-dev.organisation.domain/Rest/
        locationLive: https://ebs-live.organisation.domain/Rest/
```

Use a VPN or Firewall rules to secure data can only move between EBS and your web server. 

# How to edit web services

You can do this with agent designer in client allows SMS users to create SQL 
queries that feed into JSON web services. There are plenty of out of the box SQL 
queries which you can use such as searching (see example 3). 

1. ![Access user management](images/ebs2.png) 

# Examples 

See docs/ folder

 1. [Reading a service](/docs/1_reading.md)
 2. [Creating a learner](/docs/2_learner.md)
 3. [Creating an application](/docs/3_application.md) - with a dynamic search



