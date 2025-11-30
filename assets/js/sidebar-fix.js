/**
 * Sidebar Logic Enhancer
 * Fokus: Menyempurnakan UX AdminLTE, bukan menggantinya.
 */

$(document).ready(function () {
  "use strict";

  const CONFIG = {
    KEY: "smarthome_sidebar_state",
    MOBILE_WIDTH: 992,
  };

  // 1. Logic Persistensi State (Desktop Only)
  // Menyimpan status sidebar (collapse/expand) agar tidak reset saat refresh halaman
  function handleSidebarState() {
    if (window.innerWidth >= CONFIG.MOBILE_WIDTH) {
      const isCollapsed = $("body").hasClass("sidebar-collapse");
      localStorage.setItem(CONFIG.KEY, isCollapsed ? "collapsed" : "expanded");
    }
  }

  // Restore state saat halaman dimuat
  if (window.innerWidth >= CONFIG.MOBILE_WIDTH) {
    const savedState = localStorage.getItem(CONFIG.KEY);
    if (savedState === "collapsed") {
      $("body").addClass("sidebar-collapse");
    } else {
      $("body").removeClass("sidebar-collapse");
    }
  }

  // Simpan state setiap kali tombol toggle diklik
  $('[data-widget="pushmenu"]').on("click", function () {
    // Beri sedikit delay agar class body berubah dulu
    setTimeout(handleSidebarState, 100);
  });

  // 2. Logic Mobile Experience (Overlay Click)
  // Menutup sidebar jika user klik di area gelap (overlay)
  $(document).on("click", ".sidebar-overlay", function (e) {
    e.preventDefault();
    // Gunakan fungsi native AdminLTE untuk menutup sidebar
    $('[data-widget="pushmenu"]').trigger("click");
  });

  // 3. UX Fix: Active Menu Auto Scroll
  // Jika menu aktif ada di bawah, scroll sidebar otomatis ke posisi menu tersebut
  const activeLink = $(".nav-sidebar .nav-link.active");
  if (activeLink.length > 0) {
    const sidebar = $(".sidebar");
    const offsetTop = activeLink.offset().top;

    // Jika link aktif di luar viewport, scroll ke sana
    if (offsetTop > sidebar.height() || offsetTop < 0) {
      sidebar.animate(
        {
          scrollTop: offsetTop - 100,
        },
        500
      );
    }
  }
});
