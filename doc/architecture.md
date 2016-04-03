# Lookup-Server architecture

## Overview
Lookup-Servers can be configured on the ownCloud Admin page. By default some are provided by default but they can be removed and custom Lookup-Servers can be added. Lookup-Servers work as public telephone book for ownCloud users. ownCloud users can optionally choose to publish some of their personal data like name, city, email and more on a Lookup-Server. This has the benefit that they can be found by other ownCloud users to simplify for example sharing.

## Security
Communication with Lookup-Servers provide should be SSL encrypted and they provide basic authentication via key for managing the personal data. Additionally Lookup-Servers provide some protection against user data scraping. But the overall idea is that all information that a user chooses to publish on a Lookup-Server is considered public and and is published optionally to be found by others. When a user decided to publish their own information an authentication key is set. The record can later be changed or deleted using this authkey.


## REST
Communication between ownCloud servers and Lookup-Servers happens via REST calls. The following REST calls exists:

### Create user
This can be used by a user to create a record and initially publish their own information.
Example:
curl -X POST -d key=myauthkey -d federationid=myfedid -d name=myname -d email=myemail -d organisation=myuniversity -d country=DE -d city=Stuttgart -d picture=binarypicture -d vcard=vcarddata http://dev/owncloud/lookup-server/server/

### Get user
This can be used to read the own record using the own authkey.
Example:
curl -X GET http://dev/owncloud/lookup-server/server/?key=myauthkey

### Update user
This can be used by a user to update the own record. Remove some infromation and publish new.
Example:
curl -X PUT -d key=myauthkey -d federationid=myfedid -d name=myname -d email=myemail -d organisation=myuniversity -d country=DE -d city=Stuttgart -d picture=binarypicture -d vcard=vcarddata http://dev/owncloud/lookup-server/server/

### Delete user
This can be used by users to delete the own record. Please note that the actual DB entry is not deleted for replication and syncing purposed between different servers. But the personal data is deleted.
Example:
curl -X DELETE http://dev/owncloud/lookup-server/server/?key=myauthkey

### Search users
This call can be used to search for a user in a fuzzy way
Example:
curl -X GET http://dev/owncloud/lookup-server/server/?search=searchstring\&page=0

### Search users by email
This call can be used to search for a user by email. This has to be a perfect match.
Example:
curl -X GET http://dev/owncloud/lookup-server/server/?email=searchstring

### Search users by userid
This call can be used to search for a user by userid. For example to update the vcard entry in the local addressbook. This has to be a perfect match.
Example:
curl -X GET http://dev/owncloud/lookup-server/server/?userid=oc12345...

### Get replication log
This call is used for master-master replication between different nodes.
Example:
curl -X GET http://lookup:foobar@dev/owncloud/lookup-server/server/replication.php/?timestamp=123456

## High availability
Several Lookup-Server can do master-master replication and sync their data. This is useful to keep the data between different servers in sync.
A user only need to publish, update or delete the record only one one server but the data will be available on different servers.
The url of the other servers and the credentials of the own server needs to be configured in the config.php file.


## DB Structure
* id - The primary key. This is specific to the current host and is not replicated. Internal only.
* userid - A world wide unique public userid. This can be used by users to remember and identify other users.
* authkey - The private secret that is needed to update or delete a record.
* federationid - The public federation ID. visible to others.
* name - The public name of a user.
* email - The public email of a user.
* organisation - The organisation or company of a user. Can help to be found easier.
* country - The country of a user in cleartext.
* city - The city of a user
* picture - The binary picture of the user.
* vcard - The public vcard of a user.
* created - The internal time stamp when this record was created.
* changed - The internal time stamp when this record was created or changed.
* karma - The karma of the user. See below for detailed explenation.


## Karma
The visibility of a user in the search call depends on the Karma of a user. There are the following Karma levels.
* 0 - A newly created record where the email is not yet verified. This record is not visible in the search.
* 1 - A record with a confirmed email. This record is visible via search.
* -1 - This record was actively deleted by a user and is not visible in the search.
* >1 - This record got additional verification and is better visible then standard accounts with Karma 1.
