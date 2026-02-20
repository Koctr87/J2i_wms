/**
 * J2i Warehouse Management System
 * Main JavaScript
 */

// Sidebar Toggle (Mobile)
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('active');
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
        });
    }
    
    // Language switcher - preserve current page
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const lang = this.getAttribute('href').split('=')[1];
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        });
    });
});

// Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal-overlay.active');
        if (activeModal) {
            activeModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
});

// Confirm Delete
function confirmDelete(message, callback) {
    if (confirm(message || 'Are you sure you want to delete this item?')) {
        callback();
    }
}

// Format Currency
function formatCurrency(amount, currency = 'CZK') {
    const symbols = { CZK: 'Kč', EUR: '€', USD: '$' };
    const symbol = symbols[currency] || currency;
    
    if (currency === 'CZK') {
        return new Intl.NumberFormat('cs-CZ', { 
            minimumFractionDigits: 0,
            maximumFractionDigits: 0 
        }).format(amount) + ' ' + symbol;
    }
    
    return new Intl.NumberFormat('cs-CZ', { 
        minimumFractionDigits: 2,
        maximumFractionDigits: 2 
    }).format(amount) + ' ' + symbol;
}

// Format Date
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('cs-CZ');
}

// AJAX Helper
async function fetchData(url, options = {}) {
    const defaults = {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const config = { ...defaults, ...options };
    
    try {
        const response = await fetch(url, config);
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'An error occurred');
        }
        
        return data;
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}

// Show Toast Notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    // Add toast styles if not exists
    if (!document.querySelector('#toast-styles')) {
        const styles = document.createElement('style');
        styles.id = 'toast-styles';
        styles.textContent = `
            .toast {
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 9999;
                animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            .toast-success { background: #10b981; }
            .toast-error { background: #ef4444; }
            .toast-warning { background: #f59e0b; }
            .toast-info { background: #3b82f6; }
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(styles);
    }
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Debounce Function
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

// Calculate VAT (Marginal)
function calculateMarginalVAT(purchasePrice, sellingPrice, currencyRate = 1) {
    const purchaseInCZK = purchasePrice * currencyRate;
    const margin = sellingPrice - purchaseInCZK;
    const vatAmount = margin * (21 / 121);
    return {
        margin: Math.round(margin * 100) / 100,
        vatAmount: Math.round(vatAmount * 100) / 100
    };
}

// Calculate VAT (Reverse Charge)
function calculateReverseVAT(sellingPrice) {
    const vatAmount = sellingPrice * 0.21;
    return {
        vatAmount: Math.round(vatAmount * 100) / 100
    };
}

// Dynamic Form Field - Show/Hide based on selection
function toggleField(triggerId, targetId, showValue) {
    const trigger = document.getElementById(triggerId);
    const target = document.getElementById(targetId);
    
    if (trigger && target) {
        trigger.addEventListener('change', function() {
            if (this.value === showValue) {
                target.classList.remove('hidden');
            } else {
                target.classList.add('hidden');
            }
        });
    }
}

// Auto-resize textarea
document.querySelectorAll('textarea.auto-resize').forEach(textarea => {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
});

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        const errorEl = field.parentElement.querySelector('.form-error');
        
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');
            if (errorEl) errorEl.style.display = 'block';
        } else {
            field.classList.remove('error');
            if (errorEl) errorEl.style.display = 'none';
        }
    });
    
    return isValid;
}

// Number Input - Prevent negative values
document.querySelectorAll('input[type="number"]').forEach(input => {
    if (input.min === '' || parseFloat(input.min) >= 0) {
        input.addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
        });
    }
});

// Copy to Clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard', 'success');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}
