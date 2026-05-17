function fitPinModalToImageSize() {
    const modal = document.getElementById('pinModal');
    const modalLayout = modal ? modal.querySelector('.modal-layout') : null;
    const imagePane = modal ? modal.querySelector('.modal-image') : null;
    const detailsPane = modal ? modal.querySelector('.modal-details') : null;
    const image = document.getElementById('modalPinImage');
    const minDesktopModalHeight = 320;
    const minHeightForImageLockedLayout = 420;

    if (!modal || !image) {
        return;
    }

    const clearSizing = () => {
        if (modalLayout) {
            modalLayout.style.removeProperty('height');
            modalLayout.style.removeProperty('max-height');
        }
        if (imagePane) {
            imagePane.style.removeProperty('width');
            imagePane.style.removeProperty('height');
            imagePane.style.removeProperty('min-height');
        }
        if (detailsPane) {
            detailsPane.style.removeProperty('height');
            detailsPane.style.removeProperty('max-height');
        }
        modal.style.removeProperty('height');
        modal.style.removeProperty('max-height');
        modal.style.removeProperty('--pin-image-ratio');
    };

    if (window.innerWidth <= 900) {
        clearSizing();
        return;
    }

    const applyRenderedHeight = () => {
        const renderedHeight = Math.round(image.getBoundingClientRect().height || 0);
        const renderedWidth = Math.round(image.getBoundingClientRect().width || 0);
        if (renderedHeight <= 0 || renderedWidth <= 0) {
            return;
        }

        if (renderedHeight < minHeightForImageLockedLayout) {
            clearSizing();
            return;
        }

        const maxDesktopModalHeight = Math.floor(window.innerHeight * 0.86);
        const targetHeight = Math.min(maxDesktopModalHeight, Math.max(renderedHeight, minDesktopModalHeight));

        if (modalLayout) {
            modalLayout.style.height = `${targetHeight}px`;
            modalLayout.style.maxHeight = `${targetHeight}px`;
        }
        if (imagePane) {
            imagePane.style.minHeight = `${targetHeight}px`;
        }
        if (detailsPane) {
            detailsPane.style.height = `${targetHeight}px`;
            detailsPane.style.maxHeight = `${targetHeight}px`;
        }
        modal.style.setProperty('--pin-image-ratio', (renderedWidth / renderedHeight).toFixed(4));
    };

    clearSizing();

    if (image.complete && image.naturalWidth > 0) {
        requestAnimationFrame(applyRenderedHeight);
    } else {
        image.addEventListener('load', () => requestAnimationFrame(applyRenderedHeight), { once: true });
    }
}

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
    const outfitId = imageElement?.dataset.outfitId || pinElement.dataset.outfitId || '';

    const modalPinImage = document.getElementById('modalPinImage');
    const modalPinTitle = document.getElementById('modalPinTitle');
    const modalLikeButton = document.getElementById('modalLikeButton');
    const modalLikeCount = document.getElementById('modalLikeCount');
    const modalCreatorLink = document.getElementById('modalCreatorLink');
    const modalCreatorAvatar = document.getElementById('modalCreatorAvatar');
    const modalRemixBtn = document.getElementById('modalRemixBtn');
    const modalFindSimilarBtn = document.getElementById('modalFindSimilarBtn');

    if (modalPinImage) {
        modalPinImage.src = imageSrc;
    }
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

    if (modalRemixBtn) {
        if (outfitId) {
            modalRemixBtn.href = 'OutfitBuilder.php?remix_outfit_id=' + encodeURIComponent(String(outfitId));
            modalRemixBtn.classList.remove('hidden');
        } else {
            modalRemixBtn.classList.add('hidden');
            modalRemixBtn.href = '#';
        }
    }

    if (modalFindSimilarBtn) {
        modalFindSimilarBtn.href = 'Home.php?visual_pin_id=' + encodeURIComponent(String(pinId)) + '&content=all&search_scope=all';
    }

    const modal = document.getElementById('pinModal');
    if (modal) {
        modal.style.display = 'flex';
        fitPinModalToImageSize();
    }
}

