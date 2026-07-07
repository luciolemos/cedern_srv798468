UPDATE member_users
SET profile_photo_path = REPLACE(
  profile_photo_path,
  'assets/img/member-photos/',
  'media/membros/fotos/'
)
WHERE profile_photo_path LIKE 'assets/img/member-photos/%';

UPDATE member_users
SET profile_photo_path = REPLACE(
  profile_photo_path,
  'assets/img/avatar/',
  'media/membros/fotos/'
)
WHERE profile_photo_path LIKE 'assets/img/avatar/%';
