/* VEGHA & ASSOCIATES site.js
   Vanilla, no dependencies. Everything degrades to working HTML without it. */
(function () {
  "use strict";

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ------------------------------------------------------------ header --- */
  var header = $(".site-header");
  if (header) {
    var onHdr = function () { header.classList.toggle("is-scrolled", window.scrollY > 8); };
    window.addEventListener("scroll", onHdr, { passive: true });
    onHdr();
  }

  /* --------------------------------------------------------- mobile nav -- */
  var toggle = $(".nav-toggle");
  var nav = $("#site-nav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = toggle.getAttribute("aria-expanded") === "true";
      toggle.setAttribute("aria-expanded", String(!open));
      nav.classList.toggle("is-open", !open);
    });
    nav.addEventListener("click", function (e) {
      if (e.target.tagName === "A") {
        toggle.setAttribute("aria-expanded", "false");
        nav.classList.remove("is-open");
      }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && nav.classList.contains("is-open")) {
        toggle.setAttribute("aria-expanded", "false");
        nav.classList.remove("is-open");
        toggle.focus();
      }
    });
  }

  /* ------------------------------------------------------------- reveal -- */
  var revealables = $$(".reveal");
  if (revealables.length) {
    if (reduced || !("IntersectionObserver" in window)) {
      revealables.forEach(function (el) { el.classList.add("is-in"); });
    } else {
      /* stagger siblings that arrive in the same frame */
      var io = new IntersectionObserver(function (entries) {
        var batch = entries.filter(function (en) { return en.isIntersecting; });
        batch.forEach(function (en, i) {
          en.target.style.setProperty("--rd", (i * 90) + "ms");
          en.target.classList.add("is-in");
          io.unobserve(en.target);
        });
      }, { rootMargin: "0px 0px -8% 0px", threshold: 0.08 });
      revealables.forEach(function (el) { io.observe(el); });
    }
  }

  /* ----------------------------------------------------------- counters -- */
  var counters = $$("[data-count]");
  if (counters.length && !reduced && "IntersectionObserver" in window) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        cio.unobserve(en.target);
        var el = en.target, target = parseFloat(el.getAttribute("data-count"));
        var t0 = null, dur = 1600;
        var tick = function (t) {
          if (!t0) t0 = t;
          var p = Math.min((t - t0) / dur, 1);
          p = 1 - Math.pow(1 - p, 3); /* ease-out cubic */
          el.textContent = Math.round(target * p);
          if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
      });
    }, { threshold: 0.6 });
    counters.forEach(function (el) { cio.observe(el); });
  }

  /* ------------------------------------------------------------- to top -- */
  var toTop = $(".to-top");
  if (toTop) {
    var ticking = false;
    var onScroll = function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        toTop.classList.toggle("is-on", window.scrollY > 700);
        ticking = false;
      });
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
    toTop.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: reduced ? "auto" : "smooth" });
    });
  }

  /* ------------------------------------------------------------ sub-nav -- */
  var subnav = $(".subnav");
  if (subnav && "IntersectionObserver" in window) {
    var links = $$(".subnav a");
    var map = {};
    links.forEach(function (a) {
      var id = (a.getAttribute("href") || "").replace("#", "");
      if (id) map[id] = a;
    });
    var setActive = function (id) {
      links.forEach(function (a) { a.classList.remove("is-active"); });
      if (map[id]) {
        map[id].classList.add("is-active");
        if (map[id].scrollIntoView) {
          map[id].scrollIntoView({ block: "nearest", inline: "center", behavior: reduced ? "auto" : "smooth" });
        }
      }
    };
    var sio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) setActive(en.target.id);
      });
    }, { rootMargin: "-30% 0px -60% 0px" });
    Object.keys(map).forEach(function (id) {
      var sec = document.getElementById(id);
      if (sec) sio.observe(sec);
    });
  }

  /* ----------------------------------------------------------- lightbox -- */
  var lightbox = $("#lightbox");
  var lbLast = null;
  if (lightbox) {
    var lbImg = $("img", lightbox);
    var lbClose = $(".lightbox__close", lightbox);
    var openLb = function (srcUrl, alt) {
      lbLast = document.activeElement;
      lbImg.src = srcUrl;
      lbImg.alt = alt || "";
      lightbox.classList.add("is-open");
      lightbox.setAttribute("aria-hidden", "false");
      document.body.style.overflow = "hidden";
      lbClose.focus();
    };
    var closeLb = function () {
      lightbox.classList.remove("is-open");
      lightbox.setAttribute("aria-hidden", "true");
      lbImg.src = "";
      document.body.style.overflow = "";
      if (lbLast) lbLast.focus();
    };
    $$("[data-sheet]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        openLb(btn.getAttribute("data-sheet"), btn.getAttribute("data-sheet-alt"));
      });
    });
    lbClose.addEventListener("click", closeLb);
    lightbox.addEventListener("click", function (e) { if (e.target === lightbox) closeLb(); });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && lightbox.classList.contains("is-open")) closeLb();
    });
  }

  /* -------------------------------------------------------------- forms -- */
  /* Set ACCESS_KEY to a Web3Forms access key (free, from web3forms.com).
     Until then, forms validate fully and show a demo-mode message. */
  var ACCESS_KEY = "";
  var MAX_FILE = 1024 * 1024; /* 1 MB resume cap */

  $$("form[data-mail]").forEach(function (form) {
    var status = $(".form-status", form);
    var say = function (msg, ok) {
      if (!status) return;
      status.textContent = msg;
      status.classList.toggle("is-ok", !!ok);
      status.classList.toggle("is-bad", !ok);
    };

    var validateField = function (el) {
      var err = document.getElementById(el.id + "-err");
      var msg = "";
      if (el.required && (el.type === "radio"
            ? !form.querySelector('[name="' + el.name + '"]:checked')
            : !el.value.trim())) {
        msg = "This field is required.";
      } else if (el.type === "email" && el.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value)) {
        msg = "Enter a valid email address.";
      } else if (el.type === "tel" && el.value && !/^[+\d][\d\s\-()]{6,}$/.test(el.value)) {
        msg = "Enter a valid phone number.";
      } else if (el.type === "url" && el.value && !/^https?:\/\//.test(el.value)) {
        msg = "Include https:// at the start.";
      } else if (el.type === "file" && el.files && el.files[0] && el.files[0].size > MAX_FILE) {
        msg = "File is larger than 1 MB.";
      }
      el.setAttribute("aria-invalid", msg ? "true" : "false");
      if (err) err.textContent = msg;
      return !msg;
    };

    $$("input, select, textarea", form).forEach(function (el) {
      if (el.classList.contains("hp-field")) return;
      el.addEventListener("blur", function () { validateField(el); });
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = true, firstBad = null;
      $$("input, select, textarea", form).forEach(function (el) {
        if (el.classList.contains("hp-field")) return;
        if (!validateField(el)) { ok = false; firstBad = firstBad || el; }
      });
      if (!ok) {
        say("Please correct the highlighted fields.", false);
        if (firstBad) firstBad.focus();
        return;
      }
      if (form.querySelector(".hp-field") && form.querySelector(".hp-field").value) return; /* bot */

      if (!ACCESS_KEY) {
        say("Demo mode: the form is fully working but not yet connected to a mailbox. Set the Web3Forms ACCESS_KEY in assets/js/site.js to activate delivery to admin@veghaandassociates.com.", true);
        return;
      }

      var btn = form.querySelector('[type="submit"]');
      var label = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Sending…";

      var fd = new FormData(form);
      fd.append("access_key", ACCESS_KEY);
      fd.append("subject", (form.getAttribute("data-mail") || "Website message") + " | veghaandassociates.com");
      fd.append("from_name", "VEGHA & ASSOCIATES website");

      fetch("https://api.web3forms.com/submit", { method: "POST", body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.success) {
            say("Thank you. Your message has been sent. A partner will respond shortly.", true);
            form.reset();
          } else {
            say("Something went wrong sending the form. Please email admin@veghaandassociates.com instead.", false);
          }
        })
        .catch(function () {
          say("Network error. Please try again, or email admin@veghaandassociates.com.", false);
        })
        .finally(function () {
          btn.disabled = false;
          btn.textContent = label;
        });
    });
  });


  /* ------------------------------------------------------- blog filtering - */
  var blogList = $("#blog-list");
  if (blogList) {
    var cards = $$(".post", blogList);
    var empty = $("#blog-empty");
    var activeCat = "", q = "";
    var apply = function () {
      var shown = 0;
      cards.forEach(function (c) {
        var okCat = !activeCat || c.getAttribute("data-cat") === activeCat;
        var okQ = !q || (c.getAttribute("data-search") || "").indexOf(q) !== -1;
        var show = okCat && okQ;
        c.classList.toggle("post--hidden", !show);
        if (show) shown++;
      });
      if (empty) empty.hidden = shown > 0;
    };
    $$("#blog-cats button").forEach(function (b) {
      b.addEventListener("click", function () {
        $$("#blog-cats button").forEach(function (x) { x.classList.remove("is-active"); });
        b.classList.add("is-active");
        activeCat = b.getAttribute("data-cat") || "";
        apply();
      });
    });
    var search = $("#blog-search");
    if (search) search.addEventListener("input", function () { q = search.value.trim().toLowerCase(); apply(); });
  }

  /* --------------------------------------------- slider fill + quick rates - */
  $$("input[type=range]").forEach(function (r) {
    var paint = function () {
      var mn = parseFloat(r.min) || 0, mx = parseFloat(r.max) || 100;
      r.style.setProperty("--fill", ((parseFloat(r.value) - mn) / (mx - mn) * 100) + "%");
    };
    r.addEventListener("input", paint);
    paint();
  });
  $$("[data-set-rate]").forEach(function (b) {
    b.addEventListener("click", function () {
      var v = b.getAttribute("data-set-rate");
      var inp = document.getElementById("rate");
      var rng = document.getElementById("rate-range");
      if (inp) { inp.value = v; inp.dispatchEvent(new Event("input", { bubbles: true })); }
      if (rng) { rng.value = v; rng.dispatchEvent(new Event("input", { bubbles: true })); }
    });
  });

  /* ---------------------------------------------------------- year stamp - */
  $$("[data-year]").forEach(function (el) { el.textContent = String(new Date().getFullYear()); });
})();
