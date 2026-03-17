(function () {
  const initSlideshows = () => {
    const sliders = document.querySelectorAll('[data-cards-slideshow]');

    sliders.forEach((slider) => {
      const track = slider.querySelector('[data-slideshow-track]');
      const controls = slider.querySelector('[data-slideshow-controls]');
      const prevButton = slider.querySelector('[data-slideshow-prev]');
      const nextButton = slider.querySelector('[data-slideshow-next]');
      const status = slider.querySelector('[data-slideshow-status]');

      if (!track || !controls || !prevButton || !nextButton || !status) {
        return;
      }

      const cards = Array.from(track.querySelectorAll('.nc-testimonial-card'));
      const pageSizeMobile = Math.max(1, parseInt(slider.getAttribute('data-page-size-mobile') || '1', 10));
      const pageSizeTablet = Math.max(1, parseInt(slider.getAttribute('data-page-size-tablet') || '2', 10));
      const pageSizeDesktop = Math.max(1, parseInt(slider.getAttribute('data-page-size-desktop') || '3', 10));
      const autoplayEnabled = (slider.getAttribute('data-autoplay') || '').toLowerCase() === 'true';
      const autoplayInterval = Math.max(2000, parseInt(slider.getAttribute('data-autoplay-interval') || '5000', 10));
      let currentPage = 0;
      let autoplayTimer = null;
      let pageSize = pageSizeDesktop;
      let totalPages = 1;

      const getCurrentPageSize = () => {
        const width = window.innerWidth || document.documentElement.clientWidth || 1280;

        if (width < 768) {
          return pageSizeMobile;
        }

        if (width < 1024) {
          return pageSizeTablet;
        }

        return pageSizeDesktop;
      };

      const recalculatePagination = () => {
        pageSize = getCurrentPageSize();
        totalPages = Math.max(1, Math.ceil(cards.length / pageSize));

        if (currentPage > totalPages - 1) {
          currentPage = totalPages - 1;
        }
      };

      const render = () => {
        recalculatePagination();

        const startIndex = currentPage * pageSize;
        const endIndex = startIndex + pageSize;

        cards.forEach((card, index) => {
          card.hidden = index < startIndex || index >= endIndex;
        });

        status.textContent = `${currentPage + 1} / ${totalPages}`;
        prevButton.disabled = currentPage === 0;
        nextButton.disabled = currentPage >= totalPages - 1;
        controls.hidden = cards.length <= pageSize;
      };

      const stopAutoplay = () => {
        if (autoplayTimer !== null) {
          window.clearInterval(autoplayTimer);
          autoplayTimer = null;
        }
      };

      const startAutoplay = () => {
        if (!autoplayEnabled || autoplayTimer !== null || cards.length <= pageSize) {
          return;
        }

        autoplayTimer = window.setInterval(() => {
          currentPage = currentPage < totalPages - 1 ? currentPage + 1 : 0;
          render();
        }, autoplayInterval);
      };

      prevButton.addEventListener('click', () => {
        if (currentPage > 0) {
          currentPage -= 1;
          render();
        }
      });

      nextButton.addEventListener('click', () => {
        if (currentPage < totalPages - 1) {
          currentPage += 1;
          render();
        }
      });

      slider.addEventListener('mouseenter', stopAutoplay);
      slider.addEventListener('mouseleave', startAutoplay);
      slider.addEventListener('focusin', stopAutoplay);
      slider.addEventListener('focusout', startAutoplay);

      window.addEventListener('resize', () => {
        const previousPageSize = pageSize;
        recalculatePagination();

        if (previousPageSize !== pageSize) {
          render();
          stopAutoplay();
          startAutoplay();
        }
      });

      render();
      startAutoplay();
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSlideshows);
  } else {
    initSlideshows();
  }
})();
