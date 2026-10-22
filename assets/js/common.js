/**
 * Maruf Traders - Common Frontend Utilities, API Client & Notifications
 */

// Global CSRF Token Retrieval
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

// Global Currency Formatter (BDT ৳)
function formatBDT(amount) {
    const num = parseFloat(amount) || 0;
    return '৳ ' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Universal API Fetch Client
async function apiRequest(url, options = {}) {
    const defaultHeaders = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };

    // Auto attach CSRF if method is POST/PUT/DELETE
    if (options.method && options.method.toUpperCase() !== 'GET') {
        const token = getCsrfToken();
        if (token) {
            defaultHeaders['X-CSRF-TOKEN'] = token;
        }
    }

    options.headers = { ...defaultHeaders, ...(options.headers || {}) };

    try {
        const response = await fetch(url, options);
        
        if (response.status === 401) {
            showToast('error', 'Session expired. Redirecting to login...');
            setTimeout(() => {
                window.location.href = window.BASE_URL + '/login.php?expired=1';
            }, 1500);
            throw new Error('Unauthorized');
        }

        if (response.status === 403) {
            showToast('error', 'Access Denied: You do not have permission.');
            throw new Error('Forbidden');
        }

        const data = await response.json();
        return data;
    } catch (error) {
        if (error.message !== 'Unauthorized' && error.message !== 'Forbidden') {
            console.error('API Request Failed:', error);
            showToast('error', error.message || 'Server connection error');
        }
        throw error;
    }
}

// Premium Toast Notification
function showToast(type = 'info', message = '', title = '') {
    // Check if SweetAlert2 is available
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: '#151F36',
            color: '#F8FAFC',
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        let iconType = 'info';
        if (type === 'success') iconType = 'success';
        if (type === 'error' || type === 'danger') iconType = 'error';
        if (type === 'warning') iconType = 'warning';

        Toast.fire({
            icon: iconType,
            title: message || title
        });
    } else {
        alert((title ? title + ': ' : '') + message);
    }
}

// SweetAlert2 Confirmation Dialog Wrapper
async function confirmAction(options = {}) {
    const defaults = {
        title: 'Are you sure?',
        text: 'Do you want to proceed with this action?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#8B5CF6',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, Confirm',
        cancelButtonText: 'Cancel',
        background: '#151F36',
        color: '#F8FAFC'
    };

    const config = { ...defaults, ...options };
    
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire(config);
        return result.isConfirmed;
    } else {
        return window.confirm(config.text);
    }
}

// Generic AJAX Form Submitter with Button Loading State
function setupAjaxForm(formSelector, url, successCallback = null) {
    const form = document.querySelector(formSelector);
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        let originalText = '';
        if (submitBtn) {
            originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Processing...';
        }

        const formData = new FormData(form);
        // Ensure CSRF token is in FormData
        if (!formData.has('csrf_token')) {
            formData.append('csrf_token', getCsrfToken());
        }

        try {
            const result = await apiRequest(url, {
                method: 'POST',
                body: formData
            });

            if (result.success) {
                showToast('success', result.message || 'Action completed successfully');
                if (typeof successCallback === 'function') {
                    successCallback(result);
                }
            } else {
                showToast('error', result.message || 'Operation failed');
            }
        } catch (err) {
            // Handled in apiRequest
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    });
}
