-- SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
-- SPDX-License-Identifier: AGPL-3.0-or-later

-- keep one id per federationId, prioritizing the most recent timestamp and highest id
CREATE TEMPORARY TABLE kept (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO kept (id)
SELECT id FROM (
    SELECT id,
        ROW_NUMBER() OVER (
            PARTITION BY federationId
            ORDER BY timestamp DESC, id DESC
        ) AS rn
    FROM users
) ranked WHERE rn = 1;

-- collect all duplicate ids to be deleted
CREATE TEMPORARY TABLE duplicates (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO duplicates (id)
SELECT u.id FROM users u
LEFT JOIN kept k ON u.id = k.id
WHERE k.id IS NULL;

-- delete duplicate entries from store and users
START TRANSACTION;
DELETE s FROM store s INNER JOIN duplicates d ON s.userId = d.id;
DELETE u FROM users u INNER JOIN duplicates d ON u.id = d.id;
COMMIT;
