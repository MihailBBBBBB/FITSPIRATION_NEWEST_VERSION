(function() {
    function parseJsonResponse(responseText) {
        try {
            return JSON.parse(responseText);
        } catch (error) {
            const successMarkerIndex = responseText.indexOf('{"success"');
            const jsonStart = successMarkerIndex !== -1 ? successMarkerIndex : responseText.indexOf('{');
            const jsonEnd = responseText.lastIndexOf('}');
            if (jsonStart !== -1 && jsonEnd > jsonStart) {
                return JSON.parse(responseText.slice(jsonStart, jsonEnd + 1));
            }
            throw error;
        }
    }

    function getProfileContainer() {
        return document.querySelector('.profile-container');
    }

    function getPinNodes(pinId) {
        return Array.from(document.querySelectorAll('.pin-item[data-pin-id]')).filter(node => node.dataset.pinId === String(pinId));
    }

    function updatePinDatasets(pinItem, pinId, likeCount, userLiked) {
        pinItem.dataset.pinId = String(pinId);
        pinItem.dataset.likeCount = String(likeCount);
        pinItem.dataset.userLiked = userLiked ? '1' : '0';

        const image = pinItem.querySelector('.pin-image');
        if (image) {
            image.dataset.pinId = String(pinId);
            image.dataset.likeCount = String(likeCount);
            image.dataset.userLiked = userLiked ? '1' : '0';
        }
    }

    function updatePinButtons(pinItem, likeCount, userLiked) {
        pinItem.querySelectorAll('.like-button').forEach(button => {
            button.classList.toggle('liked', userLiked);
            button.dataset.pinId = pinItem.dataset.pinId || '';

            const countNode = button.querySelector('.like-count');
            if (countNode) {
                countNode.textContent = String(likeCount);
            }
        });
    }

    function syncModalLikeState(pinId, likeCount, userLiked) {
        const modalLikeButton = document.getElementById('modalLikeButton');
        const modalLikeCount = document.getElementById('modalLikeCount');
        if (!modalLikeButton || String(modalLikeButton.dataset.pinId || '') !== String(pinId)) {
            return;
        }

        modalLikeButton.classList.toggle('liked', userLiked);
        modalLikeButton.dataset.pinId = String(pinId);
        if (modalLikeCount) {
            modalLikeCount.textContent = String(likeCount);
        }
    }

    function removeFromOwnLikedTab(pinId, userLiked) {
        if (userLiked) {
            return;
        }

        const profileContainer = getProfileContainer();
        if (!profileContainer || profileContainer.dataset.removeLikedOnUnlike !== '1') {
            return;
        }

        const likedTab = document.getElementById('liked');
        if (!likedTab) {
            return;
        }

        likedTab.querySelectorAll('.pin-item[data-pin-id]').forEach(item => {
            if (item.dataset.pinId === String(pinId)) {
                item.remove();
            }
        });
    }

    function syncLikeState(pinId, likeCount, userLiked) {
        getPinNodes(pinId).forEach(pinItem => {
            updatePinDatasets(pinItem, pinId, likeCount, userLiked);
            updatePinButtons(pinItem, likeCount, userLiked);
        });

        syncModalLikeState(pinId, likeCount, userLiked);
        removeFromOwnLikedTab(pinId, userLiked);

        document.dispatchEvent(new CustomEvent('fitspiration:like-updated', {
            detail: {
                pinId: String(pinId),
                likeCount,
                userLiked,
            },
        }));
    }

    function requestLikeToggle(form) {
        const pinInput = form.querySelector('input[name="pin_id"]');
        const button = form.querySelector('.like-button');
        const pinId = pinInput ? pinInput.value : button?.dataset.pinId;
        if (!pinId || !button) {
            return;
        }

        const formData = new FormData(form);
        formData.append('toggle_like', '1');
        formData.append('ajax', '1');

        const requestUrl = new URL(window.location.href);
        requestUrl.searchParams.set('ajax', '1');

        button.disabled = true;

        fetch(requestUrl.toString(), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData,
        })
            .then(async response => {
                const responseText = await response.text();
                const data = parseJsonResponse(responseText);
                if (!response.ok || !data || !data.success || !data.like) {
                    throw new Error(data?.message || 'Failed to toggle like');
                }
                return data.like;
            })
            .then(likeData => {
                syncLikeState(likeData.pin_id, Number(likeData.like_count || 0), Boolean(likeData.user_liked));
            })
            .catch(error => {
                console.error('Error toggling like:', error);
                alert('Could not update like. Please try again.');
            })
            .finally(() => {
                button.disabled = false;
            });
    }

    function bindLikeForms() {
        document.querySelectorAll('.like-toggle-form').forEach(form => {
            if (form.dataset.likeBound === '1') {
                return;
            }

            form.dataset.likeBound = '1';
            form.addEventListener('submit', event => {
                event.preventDefault();
                requestLikeToggle(form);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', bindLikeForms);
    window.FitspirationLikes = {
        bind: bindLikeForms,
        syncLikeState,
    };
})();