(function () {
  'use strict';

  var toggle = document.querySelector('[data-testimonials-toggle]');
  if (!toggle) {
    return;
  }

  var hiddenCards = Array.prototype.slice.call(
    document.querySelectorAll('[data-testimonial-extra="true"]')
  );

  if (hiddenCards.length === 0) {
    toggle.hidden = true;
    return;
  }

  var expanded = false;

  function render() {
    hiddenCards.forEach(function (card) {
      card.hidden = !expanded;
    });

    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    toggle.textContent = expanded ? 'Ver menos depoimentos' : 'Ver mais depoimentos';
  }

  toggle.addEventListener('click', function () {
    expanded = !expanded;
    render();
  });

  render();
})();
