-- Broad student username cleanup
-- Safe scope:
--   Student accounts only
--
-- It fixes only these risky cases:
--   1. blank username
--   2. duplicated username
--   3. username that matches another student's userid
--
-- It does NOT touch:
--   - teachers
--   - admins
--   - student usernames that are unique and do not collide with any student userid

-- Preview the student accounts that will be corrected
SELECT
    su.userid,
    su.username,
    su.firstname,
    su.surname,
    su.systemtype
FROM tblsystemuser su
WHERE su.systemtype = 'Student'
  AND (
        su.username IS NULL
     OR su.username = ''
     OR su.username IN (
            SELECT dup.username
            FROM (
                SELECT username
                FROM tblsystemuser
                WHERE systemtype = 'Student'
                  AND username <> ''
                GROUP BY username
                HAVING COUNT(*) > 1
            ) dup
        )
     OR (
            su.username <> su.userid
        AND EXISTS (
                SELECT 1
                FROM tblsystemuser su2
                WHERE su2.systemtype = 'Student'
                  AND su2.userid = su.username
            )
        )
  )
ORDER BY su.userid;

-- Apply the correction
START TRANSACTION;

UPDATE tblsystemuser su
SET su.username = su.userid
WHERE su.systemtype = 'Student'
  AND (
        su.username IS NULL
     OR su.username = ''
     OR su.username IN (
            SELECT dup.username
            FROM (
                SELECT username
                FROM tblsystemuser
                WHERE systemtype = 'Student'
                  AND username <> ''
                GROUP BY username
                HAVING COUNT(*) > 1
            ) dup
        )
     OR (
            su.username <> su.userid
        AND EXISTS (
                SELECT 1
                FROM tblsystemuser su2
                WHERE su2.systemtype = 'Student'
                  AND su2.userid = su.username
            )
        )
  );

COMMIT;

-- Verification 1: no duplicated student usernames should remain
SELECT
    username,
    COUNT(*) AS cnt,
    GROUP_CONCAT(userid ORDER BY userid SEPARATOR ', ') AS userids
FROM tblsystemuser
WHERE systemtype = 'Student'
  AND username <> ''
GROUP BY username
HAVING COUNT(*) > 1
ORDER BY cnt DESC, username;

-- Verification 2: student accounts still not matching userid
-- These are left untouched by design unless you decide to normalize every student account.
SELECT
    userid,
    username,
    firstname,
    surname
FROM tblsystemuser
WHERE systemtype = 'Student'
  AND username <> ''
  AND username <> userid
ORDER BY userid;