function renderModalComments(comments, pinId) {
    const modalCommentList = document.getElementById('modalCommentList');
    if (!modalCommentList) return;

    modalCommentList.innerHTML = '';

    if (!Array.isArray(comments) || comments.length === 0) {
        const emptyRow = document.createElement('li');
        emptyRow.textContent = 'No comments yet.';
        modalCommentList.appendChild(emptyRow);
        return;
    }

    comments.forEach(comment => {
        const li = document.createElement('li');
        const commentId = String(comment.id || '0');
        const userImg = escapeHtml(comment.user_img || '../images/no_image.jpg');
        const username = escapeHtml(comment.username || 'Unknown');
        const commentText = escapeHtml(comment.comment || '');
        const deleteButton = comment.can_delete
            ? `<button type="button" class="comment-delete-btn" data-comment-id="${commentId}" data-pin-id="${escapeHtml(String(pinId))}" onclick="deleteComment(${commentId}, '${escapeHtml(String(pinId))}')">Delete</button>`
            : '';

        li.innerHTML = `
            <img src="${userImg}" alt="User">
            ${username}: ${commentText}
            ${deleteButton}
        `;
        modalCommentList.appendChild(li);
    });
}

function applyFetchedPinData(pinData, comments) {
    if (!pinData || !pinData.id) return;

    const pinId = String(pinData.id);
    const modalPinImage = document.getElementById('modalPinImage');
    const modalPinTitle = document.getElementById('modalPinTitle');
    const modalLikeButton = document.getElementById('modalLikeButton');
    const modalLikeCount = document.getElementById('modalLikeCount');
    const modalCreatorLink = document.getElementById('modalCreatorLink');
    const modalCreatorAvatar = document.getElementById('modalCreatorAvatar');
    const modalRemixBtn = document.getElementById('modalRemixBtn');
    const modalFindSimilarBtn = document.getElementById('modalFindSimilarBtn');
    const reportButton = document.querySelector('#pinModal .report-flag-btn');
    const reportPinIdInput = document.getElementById('reportPinId');

    if (modalPinImage) {
        modalPinImage.src = pinData.image || '../images/no_image.jpg';
        fitPinModalToImageSize();
    }
    if (modalPinTitle) modalPinTitle.textContent = pinData.title || 'Pin';
    if (modalLikeButton) {
        modalLikeButton.dataset.pinId = pinId;
        modalLikeButton.classList.toggle('liked', !!pinData.user_liked);
    }
    if (modalLikeCount) modalLikeCount.textContent = Number(pinData.like_count || 0);
    if (modalCreatorAvatar) modalCreatorAvatar.src = pinData.creator_img || '../images/no_image.jpg';

    if (modalCreatorLink) {
        if (pinData.creator_id) {
            modalCreatorLink.href = 'Profile.php?user_id=' + encodeURIComponent(String(pinData.creator_id));
            modalCreatorLink.textContent = pinData.creator_name || 'Unknown';
            modalCreatorLink.style.pointerEvents = 'auto';
            modalCreatorLink.style.color = '#1d4ed8';
        } else {
            modalCreatorLink.href = '#';
            modalCreatorLink.textContent = pinData.creator_name || 'Unknown';
            modalCreatorLink.style.pointerEvents = 'none';
            modalCreatorLink.style.color = '#6b7280';
        }
    }

    document.querySelectorAll('#pinModal input[name="pin_id"]').forEach(input => input.value = pinId);

    if (modalRemixBtn) {
        if (pinData.outfit_post_id) {
            modalRemixBtn.href = 'OutfitBuilder.php?remix_outfit_id=' + encodeURIComponent(String(pinData.outfit_post_id));
            modalRemixBtn.classList.remove('hidden');
        } else {
            modalRemixBtn.href = '#';
            modalRemixBtn.classList.add('hidden');
        }
    }

    if (modalFindSimilarBtn) {
        modalFindSimilarBtn.href = 'Home.php?visual_pin_id=' + encodeURIComponent(pinId) + '&content=all&search_scope=all';
    }

    if (reportButton) {
        reportButton.setAttribute('onclick', `openReportModal('pin', '${pinId}', '${pinId}')`);
    }
    if (reportPinIdInput) {
        reportPinIdInput.value = pinId;
    }

    renderModalComments(comments, pinId);
}

async function fetchPinModalData(pinId) {
    const url = new URL(window.location.href);
    url.searchParams.set('ajax', '1');
    url.searchParams.set('action', 'get_pin_modal');
    url.searchParams.set('pin_id', String(pinId));

    const response = await fetch(url.toString(), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    });

    if (!response.ok) {
        throw new Error('Could not load pin data');
    }

    const data = await response.json();
    if (!data || !data.success) {
        throw new Error(data?.message || 'Could not load pin data');
    }

    return data;
}

