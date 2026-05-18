function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `v2-toast v2-toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('v2-toast-visible'));

    setTimeout(() => {
        toast.classList.remove('v2-toast-visible');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

export { showToast };
export default showToast;