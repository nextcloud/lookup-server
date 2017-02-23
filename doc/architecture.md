# Lookup-Server architecture

## Overview
Lookup-Servers can be configured on the Nextcloud Admin page. By default some
are provided by default but they can be removed and custom Lookup-Servers can
be added. Lookup-Servers work as public telephone book for Nextcloud users.
Nextcloud users can optionally choose to publish some of their personal data
like name, city, email and more on a Lookup-Server. This has the benefit that
they can be found by other Nextcloud users to simplify for example sharing.

## Security
Communication with Lookup-Servers provide should be SSL encrypted and they 
provide basic authentication via public key signing of the personal data.
Additionally Lookup-Servers provide some protection against user data
scraping. But the overall idea is that all information that a user chooses
to publish on a Lookup-Server is considered public and and is published
optionally to be found by others. When a user decided to publish its own
data a public key is obtained from its Nextcloud instance (deducted from
their federated cloud id). This public key is used to verify
the signature of the send data.

### Key requirements
The length of the key must be at least 2048 bits. And the digest algorithm
is `sha512`. The signature algorithm is also `sha512`.


## REST
Communication between Nextcloud servers and Lookup-Servers happens via REST 
calls. The following REST calls exists:

### Create user
This can be used by a user to create a record and initially publish their own
information.

Endpoint: http://dev/nextcloud/lookup-server/server/users
Method: POST
Data: JSON blob 

```
{
  'message' : {
    'data' : {
      'federationId' : 'foo@cloud.bar.com',
      'name' : 'Foo Bar',
      'email' : 'foo@bar.com',
      'address' : 'Foo Road 1',
      'website' : 'example.com',
      'twitter' : '@foo',
      'phone' : '+1234567890'
    },
    'type' : 'lookupserver',
    'timestamp' : 1337,
    'signer' : 'foo@cloud.bar.com'
  },
  'signature' : '0ABCDDEE....'
}
```

### Update user
Updating a record is the same as publishing a new record. Unchanged fields will
not be touched. New fields will be added (and if possible verified). And fields
no longer in the update request will be removed.

Endpoint: http://dev/nextcloud/lookup-server/server/users
Method: POST
Data: JSON blob 

```
{
  'message' : {
    'data' : {
      'federationId' : 'foo@cloud.bar.com',
      'name' : 'Foo Bar',
      'email' : 'foo@bar.com',
      'address' : 'Foo Road 1',
      'website' : 'example.com',
      'twitter' : '@foo',
      'phone' : '+1234567890'
    },
    'type' : 'lookupserver',
    'timestamp' : 1337,
    'signer' : 'foo@cloud.bar.com'
  },
  'signature' : '0ABCDDEE....'
}
```

### Delete user
Deleting is simply removing all additional info.

Endpoint: http://dev/nextcloud/lookup-server/server/users
Method:DELETE
Data: JSON blob 

```
{
  'message' : {
    'data' : {
      'federationId' : 'foo@cloud.bar.com',
    },
    'type' : 'lookupserver',
    'timestamp' : 1337,
    'signer' : 'foo@cloud.bar.com'
  },
  'signature' : '0ABCDDEE....'
}
```

Note the server will still keep the cloud id in order to properly propagate this.
But all other personal data will be removed.

### Search users
This call can be used to search for a user in a fuzzy way
Example:
curl -X GET http://dev/nextcloud/lookup-server/server/users?search=searchstring

### Get replication log
This call is used for master-master replication between different nodes.
Example:
curl -X GET http://lookup:foobar@dev/nextcloud/lookup-server/server/replication.php/?timestamp=123456\&page=0  

## High availability
Several Lookup-Server can do master-master replication and sync their data. 
This is useful to keep the data between different servers in sync. A user only
need to publish, update or delete the record only one one server but the data
will be available on different servers. The url of the other servers and the
credentials of the own server needs to be configured in the config.php file.

## DB Structure

### User table
* id - The primary id of the table
* federationId - The federationId of the user
* timestamp - Time of the last update of the user

The timestamp is stored to prevent replaying of old requests.

### Store table
* id - Primary id of the table
* userId - Foreign Key to the User table
* k - The key
* v - The value

This table stores all the key value pairs of published information.

### emailValidation table
* id - Primary id of the table
* storeId - Foreign key to the Store table
* token - Verification token for the email

This table holds email verification data

## Karma
The visibility of a user in the search call depends on the Karma of a user. 
Karma is the number of verified fields. For example a user that has verified
their email and twitter has a karma of 2.

Only entries with a karma of at least 1 show up in search. The results are
ordered by karma.


## Verification

### Email
Every time a new user is registered or an existing user changes the email
address the emailstatus is set to unverified. A verification email is send to
the new address. Once the link in that email is clicked the email address is
set to verified again.
