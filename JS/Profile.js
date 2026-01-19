function openPinModal(imageSrc, title, pinId, likeCount, userLiked) {
    const modalPinImage = document.getElementById('modalPinImage');
    const modalPinTitle = document.getElementById('modalPinTitle');
    const modalLikeButton = document.getElementById('modalLikeButton');
    const modalLikeCount = document.getElementById('modalLikeCount');
    const modalCommentList = document.getElementById('modalCommentList');

    if (!modalPinImage || !modalPinTitle || !modalLikeButton || !modalLikeCount || !modalCommentList) {
        console.error('One or more modal elements are missing:', {
            modalPinImage: !!modalPinImage,
            modalPinTitle: !!modalPinTitle,
            modalLikeButton: !!modalLikeButton,
            modalLikeCount: !!modalLikeCount,
            modalCommentList: !!modalCommentList
        });
        return;
    }

    modalPinImage.src = imageSrc;
    modalPinImage.alt = title;
    modalPinTitle.textContent = title;
    modalLikeButton.setAttribute('data-pin-id', pinId);
    modalLikeCount.textContent = likeCount;
    modalLikeButton.classList.toggle('liked', userLiked);

    const forms = document.querySelectorAll('#pinModal form input[name="pin_id"]');
    forms.forEach(formInput => {
        formInput.value = pinId;
    });

    if (!pinData[pinId]) {
        pinData[pinId] = { likes: likeCount, comments: [] };
    }

    modalCommentList.innerHTML = '';
    pinData[pinId].comments.forEach(comment => {
        const li = document.createElement('li');
        li.textContent = comment;
        modalCommentList.appendChild(li);
    });

    const currentSearch = window.location.search || '';
    const newSearch = currentSearch.includes('pin_id=') 
        ? currentSearch.replace(/pin_id=\d+/, `pin_id=${pinId}`)
        : `${currentSearch}${currentSearch ? '&' : '?'}pin_id=${pinId}`;
    window.location.href = `Profile.php${newSearch}#pinModal`;
}

function closePinModal() {
    const modal = document.getElementById('pinModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('modalCommentInput').value = '';
        const url = new URL(window.location);
        url.searchParams.delete('pin_id');
        url.hash = '';
        window.history.replaceState({}, document.title, url);
    }
}

function applySort(sortValue) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('sort', sortValue);
    window.location.href = currentUrl.toString();
}

function openEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openAvatarModal() {
    const modal = document.getElementById('avatarModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeAvatarModal() {
    const modal = document.getElementById('avatarModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function showCreateModal() {
    console.log('showCreateModal called');
    const modal = document.getElementById('createModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeCreateModal() {
    console.log('closeCreateModal called');
    const modal = document.getElementById('createModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

const pinData = {};

function addComment() {
    console.log('addComment called');
    const pinId = document.getElementById('modalLikeButton').getAttribute('data-pin-id');
    const commentInput = document.getElementById('modalCommentInput');
    const commentText = commentInput.value.trim();

    if (commentText) {
        pinData[pinId].comments.push(commentText);
        const commentList = document.getElementById('modalCommentList');
        const li = document.createElement('li');
        li.textContent = commentText;
        commentList.appendChild(li);

        const commentCountSpan = document.querySelector(`.comment-count[data-pin-id="${pinId}"] span`);
        if (commentCountSpan) {
            commentCountSpan.textContent = pinData[pinId].comments.length;
        }

        commentInput.value = '';
    }
}

let pinIdToDelete = null;
let collectionIdToDelete = null;

function openDeleteModal(type, id, event) {
    console.log('openDeleteModal started:', { type, id });
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }

    if (type !== 'pin') {
        console.error('Invalid delete type:', type);
        alert('Error: Invalid delete type');
        return;
    }

    pinIdToDelete = id;
    const modal = document.getElementById('deleteModal');
    if (!modal) {
        console.error('deleteModal not found in DOM');
        alert('Error: deleteModal not found');
        return;
    }

    modal.style.display = 'flex';
    document.getElementById('deleteModalTitle').textContent = `Delete ${type.charAt(0).toUpperCase() + type.slice(1)}`;
    document.getElementById('deleteModalText').textContent = `Do you really want to delete this ${type}? This action cannot be undone.`;
    document.querySelector('.delete-modal-confirm').setAttribute('data-type', type);
    document.querySelector('.delete-modal-confirm').setAttribute('data-id', id);
}

function openDeleteCollectionModal(id, event) {
    console.log('openDeleteCollectionModal started:', { id });
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }

    collectionIdToDelete = id;
    const modal = document.getElementById('deleteCollectionModal');
    if (!modal) {
        console.error('deleteCollectionModal not found in DOM');
        alert('Error: deleteCollectionModal not found');
        return;
    }

    modal.style.display = 'flex';
    document.querySelector('.delete-modal-confirm').setAttribute('data-collection-id', id);
    document.getElementById('deleteCollectionModalTitle').textContent = 'Delete Collection';
    document.getElementById('deleteCollectionModalText').textContent = 'Do you really want to delete this collection? This action cannot be undone.';
}

function closeDeleteModal() {
    console.log('closeDeleteModal called');
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'none';
    }
    pinIdToDelete = null;
}

function closeDeleteCollectionModal() {
    console.log('closeDeleteCollectionModal called');
    const modal = document.getElementById('deleteCollectionModal');
    if (modal) {
        modal.style.display = 'none';
    }
    collectionIdToDelete = null;
}

function confirmDelete() {
    console.log('confirmDelete called:', { pinIdToDelete });
    if (!pinIdToDelete) {
        console.error('No pin ID to delete');
        alert('Error: No pin ID to delete');
        return;
    }

    const url = '../includes/deletePin.inc.php';
    const data = 'pin_id=' + encodeURIComponent(pinIdToDelete);
    const selector = `.pin-item[data-pin-id="${pinIdToDelete}"]`;

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data
    })
    .then(response => {
        console.log('Fetch response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Fetch response data:', data);
        if (data.success) {
            const element = document.querySelector(selector);
            if (element) {
                element.remove();
                closeDeleteModal();
            } else {
                console.error('Element not found for selector:', selector);
                alert('Error: Element not found in DOM');
            }
        } else {
            alert('Error deleting pin: ' + (data.error || 'Unknown error'));
        }
    });
}

