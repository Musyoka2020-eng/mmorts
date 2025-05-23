/**
 * MMORTS Game Alerts System
 * A wrapper around SweetAlert2 for consistent game alerts
 */

const GameAlerts = {
    /**
     * Show a battle result message with combat details
     * @param {string} result - Battle result ('victory', 'defeat', or 'draw')
     * @param {object} details - Battle details object with losses and gains
     */
    battleResult: (result, details) => {
        let icon;
        let title;
        let color;
        
        if (result === 'victory') {
            icon = 'success';
            title = 'Victory!';
            color = '#28a745';
        } else if (result === 'defeat') {
            icon = 'error';
            title = 'Defeat!';
            color = '#dc3545';
        } else {
            icon = 'info';
            title = 'Battle Ended in a Draw';
            color = '#17a2b8';
        }
        
        let html = `<div class="battle-report">`;
        
        // Show losses if provided
        if (details?.losses) {
            html += `<h4>Your Losses:</h4>
                    <ul class="battle-losses">`;
            
            for (const [unit, count] of Object.entries(details.losses)) {
                if (count > 0) {
                    html += `<li>${count} ${unit}</li>`;
                }
            }
            
            html += "</ul>";
        }
        
        // Show resources plundered if provided and it's a victory
        if (result === 'victory' && details && details.plunder) {
            html += `<h4>Resources Plundered:</h4>
                    <ul class="battle-plunder">`;
            
            for (const [resource, amount] of Object.entries(details.plunder)) {
                if (amount > 0) {
                    html += `<li>${amount} ${resource}</li>`;
                }
            }
            
            html += "</ul>";
        }
        
        html += "</div>";
        
        return Swal.fire({
            title: title,
            html: html,
            icon: icon,
            confirmButtonText: 'Continue',
            confirmButtonColor: color,
            background: '#343a40',
            color: '#fff',
            customClass: {
                container: 'game-alert-container',
                popup: 'game-alert-popup battle-result-popup',
                title: 'game-alert-title',
                confirmButton: 'game-alert-button'
            }
        });
    },
    
    /**
     * Show a success message
     * @param {string} title - Alert title
     * @param {string} message - Alert message content
     * @param {boolean} timer - Whether to auto-close the alert (default: true)
     */
    success: (title, message, timer = true) => Swal.fire({
            title: title,
            text: message,
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#28a745',
            timer: timer ? 3000 : undefined,
            timerProgressBar: timer,
            customClass: {
                container: 'game-alert-container',
                popup: 'game-alert-popup',
                title: 'game-alert-title',
                confirmButton: 'game-alert-button'
            }
        }),

    /**
     * Show an error message
     * @param {string} title - Alert title
     * @param {string} message - Alert message content
     */
    error: (title, message) => Swal.fire({
            title: title,
            text: message,
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545',
            customClass: {
                container: 'game-alert-container',
                popup: 'game-alert-popup',
                title: 'game-alert-title',
                confirmButton: 'game-alert-button'
            }
        }),

    /**
     * Show a warning message
     * @param {string} title - Alert title
     * @param {string} message - Alert message content
     */
    warning: (title, message) => Swal.fire({
            title: title,
            text: message,
            icon: 'warning',
            confirmButtonText: 'OK',
            confirmButtonColor: '#ffc107',
            customClass: {
                container: 'game-alert-container',
                popup: 'game-alert-popup',
                title: 'game-alert-title',
                confirmButton: 'game-alert-button'
            }
        }),

    /**
     * Show an info message
     * @param {string} title - Alert title
     * @param {string} message - Alert message content
     */
    info: (title, message) => Swal.fire({
            title: title,
            text: message,
            icon: 'info',
            confirmButtonText: 'OK',
            confirmButtonColor: '#17a2b8',
            customClass: {
                container: 'game-alert-container',
                popup: 'game-alert-popup',
                title: 'game-alert-title',
                confirmButton: 'game-alert-button'
            }
        }),

    /**
     * Show a confirmation dialog
     * @param {string} title - Alert title
     * @param {string} message - Alert message content
     * @param {string} confirmText - Text for the confirm button
     * @param {string} cancelText - Text for the cancel button
     * @param {function} confirmCallback - Function to execute when confirmed
     */
    confirm: (title, message, confirmCallback, confirmText = 'Confirm', cancelText = 'Cancel') => Swal.fire({
            title: title,
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            customClass: {
                container: 'game-alert-container',
                popup: 'game-alert-popup',
                title: 'game-alert-title',
                confirmButton: 'game-alert-button',
                cancelButton: 'game-alert-button'
            }
        }).then((result) => {
            if (result.isConfirmed && typeof confirmCallback === 'function') {
                confirmCallback();
            }
        }),
    
    /**
     * Show a custom-styled alert for the move city functionality
     * @param {string} title - Alert title
     * @param {string} message - Alert message content
     * @param {function} confirmCallback - Function to execute when confirmed
     */
    moveCity: (title, message, confirmCallback) => Swal.fire({
            title: title,
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Move City',
            cancelButtonText: 'Cancel',
            background: '#343a40',
            color: '#fff',
            customClass: {
                container: 'game-alert-container',
                popup: 'game-alert-popup',
                title: 'game-alert-title',
                confirmButton: 'game-alert-button',
                cancelButton: 'game-alert-button'
            }
        }).then((result) => {
            if (result.isConfirmed && typeof confirmCallback === 'function') {
                confirmCallback();
            }
        }),

    /**
     * Show a game-themed toast notification
     * @param {string} title - Toast title
     * @param {string} message - Toast message
     * @param {string} icon - Icon type ('success', 'error', 'warning', 'info', 'question')
     */
    toast: (title, message, icon = 'info') => {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            },
            customClass: {
                container: 'game-toast-container',
                popup: 'game-toast-popup'
            }
        });
        
        return Toast.fire({
            icon: icon,
            title: title,
            text: message
        });
    }
};

// Add CSS for custom styling
const gameAlertStyles = document.createElement('style');
gameAlertStyles.innerHTML = `
    .game-alert-popup {
        border: 2px solid #6c4a3c;
        border-radius: 10px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
        background-color: #343a40;
    }
    
    .game-alert-title {
        color: #e9b253;
        font-family: 'Cinzel', serif;
    }
    
    .game-alert-button {
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .game-toast-popup {
        background-color: rgba(33, 37, 41, 0.9);
        color: #fff;
        border-left: 4px solid #e9b253;
    }
`;
document.head.appendChild(gameAlertStyles);
