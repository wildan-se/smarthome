/**
 * Sidebar State Manager - Clean & Responsive
 *
 * Features:
 * - Desktop: Collapse/expand with localStorage persistence
 * - Mobile: Slide-in drawer with backdrop overlay
 * - Smooth transitions and proper event handling
 *
 * @version 3.0.0
 * @author Smart Home System
 */

const SidebarManager = (function () {
  "use strict";

  // Configuration
  const CONFIG = {
    STORAGE_KEY: "sidebar_state",
    MOBILE_BREAKPOINT: 992,
    ANIMATION_DELAY: 300,
    CLASSES: {
      SIDEBAR_COLLAPSE: "sidebar-collapse",
      SIDEBAR_OPEN: "sidebar-open",
      SIDEBAR_NO_EXPAND: "sidebar-no-expand",
    },
    SELECTORS: {
      BODY: "body",
      MAIN_SIDEBAR: ".main-sidebar",
      SIDEBAR_OVERLAY: ".sidebar-overlay",
      PUSH_MENU_BTN: '[data-widget="pushmenu"]',
    },
  };

  // Utility Functions
  const Utils = {
    isMobile() {
      return window.innerWidth < CONFIG.MOBILE_BREAKPOINT;
    },

    isTouch() {
      return (
        "ontouchstart" in window ||
        navigator.maxTouchPoints > 0 ||
        navigator.msMaxTouchPoints > 0
      );
    },

    log(message, type) {
      type = type || "info";
      const prefix = {
        info: "[INFO]",
        success: "[SUCCESS]",
        warning: "[WARNING]",
        mobile: "[MOBILE]",
        desktop: "[DESKTOP]",
      };
      console.log((prefix[type] || "[LOG]") + " " + message);
    },
  };

  // State Management
  const State = {
    save(collapsed) {
      if (Utils.isMobile()) return;
      try {
        localStorage.setItem(CONFIG.STORAGE_KEY, collapsed ? "1" : "0");
        Utils.log("State saved: " + (collapsed ? "collapsed" : "expanded"), "success");
      } catch (e) {
        Utils.log("Failed to save state: " + e.message, "warning");
      }
    },

    load() {
      if (Utils.isMobile()) return null;
      try {
        return localStorage.getItem(CONFIG.STORAGE_KEY) === "1";
      } catch (e) {
        return null;
      }
    },

    restore() {
      if (Utils.isMobile()) {
        Utils.log("Mobile mode - no state restoration", "mobile");
        return;
      }

      const collapsed = this.load();
      if (collapsed === null) return;

      if (collapsed) {
        document.body.classList.add(CONFIG.CLASSES.SIDEBAR_COLLAPSE);
        Utils.log("State restored: collapsed", "desktop");
      } else {
        document.body.classList.remove(CONFIG.CLASSES.SIDEBAR_COLLAPSE);
        Utils.log("State restored: expanded", "desktop");
      }
    },
  };

  // Event Handlers
  const Events = {
    handleToggle() {
      setTimeout(function() {
        if (Utils.isMobile()) {
          document.body.classList.toggle(CONFIG.CLASSES.SIDEBAR_OPEN);
          var status = document.body.classList.contains(CONFIG.CLASSES.SIDEBAR_OPEN) ? "opened" : "closed";
          Utils.log("Mobile sidebar " + status, "mobile");
        } else {
          var isCollapsed = document.body.classList.contains(CONFIG.CLASSES.SIDEBAR_COLLAPSE);
          State.save(isCollapsed);
        }
      }, 50);
    },

    handleOverlayClick() {
      if (document.body.classList.contains(CONFIG.CLASSES.SIDEBAR_OPEN)) {
        document.body.classList.remove(CONFIG.CLASSES.SIDEBAR_OPEN);
        Utils.log("Sidebar closed via overlay click", "mobile");
      }
    },

    handleOutsideClick(event) {
      if (!Utils.isMobile()) return;
      if (!document.body.classList.contains(CONFIG.CLASSES.SIDEBAR_OPEN)) return;

      var sidebar = document.querySelector(CONFIG.SELECTORS.MAIN_SIDEBAR);
      var toggle = document.querySelector(CONFIG.SELECTORS.PUSH_MENU_BTN);

      if (
        sidebar &&
        !sidebar.contains(event.target) &&
        toggle &&
        !toggle.contains(event.target)
      ) {
        document.body.classList.remove(CONFIG.CLASSES.SIDEBAR_OPEN);
        Utils.log("Sidebar closed via outside click", "mobile");
      }
    },

    handleResize: (function () {
      var timer;
      return function () {
        clearTimeout(timer);
        timer = setTimeout(function() {
          var wasMobile = document.body.hasAttribute("data-mobile-mode");
          var isMobile = Utils.isMobile();

          if (wasMobile !== isMobile) {
            if (isMobile) {
              document.body.setAttribute("data-mobile-mode", "true");
              document.body.classList.remove(CONFIG.CLASSES.SIDEBAR_COLLAPSE);
              document.body.classList.remove(CONFIG.CLASSES.SIDEBAR_OPEN);
              Utils.log("Switched to mobile mode", "mobile");
            } else {
              document.body.removeAttribute("data-mobile-mode");
              document.body.classList.remove(CONFIG.CLASSES.SIDEBAR_OPEN);
              State.restore();
              Utils.log("Switched to desktop mode", "desktop");
            }
          }
        }, 200);
      };
    })(),

    handleOrientationChange() {
      if (Utils.isMobile()) {
        setTimeout(function() {
          if (document.body.classList.contains(CONFIG.CLASSES.SIDEBAR_OPEN)) {
            document.body.classList.remove(CONFIG.CLASSES.SIDEBAR_OPEN);
            Utils.log("Sidebar closed due to orientation change", "mobile");
          }
        }, CONFIG.ANIMATION_DELAY);
      }
    },

    handleEscape(event) {
      if (
        event.key === "Escape" &&
        document.body.classList.contains(CONFIG.CLASSES.SIDEBAR_OPEN)
      ) {
        document.body.classList.remove(CONFIG.CLASSES.SIDEBAR_OPEN);
        Utils.log("Sidebar closed via ESC key", "mobile");
      }
    },
  };

  // Initialization
  var init = function() {
    if (Utils.isMobile()) {
      document.body.setAttribute("data-mobile-mode", "true");
    }

    State.restore();

    document.addEventListener("DOMContentLoaded", function() {
      var toggleBtn = document.querySelector(CONFIG.SELECTORS.PUSH_MENU_BTN);
      if (toggleBtn) {
        toggleBtn.addEventListener("click", Events.handleToggle);
      }

      var overlay = document.querySelector(CONFIG.SELECTORS.SIDEBAR_OVERLAY);
      if (overlay) {
        overlay.addEventListener("click", Events.handleOverlayClick);
      }

      document.addEventListener("click", Events.handleOutsideClick);
      window.addEventListener("resize", Events.handleResize);
      window.addEventListener("orientationchange", Events.handleOrientationChange);
      document.addEventListener("keydown", Events.handleEscape);

      var mode = Utils.isMobile() ? "Mobile" : "Desktop";
      Utils.log("Sidebar Manager initialized - " + mode + " mode", "success");
    });
  };

  init();

  // Public API
  return {
    isMobile: function() { return Utils.isMobile(); },
    isOpen: function() { return document.body.classList.contains(CONFIG.CLASSES.SIDEBAR_OPEN); },
    isCollapsed: function() { return document.body.classList.contains(CONFIG.CLASSES.SIDEBAR_COLLAPSE); },
    close: function() {
      if (Utils.isMobile()) {
        document.body.classList.remove(CONFIG.CLASSES.SIDEBAR_OPEN);
      }
    },
  };
})();
