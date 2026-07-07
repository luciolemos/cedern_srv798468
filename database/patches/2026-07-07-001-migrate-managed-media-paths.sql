UPDATE bookshop_books
SET cover_image_path = REPLACE(
  cover_image_path,
  'assets/img/bookshop-covers/',
  'media/livraria/capas/'
)
WHERE cover_image_path LIKE 'assets/img/bookshop-covers/%';

UPDATE library_books
SET cover_image_path = REPLACE(
  cover_image_path,
  'assets/img/library-covers/',
  'media/biblioteca/capas/'
)
WHERE cover_image_path LIKE 'assets/img/library-covers/%';

UPDATE library_books
SET pdf_path = REPLACE(
  pdf_path,
  'assets/docs/library/',
  'media/biblioteca/docs/'
)
WHERE pdf_path LIKE 'assets/docs/library/%';