async function openPinModal(pinId, pinElement) {
    if (!pinId) return;

    if (pinElement) {
        populatePinModal(pinElement);
    } else {
        const modal = document.getElementById('pinModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    try {
        const data = await fetchPinModalData(pinId);
        applyFetchedPinData(data.pin, data.comments || []);

        const url = new URL(window.location.href);
        url.searchParams.set('pin_id', String(pinId));
        url.hash = 'pinModal';
        window.history.replaceState({}, document.title, url.toString());
    } catch (error) {
        console.error('Error opening pin modal:', error);
    }
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

window.addEventListener('resize', () => {
    const modal = document.getElementById('pinModal');
    if (modal && modal.style.display === 'flex') {
        fitPinModalToImageSize();
    }
});

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
    const modal = document.getElementById('createModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeCreateModal() {
    const modal = document.getElementById('createModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function appendCommentToModal(commentData) {
    const modalCommentList = document.getElementById('modalCommentList');
    if (!modalCommentList) {
        return;
    }

    if (modalCommentList.children.length === 1 && modalCommentList.children[0].textContent.trim() === 'No comments yet.') {
        modalCommentList.innerHTML = '';
    }

    const li = document.createElement('li');
    const pinId = String(commentData.pin_id || '');
    const commentId = String(commentData.id || '0');
    li.innerHTML = `
        <img src="${escapeHtml(commentData.user_img || '../images/no_image.jpg')}" alt="User">
        ${escapeHtml(commentData.username || 'You')}: ${escapeHtml(commentData.comment || '')}
        <button type="button" class="comment-delete-btn"
            data-comment-id="${escapeHtml(commentId)}"
            data-pin-id="${escapeHtml(pinId)}"
            onclick="deleteComment(${escapeHtml(commentId)}, '${escapeHtml(pinId)}')">Delete</button>
    `;
    modalCommentList.prepend(li);

    document.querySelectorAll('.comment-count[data-pin-id="' + CSS.escape(pinId) + '"]').forEach(commentCount => {
        const badge = commentCount.querySelector('span:last-child');
        if (!badge) return;
        const currentCount = Number(badge.textContent || '0');
        badge.textContent = currentCount + 1;
    });
}

function openReportModal(targetType, targetId, pinId) {
    const reportModal = document.getElementById('reportModal');
    const targetTypeInput = document.getElementById('reportTargetType');
    const targetIdInput = document.getElementById('reportTargetId');
    const pinIdInput = document.getElementById('reportPinId');
    const subtitle = document.getElementById('reportModalSubtitle');

    if (!reportModal || !targetTypeInput || !targetIdInput || !pinIdInput) {
        return;
    }

    targetTypeInput.value = targetType;
    targetIdInput.value = targetId;
    pinIdInput.value = pinId;

    if (subtitle) {
        subtitle.textContent = targetType === 'comment'
            ? 'Reporting this comment. Tell us what happened.'
            : 'Reporting this pin. Tell us what happened.';
    }

    reportModal.style.display = 'flex';
    reportModal.setAttribute('aria-hidden', 'false');
}

function closeReportModal() {
    const reportModal = document.getElementById('reportModal');
    if (reportModal) {
        reportModal.style.display = 'none';
        reportModal.setAttribute('aria-hidden', 'true');
    }
}

function deleteComment(commentId, pinId) {
    if (!confirm('Are you sure you want to delete this comment?')) return;

    const deleteButton = document.querySelector('.comment-delete-btn[data-comment-id="' + CSS.escape(String(commentId)) + '"]');
    const commentRow = deleteButton ? deleteButton.closest('li') : null;
    const modalCommentList = document.getElementById('modalCommentList');

    const formData = new FormData();
    formData.append('delete_comment', '1');
    formData.append('comment_id', commentId);
    formData.append('pin_id', pinId);
    formData.append('ajax', '1');
    if (typeof appendCsrfToken === 'function') {
        appendCsrfToken(formData);
    }

    const url = new URL(window.location);
    url.searchParams.set('pin_id', pinId);

    fetch(url.toString(), {
        method: 'POST',
        headers: typeof getCsrfHeaders === 'function' ? getCsrfHeaders({
            'X-Requested-With': 'XMLHttpRequest'
        }) : {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
        .then(async response => {
            if (!response.ok) {
                throw new Error('Delete request failed');
            }

            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Server reported an error while deleting');
                }
                return data;
            }

            const redirectedUrl = response.url || '';
            if (redirectedUrl.includes('error=')) {
                throw new Error('Server reported an error while deleting');
            }

            await response.text();
            return { success: true };
        })
        .then(() => {
            if (commentRow) {
                commentRow.remove();
            }

            document.querySelectorAll('.comment-count[data-pin-id="' + CSS.escape(String(pinId)) + '"]').forEach(commentCount => {
                const badge = commentCount.querySelector('span:last-child');
                if (!badge) return;
                const currentCount = Number(badge.textContent || '0');
                badge.textContent = Math.max(0, currentCount - 1);
            });

            if (modalCommentList && modalCommentList.children.length === 0) {
                const emptyRow = document.createElement('li');
                emptyRow.textContent = 'No comments yet.';
                modalCommentList.appendChild(emptyRow);
            }
        })
        .catch(error => {
            console.error('Error deleting comment:', error);
            alert('Could not delete comment. Please try again.');
        });
}

let pinIdToDelete = null;
let collectionIdToDelete = null;

function setTranslatedText(elementId, translationKey) {
    const element = document.getElementById(elementId);
    if (!element) {
        return;
    }

    element.setAttribute('data-translate', translationKey);
    element.textContent = window.translator && typeof window.translator.t === 'function'
        ? window.translator.t(translationKey)
        : translationKey;
}

function openDeleteModal(type, id, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }

    if (type !== 'pin') {
        return;
    }

    pinIdToDelete = id;
    const modal = document.getElementById('deleteModal');
    if (!modal) {
        return;
    }

    modal.style.display = 'flex';
    setTranslatedText('deleteModalTitle', 'Delete Pin');
    setTranslatedText('deleteModalText', 'Do you really want to delete this pin? This action cannot be undone.');
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'none';
    }
    pinIdToDelete = null;
}

function confirmDelete() {
    if (!pinIdToDelete) {
        return;
    }

    const deletedPinId = pinIdToDelete;
    const selector = '.pin-item[data-pin-id="' + CSS.escape(String(deletedPinId)) + '"]';
    const requestBody = new URLSearchParams({
        pin_id: String(deletedPinId)
    });
    if (typeof getCsrfToken === 'function') {
        const csrfToken = getCsrfToken();
        if (csrfToken) {
            requestBody.set('csrf_token', csrfToken);
        }
    }

    fetch('../includes/deletePin.inc.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: requestBody.toString()
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Unknown error');
            }

            const element = document.querySelector(selector);
            if (element) {
                element.remove();
            }

            closeDeleteModal();

            const url = new URL(window.location);
            if (url.searchParams.get('pin_id') === String(deletedPinId)) {
                closePinModal();
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error deleting pin:', error);
            alert('Error deleting pin. Please try again.');
        });
}

function openDeleteCollectionModal(id, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }

    collectionIdToDelete = id;
    const modal = document.getElementById('deleteCollectionModal');
    if (!modal) {
        return;
    }

    modal.style.display = 'flex';
    setTranslatedText('deleteCollectionModalTitle', 'Delete Collection');
    setTranslatedText('deleteCollectionModalText', 'Do you really want to delete this collection? This action cannot be undone.');
}

