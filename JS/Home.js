
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
    : `${currentSearch}${currentSearch ? '&' : '?'}pin_id=${pinId}`;
    window.location.href = `Home.php${newSearch}#pinModal`;
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
    
    fetch(`Home.php?sort=<?php echo urlencode($sort); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>&pin_id=${pinId}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        window.location.href = `Home.php?pin_id=${pinId}&sort=<?php echo urlencode($sort); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>#pinModal`;
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
    }
});

function applySort(sortValue) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('sort', sortValue);
    window.location.href = currentUrl.toString();
}