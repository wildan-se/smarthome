/**
 * Alert Component
 * Reusable alert/notification system
 */

class Alert {
  /**
   * Show alert message
   * @param {string} target - jQuery selector for container
   * @param {string} type - Alert type: success, danger, warning, info
   * @param {string} message - Alert message (can include HTML)
   * @param {boolean} autoDismiss - Auto-hide after duration
   */
  static show(target, type, message, autoDismiss = true) {
    const alertHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;

    $(target).html(alertHTML);

    if (autoDismiss) {
      const duration = type === "danger" || type === "warning" ? 8000 : 5000;
      setTimeout(() => {
        $(target)
          .find(".alert")
          .fadeOut(300, function () {
            $(this).remove();
          });
      }, duration);
    }
  }

  /**
   * Show success alert
   */
  static success(target, message, autoDismiss = true) {
    this.show(
      target,
      "success",
      `<i class="fas fa-check-circle"></i> ${message}`,
      autoDismiss
    );
  }

  /**
   * Show error alert
   */
  static error(target, message, autoDismiss = true) {
    this.show(
      target,
      "danger",
      `<i class="fas fa-times-circle"></i> ${message}`,
      autoDismiss
    );
  }

  /**
   * Show warning alert
   */
  static warning(target, message, autoDismiss = true) {
    this.show(
      target,
      "warning",
      `<i class="fas fa-exclamation-triangle"></i> ${message}`,
      autoDismiss
    );
  }

  /**
   * Show info alert
   */
  static info(target, message, autoDismiss = true) {
    this.show(
      target,
      "info",
      `<i class="fas fa-info-circle"></i> ${message}`,
      autoDismiss
    );
  }

  /**
   * Show loading alert
   */
  static loading(target, message = "Loading...") {
    const alertHTML = `
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-spinner fa-spin"></i> ${message}
            </div>
        `;
    $(target).html(alertHTML);
  }

  /**
   * Clear all alerts in target
   */
  static clear(target) {
    $(target).empty();
  }
}
