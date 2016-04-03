# ownCloud Lookup-Server

## What is Lookup-Server?
The Lookup-Server is a server component that can be run independently from an ownCloud Server. This Lookup-Server can be used by ownCloud to find remote users that can be used for federated sharing. This is useful for autocompletion in the sharing dialog to get the federation ID.  Users can optionally decide to publish their sharing ID to be found by others. Think about it as a kind of public sharing telephone book.


## Requirements
* A Linux Server
* Apache 2.4
* PHP 5.6 or newer
* MySQL


## Installation
* Put the server subfolder on a webserver vhost root.
* Create a lookup MySQL database. User the mysql.dmp
* Create a config.php as adapt the settings. config.sample.php can be used as a template.
* Make sure that that config folder is not accessible from the internet by configure Apache to respect the .htaccess
* Add user accounts to the users table in the database
* Add a cronjob that calls cronjob.php every few minutes. Recommended is 10min


## Examples for REST calls
Create user:
curl -X POST -d key=myauthkey -d federationid=myfedid -d name=myname -d email=myemail -d organisation=myuniversity -d country=DE -d city=Stuttgart -d picture=binarypicture -d vcard=vcarddata http://dev/owncloud/lookup-server/server/

Get user:
curl -X GET http://dev/owncloud/lookup-server/server/?key=myauthkey

Update user:
curl -X PUT -d key=myauthkey -d federationid=myfedid -d name=myname -d email=myemail -d organisation=myuniversity -d country=DE -d city=Stuttgart -d picture=binarypicture -d vcard=vcarddata http://dev/owncloud/lookup-server/server/

Delete user:
curl -X DELETE http://dev/owncloud/lookup-server/server/?key=myauthkey

Search users:
curl -X GET http://dev/owncloud/lookup-server/server/?search=name\&page=0



## Contribute
If you want to contribute please open a pull request here on github. Every improvement is more than welcome.


## License
This code is licenses as AGPL v2


Frank Karlitschek
frank@ownCloud.org