function confirmDeleteCollection() {
    console.log('confirmDeleteCollection called:', { collectionIdToDelete });
    if (!collectionIdToDelete) {
        console.error('No collection ID to delete');
        alert('Error: No collection ID to delete');
        return;
    }

    const url = '../includes/deleteCollection.inc.php';
    const data = 'collection_id=' + encodeURIComponent(collectionIdToDelete);
    const selector = `.pin-item[data-collection-id="${collectionIdToDelete}"]`;

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data
    })
    .then(response => {
        console.log('Fetch response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Fetch response data:', data);
        if (data.success) {
            const element = document.querySelector(selector);
            if (element) {
                element.remove();
                closeDeleteCollectionModal();
            } else {
                console.error('Element not found for selector:', selector);
                alert('Error: Element not found in DOM');
            }
        } else {
            alert('Error deleting collection: ' + (data.error || 'Unknown error'));
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing modals');
    const deleteModal = document.getElementById('deleteModal');
    const deleteCollectionModal = document.getElementById('deleteCollectionModal');
    const createModal = document.getElementById('createModal');
    const pinModal = document.getElementById('pinModal');
    const editModal = document.getElementById('editModal');
    const avatarModal = document.getElementById('avatarModal');

    if (deleteModal) {
        deleteModal.style.display = 'none';
        console.log('deleteModal found and hidden');
    } else {
        console.error('deleteModal not found in DOM');
    }
    if (deleteCollectionModal) {
        deleteCollectionModal.style.display = 'none';
        console.log('deleteCollectionModal found and hidden');
    } else {
        console.error('deleteCollectionModal not found in DOM');
    }
    if (createModal) createModal.style.display = 'none';
    if (pinModal) pinModal.style.display = 'none';
    if (editModal) editModal.style.display = 'none';
    if (avatarModal) avatarModal.style.display = 'none';

    if (window.location.hash === '#pinModal' && pinModal) {
        pinModal.style.display = 'flex';
    }

    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            button.classList.add('active');
            document.getElementById(button.getAttribute('data-tab')).classList.add('active');
        });
    });

    document.querySelector('#pinModal .close-button')?.addEventListener('click', () => {
        console.log('pinModal close button clicked');
        closePinModal();
    });

    document.getElementById('modalLikeButton')?.addEventListener('click', () => {
        const pinId = document.getElementById('modalLikeButton').getAttribute('data-pin-id');
        pinData[pinId].likes += 1;
        document.getElementById('modalLikeCount').textContent = pinData[pinId].likes;

        const likeCountSpan = document.querySelector(`.like-count[data-pin-id="${pinId}"] span`);
        if (likeCountSpan) {
            likeCountSpan.textContent = pinData[pinId].likes;
        }
    });
});

document.addEventListener('click', function(e) {
    const pinModal = document.getElementById('pinModal');
    const createModal = document.getElementById('createModal');
    const deleteModal = document.getElementById('deleteModal');
    const deleteCollectionModal = document.getElementById('deleteCollectionModal');
    const editModal = document.getElementById('editModal');
    const avatarModal = document.getElementById('avatarModal');

    if (e.target === pinModal) {
        closePinModal();
    }
    if (e.target === createModal) {
        closeCreateModal();
    }
    if (e.target === deleteModal) {
        closeDeleteModal();
    }
    if (e.target === deleteCollectionModal) {
        closeDeleteCollectionModal();
    }
    if (e.target === editModal) {
        closeEditModal();
    }
    if (e.target === avatarModal) {
        closeAvatarModal();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        console.log('Escape key pressed');
        closePinModal();
        closeCreateModal();
        closeDeleteModal();
        closeDeleteCollectionModal();
        closeEditModal();
        closeAvatarModal();
    }
});