function closeDeleteCollectionModal() {
    const modal = document.getElementById('deleteCollectionModal');
    if (modal) {
        modal.style.display = 'none';
    }
    collectionIdToDelete = null;
}

function confirmDeleteCollection() {
    if (!collectionIdToDelete) {
        return;
    }

    const deletedCollectionId = collectionIdToDelete;
    const selector = '.pin-item[data-collection-id="' + CSS.escape(String(deletedCollectionId)) + '"]';
    const requestBody = new URLSearchParams({
        collection_id: String(deletedCollectionId)
    });
    if (typeof getCsrfToken === 'function') {
        const csrfToken = getCsrfToken();
        if (csrfToken) {
            requestBody.set('csrf_token', csrfToken);
        }
    }

    fetch('../includes/deleteCollection.inc.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: requestBody.toString()
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Unknown error');
            }

            const element = document.querySelector(selector);
            if (element) {
                element.remove();
            }

            closeDeleteCollectionModal();
        })
        .catch(error => {
            console.error('Error deleting collection:', error);
            alert('Error deleting collection. Please try again.');
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const pinModalRoot = document.getElementById('pinModal');
    if (pinModalRoot && pinModalRoot.parentElement !== document.body) {
        document.body.appendChild(pinModalRoot);
    }

    document.addEventListener('click', function(e) {
        const pinItem = e.target.closest('.pin-item[data-pin-id]');
        if (!pinItem) return;

        if (
            e.target.closest('.delete-cross') ||
            e.target.closest('.like-button') ||
            e.target.closest('.like-toggle-form') ||
            e.target.closest('.collection-link') ||
            e.target.closest('.outfit-edit-link') ||
            e.target.closest('.outfit-edit-cta')
        ) {
            return;
        }

        const pinId = pinItem.dataset.pinId || '';
        if (!pinId || isNaN(Number(pinId))) return;
        openPinModal(pinId, pinItem);
    });

    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            button.classList.add('active');
            document.getElementById(button.getAttribute('data-tab')).classList.add('active');
        });
    });

    const pinId = new URLSearchParams(window.location.search).get('pin_id');
    if (pinId) {
        const selectedPin = document.querySelector('.pin-item[data-pin-id="' + CSS.escape(pinId) + '"]');
        openPinModal(pinId, selectedPin || null);
    }

    const commentForm = document.getElementById('modalCommentForm');
    const commentInput = document.getElementById('modalCommentInput');
    if (commentForm && commentInput) {
        commentForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const commentText = commentInput.value.trim();
            if (!commentText) {
                return;
            }

            const submitButton = commentForm.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            const formData = new FormData(commentForm);
            formData.append('add_comment', '1');
            formData.append('ajax', '1');
            if (typeof appendCsrfToken === 'function') {
                appendCsrfToken(formData);
            }

            const submitUrl = new URL(window.location.href);
            const activePinId = formData.get('pin_id') || new URLSearchParams(window.location.search).get('pin_id') || '';
            if (activePinId) {
                submitUrl.searchParams.set('pin_id', activePinId);
            }
            submitUrl.searchParams.set('ajax', '1');

            fetch(submitUrl.toString(), {
                method: 'POST',
                headers: typeof getCsrfHeaders === 'function' ? getCsrfHeaders({
                    'X-Requested-With': 'XMLHttpRequest'
                }) : {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(async response => {
                    const responseText = await response.text();
                    let data = null;

                    try {
                        data = JSON.parse(responseText);
                    } catch (e) {
                        const successMarkerIndex = responseText.indexOf('{"success"');
                        const jsonStart = successMarkerIndex !== -1 ? successMarkerIndex : responseText.indexOf('{');
                        const jsonEnd = responseText.lastIndexOf('}');
                        if (jsonStart !== -1 && jsonEnd > jsonStart) {
                            const candidateJson = responseText.slice(jsonStart, jsonEnd + 1);
                            try {
                                data = JSON.parse(candidateJson);
                            } catch (innerError) {
                                throw new Error('Invalid server response');
                            }
                        } else {
                            throw new Error('Invalid server response');
                        }
                    }

                    if (!response.ok || !data || !data.success || !data.comment) {
                        throw new Error(data?.message || 'Failed to add comment');
                    }

                    return data;
                })
                .then(data => {
                    appendCommentToModal(data.comment);
                    commentInput.value = '';
                })
                .catch(error => {
                    console.error('Error adding comment:', error);
                    alert('Could not add comment. Please try again.');
                })
                .finally(() => {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
        });
    }
});

document.addEventListener('click', function(e) {
    const pinModal = document.getElementById('pinModal');
    const reportModal = document.getElementById('reportModal');
    const createModal = document.getElementById('createModal');
    const deleteModal = document.getElementById('deleteModal');
    const deleteCollectionModal = document.getElementById('deleteCollectionModal');
    const editModal = document.getElementById('editModal');
    const avatarModal = document.getElementById('avatarModal');
    const followListModal = document.getElementById('followListModal');

    if (e.target === pinModal) {
        closePinModal();
    }
    if (e.target === reportModal) {
        closeReportModal();
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
    if (e.target === followListModal && typeof closeFollowListModal === 'function') {
        closeFollowListModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePinModal();
        closeReportModal();
        closeCreateModal();
        closeDeleteModal();
        closeDeleteCollectionModal();
        closeEditModal();
        closeAvatarModal();
        if (typeof closeFollowListModal === 'function') {
            closeFollowListModal();
        }
    }
});