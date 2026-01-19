function openPinModal(imageSrc, title, pinId, likeCount, userLiked) {
    const modalPinImage = document.getElementById('modalPinImage');
    const modalPinTitle = document.getElementById('modalPinTitle');
    const modalLikeButton = document.getElementById('modalLikeButton');
    const modalLikeCount = document.getElementById('modalLikeCount');
    
    if (!modalPinImage || !modalPinTitle || !modalLikeButton || !modalLikeCount) {
        console.error('One or more modal elements are missing:', {
            modalPinImage: !!modalPinImage,
            modalPinTitle: !!modalPinTitle,
            modalLikeButton: !!modalLikeButton,
            modalLikeCount: !!modalLikeCount
        });
        return;
    }
    
    modalPinImage.src = imageSrc;
    modalPinTitle.textContent = title;
    modalLikeButton.setAttribute('data-pin-id', pinId);
    modalLikeCount.textContent = likeCount;
    modalLikeButton.classList.toggle('liked', userLiked);
    
    const forms = document.querySelectorAll('#pinModal form input[name="pin_id"]');
    forms.forEach(formInput => {
        formInput.value = pinId;
    });
    
    const currentSearch = window.location.search || '';
    const newSearch = currentSearch.includes('pin_id=') 
    ? currentSearch.replace(/pin_id=\d+/, `pin_id=${pinId}`)
    : `${currentSearch}${currentSearch ? '&' : '?'}pin_id=${pinId}&collection_id=<?php echo urlencode($collection_id); ?>&sort=<?php echo urlencode($sort); ?>`;
    window.location.href = `collectionDetails.php${newSearch}#pinModal`;
}

function closePinModal() {
    const modal = document.getElementById('pinModal');
    if (modal) {
        modal.style.display = 'none';
        const url = new URL(window.location);
        url.searchParams.delete('pin_id');
        url.hash = '';
        window.history.replaceState({}, document.title, url);
    }
}

function deleteComment(commentId, pinId) {
    if (!confirm('Are you sure you want to delete this comment?')) return;
    
    const formData = new FormData();
    formData.append('delete_comment', true);
    formData.append('comment_id', commentId);
    formData.append('pin_id', pinId);
    
    fetch(`collectionDetails.php?collection_id=<?php echo urlencode($collection_id); ?>&sort=<?php echo urlencode($sort); ?>&pin_id=${pinId}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        window.location.href = `collectionDetails.php?collection_id=<?php echo urlencode($collection_id); ?>&pin_id=${pinId}&sort=<?php echo urlencode($sort); ?>#pinModal`;
    })
    .catch(error => console.error('Error deleting comment:', error));
}

window.addEventListener('load', function() {
    const modal = document.getElementById('pinModal');
    if (window.location.hash === '#pinModal' && modal) {
        modal.style.display = 'flex';
    }
});

document.addEventListener('click', function(e) {
    const modal = document.getElementById('pinModal');
    if (e.target === modal) {
        closePinModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePinModal();
        closeDeleteModal();
    }
});

function applySort() {
    const sortValue = document.getElementById('sort').value;
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('sort', sortValue);
    currentUrl.searchParams.set('collection_id', '<?php echo urlencode($collection_id); ?>');
    window.location.href = currentUrl.toString();
}

function openDeleteModal(type, id, event) {
    event.stopPropagation();
    document.getElementById('deleteModal').style.display = 'flex';
    document.getElementById('deleteModalTitle').textContent = `Delete ${type.charAt(0).toUpperCase() + type.slice(1)}`;
    document.getElementById('deleteModalText').textContent = `Do you really want to delete this ${type}? This action cannot be undone.`;
    document.querySelector('.delete-modal-confirm').setAttribute('data-type', type);
    document.querySelector('.delete-modal-confirm').setAttribute('data-id', id);
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function confirmDelete() {
    const type = document.querySelector('.delete-modal-confirm').getAttribute('data-type');
    const id = document.querySelector('.delete-modal-confirm').getAttribute('data-id');
    const formData = new FormData();
    formData.append('delete_pin', true);
    formData.append('pin_id', id);
    
    fetch(`collectionDetails.php?collection_id=<?php echo urlencode($collection_id); ?>&sort=<?php echo urlencode($sort); ?>`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        window.location.href = `collectionDetails.php?collection_id=<?php echo urlencode($collection_id); ?>&sort=<?php echo urlencode($sort); ?>`;
    })
    .catch(error => console.error('Error deleting pin:', error));
    closeDeleteModal();
}