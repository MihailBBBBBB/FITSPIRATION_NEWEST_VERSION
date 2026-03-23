function populatePinModal(pinElement) {
    if (!pinElement) return;

    const imageSrc = pinElement.querySelector('.pin-image')?.dataset.image || pinElement.dataset.image || '../images/no_image.jpg';
    const title = pinElement.querySelector('.pin-title')?.textContent || pinElement.dataset.title || 'Pin';
    const pinId = pinElement.dataset.pinId || '';
    const imageElement = pinElement.querySelector('.pin-image');
    const likeCount = Number(imageElement?.dataset.likeCount || pinElement.dataset.likeCount || 0);
    const userLiked = (imageElement?.dataset.userLiked === '1') || (pinElement.dataset.userLiked === '1');
    const creatorId = imageElement?.dataset.creatorId || pinElement.dataset.creatorId || '';
    const creatorName = imageElement?.dataset.creatorName || pinElement.dataset.creatorName || 'Unknown';
    const creatorImg = imageElement?.dataset.creatorImg || pinElement.dataset.creatorImg || '../images/no_image.jpg';

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

    const pinIdInputs = document.querySelectorAll('#pinModal input[name="pin_id"]');
    pinIdInputs.forEach(input => input.value = pinId);

    const modal = document.getElementById('pinModal');
    if (modal) modal.style.display = 'flex';
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

window.addEventListener('load', function() {
    const pinItems = document.querySelectorAll('.pin-item[data-pin-id]');
    pinItems.forEach(item => {
        item.addEventListener('click', (e) => {
            // Prevent modal opening if clicking on delete cross or like button
            if (e.target.closest('.delete-cross') || e.target.closest('.like-button') || e.target.closest('form')) return;
            const pinId = item.dataset.pinId || '';
            if (!pinId || pinId === 'undefined' || !isNaN(pinId) === false) return; // Skip invalid pin IDs
            openPinModal(pinId);
        });
    });

    const urlParams = new URLSearchParams(window.location.search);
    const pinId = urlParams.get('pin_id');
    if (pinId) {
        const modal = document.getElementById('pinModal');
        if (modal) modal.style.display = 'flex';
    }

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