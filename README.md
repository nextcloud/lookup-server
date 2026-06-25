<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Nextcloud Lookup-Server

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/lookup-server)](https://api.reuse.software/info/github.com/nextcloud/lookup-server)

## What is Lookup-Server?

The Lookup-Server is a server component that can be run independently from an Nextcloud Server. This Lookup-Server can be used by Nextcloud to find remote users that can be used for federated sharing. This is useful for autocompletion in the sharing dialog to get the federation ID.  Users can optionally decide to publish their sharing ID to be found by others. Think about it as a kind of public sharing telephone book.

## Documentation

Please look into the [doc](./doc) directory for more information.

## Duplicate `federationId` entries on `users` table

In some cases, due to race condition, duplicate entries may have been inserted into the `users` table, meaning more than one row with the same `federationId`. While this does not cause any critical issues, it can lead to unnecessary database growth over time.

Since this was reported, a `UNIQUE KEY` constraint has been added to `users.federationId` in `mysql.dmp`, preventing this from happening on new installations.

If your installation was set up before this change, two SQL scripts are available under `sql/` to help you fix this:

1. `sql/cleanup_duplicate_users.sql` - removes entries with duplicated `federationId`, keeping the most recent one
2. `sql/add_unique_federationId.sql` - adds the unique constraint on `users.federationId`

`cleanup_duplicate_users.sql` should be run before `add_unique_federationId.sql`, since the unique constraint cannot be added while duplicates exist.

Scripts can be run like:
```bash
mysql -h database_host -u username -p database_name < sql/cleanup_duplicate_users.sql
```

> Even though data in these tables is regenerated upon new user creation/login, it is advised to back up your database before running the scripts.

## License

This code is licensed as AGPL v3 or later.

## Contribute

If you want to contribute please open a pull request here on github. Every improvement is welcome.

## Author

Frank Karlitschek
frank@nextcloud.com
