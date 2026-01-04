/**
 * Proxima Admin Panel - JavaScript
 * Minimal JS for confirmations and interactions
 */

/**
 * Confirm delete action
 */
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this? This action cannot be undone.');
}

/**
 * Confirm action with custom message
 */
function confirmAction(message) {
    return confirm(message);
}

/**
 * Confirm destructive action - requires typing confirmation
 */
function confirmDestructive(message, confirmText) {
    const result = prompt(message);
    return result === confirmText;
}

/**
 * Show loading state on button
 */
function setLoading(button, loading = true) {
    if (loading) {
        button.dataset.originalText = button.textContent;
        button.textContent = 'Loading...';
        button.disabled = true;
    } else {
        button.textContent = button.dataset.originalText || button.textContent;
        button.disabled = false;
    }
}

/**
 * Auto-dismiss alerts after delay
 */
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);
    });
});

/**
 * Delete record form submission
 */
function submitDeleteForm(formId) {
    const form = document.getElementById(formId);
    if (form && confirmDelete()) {
        form.submit();
    }
}

/**
 * Delete table confirmation
 */
function confirmDeleteTable(tableName) {
    return confirm('Delete table "' + tableName + '"?\n\nThis will permanently remove the table and all its data. This action cannot be undone.');
}

/**
 * Fresh migration confirmation
 */
function confirmFreshMigration() {
    const result = prompt('⚠️ WARNING: This will DROP all tables and recreate them.\n\nAll data will be permanently LOST!\n\nType "DELETE ALL DATA" to confirm:');
    return result === 'DELETE ALL DATA';
}

/**
 * Sync with destructive changes confirmation
 */
function confirmDestructiveSync(warnings) {
    const message = '⚠️ WARNING: DESTRUCTIVE CHANGES DETECTED\n\n' +
        'The following column changes may cause DATA LOSS:\n\n' +
        warnings + '\n\n' +
        'Examples:\n' +
        '  - Type changes (string→integer): Data will be converted (may become 0)\n' +
        '  - Length reduction: Data will be truncated\n' +
        '  - nullable→NOT NULL: NULLs may cause errors\n\n' +
        'Type "YES" to confirm:';
    
    const result = prompt(message);
    return result === 'YES';
}
