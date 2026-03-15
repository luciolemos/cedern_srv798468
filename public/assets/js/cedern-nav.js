const initCedernNav = () => {
  const header = document.querySelector(".nc-header");
  const toggle = document.querySelector(".nc-nav-toggle");
  const nav = document.getElementById("nc-nav-menu");
  if (!header || !toggle || !nav) {
    return;
  }

  const desktopQuery = window.matchMedia("(min-width: 981px)");
  const navGroups = Array.from(nav.querySelectorAll("[data-nav-group]"));

  const setGroupOpen = (group, open) => {
    const toggle = group.querySelector(".nc-nav-group-toggle");
    const submenu = group.querySelector(".nc-nav-submenu");
    if (!toggle || !submenu) {
      return;
    }

    group.classList.toggle("is-open", open);
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    submenu.hidden = !open;
  };

  const closeAllGroups = () => {
    navGroups.forEach((group) => setGroupOpen(group, false));
  };

  const syncGroupsForViewport = () => {
    const isDesktop = desktopQuery.matches;
    navGroups.forEach((group) => {
      setGroupOpen(group, false);
      const submenu = group.querySelector(".nc-nav-submenu");
      if (!submenu) {
        return;
      }
      submenu.hidden = !isDesktop;
    });
  };

  const isMenuOpen = () => toggle.getAttribute("aria-expanded") === "true";

  const setState = (open) => {
    const isDesktop = desktopQuery.matches;
    const expanded = isDesktop ? true : open;

    toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
    toggle.setAttribute("aria-label", expanded ? "Fechar menu" : "Abrir menu");
    
    // Toggle hidden attribute
    nav.hidden = isDesktop ? false : !expanded;
    
    // Toggle class for CSS display
    if (expanded && !isDesktop) {
      nav.classList.add("is-open");
    } else {
      nav.classList.remove("is-open");
    }
  };

  const closeMenu = () => {
    if (!desktopQuery.matches) {
      // Small delay to allow click event to propagate
      setTimeout(() => {
        setState(false);
        closeAllGroups();
      }, 50);
    }
  };

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  const getAnchorOffset = () => {
    const cssOffset = Number.parseFloat(
      window.getComputedStyle(document.documentElement).getPropertyValue("--anchor-offset")
    );
    const fallbackOffset = header.getBoundingClientRect().height + 16;

    if (Number.isFinite(cssOffset) && cssOffset >= 0) {
      return cssOffset;
    }

    return fallbackOffset;
  };

  const getHashTarget = (hash) => {
    if (!hash || hash === "#" || !hash.startsWith("#")) {
      return null;
    }

    const encodedId = hash.slice(1);
    if (!encodedId) {
      return null;
    }

    const decodedId = (() => {
      try {
        return window.decodeURIComponent(encodedId);
      } catch (error) {
        return encodedId;
      }
    })();

    return document.getElementById(decodedId);
  };

  const getScrollTopForTarget = (target) => {
    let targetTop = 0;
    let current = target;

    while (current) {
      targetTop += current.offsetTop;
      current = current.offsetParent;
    }

    return Math.max(0, targetTop - getAnchorOffset());
  };

  const scrollToHash = (hash, smooth = true) => {
    const target = getHashTarget(hash);
    if (!target) {
      return;
    }

    const useSmooth = smooth && !prefersReducedMotion.matches;
    window.scrollTo({
      top: getScrollTopForTarget(target),
      behavior: useSmooth ? "smooth" : "auto",
    });
  };

  toggle.addEventListener("click", () => {
    setState(!isMenuOpen());
    if (desktopQuery.matches) {
      closeAllGroups();
    }
  });

  navGroups.forEach((group) => {
    const groupToggle = group.querySelector(".nc-nav-group-toggle");
    if (!groupToggle) {
      return;
    }

    const isMemberUserMenu = group.classList.contains("nc-member-nav-group");
    let memberMenuCloseTimer = null;

    groupToggle.addEventListener("click", (event) => {
      event.preventDefault();

      const willOpen = groupToggle.getAttribute("aria-expanded") !== "true";

      closeAllGroups();
      setGroupOpen(group, willOpen);
    });

    if (isMemberUserMenu) {
      group.addEventListener("mouseenter", () => {
        if (!desktopQuery.matches) {
          return;
        }

        if (memberMenuCloseTimer) {
          window.clearTimeout(memberMenuCloseTimer);
          memberMenuCloseTimer = null;
        }

        closeAllGroups();
        setGroupOpen(group, true);
      });

      group.addEventListener("mouseleave", () => {
        if (!desktopQuery.matches) {
          return;
        }

        if (memberMenuCloseTimer) {
          window.clearTimeout(memberMenuCloseTimer);
        }

        memberMenuCloseTimer = window.setTimeout(() => {
          setGroupOpen(group, false);
          memberMenuCloseTimer = null;
        }, 320);
      });
    }
  });

  nav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", (event) => {
      const href = link.getAttribute("href") || "";
      const isHashLink = href.startsWith("#") || href.startsWith("/#");
      const hash = href.startsWith("/#") ? href.slice(1) : href;

      if (isHashLink && window.location.pathname === "/") {
        event.preventDefault();
        if (window.location.hash !== hash) {
          window.history.pushState(null, "", hash);
        }
        closeMenu();
        window.setTimeout(() => scrollToHash(hash, true), 0);
        return;
      }

      closeMenu();
    });
  });

  window.addEventListener("hashchange", () => {
    if (window.location.pathname === "/" && window.location.hash) {
      window.setTimeout(() => scrollToHash(window.location.hash, false), 0);
    }
  });

  window.addEventListener(
    "resize",
    () => {
      if (window.location.pathname === "/" && window.location.hash) {
        window.setTimeout(() => scrollToHash(window.location.hash, false), 0);
      }
    },
    { passive: true }
  );

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeAllGroups();
      closeMenu();
    }
  });

  document.addEventListener("pointerdown", (event) => {
    if (desktopQuery.matches || !isMenuOpen()) {
      return;
    }

    const target = event.target;
    if (target instanceof Node && !header.contains(target)) {
      closeMenu();
    }
  });

  window.addEventListener(
    "scroll",
    () => {
      if (!desktopQuery.matches && isMenuOpen()) {
        closeMenu();
      }
    },
    { passive: true }
  );

  const syncState = () => setState(false);
  const syncNavState = () => {
    syncState();
    syncGroupsForViewport();
  };
  if (typeof desktopQuery.addEventListener === "function") {
    desktopQuery.addEventListener("change", syncNavState);
  } else if (typeof desktopQuery.addListener === "function") {
    desktopQuery.addListener(syncNavState);
  }

  syncNavState();

  if (window.location.pathname === "/" && window.location.hash) {
    window.setTimeout(() => scrollToHash(window.location.hash, false), 0);
  }
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    try {
      initCedernNav();
    } catch (error) {
      console.error("[CEDE] Falha ao iniciar menu:", error);
    }
  });
} else {
  try {
    initCedernNav();
  } catch (error) {
    console.error("[CEDE] Falha ao iniciar menu:", error);
  }
}
