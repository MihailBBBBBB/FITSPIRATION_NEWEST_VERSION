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

    const forms = document.querySelectorAll('#pinModal form input[name="pin_id"]');
    forms.forEach(formInput => {
        formInput.value = pinId;
    });

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
        const userImg = escapeHtml(comment.user_img || '../images/default_avatar.svg');
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
    if (modalCreatorAvatar) modalCreatorAvatar.src = pinData.creator_img || '../images/default_avatar.svg';

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

    document.querySelectorAll('#pinModal form input[name="pin_id"]').forEach(input => input.value = pinId);

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
            <img src="${escapeHtml(commentData.user_img || '../images/default_avatar.svg')}" alt="User">
            ${escapeHtml(commentData.username || 'You')}: ${escapeHtml(commentData.comment || '')}
            <button type="button" class="comment-delete-btn"
                data-comment-id="${escapeHtml(commentId)}"
                data-pin-id="${escapeHtml(pinId)}"
                onclick="deleteComment(${escapeHtml(commentId)}, '${escapeHtml(pinId)}')">Delete</button>
        `;
        modalCommentList.prepend(li);

        const pinItem = document.querySelector('.pin-item[data-pin-id="' + CSS.escape(pinId) + '"]');
        const commentCountBadge = pinItem ? pinItem.querySelector('.comment-count span:last-child') : null;
        if (commentCountBadge) {
            const currentCount = Number(commentCountBadge.textContent || '0');
            commentCountBadge.textContent = currentCount + 1;
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
    const pinItem = document.querySelector('.pin-item[data-pin-id="' + CSS.escape(String(pinId)) + '"]');
    const commentCountBadge = pinItem ? pinItem.querySelector('.comment-count span:last-child') : null;
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

        if (commentCountBadge) {
            const currentCount = Number(commentCountBadge.textContent || '0');
            commentCountBadge.textContent = Math.max(0, currentCount - 1);
        }

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

window.addEventListener('load', function() {
    const pinItems = document.querySelectorAll('.pin-item[data-pin-id]');
    pinItems.forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.delete-cross') || e.target.closest('.like-button') || e.target.closest('form')) return;

            const pinId = item.dataset.pinId || '';
            if (!pinId || isNaN(Number(pinId))) return;

            openPinModal(pinId, item);
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
                            // Try to recover JSON payload when PHP warnings/notices wrap output.
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
                        if (!data.success || !data.comment) {
                            throw new Error(data.message || 'Failed to add comment');
                        }

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
    const modal = document.getElementById('pinModal');
    const reportModal = document.getElementById('reportModal');
    if (e.target === modal) {
        closePinModal();
    }
    if (e.target === reportModal) {
        closeReportModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePinModal();
        closeReportModal();
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
    const titleKey = `Delete ${type.charAt(0).toUpperCase() + type.slice(1)}`;
    const textKey = `Do you really want to delete this ${type}? This action cannot be undone.`;
    document.getElementById('deleteModal').style.display = 'flex';
    document.getElementById('deleteModalTitle').setAttribute('data-translate', titleKey);
    document.getElementById('deleteModalTitle').textContent = window.translator && typeof window.translator.t === 'function'
        ? window.translator.t(titleKey)
        : titleKey;
    document.getElementById('deleteModalText').setAttribute('data-translate', textKey);
    document.getElementById('deleteModalText').textContent = window.translator && typeof window.translator.t === 'function'
        ? window.translator.t(textKey)
        : textKey;
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
    if (typeof appendCsrfToken === 'function') {
        appendCsrfToken(formData);
    }

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

function initCollaboratorLiveSearch() {
    const input = document.getElementById('inviteUsernameSearch');
    const searchResults = document.getElementById('inviteUserResults');
    if (!input || !searchResults) {
        return;
    }

    let searchDebounceTimer = null;

    const hideResults = () => {
        searchResults.innerHTML = '';
        searchResults.classList.remove('show');
    };

    const renderUserResults = users => {
        if (!Array.isArray(users) || users.length === 0) {
            searchResults.innerHTML = '<div class="search-result-item disabled">No users found</div>';
            searchResults.classList.add('show');
            return;
        }

        searchResults.innerHTML = users.map(user => {
            const safeUsername = escapeHtml(user.username || 'User');
            const safeUserId = Number(user.id || 0);
            const avatarSrc = String(user.img || '../images/default_avatar.svg');

            return `
                <button type="button" class="search-result-item" data-user-id="${safeUserId}" data-username="${safeUsername}">
                    <span class="search-user-row">
                        <img class="search-avatar" src="${avatarSrc}" alt="${safeUsername} avatar" onerror="this.src='../images/default_avatar.svg'">
                        <span class="username no-translate" data-user-content="true">${safeUsername}</span>
                    </span>
                </button>
            `;
        }).join('');

        searchResults.classList.add('show');
    };

    input.addEventListener('input', () => {
        const query = input.value.trim();
        window.clearTimeout(searchDebounceTimer);

        if (query.length < 2) {
            hideResults();
            return;
        }

        searchDebounceTimer = window.setTimeout(() => {
            fetch(`../includes/searchUsers.inc.php?search=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        hideResults();
                        return;
                    }

                    renderUserResults(data.users || []);
                })
                .catch(() => {
                    hideResults();
                });
        }, 150);
    });

    searchResults.addEventListener('click', event => {
        const button = event.target.closest('.search-result-item[data-username]');
        if (!button) {
            return;
        }

        const selectedUsername = button.getAttribute('data-username') || '';
        if (selectedUsername) {
            input.value = selectedUsername;
        }
        hideResults();
    });

    document.addEventListener('click', event => {
        if (!event.target.closest('.collab-invite-search')) {
            hideResults();
        }
    });
}

window.addEventListener('DOMContentLoaded', initCollaboratorLiveSearch);