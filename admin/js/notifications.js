/**
 * Simple UniVoucher Notification System
 */
(function($) {
    'use strict';

    // Create notification container if it doesn't exist
    function createContainer() {
        if ($('#univoucher-notifications').length === 0) {
            $('body').append('<div id="univoucher-notifications"></div>');
        }
    }

    // Main notification function
    window.univoucherNotify = function(message, type, duration) {
        type = type || 'info';
        duration = duration || 10000; // Default 10 seconds

        createContainer();

        var notificationId = 'notification-' + Date.now();
        var typeClass = 'univoucher-notification-' + type;
        
        // Check if message contains a transaction hash (long hex string)
        var hasTransactionHash = /0x[a-fA-F0-9]{64}/.test(message);
        var extraClass = hasTransactionHash ? ' univoucher-notification-with-tx' : '';
        
        var notification = $('<div class="univoucher-notification ' + typeClass + extraClass + '" id="' + notificationId + '">' +
            '<div class="univoucher-notification-content">' + message + '</div>' +
            '<button class="univoucher-notification-close">&times;</button>' +
            '<div class="univoucher-notification-progress" style="width: 100%;"></div>' +
            '</div>');

        $('#univoucher-notifications').append(notification);

        // Animate in
        setTimeout(function() {
            notification.addClass('show');
        }, 50);

        // Start progress bar animation with hover pause functionality
        var progressBar = notification.find('.univoucher-notification-progress');
        var startTime = Date.now();
        var pausedTime = 0;
        var isPaused = false;
        
        var progressInterval = setInterval(function() {
            if (!isPaused) {
                var elapsed = Date.now() - startTime - pausedTime;
                var remaining = Math.max(0, duration - elapsed);
                var percent = (remaining / duration) * 100;
                progressBar.css('width', percent + '%');
                
                if (remaining <= 0) {
                    clearInterval(progressInterval);
                }
            }
        }, 50);

        // Auto remove
        var timeout = setTimeout(function() {
            clearInterval(progressInterval);
            removeNotification(notificationId);
        }, duration);

        // Pause on hover, resume on leave
        var pauseStartTime;
        notification.on('mouseenter', function() {
            if (!isPaused) {
                isPaused = true;
                pauseStartTime = Date.now();
                clearTimeout(timeout);
            }
        }).on('mouseleave', function() {
            if (isPaused) {
                isPaused = false;
                pausedTime += Date.now() - pauseStartTime;
                
                // Recalculate remaining time and set new timeout
                var elapsed = Date.now() - startTime - pausedTime;
                var remaining = Math.max(0, duration - elapsed);
                
                if (remaining > 0) {
                    timeout = setTimeout(function() {
                        clearInterval(progressInterval);
                        removeNotification(notificationId);
                    }, remaining);
                }
            }
        });

        // Manual close
        notification.find('.univoucher-notification-close').on('click', function() {
            isPaused = false;
            clearTimeout(timeout);
            clearInterval(progressInterval);
            removeNotification(notificationId);
        });
    };

    // Remove notification
    function removeNotification(id) {
        var $notification = $('#' + id);
        $notification.removeClass('show');
        setTimeout(function() {
            $notification.remove();
        }, 300);
    }

    // Convenience functions
    window.univoucherNotify.success = function(message, duration) {
        univoucherNotify(message, 'success', duration);
    };

    window.univoucherNotify.error = function(message, duration) {
        univoucherNotify(message, 'error', duration);
    };

    window.univoucherNotify.warning = function(message, duration) {
        univoucherNotify(message, 'warning', duration);
    };

    window.univoucherNotify.info = function(message, duration) {
        univoucherNotify(message, 'info', duration);
    };

})(jQuery); 