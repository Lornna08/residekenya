/* Reside Ke — frontend behaviour. Vanilla, no dependencies. */
(function () {
  "use strict";

  /* Theme switcher */
  var THEMES = ["light", "dark", "blue", "earth"];
  var root = document.documentElement;
  function currentTheme() {
    return root.getAttribute("data-theme") || "light";
  }
  function applyTheme(name) {
    if (THEMES.indexOf(name) === -1) name = "light";
    if (name === "light") { root.removeAttribute("data-theme"); }
    else { root.setAttribute("data-theme", name); }
    try { localStorage.setItem("reside_theme", name); } catch (e) {}
    document.querySelectorAll("[data-theme-choice]").forEach(function (b) {
      b.setAttribute("aria-current", String(b.getAttribute("data-theme-choice") === name));
    });
  }
  var themeBtn = document.getElementById("themeBtn");
  var themeMenu = document.getElementById("themeMenu");
  if (themeBtn && themeMenu) {
    themeBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      var open = themeMenu.classList.toggle("open");
      themeBtn.setAttribute("aria-expanded", String(open));
    });
    themeMenu.querySelectorAll("[data-theme-choice]").forEach(function (b) {
      b.addEventListener("click", function () {
        applyTheme(b.getAttribute("data-theme-choice"));
        themeMenu.classList.remove("open");
        themeBtn.setAttribute("aria-expanded", "false");
      });
    });
    document.addEventListener("click", function (e) {
      if (!themeMenu.contains(e.target) && e.target !== themeBtn) themeMenu.classList.remove("open");
    });
    applyTheme(currentTheme());
  }

  /* Mobile nav */
  var toggle = document.getElementById("navToggle");
  var menu = document.getElementById("mobileMenu");
  if (toggle && menu) {
    toggle.addEventListener("click", function () {
      var open = menu.classList.toggle("open");
      toggle.setAttribute("aria-expanded", String(open));
    });
  }

  /* Sticky header shadow on scroll */
  var header = document.getElementById("siteHeader");
  if (header) {
    var onScroll = function () {
      header.classList.toggle("scrolled", window.scrollY > 12);
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  /* Scroll reveal */
  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && reveals.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add("in");
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
    reveals.forEach(function (el) { io.observe(el); });
    /* Failsafe: if any reveal hasn't shown within 1.2s, reveal it anyway */
    setTimeout(function () {
      reveals.forEach(function (el) { el.classList.add("in"); });
    }, 1200);
  } else {
    reveals.forEach(function (el) { el.classList.add("in"); });
  }

  /* Hero search tabs -> set hidden purpose field */
  var tabWrap = document.querySelector(".search-tabs");
  if (tabWrap) {
    var purposeInput = document.getElementById("searchPurpose");
    tabWrap.addEventListener("click", function (e) {
      var btn = e.target.closest("button");
      if (!btn) return;
      tabWrap.querySelectorAll("button").forEach(function (b) { b.classList.remove("active"); });
      btn.classList.add("active");
      if (purposeInput) purposeInput.value = btn.dataset.purpose || "";
    });
  }

  /* Advanced search toggle */
  var advBtn = document.querySelector(".search-toggle-adv");
  var adv = document.querySelector(".search-advanced");
  if (advBtn && adv) {
    advBtn.addEventListener("click", function () {
      var open = adv.classList.toggle("open");
      advBtn.textContent = open ? "Fewer filters" : "More filters";
    });
  }

  /* County/town dependent dropdown (listings + search) */
  document.querySelectorAll("[data-county-select]").forEach(function (sel) {
    sel.addEventListener("change", function () {
      var townSel = document.querySelector("[data-town-select]");
      if (!townSel) return;
      var cid = sel.value;
      townSel.innerHTML = '<option value="">Any town</option>';
      if (!cid) return;
      fetch((window.RESIDE_BASE || "") + "/api/towns.php?county_id=" + encodeURIComponent(cid))
        .then(function (r) { return r.json(); })
        .then(function (towns) {
          towns.forEach(function (t) {
            var o = document.createElement("option");
            o.value = t.id; o.textContent = t.name;
            townSel.appendChild(o);
          });
        }).catch(function () {});
    });
  });

  /* Favorites (localStorage-free: uses in-memory + cookie fallback avoided per env; use sessionless toggle) */
  var favs = {};
  document.addEventListener("click", function (e) {
    var b = e.target.closest("[data-fav]");
    if (!b) return;
    var id = b.getAttribute("data-fav");
    favs[id] = !favs[id];
    b.style.color = favs[id] ? "#c8a75d" : "";
    b.querySelector("svg").setAttribute("fill", favs[id] ? "#c8a75d" : "none");
  });

  /* Newsletter */
  var nf = document.getElementById("newsletterForm");
  if (nf) {
    nf.addEventListener("submit", function (e) {
      e.preventDefault();
      var fd = new FormData(nf);
      fetch((window.RESIDE_BASE || "") + "/api/subscribe.php", { method: "POST", body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          nf.innerHTML = '<p style="color:#c8a75d;font-size:.9rem">' + (res.message || "Thank you for subscribing.") + "</p>";
        })
        .catch(function () {
          nf.innerHTML = '<p style="font-size:.9rem">Something went wrong. Please try again.</p>';
        });
    });
  }
})();
