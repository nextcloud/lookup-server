-- SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
-- SPDX-License-Identifier: AGPL-3.0-or-later

ALTER TABLE users DROP INDEX `federationId`, ADD UNIQUE KEY `federationId` (`federationId`(191));
