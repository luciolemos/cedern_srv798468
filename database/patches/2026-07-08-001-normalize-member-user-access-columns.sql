UPDATE member_users
SET status = CASE
    WHEN status IS NULL OR TRIM(status) = '' THEN 'pending'
    WHEN LOWER(TRIM(status)) IN ('pending') THEN 'pending'
    WHEN LOWER(TRIM(status)) IN ('active', 'approved', 'aprovado') THEN 'active'
    WHEN LOWER(TRIM(status)) IN ('blocked', 'inactive', 'inativo', 'bloqueado') THEN 'blocked'
    ELSE 'pending'
END;

UPDATE member_users
SET association_status = CASE
    WHEN association_status IS NULL OR TRIM(association_status) = '' THEN
        CASE
            WHEN status = 'pending' THEN 'applicant'
            ELSE 'member'
        END
    WHEN LOWER(TRIM(association_status)) IN ('applicant', 'solicitante', 'pending') THEN 'applicant'
    WHEN LOWER(TRIM(association_status)) IN ('member', 'associado', 'active') THEN 'member'
    WHEN LOWER(TRIM(association_status)) IN ('former', 'desligado', 'inactive', 'blocked') THEN 'former'
    ELSE 'member'
END;

ALTER TABLE member_users
    MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending',
    MODIFY COLUMN association_status VARCHAR(20) NOT NULL DEFAULT 'applicant';

UPDATE member_users
SET
    status = 'pending',
    role_id = NULL,
    member_type = NULL,
    institutional_role = NULL,
    is_contributor = 0
WHERE association_status = 'applicant';

UPDATE member_users
SET
    status = 'blocked',
    role_id = NULL,
    member_type = NULL,
    institutional_role = NULL,
    is_contributor = 0
WHERE association_status = 'former';

UPDATE member_users
SET status = 'active'
WHERE association_status = 'member'
  AND status NOT IN ('active', 'blocked');

UPDATE member_users
SET institutional_role = NULL
WHERE association_status = 'member'
  AND status <> 'active';
