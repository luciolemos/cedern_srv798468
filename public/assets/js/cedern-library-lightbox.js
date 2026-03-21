(function () {
  var lockedScrollY = null;

  function setPageScrollLock(isLocked) {
    var root = document.documentElement;
    var body = document.body;

    if (isLocked) {
      if (lockedScrollY !== null) {
        return;
      }

      lockedScrollY = window.scrollY || root.scrollTop || 0;

      root.classList.add('is-library-cover-dialog-open');
      body.classList.add('is-library-cover-dialog-open');
      body.style.position = 'fixed';
      body.style.top = '-' + lockedScrollY + 'px';
      body.style.left = '0';
      body.style.right = '0';
      body.style.width = '100%';
      body.style.overflow = 'hidden';

      return;
    }

    if (lockedScrollY === null) {
      return;
    }

    root.classList.remove('is-library-cover-dialog-open');
    body.classList.remove('is-library-cover-dialog-open');
    body.style.position = '';
    body.style.top = '';
    body.style.left = '';
    body.style.right = '';
    body.style.width = '';
    body.style.overflow = '';

    window.scrollTo(0, lockedScrollY);
    lockedScrollY = null;
  }

  function buildCaption(title, author) {
    var safeTitle = (title || '').trim();
    var safeAuthor = (author || '').trim();

    if (safeTitle && safeAuthor) {
      return safeTitle + ' · ' + safeAuthor;
    }

    return safeTitle || safeAuthor || '';
  }

  function clearStage(stage) {
    while (stage.firstChild) {
      stage.removeChild(stage.firstChild);
    }
  }

  function buildLightboxContent(cover) {
    var coverImage = cover.querySelector('.nc-library-bookplate-image');

    if (coverImage) {
      var previewImage = document.createElement('img');
      previewImage.className = 'nc-library-cover-dialog-image';
      previewImage.src = coverImage.currentSrc || coverImage.getAttribute('src') || '';
      previewImage.alt = coverImage.getAttribute('alt') || '';
      previewImage.decoding = 'async';
      return previewImage;
    }

    var clone = cover.cloneNode(true);
    clone.removeAttribute('id');
    clone.classList.add('is-lightbox-preview');
    return clone;
  }

  document.addEventListener('DOMContentLoaded', function () {
    var dialog = document.querySelector('[data-library-cover-dialog]');
    var stage = document.querySelector('[data-library-cover-stage]');
    var caption = document.querySelector('[data-library-cover-caption]');
    var closeButton = document.querySelector('[data-library-cover-close]');
    var triggers = document.querySelectorAll('[data-library-cover-trigger]');

    if (!dialog || !stage || !caption || !closeButton || !triggers.length) {
      return;
    }

    function closeDialog() {
      if (typeof dialog.close === 'function' && dialog.open) {
        dialog.close();
      } else {
        dialog.removeAttribute('open');
      }

      setPageScrollLock(false);
      clearStage(stage);
      caption.hidden = true;
      caption.textContent = '';
    }

    function openDialog(trigger) {
      var cover = trigger.querySelector('.nc-library-bookplate');

      if (!cover) {
        return;
      }

      clearStage(stage);

      var content = buildLightboxContent(cover);

      if (!content) {
        return;
      }

      stage.appendChild(content);

      var captionText = buildCaption(
        trigger.getAttribute('data-library-cover-title'),
        trigger.getAttribute('data-library-cover-author')
      );

      if (captionText) {
        caption.textContent = captionText;
        caption.hidden = false;
      } else {
        caption.hidden = true;
        caption.textContent = '';
      }

      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else {
        dialog.setAttribute('open', 'open');
      }

      setPageScrollLock(true);
    }

    triggers.forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        openDialog(trigger);
      });
    });

    closeButton.addEventListener('click', closeDialog);

    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) {
        closeDialog();
      }
    });

    dialog.addEventListener('close', function () {
      setPageScrollLock(false);
      clearStage(stage);
      caption.hidden = true;
      caption.textContent = '';
    });
  });
})();
