(() => {
  const forms = Array.from(
    document.querySelectorAll("form[data-recaptcha-action][data-recaptcha-site-key]")
  );

  const enableMobileBadgeToggle = () => {
    if (!window.matchMedia("(max-width: 768px)").matches) {
      return;
    }

    const attach = (badge) => {
      if (badge.dataset.ncBadgeToggleReady === "true") {
        return;
      }

      badge.dataset.ncBadgeToggleReady = "true";
      badge.classList.remove("is-expanded");

      const setExpanded = (isExpanded) => {
        badge.classList.toggle("is-expanded", Boolean(isExpanded));
      };

      const isInsideBadge = (event) =>
        event.target instanceof Node && badge.contains(event.target);

      const onGlobalPress = (event) => {
        setExpanded(isInsideBadge(event));
      };

      if (window.PointerEvent) {
        document.addEventListener("pointerdown", onGlobalPress, true);
      } else {
        document.addEventListener("touchstart", onGlobalPress, {
          passive: true,
          capture: true,
        });
        document.addEventListener("mousedown", onGlobalPress, true);
      }

      badge.addEventListener("focusin", () => setExpanded(true));
      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          setExpanded(false);
        }
      });
    };

    const existingBadge = document.querySelector(".grecaptcha-badge");
    if (existingBadge) {
      attach(existingBadge);
      return;
    }

    if (!document.body) {
      return;
    }

    const observer = new MutationObserver(() => {
      const badge = document.querySelector(".grecaptcha-badge");
      if (!badge) {
        return;
      }

      observer.disconnect();
      attach(badge);
    });

    observer.observe(document.body, { childList: true, subtree: true });
  };

  enableMobileBadgeToggle();

  if (!forms.length) {
    return;
  }

  const recaptchaErrorMessage =
    "Nao foi possivel validar a verificacao anti-spam agora. Atualize a pagina e tente novamente.";

  const setFeedback = (form, message) => {
    const feedback = form.querySelector("[data-recaptcha-feedback]");
    if (!feedback) {
      return;
    }

    feedback.textContent = message;
    feedback.hidden = message === "";
  };

  const setSubmittingState = (form, isSubmitting) => {
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
      button.disabled = isSubmitting;
      button.setAttribute("aria-disabled", isSubmitting ? "true" : "false");
      button.classList.toggle("is-loading", isSubmitting);
    });
  };

  const resolveRecaptchaClient = () =>
    new Promise((resolve, reject) => {
      let attempts = 0;
      const maxAttempts = 40;

      const tick = () => {
        const recaptcha = window.grecaptcha;

        if (
          recaptcha &&
          typeof recaptcha.ready === "function" &&
          typeof recaptcha.execute === "function"
        ) {
          resolve(recaptcha);
          return;
        }

        attempts += 1;
        if (attempts >= maxAttempts) {
          reject(new Error("reCAPTCHA indisponivel"));
          return;
        }

        window.setTimeout(tick, 250);
      };

      tick();
    });

  forms.forEach((form) => {
    const tokenInput = form.querySelector('input[name="recaptcha_token"]');
    const action = form.getAttribute("data-recaptcha-action") || "";
    const siteKey = form.getAttribute("data-recaptcha-site-key") || "";

    if (!tokenInput || !action || !siteKey) {
      return;
    }

    form.addEventListener("submit", (event) => {
      if (form.dataset.recaptchaVerified === "true") {
        form.dataset.recaptchaVerified = "false";
        return;
      }

      event.preventDefault();
      setFeedback(form, "");
      setSubmittingState(form, true);

      resolveRecaptchaClient()
        .then(
          (recaptcha) =>
            new Promise((resolve, reject) => {
              recaptcha.ready(() => {
                recaptcha
                  .execute(siteKey, { action })
                  .then(resolve)
                  .catch(reject);
              });
            })
        )
        .then((token) => {
          if (typeof token !== "string" || token.trim() === "") {
            throw new Error("Token vazio");
          }

          tokenInput.value = token;
          form.dataset.recaptchaVerified = "true";

          if (typeof form.requestSubmit === "function") {
            form.requestSubmit(event.submitter || undefined);
            return;
          }

          form.submit();
        })
        .catch(() => {
          tokenInput.value = "";
          setSubmittingState(form, false);
          setFeedback(form, recaptchaErrorMessage);
        });
    });
  });
})();
