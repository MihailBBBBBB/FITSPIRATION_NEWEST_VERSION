function toggleAllReports(checked) {
    document.querySelectorAll('.report-checkbox').forEach(checkbox => {
        checkbox.checked = checked;
    });
    updateBulkPanel();
}

function updateBulkPanel() {
    const selectedCheckboxes = Array.from(document.querySelectorAll('.report-checkbox:checked'));
    const selectedCount = selectedCheckboxes.length;
    const bulkPanel = document.getElementById('bulkActionsPanel');
    const selectionsContainer = document.getElementById('bulkReportSelections');

    if (selectionsContainer) {
        selectionsContainer.innerHTML = selectedCheckboxes
            .map(checkbox => `<input type="hidden" name="selected_reports[]" value="${checkbox.value}">`)
            .join('');
    }
    
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
