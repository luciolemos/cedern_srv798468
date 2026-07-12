ALTER TABLE member_users
    MODIFY COLUMN is_contributor TINYINT(1) NULL DEFAULT NULL;

UPDATE member_users
SET is_contributor = CASE
    WHEN association_status = 'applicant' THEN NULL
    WHEN association_status <> 'member' THEN 0
    WHEN is_contributor = 1 THEN 1
    WHEN is_contributor = 0 THEN 0
    ELSE NULL
END;
