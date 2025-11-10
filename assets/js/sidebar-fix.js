/**
 * Sidebar Collapsed Hover Fix
 * Force disable all transforms on collapsed sidebar hover
 */

(function () {
  "use strict";

  // Function to remove transform from collapsed sidebar items
  function forceNoTransform() {
    // Check if sidebar is collapsed
    if (document.body.classList.contains("sidebar-collapse")) {
      // CRITICAL: Force main-sidebar itself to stay in place
      const mainSidebar = document.querySelector(".main-sidebar");
      if (mainSidebar) {
        mainSidebar.style.position = "fixed";
        mainSidebar.style.left = "0";
        mainSidebar.style.transform = "none";
        mainSidebar.style.webkitTransform = "none";
        mainSidebar.style.translate = "none";
        mainSidebar.style.marginLeft = "0";
      }

      const navLinks = document.querySelectorAll(
        ".sidebar-mini.sidebar-collapse .nav-sidebar .nav-link"
      );

      navLinks.forEach((link) => {
        // Force inline styles to override everything
        link.style.transform = "none";
        link.style.webkitTransform = "none";
        link.style.position = "relative";
        link.style.left = "0";
        link.style.right = "0";

        // Also for icon
        const icon = link.querySelector(".nav-icon, i");
        if (icon) {
          icon.style.transform = "none";
          icon.style.webkitTransform = "none";
        }

        // Also for text
        const text = link.querySelector("p");
        if (text) {
          text.style.transform = "none";
          text.style.webkitTransform = "none";
        }
      });
    }
  }

  // Run on page load
  document.addEventListener("DOMContentLoaded", forceNoTransform);

  // Run on sidebar toggle
  const pushMenuBtn = document.querySelector('[data-widget="pushmenu"]');
  if (pushMenuBtn) {
    pushMenuBtn.addEventListener("click", function () {
      // Wait for AdminLTE animation to complete
      setTimeout(forceNoTransform, 350);
    });
  }

  // Also monitor for class changes on body
  const observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (mutation.attributeName === "class") {
        setTimeout(forceNoTransform, 100);
      }
    });
  });

  observer.observe(document.body, {
    attributes: true,
    attributeFilter: ["class"],
  });

  // Force on every hover (aggressive approach)
  document.addEventListener(
    "mouseover",
    function (e) {
      if (document.body.classList.contains("sidebar-collapse")) {
        // Force main-sidebar position
        const mainSidebar = document.querySelector(".main-sidebar");
        if (mainSidebar) {
          mainSidebar.style.transform = "none";
          mainSidebar.style.webkitTransform = "none";
          mainSidebar.style.left = "0";
          mainSidebar.style.position = "fixed";
        }

        // Force nav-link
        const navLink = e.target.closest(".nav-sidebar .nav-link");
        if (navLink) {
          navLink.style.transform = "none";
          navLink.style.webkitTransform = "none";
        }
      }
    },
    true
  );

  console.log("✅ Sidebar collapse fix loaded - main-sidebar locked");
})();
