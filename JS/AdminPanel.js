function toggleAllReports(checked) {
    document.querySelectorAll('.report-checkbox').forEach(checkbox => {
        checkbox.checked = checked;
    });
    updateBulkPanel();
}

function updateBulkPanel() {
    const selectedCount = document.querySelectorAll('.report-checkbox:checked').length;
    const bulkPanel = document.getElementById('bulkActionsPanel');
    
    if (selectedCount > 0) {
        bulkPanel.style.display = 'flex';
    } else {
        bulkPanel.style.display = 'none';
    }
}

function cancelBulkActions() {
    document.getElementById('selectAllReports').checked = false;
    document.querySelectorAll('.report-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    updateBulkPanel();
}
