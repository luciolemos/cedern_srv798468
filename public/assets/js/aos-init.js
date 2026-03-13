(function () {
  var nodes = document.querySelectorAll('[data-aos]');
  if (!nodes.length) {
    return;
  }

  function revealAll() {
    nodes.forEach(function (node) {
      node.classList.add('aos-animate');
    });
  }

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    revealAll();
    return;
  }

  if (typeof AOS === 'undefined') {
    revealAll();
    return;
  }

  try {
    AOS.init({
      duration: 700,
      easing: 'ease-out-cubic',
      once: true,
      offset: 70,
      startEvent: 'DOMContentLoaded',
      mirror: false,
      disable: function () {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      }
    });

    // Fail-safe: if AOS loaded but did not animate initial viewport elements,
    // reveal everything to avoid a blank-looking page.
    setTimeout(function () {
      var animated = document.querySelectorAll('[data-aos].aos-animate').length;
      if (animated === 0) {
        revealAll();
      }
    }, 350);
  } catch (error) {
    revealAll();
  }
})();
  }

  try {
    AOS.init({
      duration: 700,
      easing: 'ease-out-cubic',
      once: true,
      offset: 70,
      startEvent: 'DOMContentLoaded',
      mirror: false,
      disable: function () {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      }
    });

    // Fail-safe: if AOS loaded but did not animate initial viewport elements,
    // reveal everything to avoid a blank-looking page.
    setTimeout(function () {
      var animated = document.querySelectorAll('[data-aos].aos-animate').length;
      if (animated === 0) {
        revealAll();
      }
    }, 350);
  } catch (error) {
    revealAll();
  }
})();
