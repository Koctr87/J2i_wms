<?php
/**
 * J2i Warehouse Management System
 * Footer Template
 */
?>
</div><!-- .page-content -->
</main><!-- .main -->
</div><!-- .app -->

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
    // Mobile sidebar toggle
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('open');
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function (e) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.querySelector('.mobile-menu-toggle');

        if (window.innerWidth <= 768 &&
            sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            !toggle.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });

    // Modal functions
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
            document.body.style.overflow = '';
        }
    });

    // Toast notifications
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 4000);
    }

    // Format currency
    function formatCurrency(amount, currency = 'CZK') {
        return new Intl.NumberFormat('cs-CZ', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(amount);
    }

    // AJAX helper
    async function sendRequest(action, data = {}) {
        const params = new URLSearchParams({ action, ...data });
        const response = await fetch('<?= APP_URL ?>/api/ajax-handlers.php?' + params);
        return response.json();
    }

    // Status tab switching
    document.querySelectorAll('.status-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            this.closest('.status-tabs').querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Warehouse map tab switching
    document.querySelectorAll('.warehouse-map-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            this.closest('.warehouse-map-tabs').querySelectorAll('.warehouse-map-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Initialize tooltips and other UI elements
    document.addEventListener('DOMContentLoaded', function () {
        // Add any initialization code here
    });
</script>

</body>

</html>