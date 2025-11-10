/**
 * Sidebar State Manager
 *
 * Features:
 * - Prevents hover transform issues on collapsed sidebar
 * - Persists sidebar state across navigation using localStorage
 * - Auto-restore state on page load
 *
 * @version 2.0.0
 * @author Smart Home System
 */

const SidebarManager = (function () {
  "use strict";

  // ==================== Constants ====================
  const CONFIG = {
    STORAGE_KEY: "sidebar_collapsed_state",
    ANIMATION_DELAY: 350,
    RESTORE_DELAY: 100,
    SIDEBAR_CLASS: "sidebar-collapse",
    SIDEBAR_OPEN_CLASS: "sidebar-open",
    MOBILE_BREAKPOINT: 992, // px
    SELECTORS: {
      BODY: "body",
      MAIN_SIDEBAR: ".main-sidebar",
      PUSH_MENU_BTN: '[data-widget="pushmenu"]',
      NAV_LINKS: ".sidebar-mini.sidebar-collapse .nav-sidebar .nav-link",
      NAV_ICON: ".nav-icon, i",
      NAV_TEXT: "p",
      WRAPPER: ".wrapper",
    },
    STATES: {
      COLLAPSED: "collapsed",
      EXPANDED: "expanded",
    },
  };

  // ==================== Device Detection ====================
  const DeviceDetector = {
    /**
     * Check if current device is mobile (< 992px)
     */
    isMobile() {
      return window.innerWidth < CONFIG.MOBILE_BREAKPOINT;
    },

    /**
     * Check if device has touch capability
     */
    isTouch() {
      return (
        "ontouchstart" in window ||
        navigator.maxTouchPoints > 0 ||
        navigator.msMaxTouchPoints > 0
      );
    },

    /**
     * Get current orientation
     */
    getOrientation() {
      return window.innerWidth > window.innerHeight ? "landscape" : "portrait";
    },

    /**
     * Check if iOS device
     */
    isIOS() {
      return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    },
  };

  // ==================== State Management ====================
  const StateManager = {
    /**
     * Check if sidebar is currently collapsed
     */
    isCollapsed() {
      return document.body.classList.contains(CONFIG.SIDEBAR_CLASS);
    },

    /**
     * Check if sidebar is currently open (mobile)
     */
    isOpen() {
      return document.body.classList.contains(CONFIG.SIDEBAR_OPEN_CLASS);
    },

    /**
     * Get current sidebar state
     */
    getCurrentState() {
      return this.isCollapsed()
        ? CONFIG.STATES.COLLAPSED
        : CONFIG.STATES.EXPANDED;
    },

    /**
     * Save sidebar state to localStorage (desktop only)
     */
    save() {
      // Don't save state on mobile devices
      if (DeviceDetector.isMobile()) {
        console.log("📱 Mobile device - state not saved");
        return;
      }

      const state = this.getCurrentState();
      try {
        localStorage.setItem(CONFIG.STORAGE_KEY, state);
        console.log(`💾 Sidebar state saved: ${state}`);
      } catch (error) {
        console.error("Failed to save sidebar state:", error);
      }
    },

    /**
     * Load sidebar state from localStorage
     */
    load() {
      try {
        return localStorage.getItem(CONFIG.STORAGE_KEY);
      } catch (error) {
        console.error("Failed to load sidebar state:", error);
        return null;
      }
    },

    /**
     * Apply saved state to sidebar (desktop only)
     */
    restore() {
      // Don't restore state on mobile devices
      if (DeviceDetector.isMobile()) {
        console.log("📱 Mobile device - using default sidebar state");
        return;
      }

      const savedState = this.load();

      if (!savedState) return;

      if (savedState === CONFIG.STATES.COLLAPSED && !this.isCollapsed()) {
        document.body.classList.add(CONFIG.SIDEBAR_CLASS);
        console.log("🔄 Sidebar restored: collapsed");
      } else if (savedState === CONFIG.STATES.EXPANDED && this.isCollapsed()) {
        document.body.classList.remove(CONFIG.SIDEBAR_CLASS);
        console.log("🔄 Sidebar restored: expanded");
      }
    },
  };

  // ==================== Transform Fix ====================
  const TransformFix = {
    /**
     * Apply inline styles to element
     */
    applyStyles(element, styles) {
      if (!element) return;
      Object.assign(element.style, styles);
    },

    /**
     * Fix main sidebar transform issues (desktop only)
     */
    fixMainSidebar() {
      // Don't apply fix on mobile
      if (DeviceDetector.isMobile()) return;

      const mainSidebar = document.querySelector(CONFIG.SELECTORS.MAIN_SIDEBAR);
      this.applyStyles(mainSidebar, {
        position: "fixed",
        left: "0",
        transform: "none",
        webkitTransform: "none",
        translate: "none",
        marginLeft: "0",
      });
    },

    /**
     * Fix navigation link transforms (desktop only)
     */
    fixNavLinks() {
      // Don't apply fix on mobile
      if (DeviceDetector.isMobile()) return;

      const navLinks = document.querySelectorAll(CONFIG.SELECTORS.NAV_LINKS);

      navLinks.forEach((link) => {
        // Fix link itself
        this.applyStyles(link, {
          transform: "none",
          webkitTransform: "none",
          position: "relative",
          left: "0",
          right: "0",
        });

        // Fix icon
        const icon = link.querySelector(CONFIG.SELECTORS.NAV_ICON);
        this.applyStyles(icon, {
          transform: "none",
          webkitTransform: "none",
        });

        // Fix text
        const text = link.querySelector(CONFIG.SELECTORS.NAV_TEXT);
        this.applyStyles(text, {
          transform: "none",
          webkitTransform: "none",
        });
      });
    },

    /**
     * Apply all transform fixes if sidebar is collapsed
     */
    apply() {
      if (!StateManager.isCollapsed()) return;
      if (DeviceDetector.isMobile()) return;

      this.fixMainSidebar();
      this.fixNavLinks();
    },
  };

  // ==================== Event Handlers ====================
  const EventHandlers = {
    /**
     * Handle sidebar toggle button click
     */
    onToggleClick() {
      setTimeout(() => {
        StateManager.save();
        TransformFix.apply();
      }, CONFIG.ANIMATION_DELAY);
    },

    /**
     * Handle body class mutations
     */
    onBodyClassChange(mutations) {
      mutations.forEach((mutation) => {
        if (mutation.attributeName === "class") {
          setTimeout(() => {
            StateManager.save();
            TransformFix.apply();
          }, CONFIG.RESTORE_DELAY);
        }
      });
    },

    /**
     * Handle mouseover on sidebar elements (desktop only)
     */
    onMouseOver(event) {
      // Disable on mobile/touch devices
      if (DeviceDetector.isMobile() || DeviceDetector.isTouch()) return;
      if (!StateManager.isCollapsed()) return;

      // Fix main sidebar
      const mainSidebar = document.querySelector(CONFIG.SELECTORS.MAIN_SIDEBAR);
      TransformFix.applyStyles(mainSidebar, {
        transform: "none",
        webkitTransform: "none",
        left: "0",
        position: "fixed",
      });

      // Fix nav link if hovering over one
      const navLink = event.target.closest(".nav-sidebar .nav-link");
      TransformFix.applyStyles(navLink, {
        transform: "none",
        webkitTransform: "none",
      });
    },

    /**
     * Handle window resize
     */
    onResize() {
      // Clear transform fixes when switching between mobile/desktop
      const isMobile = DeviceDetector.isMobile();

      if (isMobile) {
        // Remove desktop-specific fixes on mobile
        const mainSidebar = document.querySelector(
          CONFIG.SELECTORS.MAIN_SIDEBAR
        );
        if (mainSidebar) {
          mainSidebar.style.cssText = "";
        }

        // Remove sidebar-collapse class on mobile
        if (StateManager.isCollapsed()) {
          document.body.classList.remove(CONFIG.SIDEBAR_CLASS);
        }
      } else {
        // Restore saved state when switching back to desktop
        StateManager.restore();
        setTimeout(() => {
          TransformFix.apply();
        }, CONFIG.RESTORE_DELAY);
      }

      console.log(
        `📏 Window resized - Mode: ${isMobile ? "Mobile" : "Desktop"}`
      );
    },

    /**
     * Handle orientation change (mobile)
     */
    onOrientationChange() {
      const orientation = DeviceDetector.getOrientation();
      console.log(`🔄 Orientation changed: ${orientation}`);

      // Close sidebar on orientation change (mobile)
      if (DeviceDetector.isMobile() && StateManager.isOpen()) {
        document.body.classList.remove(CONFIG.SIDEBAR_OPEN_CLASS);
      }
    },

    /**
     * Handle page load
     */
    onPageLoad() {
      StateManager.restore();
      setTimeout(() => {
        TransformFix.apply();
      }, CONFIG.RESTORE_DELAY);

      // Log device info
      console.log(
        `📱 Device: ${DeviceDetector.isMobile() ? "Mobile" : "Desktop"}`
      );
      console.log(`👆 Touch: ${DeviceDetector.isTouch() ? "Yes" : "No"}`);
      if (DeviceDetector.isMobile()) {
        console.log(`📐 Orientation: ${DeviceDetector.getOrientation()}`);
      }
    },
  };

  // ==================== Initialization ====================
  const init = () => {
    // Restore state immediately (before DOMContentLoaded)
    StateManager.restore();

    // Setup event listeners when DOM is ready
    document.addEventListener("DOMContentLoaded", () => {
      EventHandlers.onPageLoad();

      // Toggle button listener
      const pushMenuBtn = document.querySelector(
        CONFIG.SELECTORS.PUSH_MENU_BTN
      );
      if (pushMenuBtn) {
        pushMenuBtn.addEventListener("click", EventHandlers.onToggleClick);
      }

      // Body class mutation observer
      const observer = new MutationObserver(EventHandlers.onBodyClassChange);
      observer.observe(document.body, {
        attributes: true,
        attributeFilter: ["class"],
      });

      // Mouseover listener for aggressive fix (desktop only)
      if (!DeviceDetector.isTouch()) {
        document.addEventListener("mouseover", EventHandlers.onMouseOver, true);
      }

      // Window resize listener with debounce
      let resizeTimer;
      window.addEventListener("resize", () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
          EventHandlers.onResize();
        }, 250);
      });

      // Orientation change listener (mobile)
      window.addEventListener("orientationchange", () => {
        setTimeout(() => {
          EventHandlers.onOrientationChange();
        }, 300);
      });

      // Close sidebar when clicking outside (mobile)
      if (DeviceDetector.isMobile()) {
        document.addEventListener("click", (event) => {
          const sidebar = document.querySelector(CONFIG.SELECTORS.MAIN_SIDEBAR);
          const toggleBtn = document.querySelector(
            CONFIG.SELECTORS.PUSH_MENU_BTN
          );

          // Check if sidebar is open and click is outside
          if (
            StateManager.isOpen() &&
            sidebar &&
            !sidebar.contains(event.target) &&
            !toggleBtn.contains(event.target)
          ) {
            document.body.classList.remove(CONFIG.SIDEBAR_OPEN_CLASS);
            console.log("📱 Sidebar closed - clicked outside");
          }
        });
      }

      console.log(
        `✅ Sidebar Manager initialized - ${
          DeviceDetector.isMobile() ? "Mobile" : "Desktop"
        } mode`
      );
    });
  };

  // Auto-initialize
  init();

  // Public API (for debugging/testing)
  return {
    getState: () => StateManager.getCurrentState(),
    isCollapsed: () => StateManager.isCollapsed(),
    isMobile: () => DeviceDetector.isMobile(),
    isTouch: () => DeviceDetector.isTouch(),
    applyFix: () => TransformFix.apply(),
  };
})();
