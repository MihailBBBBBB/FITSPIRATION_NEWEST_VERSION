function populatePinModal(pinElement) {
    if (!pinElement) return;

    const imageElement = pinElement.querySelector('.pin-image');
    const imageSrc = imageElement?.dataset.image || '../images/no_image.jpg';
    const title = imageElement?.dataset.title || pinElement.dataset.title || 'Pin';
    const pinId = imageElement?.dataset.pinId || pinElement.dataset.pinId || '';
    const likeCount = Number(imageElement?.dataset.likeCount || 0);
    const userLiked = imageElement?.dataset.userLiked === '1';
    const creatorId = imageElement?.dataset.creatorId || '';
    const creatorName = imageElement?.dataset.creatorName || 'Unknown';
    const creatorImg = imageElement?.dataset.creatorImg || '../images/no_image.jpg';

    const modalPinImage = document.getElementById('modalPinImage');
    const modalPinTitle = document.getElementById('modalPinTitle');
    const modalLikeButton = document.getElementById('modalLikeButton');
    const modalLikeCount = document.getElementById('modalLikeCount');
    const modalCreatorLink = document.getElementById('modalCreatorLink');
    const modalCreatorAvatar = document.getElementById('modalCreatorAvatar');

    if (modalPinImage) modalPinImage.src = imageSrc;
    if (modalPinTitle) modalPinTitle.textContent = title;
    if (modalLikeButton) {
        modalLikeButton.dataset.pinId = pinId;
        modalLikeButton.classList.toggle('liked', userLiked);
    }
    if (modalLikeCount) modalLikeCount.textContent = likeCount;
    if (modalCreatorAvatar) modalCreatorAvatar.src = creatorImg;

    if (modalCreatorLink) {
        if (creatorId) {
            modalCreatorLink.href = 'Profile.php?user_id=' + encodeURIComponent(creatorId);
            modalCreatorLink.textContent = creatorName;
            modalCreatorLink.style.pointerEvents = 'auto';
            modalCreatorLink.style.color = '#1d4ed8';
        } else {
            modalCreatorLink.href = '#';
            modalCreatorLink.textContent = creatorName;
            modalCreatorLink.style.pointerEvents = 'none';
            modalCreatorLink.style.color = '#6b7280';
        }
    }

    const forms = document.querySelectorAll('#pinModal form input[name="pin_id"]');
    forms.forEach(formInput => {
        formInput.value = pinId;
    });
}

function openPinModal(pinId) {
    if (!pinId) return;
    const url = new URL(window.location);
    url.searchParams.set('pin_id', pinId);
    url.hash = 'pinModal';
    window.location.href = url.toString();
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
    formData.append('delete_comment', '1');
    formData.append('comment_id', commentId);
    formData.append('pin_id', pinId);

    const url = new URL(window.location);
    url.searchParams.set('pin_id', pinId);

    fetch(url.toString(), {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        const redirectUrl = new URL(window.location);
        redirectUrl.searchParams.set('pin_id', pinId);
        redirectUrl.hash = 'pinModal';
        window.location.href = redirectUrl.toString();
    })
    .catch(error => console.error('Error deleting comment:', error));
}

window.addEventListener('load', function() {
    const pinItems = document.querySelectorAll('.pin-item[data-pin-id]');
    pinItems.forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.delete-cross') || e.target.closest('.like-button') || e.target.closest('form')) return;

            const pinId = item.dataset.pinId || '';
            if (!pinId || isNaN(Number(pinId))) return;

            openPinModal(pinId);
        });
    });

    const pinId = new URLSearchParams(window.location.search).get('pin_id');
    if (pinId) {
        const selectedPin = document.querySelector('.pin-item[data-pin-id="' + CSS.escape(pinId) + '"]');
        populatePinModal(selectedPin);

        const modal = document.getElementById('pinModal');
        if (modal) {
            modal.style.display = 'flex';
        }
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
    const id = document.querySelector('.delete-modal-confirm').getAttribute('data-id');
    const formData = new FormData();
    formData.append('delete_pin', '1');
    formData.append('pin_id', id);

    const url = new URL(window.location);

    fetch(url.toString(), {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        const redirectUrl = new URL(window.location);
        redirectUrl.searchParams.delete('pin_id');
        redirectUrl.hash = '';
        window.location.href = redirectUrl.toString();
    })
    .catch(error => console.error('Error deleting pin:', error));
    closeDeleteModal();
}