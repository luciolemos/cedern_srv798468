document.addEventListener('DOMContentLoaded', function () {
  var copyBtn = document.querySelector('[data-pix-copy]');
  var feedback = document.querySelector('[data-pix-copy-feedback]');
  if (!copyBtn) return;

  function isDesktop() {
    return window.innerWidth >= 768;
  }

  copyBtn.addEventListener('click', function () {
    var key = copyBtn.getAttribute('data-pix-key');
    if (!key) return;
    navigator.clipboard.writeText(key).then(function () {
      if (isDesktop()) {
        feedback.hidden = false;
        setTimeout(function () {
          feedback.hidden = true;
        }, 1800);
      }
    });
  });
});
