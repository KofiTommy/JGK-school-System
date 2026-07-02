-- Fix duplicated/misaligned student usernames for the affected Home Econs classes.
-- Safe scope:
--   2 Home Econs  / September 2024
--   3 Home Econs  / September 2023
--
-- What it does:
--   Sets username = userid for the targeted active student accounts only.
--
-- Recommended use:
--   1. Run the preview query first.
--   2. Confirm the affected rows look correct.
--   3. Run the transaction block.
--   4. Run the final verification query.

-- Preview rows that will be corrected
SELECT
    su.userid,
    su.username,
    su.firstname,
    su.surname,
    ce.class_name,
    bh.batch,
    su.status AS account_status,
    cl.status AS class_status
FROM tblsystemuser su
INNER JOIN tblclass cl
    ON cl.userid = su.userid
   AND cl.status = 'active'
INNER JOIN tblclassentry ce
    ON ce.class_entryid = cl.class_entryid
LEFT JOIN tblbatch bh
    ON bh.batchid = cl.batchid
WHERE su.systemtype = 'Student'
  AND (
        (ce.class_name = '2 Home Econs' AND bh.batch = 'September 2024')
     OR (ce.class_name = '3 Home Econs' AND bh.batch = 'September 2023')
  )
  AND (
        su.username IS NULL
     OR su.username = ''
     OR su.username <> su.userid
  )
ORDER BY ce.class_name, bh.batch, su.userid;

-- Apply the correction
START TRANSACTION;

UPDATE tblsystemuser su
INNER JOIN tblclass cl
    ON cl.userid = su.userid
   AND cl.status = 'active'
INNER JOIN tblclassentry ce
    ON ce.class_entryid = cl.class_entryid
LEFT JOIN tblbatch bh
    ON bh.batchid = cl.batchid
SET su.username = su.userid
WHERE su.systemtype = 'Student'
  AND (
        (ce.class_name = '2 Home Econs' AND bh.batch = 'September 2024')
     OR (ce.class_name = '3 Home Econs' AND bh.batch = 'September 2023')
  )
  AND (
        su.username IS NULL
     OR su.username = ''
     OR su.username <> su.userid
  );

COMMIT;

-- Verify after update
SELECT
    su.userid,
    su.username,
    su.firstname,
    su.surname,
    ce.class_name,
    bh.batch
FROM tblsystemuser su
INNER JOIN tblclass cl
    ON cl.userid = su.userid
   AND cl.status = 'active'
INNER JOIN tblclassentry ce
    ON ce.class_entryid = cl.class_entryid
LEFT JOIN tblbatch bh
    ON bh.batchid = cl.batchid
WHERE su.systemtype = 'Student'
  AND (
        (ce.class_name = '2 Home Econs' AND bh.batch = 'September 2024')
     OR (ce.class_name = '3 Home Econs' AND bh.batch = 'September 2023')
  )
ORDER BY ce.class_name, bh.batch, su.userid;
