/**
 * Global Utility Functions
 * Common functions used across pages
 */

// Format number with thousand separator
function formatNumber(num) {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Format date to Indonesian format
function formatDate(date) {
  const d = new Date(date);
  const options = {
    timeZone: "Asia/Jakarta",
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  };
  return d.toLocaleString("id-ID", options);
}

// Debounce function
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Show loading overlay
function showLoading(message = "Loading...") {
  Swal.fire({
    title: message,
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });
}

// Hide loading overlay
function hideLoading() {
  Swal.close();
}

// Show success toast
function showSuccessToast(message) {
  Swal.fire({
    icon: "success",
    title: message,
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
  });
}

// Show error toast
function showErrorToast(message) {
  Swal.fire({
    icon: "error",
    title: message,
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 5000,
    timerProgressBar: true,
  });
}

// Clear localStorage blacklist (for RFID)
function clearBlacklist() {
  localStorage.removeItem("lastAddedUID");
  localStorage.removeItem("lastAddTime");
}

// Show confirmation dialog (konsisten dengan desain square popup)
function showConfirmDialog(options) {
  const defaults = {
    title: "Konfirmasi",
    message: "Apakah Anda yakin?",
    icon: "warning",
    iconColor: "#ffc107",
    confirmText: "Ya",
    cancelText: "Batal",
    confirmColor: "#dc3545",
    cancelColor: "#6c757d",
  };

  const config = { ...defaults, ...options };

  return Swal.fire({
    title: config.title,
    html: `
      <div style="text-align: center; padding: 15px 5px;">
        <div style="margin-bottom: 25px;">
          <i class="fas fa-exclamation-circle" style="color: ${config.iconColor}; font-size: 64px;"></i>
        </div>
        <p style="margin: 0; color: #495057; font-size: 15px;">
          ${config.message}
        </p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: config.confirmText,
    cancelButtonText: config.cancelText,
    confirmButtonColor: config.confirmColor,
    cancelButtonColor: config.cancelColor,
    reverseButtons: true,
    customClass: {
      popup: "swal2-square-popup",
      confirmButton: "btn btn-danger btn-square",
      cancelButton: "btn btn-secondary btn-square",
    },
    buttonsStyling: false,
    width: "450px",
  });
}

// AJAX Error Handler
function handleAjaxError(xhr, status, error) {
  console.error("AJAX Error:", { xhr, status, error });

  let message = "Terjadi kesalahan saat menghubungi server";

  if (xhr.responseJSON && xhr.responseJSON.error) {
    message = xhr.responseJSON.error;
  } else if (xhr.responseText) {
    message = xhr.responseText;
  }

  showErrorToast(message);
}
