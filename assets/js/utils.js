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
