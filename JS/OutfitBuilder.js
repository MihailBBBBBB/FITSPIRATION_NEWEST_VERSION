(function() {
    function t(str) {
        return (window.translator && window.translator.t) ? window.translator.t(str) : str;
    }

    var wardrobeUpload = document.getElementById('wardrobeUpload');
    var wardrobeList = document.getElementById('wardrobeList');
    var outfitCanvas = document.getElementById('outfitCanvas');
    var builderStage = document.getElementById('builderStage');
    var statusMessage = document.getElementById('statusMessage');

    var selectedLabel = document.getElementById('selectedLabel');
    var itemCategorySelect = document.getElementById('itemCategorySelect');
    var itemSlotSelect = document.getElementById('itemSlotSelect');
    var snapItemBtn = document.getElementById('snapItemBtn');
    var fitToSlotBtn = document.getElementById('fitToSlotBtn');
    var scaleRange = document.getElementById('scaleRange');
    var rotateRange = document.getElementById('rotateRange');

    var removeBgBtn = document.getElementById('removeBgBtn');
    var bringFrontBtn = document.getElementById('bringFrontBtn');
    var sendBackBtn = document.getElementById('sendBackBtn');
    var deleteItemBtn = document.getElementById('deleteItemBtn');
    var clearOutfitBtn = document.getElementById('clearOutfitBtn');
    var downloadBtn = document.getElementById('downloadBtn');
    var saveOutfitBtn = document.getElementById('saveOutfitBtn');
    var saveAsNewBtn = document.getElementById('saveAsNewBtn');
    var outfitNameInput = document.getElementById('outfitNameInput');
    var publishOutfitToggle = document.getElementById('publishOutfitToggle');
    var selectedMetaSummary = document.getElementById('selectedMetaSummary');
    var suggestOutfitBtn = document.getElementById('suggestOutfitBtn');
    var matchWhyList = document.getElementById('matchWhyList');
    var initialStateElement = document.getElementById('builderInitialState');
    var draftStateLabel = document.getElementById('draftStateLabel');
    var editModeBadge = document.getElementById('editModeBadge');
    var remixModeBadge = document.getElementById('remixModeBadge');
    var nudgeButtons = Array.prototype.slice.call(document.querySelectorAll('[data-nudge]'));
    var layerOrderRows = Array.prototype.slice.call(document.querySelectorAll('.layer-order-row'));

    if (!wardrobeUpload || !wardrobeList || !outfitCanvas || !builderStage) {
        return;
    }

    var initialBuilderConfig = {
        mode: 'create',
        outfitId: null,
        name: '',
        builderState: null,
        loadError: ''
    };

    if (initialStateElement) {
        try {
            initialBuilderConfig = Object.assign(initialBuilderConfig, JSON.parse(initialStateElement.textContent || '{}'));
        } catch (error) {
            initialBuilderConfig.loadError = 'Could not read the saved outfit data.';
        }
    }

    var CATEGORY_OPTIONS = [
        { value: 'head', label: 'Headwear' },
        { value: 'top', label: 'Top' },
        { value: 'outerwear', label: 'Outerwear' },
        { value: 'bottoms', label: 'Bottoms' },
        { value: 'shoes', label: 'Shoes' },
        { value: 'accessory', label: 'Accessory' },
        { value: 'bag', label: 'Bag' }
    ];

    var CATEGORY_TO_SLOTS = {
        head: ['head'],
        top: ['top'],
        outerwear: ['outerwear', 'top'],
        bottoms: ['bottoms'],
        shoes: ['shoes'],
        accessory: ['accessory_front', 'accessory_back'],
        bag: ['accessory_back', 'accessory_front']
    };

    var SLOT_PRIORITY = ['top', 'bottoms', 'shoes', 'outerwear', 'head', 'accessory_front'];
    var NEUTRAL_COLORS = ['black', 'white', 'gray', 'beige', 'brown', 'navy', 'denim'];
    var COMPLEMENTARY_COLORS = {
        blue: ['white', 'gray', 'beige', 'brown', 'denim'],
        red: ['black', 'white', 'gray', 'denim'],
        green: ['black', 'white', 'beige', 'brown'],
        yellow: ['black', 'white', 'navy', 'brown'],
        purple: ['white', 'black', 'gray'],
        pink: ['white', 'gray', 'denim', 'black'],
        orange: ['black', 'white', 'navy', 'beige']
    };

    var MOBILE_SLOT_LABELS = {
        head: 'Head',
        top: 'Top',
        outerwear: 'Outer',
        bottoms: 'Bottom',
        shoes: 'Shoes',
        accessory_back: 'Back',
        accessory_front: 'Front'
    };

    var SLOT_DEFS = [
        {
            id: 'head',
            label: 'Head Slot',
            dockLeft: 0.045,
            dockTop: 0.065,
            dockWidth: 0.18,
            dockHeight: 0.12,
            fitLeft: 0.405,
            fitTop: 0.055,
            fitWidth: 0.19,
            fitHeight: 0.13,
            defaultZ: 55
        },
        {
            id: 'top',
            label: 'Base Top',
            dockLeft: 0.045,
            dockTop: 0.225,
            dockWidth: 0.18,
            dockHeight: 0.14,
            fitLeft: 0.355,
            fitTop: 0.185,
            fitWidth: 0.29,
            fitHeight: 0.2,
            defaultZ: 34
        },
        {
            id: 'outerwear',
            label: 'Outer Layer',
            dockLeft: 0.045,
            dockTop: 0.405,
            dockWidth: 0.18,
            dockHeight: 0.14,
            fitLeft: 0.325,
            fitTop: 0.17,
            fitWidth: 0.35,
            fitHeight: 0.24,
            defaultZ: 42
        },
        {
            id: 'bottoms',
            label: 'Bottom Layer',
            dockLeft: 0.775,
            dockTop: 0.235,
            dockWidth: 0.18,
            dockHeight: 0.14,
            fitLeft: 0.385,
            fitTop: 0.44,
            fitWidth: 0.23,
            fitHeight: 0.25,
            defaultZ: 28
        },
        {
            id: 'shoes',
            label: 'Footwear',
            dockLeft: 0.775,
            dockTop: 0.415,
            dockWidth: 0.18,
            dockHeight: 0.12,
            fitLeft: 0.39,
            fitTop: 0.765,
            fitWidth: 0.22,
            fitHeight: 0.1,
            defaultZ: 18
        },
        {
            id: 'accessory_back',
            label: 'Back Accessory',
            dockLeft: 0.775,
            dockTop: 0.595,
            dockWidth: 0.18,
            dockHeight: 0.12,
            fitLeft: 0.305,
            fitTop: 0.18,
            fitWidth: 0.38,
            fitHeight: 0.34,
            defaultZ: 8
        },
        {
            id: 'accessory_front',
            label: 'Front Accessory',
            dockLeft: 0.775,
            dockTop: 0.745,
            dockWidth: 0.18,
            dockHeight: 0.12,
            fitLeft: 0.315,
            fitTop: 0.18,
            fitWidth: 0.37,
            fitHeight: 0.34,
            defaultZ: 60
        }
    ];

    var wardrobeItems = [];
    var outfitItems = [];
    var selectedId = null;
    var lastZ = 100;
    var nextWardrobeId = 1;
    var nextOutfitId = 1;
    var slotLayer = document.createElement('div');
    var slotElements = {};
    var draggedWardrobeItem = null;
    var focusedSlotId = null;
    var currentOutfitId = initialBuilderConfig.outfitId ? parseInt(initialBuilderConfig.outfitId, 10) : null;
    var isEditMode = initialBuilderConfig.mode === 'edit' && !!currentOutfitId;
    var remixSource = initialBuilderConfig.remixSource || null;
    var hasUnsavedChanges = false;
    var suppressDirtyTracking = false;
    var autosaveTimer = null;
    var lastDraftSavedAt = 0;

    slotLayer.className = 'slot-layer';
    builderStage.insertBefore(slotLayer, outfitCanvas);

    function setStatus(text) {
        statusMessage.textContent = text || '';
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function getDraftStorageKey(outfitIdOverride) {
        var effectiveId = typeof outfitIdOverride === 'number' ? outfitIdOverride : currentOutfitId;
        return 'fitspiration_builder_draft_' + (effectiveId ? String(effectiveId) : 'new');
    }

    function setDraftLabel(text, tone) {
        if (!draftStateLabel) {
            return;
        }

        draftStateLabel.textContent = text;
        draftStateLabel.classList.remove('neutral', 'warning', 'success');
        draftStateLabel.classList.add(tone || 'neutral');
    }

    function setDirtyState(isDirty, options) {
        options = options || {};
        hasUnsavedChanges = !!isDirty;

        if (hasUnsavedChanges) {
            setDraftLabel(options.text || t('Unsaved changes'), 'warning');
        } else if (options.savedDraftAt) {
            setDraftLabel(t('Draft autosaved'), 'success');
        } else {
            setDraftLabel(t('Draft clean'), 'neutral');
        }
    }

    function serializeDraftPayload() {
        return {
            outfitId: currentOutfitId,
            name: (outfitNameInput.value || '').trim(),
            builderState: serializeOutfitState(),
            savedAt: Date.now()
        };
    }

    function saveDraftNow() {
        if (suppressDirtyTracking) {
            return;
        }

        try {
            if (!outfitItems.length) {
                window.localStorage.removeItem(getDraftStorageKey());
                return;
            }

            var payload = serializeDraftPayload();
            window.localStorage.setItem(getDraftStorageKey(), JSON.stringify(payload));
            lastDraftSavedAt = payload.savedAt;
            setDirtyState(hasUnsavedChanges, { savedDraftAt: payload.savedAt, text: hasUnsavedChanges ? t('Unsaved changes') : t('Draft clean') });
        } catch (error) {
            setDraftLabel(t('Draft storage unavailable'), 'warning');
        }
    }

    function scheduleDraftSave() {
        if (suppressDirtyTracking) {
            return;
        }

        if (autosaveTimer) {
            window.clearTimeout(autosaveTimer);
        }

        autosaveTimer = window.setTimeout(function() {
            saveDraftNow();
        }, 700);
    }

    function clearDraft(outfitIdOverride) {
        try {
            window.localStorage.removeItem(getDraftStorageKey(outfitIdOverride));
        } catch (error) {
        }
    }

    function loadStoredDraft() {
        try {
            var raw = window.localStorage.getItem(getDraftStorageKey());
            if (!raw) {
                return null;
            }

            var parsed = JSON.parse(raw);
            if (!parsed || !parsed.builderState || !Array.isArray(parsed.builderState.items)) {
                return null;
            }

            return parsed;
        } catch (error) {
            return null;
        }
    }

    function markDirty() {
        if (suppressDirtyTracking) {
            return;
        }

        setDirtyState(true);
        scheduleDraftSave();
    }

    function updateSaveButtonState() {
        if (!saveOutfitBtn) {
            return;
        }

        saveOutfitBtn.textContent = isEditMode ? t('Save Changes') : t('Save to Profile');

        if (saveAsNewBtn) {
            saveAsNewBtn.classList.toggle('hidden', !isEditMode);
        }

        if (editModeBadge) {
            editModeBadge.classList.toggle('hidden', !isEditMode);
        }

        if (remixModeBadge) {
            remixModeBadge.classList.toggle('hidden', !remixSource);
        }
    }

    function syncOutfitUrl(outfitId) {
        if (!outfitId || !window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        var url = new URL(window.location.href);
        url.searchParams.set('outfit_id', String(outfitId));
        window.history.replaceState({}, '', url.toString());
    }

    function clearOutfitUrl() {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        var url = new URL(window.location.href);
        url.searchParams.delete('outfit_id');
        window.history.replaceState({}, '', url.toString());
    }

    function getStageSize() {
        return {
            width: outfitCanvas.clientWidth,
            height: outfitCanvas.clientHeight
        };
    }

    function getSlotDef(slotId) {
        for (var i = 0; i < SLOT_DEFS.length; i++) {
            if (SLOT_DEFS[i].id === slotId) {
                return SLOT_DEFS[i];
            }
        }
        return null;
    }

    function isCompactBuilderView() {
        return window.matchMedia && window.matchMedia('(max-width: 640px)').matches;
    }

    function getDockMetrics(slot) {
        var metrics = {
            dockLeft: slot.dockLeft,
            dockTop: slot.dockTop,
            dockWidth: slot.dockWidth,
            dockHeight: slot.dockHeight
        };

        if (!isCompactBuilderView()) {
            return metrics;
        }

        var mobileDockMap = {
            head: { dockLeft: 0.06, dockTop: 0.085, dockWidth: 0.18, dockHeight: 0.105 },
            top: { dockLeft: 0.06, dockTop: 0.24, dockWidth: 0.18, dockHeight: 0.13 },
            outerwear: { dockLeft: 0.06, dockTop: 0.42, dockWidth: 0.18, dockHeight: 0.13 },
            bottoms: { dockLeft: 0.76, dockTop: 0.24, dockWidth: 0.18, dockHeight: 0.13 },
            shoes: { dockLeft: 0.76, dockTop: 0.42, dockWidth: 0.18, dockHeight: 0.105 },
            accessory_back: { dockLeft: 0.76, dockTop: 0.59, dockWidth: 0.18, dockHeight: 0.105 },
            accessory_front: { dockLeft: 0.76, dockTop: 0.735, dockWidth: 0.18, dockHeight: 0.105 }
        };

        return mobileDockMap[slot.id] || metrics;
    }

    function getPlacementRect(slotId) {
        var slot = getSlotDef(slotId);
        if (!slot) {
            return null;
        }

        var stageSize = getStageSize();
        return {
            left: Math.round(stageSize.width * slot.fitLeft),
            top: Math.round(stageSize.height * slot.fitTop),
            width: Math.round(stageSize.width * slot.fitWidth),
            height: Math.round(stageSize.height * slot.fitHeight)
        };
    }

    function getDockRect(slotId) {
        var slot = getSlotDef(slotId);
        if (!slot) {
            return null;
        }

        var dockMetrics = getDockMetrics(slot);

        var stageSize = getStageSize();
        return {
            left: Math.round(stageSize.width * dockMetrics.dockLeft),
            top: Math.round(stageSize.height * dockMetrics.dockTop),
            width: Math.round(stageSize.width * dockMetrics.dockWidth),
            height: Math.round(stageSize.height * dockMetrics.dockHeight)
        };
    }

    function categoryLabel(category) {
        for (var i = 0; i < CATEGORY_OPTIONS.length; i++) {
            if (CATEGORY_OPTIONS[i].value === category) {
                return CATEGORY_OPTIONS[i].label;
            }
        }
        return category;
    }

    function slotLabel(slotId) {
        var slot = getSlotDef(slotId);
        return slot ? slot.label : 'Unslotted';
    }

    function attachImageFallback(imgElement) {
        if (!imgElement || imgElement.dataset.fallbackBound === '1') {
            return;
        }

        imgElement.dataset.fallbackBound = '1';
        imgElement.addEventListener('error', function() {
            if (imgElement.dataset.fallbackApplied === '1') {
                return;
            }

            imgElement.dataset.fallbackApplied = '1';
            imgElement.src = '/FITSPIRATION/images/no_image.jpg';
        });
    }

    function updateLegendState(activeSlotId) {
        for (var i = 0; i < layerOrderRows.length; i++) {
            layerOrderRows[i].classList.toggle('active', layerOrderRows[i].dataset.slotId === activeSlotId);
        }
    }

    function compatibleSlotsForCategory(category) {
        return CATEGORY_TO_SLOTS[category] ? CATEGORY_TO_SLOTS[category].slice() : ['top'];
    }

    function defaultSlotForCategory(category) {
        return compatibleSlotsForCategory(category)[0];
    }

    function inferCategory(name) {
        var value = String(name || '').toLowerCase();
        if (/hat|cap|beanie|helmet|hood/.test(value)) return 'head';
        if (/coat|jacket|hoodie|blazer|cardigan|outer/.test(value)) return 'outerwear';
        if (/jean|pant|trouser|bottom|skirt|short|legging/.test(value)) return 'bottoms';
        if (/shoe|boot|heel|sneaker|loafer|slipper/.test(value)) return 'shoes';
        if (/bag|purse|tote|backpack/.test(value)) return 'bag';
        if (/belt|necklace|glove|scarf|chain|ring|bracelet|glass|accessor/.test(value)) return 'accessory';
        return 'top';
    }

    function inferColor(name) {
        var value = String(name || '').toLowerCase();
        var colorMap = [
            ['black', /black|charcoal/],
            ['white', /white|ivory|cream/],
            ['gray', /gray|grey|silver/],
            ['blue', /blue|navy|cobalt/],
            ['denim', /denim|jean/],
            ['red', /red|burgundy|maroon/],
            ['green', /green|olive|khaki/],
            ['beige', /beige|tan|sand|camel/],
            ['brown', /brown|chocolate|mocha/],
            ['yellow', /yellow|mustard|gold/],
            ['purple', /purple|violet|lavender/],
            ['pink', /pink|rose|fuchsia/],
            ['orange', /orange|coral/]
        ];

        for (var i = 0; i < colorMap.length; i++) {
            if (colorMap[i][1].test(value)) {
                return colorMap[i][0];
            }
        }

        return 'neutral';
    }

    function inferSeason(name) {
        var value = String(name || '').toLowerCase();
        if (/winter|coat|puffer|wool|thermal|fleece/.test(value)) return 'winter';
        if (/summer|linen|tank|short|sandal|tee/.test(value)) return 'summer';
        if (/spring|cardigan|light/.test(value)) return 'spring';
        if (/fall|autumn|trench|flannel/.test(value)) return 'fall';
        return 'all-season';
    }

    function inferStyle(name) {
        var value = String(name || '').toLowerCase();
        if (/street|cargo|oversized|sneaker|hoodie/.test(value)) return 'streetwear';
        if (/formal|blazer|trouser|loafer|shirt/.test(value)) return 'formal';
        if (/sport|athletic|gym|running/.test(value)) return 'sport';
        if (/vintage|retro|classic/.test(value)) return 'vintage';
        return 'casual';
    }

    function inferOccasion(name) {
        var value = String(name || '').toLowerCase();
        if (/party|night|club|evening/.test(value)) return 'night-out';
        if (/office|work|formal|meeting/.test(value)) return 'work';
        if (/date|dinner|event/.test(value)) return 'social';
        if (/sport|gym|training|running/.test(value)) return 'active';
        return 'daily';
    }

    function normalizeMeta(meta, name, category) {
        var source = meta && typeof meta === 'object' ? meta : {};
        return {
            category: source.category || category || inferCategory(name),
            color: source.color || inferColor(name),
            season: source.season || inferSeason(name),
            style: source.style || inferStyle(name),
            occasion: source.occasion || inferOccasion(name)
        };
    }

    function renderSmartMetaSummary(item) {
        if (!selectedMetaSummary) {
            return;
        }

        if (!item) {
            selectedMetaSummary.textContent = t('Select an item to see metadata and generate a matching outfit.');
            return;
        }

        var meta = item.meta || normalizeMeta(null, item.name, item.category);
        selectedMetaSummary.textContent =
            t('Category: ') + categoryLabel(meta.category) +
            ' • ' + t('Color: ') + meta.color +
            ' • ' + t('Season: ') + meta.season +
            ' • ' + t('Style: ') + meta.style +
            ' • ' + t('Occasion: ') + meta.occasion;
    }

    function renderMatchReasons(reasons) {
        if (!matchWhyList) {
            return;
        }

        matchWhyList.innerHTML = '';
        if (!reasons || !reasons.length) {
            var empty = document.createElement('li');
            empty.textContent = t('No matching explanation yet. Select an item and generate a look.');
            matchWhyList.appendChild(empty);
            return;
        }

        for (var i = 0; i < reasons.length; i++) {
            var li = document.createElement('li');
            li.textContent = reasons[i];
            matchWhyList.appendChild(li);
        }
    }

    function categoriesForSlot(slotId) {
        var categories = [];
        var keys = Object.keys(CATEGORY_TO_SLOTS);
        for (var i = 0; i < keys.length; i++) {
            if (CATEGORY_TO_SLOTS[keys[i]].indexOf(slotId) !== -1) {
                categories.push(keys[i]);
            }
        }
        return categories;
    }

    function isColorCompatible(sourceColor, candidateColor) {
        if (!sourceColor || !candidateColor) {
            return false;
        }
        if (sourceColor === candidateColor) {
            return true;
        }
        if (NEUTRAL_COLORS.indexOf(sourceColor) !== -1 || NEUTRAL_COLORS.indexOf(candidateColor) !== -1) {
            return true;
        }
        var complementary = COMPLEMENTARY_COLORS[sourceColor] || [];
        return complementary.indexOf(candidateColor) !== -1;
    }

    function compatibilityScore(sourceMeta, candidateMeta, slotId) {
        var score = 0;
        var reasons = [];

        if (isColorCompatible(sourceMeta.color, candidateMeta.color)) {
            score += sourceMeta.color === candidateMeta.color ? 24 : 16;
            reasons.push('Color harmony: ' + sourceMeta.color + ' with ' + candidateMeta.color + '.');
        }

        if (sourceMeta.style === candidateMeta.style) {
            score += 16;
            reasons.push('Style consistency: both lean ' + sourceMeta.style + '.');
        } else if ((sourceMeta.style === 'casual' && candidateMeta.style === 'streetwear') || (sourceMeta.style === 'streetwear' && candidateMeta.style === 'casual')) {
            score += 10;
            reasons.push('Style blend: casual and streetwear pair well.');
        }

        if (sourceMeta.season === candidateMeta.season || candidateMeta.season === 'all-season' || sourceMeta.season === 'all-season') {
            score += 12;
            reasons.push('Season fit: suitable for ' + (sourceMeta.season === 'all-season' ? candidateMeta.season : sourceMeta.season) + '.');
        }

        if (sourceMeta.occasion === candidateMeta.occasion || candidateMeta.occasion === 'daily') {
            score += 14;
            reasons.push('Occasion match: tuned for ' + sourceMeta.occasion + '.');
        }

        if (slotId === 'shoes' || slotId === 'bottoms' || slotId === 'top') {
            score += 6;
        }

        return {
            score: score,
            reasons: reasons
        };
    }

    function findBestCandidateForSlot(slotId, sourceItem, usedIds) {
        var slotCategories = categoriesForSlot(slotId);
        var sourceMeta = sourceItem.meta || normalizeMeta(null, sourceItem.name, sourceItem.category);
        var best = null;

        for (var i = 0; i < wardrobeItems.length; i++) {
            var candidate = wardrobeItems[i];
            if (!candidate || !candidate.src || usedIds[candidate.src]) {
                continue;
            }

            var candidateCategory = candidate.category || inferCategory(candidate.name || '');
            if (slotCategories.indexOf(candidateCategory) === -1) {
                continue;
            }

            var candidateMeta = candidate.meta || normalizeMeta(null, candidate.name, candidateCategory);
            var scored = compatibilityScore(sourceMeta, candidateMeta, slotId);

            if (!best || scored.score > best.score) {
                best = {
                    candidate: candidate,
                    score: scored.score,
                    reasons: scored.reasons,
                    category: candidateCategory,
                    meta: candidateMeta
                };
            }
        }

        return best;
    }

    function autoGenerateOutfitFromSelected() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            setStatus(t('Select one item first.'));
            return;
        }

        var reasons = [];
        var usedIds = {};
        usedIds[selected.src] = true;

        for (var i = 0; i < outfitItems.length; i++) {
            if (outfitItems[i].src) {
                usedIds[outfitItems[i].src] = true;
            }
        }

        if (!selected.meta) {
            selected.meta = normalizeMeta(null, selected.name, selected.category);
        }

        var selectedSlot = selected.slotId || defaultSlotForCategory(selected.category);

        for (var s = 0; s < SLOT_PRIORITY.length; s++) {
            var slotId = SLOT_PRIORITY[s];
            if (slotId === selectedSlot) {
                continue;
            }

            if (!isStackableSlot(slotId) && findItemsInSlot(slotId).length > 0) {
                continue;
            }

            var best = findBestCandidateForSlot(slotId, selected, usedIds);
            if (!best || best.score <= 0) {
                continue;
            }

            var created = createOutfitItem(best.candidate.src, best.candidate.name, {
                category: best.category,
                slotId: slotId,
                meta: best.meta,
                skipStatus: true
            });

            if (created && created.item) {
                usedIds[best.candidate.src] = true;
                reasons.push(best.candidate.name + ' -> ' + slotLabel(slotId) + ' | ' + (best.reasons[0] || 'Balanced match.'));
            }
        }

        renderMatchReasons(reasons);
        if (reasons.length) {
            setStatus(t('Smart match completed. Added ') + reasons.length + t(' matching pieces.'));
        } else {
            setStatus(t('No strong matches found yet. Add more wardrobe items for better suggestions.'));
        }
    }

    function colorDistance(r1, g1, b1, r2, g2, b2) {
        var dr = r1 - r2;
        var dg = g1 - g2;
        var db = b1 - b2;
        return Math.sqrt(dr * dr + dg * dg + db * db);
    }

    function getAverageBorderColor(imageData, width, height) {
        var data = imageData.data;
        var border = Math.max(2, Math.floor(Math.min(width, height) * 0.02));
        var totalR = 0;
        var totalG = 0;
        var totalB = 0;
        var count = 0;

        for (var y = 0; y < height; y++) {
            for (var x = 0; x < width; x++) {
                var onBorder = (x < border) || (x >= width - border) || (y < border) || (y >= height - border);
                if (!onBorder) {
                    continue;
                }

                var idx = (y * width + x) * 4;
                totalR += data[idx];
                totalG += data[idx + 1];
                totalB += data[idx + 2];
                count += 1;
            }
        }

        return {
            r: Math.round(totalR / Math.max(1, count)),
            g: Math.round(totalG / Math.max(1, count)),
            b: Math.round(totalB / Math.max(1, count))
        };
    }

    function removeBackgroundFromCanvas(canvas) {
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return { ok: false, message: 'Could not create canvas context.' };
        }

        var width = canvas.width;
        var height = canvas.height;
        if (width < 2 || height < 2) {
            return { ok: false, message: 'Image too small.' };
        }

        var imageData = ctx.getImageData(0, 0, width, height);
        var data = imageData.data;
        var bg = getAverageBorderColor(imageData, width, height);

        var strictThreshold = 48;
        var softThreshold = 68;
        var totalPixels = width * height;
        var mask = new Uint8Array(totalPixels);
        var queue = new Uint32Array(totalPixels);
        var qHead = 0;
        var qTail = 0;

        function enqueueIfBackground(x, y, threshold) {
            var pos = y * width + x;
            if (mask[pos] === 1) {
                return;
            }

            var idx = pos * 4;
            var dist = colorDistance(data[idx], data[idx + 1], data[idx + 2], bg.r, bg.g, bg.b);
            if (dist <= threshold) {
                mask[pos] = 1;
                queue[qTail++] = pos;
            }
        }

        for (var x = 0; x < width; x++) {
            enqueueIfBackground(x, 0, strictThreshold);
            enqueueIfBackground(x, height - 1, strictThreshold);
        }
        for (var y = 0; y < height; y++) {
            enqueueIfBackground(0, y, strictThreshold);
            enqueueIfBackground(width - 1, y, strictThreshold);
        }

        while (qHead < qTail) {
            var pos = queue[qHead++];
            var px = pos % width;
            var py = (pos - px) / width;

            if (px > 0) enqueueIfBackground(px - 1, py, softThreshold);
            if (px < width - 1) enqueueIfBackground(px + 1, py, softThreshold);
            if (py > 0) enqueueIfBackground(px, py - 1, softThreshold);
            if (py < height - 1) enqueueIfBackground(px, py + 1, softThreshold);
        }

        var removedRatio = qTail / totalPixels;
        if (removedRatio < 0.03 || removedRatio > 0.92) {
            return { ok: false, message: 'Could not detect a clear removable background.' };
        }

        for (var p = 0; p < totalPixels; p++) {
            if (mask[p] === 1) {
                data[p * 4 + 3] = 0;
            }
        }

        var edgeFadeAlpha = 90;
        for (var p2 = 0; p2 < totalPixels; p2++) {
            if (mask[p2] === 1) {
                continue;
            }

            var x2 = p2 % width;
            var y2 = (p2 - x2) / width;
            var nearBg = false;

            if (x2 > 0 && mask[p2 - 1] === 1) nearBg = true;
            if (!nearBg && x2 < width - 1 && mask[p2 + 1] === 1) nearBg = true;
            if (!nearBg && y2 > 0 && mask[p2 - width] === 1) nearBg = true;
            if (!nearBg && y2 < height - 1 && mask[p2 + width] === 1) nearBg = true;

            if (nearBg) {
                var alphaIdx = p2 * 4 + 3;
                data[alphaIdx] = Math.min(data[alphaIdx], edgeFadeAlpha);
            }
        }

        ctx.putImageData(imageData, 0, 0);
        return { ok: true };
    }

    function processBackgroundRemoval(dataUrl) {
        return new Promise(function(resolve) {
            var img = new Image();
            img.onload = function() {
                var canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;

                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    resolve({ ok: false, message: 'Could not create canvas context.' });
                    return;
                }

                ctx.drawImage(img, 0, 0);
                var result = removeBackgroundFromCanvas(canvas);
                if (!result.ok) {
                    resolve(result);
                    return;
                }

                resolve({ ok: true, dataUrl: canvas.toDataURL('image/png') });
            };
            img.onerror = function() {
                resolve({ ok: false, message: 'Could not load image.' });
            };
            img.src = dataUrl;
        });
    }

    function findOutfitItem(id) {
        for (var i = 0; i < outfitItems.length; i++) {
            if (outfitItems[i].id === id) {
                return outfitItems[i];
            }
        }
        return null;
    }

    function findItemsInSlot(slotId) {
        return outfitItems
            .filter(function(item) {
                return item.slotId === slotId;
            })
            .sort(function(a, b) {
                return b.z - a.z;
            });
    }

    function isStackableSlot(slotId) {
        return slotId === 'accessory_front' || slotId === 'accessory_back';
    }

    function removeOutfitItemById(itemId) {
        var item = findOutfitItem(itemId);
        if (!item) {
            return;
        }

        item.element.remove();
        outfitItems = outfitItems.filter(function(entry) {
            return entry.id !== itemId;
        });

        if (selectedId === itemId) {
            selectedId = null;
        }

        markDirty();
    }

    function clearSlotForIncomingItem(slotId, incomingItemId) {
        if (isStackableSlot(slotId)) {
            return 0;
        }

        var removedCount = 0;
        var slotItems = findItemsInSlot(slotId);
        for (var i = 0; i < slotItems.length; i++) {
            if (slotItems[i].id === incomingItemId) {
                continue;
            }

            removeOutfitItemById(slotItems[i].id);
            removedCount += 1;
        }

        return removedCount;
    }

    function unequipTopItemFromSlot(slotId) {
        var slotItems = findItemsInSlot(slotId);
        if (!slotItems.length) {
            return null;
        }

        var removedItem = slotItems[0];
        removeOutfitItemById(removedItem.id);

        var remaining = findItemsInSlot(slotId);
        if (remaining.length) {
            selectOutfitItem(remaining[0].id);
        } else {
            selectedId = null;
            refreshSelectionUi();
        }

        updateSlotStates();
        return removedItem;
    }

    function cycleSlotItems(slotId) {
        if (!isStackableSlot(slotId)) {
            return null;
        }

        var slotItems = findItemsInSlot(slotId);
        if (slotItems.length < 2) {
            return null;
        }

        var reordered = slotItems.slice(1).concat(slotItems[0]);
        var slot = getSlotDef(slotId);
        var baseZ = slot ? slot.defaultZ : 10;

        for (var i = 0; i < reordered.length; i++) {
            reordered[i].z = baseZ + (reordered.length - i);
            applyItemTransform(reordered[i]);
        }

        lastZ = Math.max(lastZ, baseZ + reordered.length);
        updateSlotStates();
        markDirty();
        return reordered[0];
    }

    function setControlsDisabled(disabled) {
        var controls = [itemCategorySelect, itemSlotSelect, snapItemBtn, fitToSlotBtn, scaleRange, rotateRange, removeBgBtn, bringFrontBtn, sendBackBtn, deleteItemBtn];
        for (var i = 0; i < controls.length; i++) {
            if (controls[i]) {
                controls[i].disabled = disabled;
            }
        }
        for (var j = 0; j < nudgeButtons.length; j++) {
            nudgeButtons[j].disabled = disabled;
        }
    }

    function renderSlotLayer() {
        slotLayer.innerHTML = '';
        slotElements = {};

        for (var i = 0; i < SLOT_DEFS.length; i++) {
            var slot = SLOT_DEFS[i];
            var rect = getDockRect(slot.id);
            if (!rect) {
                continue;
            }

            var overlay = document.createElement('div');
            overlay.className = 'slot-overlay slot-' + slot.id.replace('_', '-');
            overlay.dataset.slotId = slot.id;
            overlay.dataset.label = slot.label;
            overlay.dataset.shortLabel = MOBILE_SLOT_LABELS[slot.id] || slot.label;
            overlay.style.left = rect.left + 'px';
            overlay.style.top = rect.top + 'px';
            overlay.style.width = rect.width + 'px';
            overlay.style.height = rect.height + 'px';

            var preview = document.createElement('div');
            preview.className = 'slot-preview';

            var previewImage = document.createElement('img');
            previewImage.className = 'slot-preview-image';
            previewImage.alt = slot.label;
            attachImageFallback(previewImage);

            var previewName = document.createElement('span');
            previewName.className = 'slot-preview-name';
            previewName.textContent = 'Empty';

            var previewCount = document.createElement('span');
            previewCount.className = 'slot-preview-count';
            previewCount.hidden = true;

            var actions = document.createElement('div');
            actions.className = 'slot-actions';

            var cycleBtn = document.createElement('button');
            cycleBtn.type = 'button';
            cycleBtn.className = 'slot-action-btn slot-cycle-btn';
            cycleBtn.innerHTML = '<i class="fa-solid fa-rotate"></i>';
            cycleBtn.setAttribute('aria-label', 'Cycle items');
            cycleBtn.title = 'Cycle items';
            cycleBtn.hidden = true;
            cycleBtn.addEventListener('click', function(event) {
                event.stopPropagation();
                var parentOverlay = this.closest('.slot-overlay');
                if (!parentOverlay) {
                    return;
                }

                var cycledItem = cycleSlotItems(parentOverlay.dataset.slotId);
                if (!cycledItem) {
                    return;
                }

                selectOutfitItem(cycledItem.id);
                setStatus(t('Cycled accessory stack in the ') + getSlotDef(parentOverlay.dataset.slotId).label + t(' slot.'));
            });

            var unequipBtn = document.createElement('button');
            unequipBtn.type = 'button';
            unequipBtn.className = 'slot-action-btn slot-unequip-btn';
            unequipBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            unequipBtn.setAttribute('aria-label', 'Remove item');
            unequipBtn.title = 'Remove item';
            unequipBtn.hidden = true;
            unequipBtn.addEventListener('click', function(event) {
                event.stopPropagation();
                var parentOverlay = this.closest('.slot-overlay');
                if (!parentOverlay) {
                    return;
                }

                var removedItem = unequipTopItemFromSlot(parentOverlay.dataset.slotId);
                if (!removedItem) {
                    return;
                }

                setStatus(t('Removed ') + removedItem.name + t(' from the ') + getSlotDef(parentOverlay.dataset.slotId).label + t(' slot.'));
            });

            actions.appendChild(cycleBtn);
            actions.appendChild(unequipBtn);

            preview.appendChild(previewImage);
            preview.appendChild(previewName);
            preview.appendChild(previewCount);
            overlay.appendChild(preview);
            overlay.appendChild(actions);

            overlay.addEventListener('dragover', function(event) {
                if (!draggedWardrobeItem) {
                    return;
                }
                event.preventDefault();
            });
            overlay.addEventListener('dragenter', function(event) {
                if (!draggedWardrobeItem) {
                    return;
                }
                event.preventDefault();
                this.classList.add('targeted');
            });
            overlay.addEventListener('dragleave', function() {
                this.classList.remove('targeted');
            });
            overlay.addEventListener('drop', function(event) {
                if (!draggedWardrobeItem) {
                    return;
                }
                event.preventDefault();
                var droppedSlotId = this.dataset.slotId;
                var result = createOutfitItem(draggedWardrobeItem.src, draggedWardrobeItem.name, {
                    category: draggedWardrobeItem.category,
                    slotId: droppedSlotId
                });
                setStatus(
                    result.removedCount > 0
                        ? t('Added a copy to the ') + getSlotDef(droppedSlotId).label + t(' slot and replaced the previous piece.')
                        : t('Added a copy to the ') + getSlotDef(droppedSlotId).label + t(' slot.')
                );
                draggedWardrobeItem = null;
                updateSlotStates();
            });
            overlay.addEventListener('click', function() {
                if (draggedWardrobeItem) {
                    return;
                }

                var slotItems = findItemsInSlot(this.dataset.slotId);
                if (!slotItems.length) {
                    return;
                }

                selectOutfitItem(slotItems[0].id);
                setStatus(t('Selected ') + slotItems[0].name + t(' from the ') + getSlotDef(this.dataset.slotId).label + t(' slot.'));
            });
            slotLayer.appendChild(overlay);
            slotElements[slot.id] = overlay;
        }

        updateSlotStates();
    }

    function highlightSlots(category, targetedSlotId) {
        var compatible = compatibleSlotsForCategory(category);
        for (var i = 0; i < SLOT_DEFS.length; i++) {
            var slot = SLOT_DEFS[i];
            var overlay = slotElements[slot.id];
            if (!overlay) {
                continue;
            }
            overlay.classList.toggle('compatible', compatible.indexOf(slot.id) !== -1);
            overlay.classList.toggle('targeted', targetedSlotId === slot.id);
        }
    }

    function updateSlotStates() {
        var occupied = {};
        for (var i = 0; i < outfitItems.length; i++) {
            if (outfitItems[i].slotId) {
                occupied[outfitItems[i].slotId] = true;
            }
        }

        for (var j = 0; j < SLOT_DEFS.length; j++) {
            var slotId = SLOT_DEFS[j].id;
            var overlay = slotElements[slotId];
            if (overlay) {
                var slotItems = findItemsInSlot(slotId);
                var topItem = slotItems[0] || null;
                var previewImage = overlay.querySelector('.slot-preview-image');
                var previewName = overlay.querySelector('.slot-preview-name');
                var previewCount = overlay.querySelector('.slot-preview-count');
                var cycleBtn = overlay.querySelector('.slot-cycle-btn');
                var unequipBtn = overlay.querySelector('.slot-unequip-btn');

                overlay.classList.toggle('occupied', !!occupied[slotId]);
                overlay.classList.toggle('has-selection', !!topItem && topItem.id === selectedId);
                overlay.classList.toggle('legend-focus', focusedSlotId === slotId);

                if (topItem) {
                    if (previewImage) {
                        previewImage.src = topItem.src;
                        previewImage.hidden = false;
                    }
                    if (previewName) {
                        previewName.textContent = topItem.name;
                    }
                    if (previewCount) {
                        previewCount.hidden = !isStackableSlot(slotId) || slotItems.length < 2;
                        previewCount.textContent = isStackableSlot(slotId) && slotItems.length > 1 ? '+' + (slotItems.length - 1) : '';
                    }
                    if (cycleBtn) {
                        cycleBtn.hidden = !isStackableSlot(slotId) || slotItems.length < 2;
                    }
                    if (unequipBtn) {
                        unequipBtn.hidden = false;
                    }
                } else {
                    if (previewImage) {
                        previewImage.hidden = true;
                        previewImage.removeAttribute('src');
                    }
                    if (previewName) {
                        previewName.textContent = 'Empty';
                    }
                    if (previewCount) {
                        previewCount.hidden = true;
                        previewCount.textContent = '';
                    }
                    if (cycleBtn) {
                        cycleBtn.hidden = true;
                    }
                    if (unequipBtn) {
                        unequipBtn.hidden = true;
                    }
                }
            }
        }

        var selected = findOutfitItem(selectedId);
        if (selected) {
            highlightSlots(selected.category, selected.slotId);
            updateLegendState(selected.slotId);
        } else {
            highlightSlots('top', null);
            for (var k = 0; k < SLOT_DEFS.length; k++) {
                if (slotElements[SLOT_DEFS[k].id]) {
                    slotElements[SLOT_DEFS[k].id].classList.remove('compatible');
                    slotElements[SLOT_DEFS[k].id].classList.remove('targeted');
                }
            }
            updateLegendState(focusedSlotId);
        }
    }

    function applyItemTransform(item) {
        item.element.style.width = item.width + 'px';
        item.element.style.height = item.height + 'px';
        item.element.style.transform = 'translate(' + item.x + 'px,' + item.y + 'px) scale(' + item.scale + ') rotate(' + item.rotation + 'deg)';
        item.element.style.zIndex = String(item.z);
        item.element.classList.toggle('is-snapped', !!item.slotId);
        item.element.dataset.slotId = item.slotId || '';
    }

    function applySlotLayout(item, resetScale) {
        if (!item.slotId) {
            return false;
        }

        var rect = getPlacementRect(item.slotId);
        if (!rect) {
            return false;
        }

        item.width = rect.width;
        item.height = rect.height;
        item.x = rect.left + item.offsetX;
        item.y = rect.top + item.offsetY;
        if (resetScale) {
            item.scale = 1;
        }
        applyItemTransform(item);
        return true;
    }

    function snapItemToSlot(item, slotId, options) {
        options = options || {};
        var removedCount = clearSlotForIncomingItem(slotId, item.id);
        item.slotId = slotId;
        item.offsetX = options.preserveOffset ? item.offsetX : 0;
        item.offsetY = options.preserveOffset ? item.offsetY : 0;

        if (!item.layerLocked) {
            var slot = getSlotDef(slotId);
            if (slot) {
                item.z = slot.defaultZ;
                lastZ = Math.max(lastZ, item.z);
            }
        }

        applySlotLayout(item, !!options.resetScale);
        updateSlotStates();
        if (!options.silentChange) {
            markDirty();
        }
        return removedCount;
    }

    function refreshSelectionUi() {
        var selected = findOutfitItem(selectedId);
        for (var i = 0; i < outfitItems.length; i++) {
            outfitItems[i].element.classList.toggle('selected', selected && outfitItems[i].id === selected.id);
        }

        if (!selected) {
            selectedLabel.textContent = t('No item selected');
            scaleRange.value = '1';
            rotateRange.value = '0';
            itemCategorySelect.value = 'top';
            itemSlotSelect.value = 'top';
            setControlsDisabled(true);
            if (suggestOutfitBtn) {
                suggestOutfitBtn.disabled = true;
            }
            renderSmartMetaSummary(null);
            updateSlotStates();
            return;
        }

        selectedLabel.textContent = t('Selected: ') + selected.name + ' • ' + categoryLabel(selected.category) + ' • ' + slotLabel(selected.slotId);
        scaleRange.value = String(selected.scale);
        rotateRange.value = String(selected.rotation);
        itemCategorySelect.value = selected.category;
        itemSlotSelect.value = selected.slotId || defaultSlotForCategory(selected.category);
        focusedSlotId = selected.slotId || focusedSlotId;
        setControlsDisabled(false);
        if (suggestOutfitBtn) {
            suggestOutfitBtn.disabled = false;
        }
        renderSmartMetaSummary(selected);
        updateSlotStates();
    }

    function selectOutfitItem(id) {
        selectedId = id;
        refreshSelectionUi();
    }

    function getItemCenter(item) {
        return {
            x: item.x + item.width / 2,
            y: item.y + item.height / 2
        };
    }

    function getNearestCompatibleSlot(item) {
        var compatibleSlots = compatibleSlotsForCategory(item.category);
        var bestSlotId = null;
        var bestScore = Infinity;
        var center = getItemCenter(item);
        var stageSize = getStageSize();
        var threshold = Math.max(90, Math.min(stageSize.width, stageSize.height) * 0.18);

        for (var i = 0; i < compatibleSlots.length; i++) {
            var rect = getPlacementRect(compatibleSlots[i]);
            if (!rect) {
                continue;
            }

            var slotCenterX = rect.left + rect.width / 2;
            var slotCenterY = rect.top + rect.height / 2;
            var dx = center.x - slotCenterX;
            var dy = center.y - slotCenterY;
            var distance = Math.sqrt(dx * dx + dy * dy);
            var overlap = center.x >= rect.left && center.x <= rect.left + rect.width && center.y >= rect.top && center.y <= rect.top + rect.height;
            var score = overlap ? distance * 0.25 : distance;

            if (score < bestScore) {
                bestScore = score;
                bestSlotId = compatibleSlots[i];
            }
        }

        return bestScore <= threshold ? bestSlotId : null;
    }

    function makeDraggable(item) {
        var drag = {
            active: false,
            startX: 0,
            startY: 0,
            originX: 0,
            originY: 0,
            originSlotId: null,
            targetSlotId: null
        };

        item.element.addEventListener('pointerdown', function(event) {
            event.preventDefault();
            selectOutfitItem(item.id);
            item.z = ++lastZ;
            applyItemTransform(item);

            drag.active = true;
            drag.startX = event.clientX;
            drag.startY = event.clientY;
            drag.originX = item.x;
            drag.originY = item.y;
            drag.originSlotId = item.slotId;
            drag.targetSlotId = item.slotId;

            item.element.setPointerCapture(event.pointerId);
            item.element.style.cursor = 'grabbing';
            highlightSlots(item.category, item.slotId);
        });

        item.element.addEventListener('pointermove', function(event) {
            if (!drag.active) {
                return;
            }

            item.x = drag.originX + (event.clientX - drag.startX);
            item.y = drag.originY + (event.clientY - drag.startY);
            applyItemTransform(item);

            drag.targetSlotId = getNearestCompatibleSlot(item);
            highlightSlots(item.category, drag.targetSlotId);
        });

        function endDrag(event) {
            if (!drag.active) {
                return;
            }

            drag.active = false;
            item.element.style.cursor = 'grab';
            if (event && typeof event.pointerId === 'number') {
                item.element.releasePointerCapture(event.pointerId);
            }

            if (drag.targetSlotId) {
                var removedCount = snapItemToSlot(item, drag.targetSlotId);
                setStatus(
                    removedCount > 0
                        ? t('Item snapped to ') + getSlotDef(drag.targetSlotId).label + t(' and replaced the previous piece.')
                        : t('Item snapped to ') + getSlotDef(drag.targetSlotId).label + '.'
                );
            } else if (drag.originSlotId) {
                snapItemToSlot(item, drag.originSlotId, { preserveOffset: true });
                setStatus(t('Drop near a highlighted zone to snap the item.'));
            } else {
                updateSlotStates();
            }

            refreshSelectionUi();
        }

        item.element.addEventListener('pointerup', endDrag);
        item.element.addEventListener('pointercancel', endDrag);
    }

    function createOutfitItem(dataUrl, name, options) {
        options = options || {};
        var category = options.category || inferCategory(name);
        var slotId = options.slotId || defaultSlotForCategory(category);
        var itemId = nextOutfitId++;
        var preserveLayout = !!options.preserveLayout;

        var element = document.createElement('div');
        element.className = 'outfit-item';

        var img = document.createElement('img');
        img.src = dataUrl;
        img.alt = name;
        attachImageFallback(img);
        element.appendChild(img);
        outfitCanvas.appendChild(element);

        var item = {
            id: itemId,
            name: name,
            src: dataUrl,
            category: category,
            meta: normalizeMeta(options.meta, name, category),
            slotId: slotId,
            width: 180,
            height: 180,
            x: 0,
            y: 0,
            offsetX: typeof options.offsetX === 'number' ? options.offsetX : 0,
            offsetY: typeof options.offsetY === 'number' ? options.offsetY : 0,
            scale: typeof options.scale === 'number' ? options.scale : 1,
            rotation: typeof options.rotation === 'number' ? options.rotation : 0,
            z: typeof options.z === 'number' ? options.z : ++lastZ,
            layerLocked: !!options.layerLocked,
            element: element,
            imageEl: img
        };

        outfitItems.push(item);
        makeDraggable(item);
        var removedCount = snapItemToSlot(item, slotId, { resetScale: !preserveLayout, preserveOffset: preserveLayout, silentChange: !!options.silentChange });

        if (typeof options.scale === 'number') {
            item.scale = options.scale;
        }
        if (typeof options.rotation === 'number') {
            item.rotation = options.rotation;
        }
        if (typeof options.z === 'number') {
            item.z = options.z;
            lastZ = Math.max(lastZ, item.z);
        }

        applyItemTransform(item);

        if (!options.skipSelect) {
            selectOutfitItem(item.id);
        } else {
            updateSlotStates();
        }

        if (!options.skipStatus) {
            setStatus(
                removedCount > 0
                    ? 'Added "' + name + '" and replaced the previous item in this slot.'
                    : 'Added "' + name + '" to outfit.'
            );
        }

        if (!options.silentChange) {
            markDirty();
        }

        return { item: item, removedCount: removedCount };
    }

    function serializeOutfitState() {
        return {
            version: 1,
            items: outfitItems.slice().sort(function(a, b) {
                return a.z - b.z;
            }).map(function(item) {
                return {
                    name: item.name,
                    src: item.src,
                    category: item.category,
                    meta: item.meta,
                    slotId: item.slotId,
                    offsetX: Math.round(item.offsetX || 0),
                    offsetY: Math.round(item.offsetY || 0),
                    scale: Number(item.scale || 1),
                    rotation: Number(item.rotation || 0),
                    z: parseInt(item.z, 10) || 0,
                    layerLocked: !!item.layerLocked
                };
            })
        };
    }

    function loadInitialOutfitState() {
        var storedDraft = loadStoredDraft();
        var initialState = storedDraft && storedDraft.builderState ? storedDraft.builderState : initialBuilderConfig.builderState;
        if (!initialState || !Array.isArray(initialState.items) || !initialState.items.length) {
            if (initialBuilderConfig.loadError) {
                setStatus(initialBuilderConfig.loadError);
            }
            return;
        }

        suppressDirtyTracking = true;

        var items = initialState.items.slice().sort(function(a, b) {
            return (parseInt(a.z, 10) || 0) - (parseInt(b.z, 10) || 0);
        });

        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            if (!item || !item.src || !item.slotId) {
                continue;
            }

            createOutfitItem(item.src, item.name || 'Item', {
                category: item.category || inferCategory(item.name || ''),
                meta: item.meta || null,
                slotId: item.slotId,
                offsetX: Number(item.offsetX || 0),
                offsetY: Number(item.offsetY || 0),
                scale: Number(item.scale || 1),
                rotation: Number(item.rotation || 0),
                z: Number(item.z || 0),
                layerLocked: !!item.layerLocked,
                preserveLayout: true,
                skipSelect: true,
                skipStatus: true,
                silentChange: true
            });
        }

        suppressDirtyTracking = false;

        if (outfitItems.length) {
            var topmostItem = outfitItems.slice().sort(function(a, b) {
                return b.z - a.z;
            })[0];
            selectOutfitItem(topmostItem.id);
            if (storedDraft) {
                if (storedDraft.name && outfitNameInput) {
                    outfitNameInput.value = storedDraft.name;
                }
                setDirtyState(true, { text: t('Restored autosaved draft') });
                setStatus(t('Restored your autosaved draft.'));
            } else {
                setDirtyState(false);
                setStatus(t('Loaded saved outfit for editing.'));
            }
        } else if (initialBuilderConfig.loadError) {
            setStatus(initialBuilderConfig.loadError);
        }
    }

    function createCategorySelect(initialValue, onChange) {
        var select = document.createElement('select');
        select.className = 'wardrobe-category';

        for (var i = 0; i < CATEGORY_OPTIONS.length; i++) {
            var option = document.createElement('option');
            option.value = CATEGORY_OPTIONS[i].value;
            option.textContent = CATEGORY_OPTIONS[i].label;
            select.appendChild(option);
        }

        select.value = initialValue;
        select.addEventListener('change', function() {
            onChange(select.value);
        });
        return select;
    }

    function enhanceWardrobeCard(card, item, img, meta, title) {
        card.draggable = true;
        card.addEventListener('dragstart', function(event) {
            draggedWardrobeItem = item;
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'copy';
                event.dataTransfer.setData('text/plain', String(item.id));
            }
            highlightSlots(item.category, null);
            setStatus(t('Drag this item into a slot near the mannequin to duplicate it.'));
        });
        card.addEventListener('dragend', function() {
            draggedWardrobeItem = null;
            updateSlotStates();
        });
        attachImageFallback(img);

        var categorySelect = createCategorySelect(item.category, function(value) {
            item.category = value;
        });

        var actions = document.createElement('div');
        actions.className = 'wardrobe-actions';

        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.textContent = t('Add');
        addBtn.addEventListener('click', function() {
            createOutfitItem(item.src, item.name, { category: item.category, meta: item.meta || null });
        });

        var rmBgBtn = document.createElement('button');
        rmBgBtn.type = 'button';
        rmBgBtn.textContent = t('No BG');
        rmBgBtn.className = 'secondary';
        rmBgBtn.addEventListener('click', async function() {
            rmBgBtn.disabled = true;
            setStatus(t('Removing background from wardrobe item...'));
            var processed = await processBackgroundRemoval(item.src);
            if (!processed.ok) {
                setStatus(processed.message || t('Could not remove background.'));
                rmBgBtn.disabled = false;
                return;
            }

            item.src = processed.dataUrl;
            img.src = item.src;
            setStatus(t('Background removed for wardrobe item.'));
            rmBgBtn.disabled = false;
        });

        actions.appendChild(addBtn);
        actions.appendChild(rmBgBtn);
        meta.appendChild(title);
        meta.appendChild(categorySelect);
        meta.appendChild(actions);
    }

    function renderWardrobeCard(item, targetList, prepend) {
        var card = document.createElement('div');
        card.className = 'wardrobe-card';

        var img = document.createElement('img');
        img.src = item.src;
        img.alt = item.name;

        var meta = document.createElement('div');
        meta.className = 'wardrobe-meta';

        var title = document.createElement('span');
        title.className = 'wardrobe-title';
        title.textContent = item.name;

        card.appendChild(img);
        card.appendChild(meta);
        meta.appendChild(title);

        enhanceWardrobeCard(card, item, img, meta, title);

        if (prepend) {
            targetList.prepend(card);
        } else {
            targetList.appendChild(card);
        }
    }

    function initializeExistingLikedCards() {
        var existingCards = Array.prototype.slice.call(document.querySelectorAll('#likedPinsList .liked-pin-card'));
        for (var i = 0; i < existingCards.length; i++) {
            var card = existingCards[i];
            var img = card.querySelector('img');
            var meta = card.querySelector('.wardrobe-meta');
            var title = card.querySelector('.wardrobe-title');
            if (!img || !meta || !title || card.dataset.enhanced === '1') {
                continue;
            }

            var item = {
                id: parseInt(card.dataset.itemId || '0', 10) || nextWardrobeId++,
                name: card.dataset.itemName || title.textContent || 'Untitled',
                src: card.dataset.itemSrc || img.getAttribute('src') || '',
                category: card.dataset.itemCategory || inferCategory(card.dataset.itemName || title.textContent || ''),
                meta: normalizeMeta(null, card.dataset.itemName || title.textContent || '', card.dataset.itemCategory || inferCategory(card.dataset.itemName || title.textContent || ''))
            };

            if (!wardrobeItems.some(function(existing) { return existing.id === item.id; })) {
                wardrobeItems.push(item);
            }

            enhanceWardrobeCard(card, item, img, meta, title);
            card.dataset.enhanced = '1';
        }
    }

    function readFileAsDataUrl(file) {
        return new Promise(function(resolve, reject) {
            var reader = new FileReader();
            reader.onload = function() {
                resolve(reader.result);
            };
            reader.onerror = function() {
                reject(new Error('Failed to read file.'));
            };
            reader.readAsDataURL(file);
        });
    }

    wardrobeUpload.addEventListener('change', async function() {
        var files = Array.prototype.slice.call(wardrobeUpload.files || []);
        if (!files.length) {
            return;
        }

        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            if (!file.type || file.type.indexOf('image/') !== 0) {
                continue;
            }

            try {
                var dataUrl = await readFileAsDataUrl(file);
                var item = {
                    id: nextWardrobeId++,
                    name: file.name,
                    src: dataUrl,
                    category: inferCategory(file.name),
                    meta: normalizeMeta(null, file.name, inferCategory(file.name))
                };
                wardrobeItems.push(item);
                renderWardrobeCard(item, wardrobeList, true);
                createOutfitItem(item.src, item.name, { category: item.category, meta: item.meta });
            } catch (error) {
                setStatus(t('Could not read one of the selected files.'));
            }
        }

        wardrobeUpload.value = '';
    });

    scaleRange.addEventListener('input', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }
        selected.scale = parseFloat(scaleRange.value);
        applyItemTransform(selected);
        markDirty();
    });

    rotateRange.addEventListener('input', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }
        selected.rotation = parseFloat(rotateRange.value);
        applyItemTransform(selected);
        markDirty();
    });

    itemCategorySelect.addEventListener('change', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }

        selected.category = itemCategorySelect.value;
        var targetSlot = selected.slotId;
        if (compatibleSlotsForCategory(selected.category).indexOf(targetSlot) === -1) {
            targetSlot = defaultSlotForCategory(selected.category);
        }
        snapItemToSlot(selected, targetSlot);
        refreshSelectionUi();
        setStatus(t('Category updated and item snapped into a matching zone.'));
    });

    itemSlotSelect.addEventListener('change', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }

        snapItemToSlot(selected, itemSlotSelect.value, { resetScale: false });
        refreshSelectionUi();
        setStatus(t('Item snapped to the selected slot.'));
    });

    snapItemBtn.addEventListener('click', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }

        snapItemToSlot(selected, defaultSlotForCategory(selected.category));
        refreshSelectionUi();
        setStatus(t('Item snapped to its default zone.'));
    });

    fitToSlotBtn.addEventListener('click', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected || !selected.slotId) {
            return;
        }

        selected.offsetX = 0;
        selected.offsetY = 0;
        applySlotLayout(selected, true);
        refreshSelectionUi();
        setStatus(t('Item refit to the slot bounds.'));
    });

    for (var n = 0; n < nudgeButtons.length; n++) {
        nudgeButtons[n].addEventListener('click', function() {
            var selected = findOutfitItem(selectedId);
            if (!selected) {
                return;
            }

            var step = 8;
            var direction = this.dataset.nudge;
            if (direction === 'up') selected.offsetY -= step;
            if (direction === 'down') selected.offsetY += step;
            if (direction === 'left') selected.offsetX -= step;
            if (direction === 'right') selected.offsetX += step;

            if (selected.slotId) {
                applySlotLayout(selected, false);
            } else {
                selected.x += direction === 'right' ? step : direction === 'left' ? -step : 0;
                selected.y += direction === 'down' ? step : direction === 'up' ? -step : 0;
                applyItemTransform(selected);
            }

            markDirty();
        });
    }

    removeBgBtn.addEventListener('click', async function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            setStatus(t('Select an item first.'));
            return;
        }

        removeBgBtn.disabled = true;
        setStatus(t('Removing background from selected item...'));

        var processed = await processBackgroundRemoval(selected.src);
        if (!processed.ok) {
            setStatus(processed.message || t('Could not remove background.'));
            removeBgBtn.disabled = false;
            return;
        }

        selected.src = processed.dataUrl;
        selected.imageEl.src = selected.src;
        markDirty();
        setStatus(t('Background removed for selected item.'));
        removeBgBtn.disabled = false;
    });

    bringFrontBtn.addEventListener('click', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }
        selected.layerLocked = true;
        selected.z = ++lastZ;
        applyItemTransform(selected);
        markDirty();
    });

    sendBackBtn.addEventListener('click', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }

        selected.layerLocked = true;
        selected.z = 1;
        outfitItems
            .filter(function(item) { return item.id !== selected.id; })
            .sort(function(a, b) { return a.z - b.z; })
            .forEach(function(item, index) {
                item.z = index + 2;
                applyItemTransform(item);
            });
        applyItemTransform(selected);
        markDirty();
    });

    deleteItemBtn.addEventListener('click', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }

        selected.element.remove();
        outfitItems = outfitItems.filter(function(item) {
            return item.id !== selected.id;
        });
        selectedId = null;
        refreshSelectionUi();
        updateSlotStates();
        setStatus(t('Item deleted.'));
    });

    clearOutfitBtn.addEventListener('click', function() {
        for (var i = 0; i < outfitItems.length; i++) {
            outfitItems[i].element.remove();
        }
        outfitItems = [];
        selectedId = null;
        refreshSelectionUi();
        updateSlotStates();
        clearDraft();
        setDirtyState(false);
        setStatus(t('Outfit cleared.'));
        renderMatchReasons(null);
    });

    async function saveOutfit(saveMode) {
        if (!outfitItems.length) {
            setStatus(t('Add items to your outfit before saving.'));
            return;
        }

        var width = outfitCanvas.clientWidth;
        var height = outfitCanvas.clientHeight;
        if (!width || !height) {
            setStatus(t('Could not export outfit.'));
            return;
        }

        var canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            setStatus(t('Could not export outfit.'));
            return;
        }

        drawBuilderStageBackground(ctx, width, height);
        drawMannequin(ctx, width, height);

        var sorted = outfitItems.slice().sort(function(a, b) { return a.z - b.z; });
        for (var i = 0; i < sorted.length; i++) {
            await drawItemToContext(ctx, sorted[i]);
        }

        var previousOutfitId = currentOutfitId;
        var previewCanvas = buildSquareOutfitPreview(canvas, width, height, sorted);
        var imageData = previewCanvas.toDataURL('image/png');
        var name = (outfitNameInput.value || '').trim() || 'My Outfit';
        var builderState = serializeOutfitState();
        var targetOutfitId = saveMode === 'fork' ? null : currentOutfitId;
        var publishPost = publishOutfitToggle ? !!publishOutfitToggle.checked : true;

        saveOutfitBtn.disabled = true;
        if (saveAsNewBtn) {
            saveAsNewBtn.disabled = true;
        }
        setStatus(saveMode === 'fork' ? t('Saving a new outfit...') : t('Saving outfit...'));

        try {
            function extractServerMessage(payload, fallbackText) {
                var rawText = (fallbackText || '').trim();
                var sanitizedText = rawText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                if (payload && typeof payload === 'object') {
                    var message = payload.error || payload.message || payload.details;
                    if (typeof message === 'string' && message.trim()) {
                        return message.trim();
                    }
                }

                if (sanitizedText) {
                    return sanitizedText.slice(0, 220);
                }

                return '';
            }

            var requestHeaders = { 'Content-Type': 'application/json' };
            if (typeof getCsrfHeaders === 'function') {
                requestHeaders = getCsrfHeaders(requestHeaders);
            }
            var response = await fetch('../includes/saveOutfit.inc.php', {
                method: 'POST',
                headers: requestHeaders,
                body: JSON.stringify({
                    image_data: imageData,
                    name: name,
                    outfit_id: targetOutfitId,
                    builder_state: builderState,
                    publish_post: publishPost,
                    remix_source_outfit_id: remixSource && remixSource.outfitId ? Number(remixSource.outfitId) : null
                })
            });

            var responseText = await response.text();
            var result = null;
            if (responseText) {
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error(extractServerMessage(null, responseText) || ('Unexpected server response (' + response.status + ')'));
                }
            }

            if (!response.ok) {
                throw new Error(extractServerMessage(result, responseText) || ('Request failed (' + response.status + ')'));
            }

            if (result.success) {
                if (saveMode === 'fork') {
                    clearDraft(previousOutfitId);
                    clearOutfitUrl();
                }
                currentOutfitId = parseInt(result.id, 10) || currentOutfitId;
                isEditMode = !!currentOutfitId;
                updateSaveButtonState();
                syncOutfitUrl(currentOutfitId);
                clearDraft(previousOutfitId);
                clearDraft(currentOutfitId);
                setDirtyState(false);
                remixSource = null;
                if (remixModeBadge) {
                    remixModeBadge.classList.add('hidden');
                }
                setStatus(saveMode === 'fork' ? t('Saved as a new outfit.') : (result.mode === 'updated' ? t('Outfit changes saved.') : t('Outfit saved to your profile!')));
            } else {
                setStatus(extractServerMessage(result, responseText) || t('Could not save outfit.'));
            }
        } catch (err) {
            setStatus((err && err.message) ? err.message : t('Network error. Please try again.'));
        }

        saveOutfitBtn.disabled = false;
        if (saveAsNewBtn) {
            saveAsNewBtn.disabled = false;
        }
    }

    function drawBuilderStageBackground(ctx, width, height) {
        var bgGradient = ctx.createLinearGradient(0, 0, 0, height);
        bgGradient.addColorStop(0, '#1a1c28');
        bgGradient.addColorStop(1, '#111827');
        ctx.fillStyle = bgGradient;
        ctx.fillRect(0, 0, width, height);
    }

    function drawMannequin(ctx, width, height) {
        var cx = width * 0.5;

        var head = { x: cx - 32, y: 60, w: 64, h: 64 };
        var torso = { x: cx - 65, y: 128, w: 130, h: 210 };
        var leftArm = { x: cx - 108, y: 146, w: 36, h: 180, angle: 10 * Math.PI / 180 };
        var rightArm = { x: cx + 72, y: 146, w: 36, h: 180, angle: -10 * Math.PI / 180 };
        var leftLeg = { x: cx - 52, y: 332, w: 48, h: 220 };
        var rightLeg = { x: cx + 4, y: 332, w: 48, h: 220 };

        ctx.save();
        ctx.fillStyle = 'rgba(148, 163, 184, 0.08)';
        ctx.strokeStyle = 'rgba(203, 213, 225, 0.16)';
        ctx.setLineDash([4, 4]);
        ctx.lineWidth = 1;

        ctx.beginPath();
        ctx.ellipse(head.x + head.w / 2, head.y + head.h / 2, head.w / 2, head.h / 2, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();

        ctx.beginPath();
        ctx.roundRect(torso.x, torso.y, torso.w, torso.h, [70, 70, 40, 40]);
        ctx.fill();
        ctx.stroke();

        function drawRotatedLimb(limb) {
            var centerX = limb.x + limb.w / 2;
            var centerY = limb.y + limb.h / 2;
            ctx.save();
            ctx.translate(centerX, centerY);
            ctx.rotate(limb.angle || 0);
            ctx.beginPath();
            ctx.roundRect(-limb.w / 2, -limb.h / 2, limb.w, limb.h, 28);
            ctx.fill();
            ctx.stroke();
            ctx.restore();
        }

        drawRotatedLimb(leftArm);
        drawRotatedLimb(rightArm);

        ctx.beginPath();
        ctx.roundRect(leftLeg.x, leftLeg.y, leftLeg.w, leftLeg.h, 30);
        ctx.fill();
        ctx.stroke();

        ctx.beginPath();
        ctx.roundRect(rightLeg.x, rightLeg.y, rightLeg.w, rightLeg.h, 30);
        ctx.fill();
        ctx.stroke();

        ctx.restore();
    }

    function getMannequinBounds(width, height) {
        var cx = width * 0.5;

        return {
            left: Math.max(0, cx - 126),
            top: 60,
            right: Math.min(width, cx + 118),
            bottom: Math.min(height, 552)
        };
    }

    function getItemBounds(item) {
        var drawWidth = item.width || 0;
        var drawHeight = item.height || 0;
        var scale = item.scale || 1;
        var rotation = (item.rotation || 0) * Math.PI / 180;

        var scaledWidth = drawWidth * scale;
        var scaledHeight = drawHeight * scale;
        var absCos = Math.abs(Math.cos(rotation));
        var absSin = Math.abs(Math.sin(rotation));
        var bboxWidth = scaledWidth * absCos + scaledHeight * absSin;
        var bboxHeight = scaledWidth * absSin + scaledHeight * absCos;

        var centerX = (item.x || 0) + drawWidth / 2;
        var centerY = (item.y || 0) + drawHeight / 2;

        return {
            left: centerX - bboxWidth / 2,
            top: centerY - bboxHeight / 2,
            right: centerX + bboxWidth / 2,
            bottom: centerY + bboxHeight / 2
        };
    }

    function buildSquareOutfitPreview(sourceCanvas, width, height, items) {
        var mannequinBounds = getMannequinBounds(width, height);
        var bounds = {
            left: mannequinBounds.left,
            top: mannequinBounds.top,
            right: mannequinBounds.right,
            bottom: mannequinBounds.bottom
        };

        for (var i = 0; i < items.length; i++) {
            var itemBounds = getItemBounds(items[i]);
            if (!isFinite(itemBounds.left) || !isFinite(itemBounds.top) || !isFinite(itemBounds.right) || !isFinite(itemBounds.bottom)) {
                continue;
            }

            bounds.left = Math.min(bounds.left, itemBounds.left);
            bounds.top = Math.min(bounds.top, itemBounds.top);
            bounds.right = Math.max(bounds.right, itemBounds.right);
            bounds.bottom = Math.max(bounds.bottom, itemBounds.bottom);
        }

        var centerX = (mannequinBounds.left + mannequinBounds.right) / 2;
        var centerY = (mannequinBounds.top + mannequinBounds.bottom) / 2;

        var halfSide = Math.max(
            centerX - bounds.left,
            bounds.right - centerX,
            centerY - bounds.top,
            bounds.bottom - centerY
        ) * 1.05;

        var minHalfSide = Math.max(110, Math.min(width, height) * 0.3);
        var maxHalfSide = Math.max(width, height) / 2;
        halfSide = Math.max(minHalfSide, Math.min(maxHalfSide, halfSide));
        var side = halfSide * 2;

        var cropX = centerX - side / 2;
        var cropY = centerY - side / 2;

        cropX = Math.max(0, Math.min(cropX, width - side));
        cropY = Math.max(0, Math.min(cropY, height - side));

        var previewCanvas = document.createElement('canvas');
        previewCanvas.width = Math.round(side);
        previewCanvas.height = Math.round(side);

        var previewCtx = previewCanvas.getContext('2d');
        if (!previewCtx) {
            return sourceCanvas;
        }

        previewCtx.drawImage(
            sourceCanvas,
            cropX,
            cropY,
            side,
            side,
            0,
            0,
            previewCanvas.width,
            previewCanvas.height
        );

        return previewCanvas;
    }

    function drawItemToContext(ctx, item) {
        return new Promise(function(resolve) {
            function paintImage(img) {
                var drawWidth = item.width;
                var drawHeight = item.height;
                var centerX = item.x + drawWidth / 2;
                var centerY = item.y + drawHeight / 2;

                var naturalWidth = img.naturalWidth || drawWidth;
                var naturalHeight = img.naturalHeight || drawHeight;
                var containScale = Math.min(drawWidth / naturalWidth, drawHeight / naturalHeight);
                var fittedWidth = naturalWidth * containScale;
                var fittedHeight = naturalHeight * containScale;

                ctx.save();
                ctx.translate(centerX, centerY);
                ctx.rotate((item.rotation * Math.PI) / 180);
                ctx.scale(item.scale, item.scale);
                ctx.drawImage(img, -fittedWidth / 2, -fittedHeight / 2, fittedWidth, fittedHeight);
                ctx.restore();
                resolve();
            }

            if (item.imageEl && item.imageEl.complete && (item.imageEl.naturalWidth || item.imageEl.width)) {
                paintImage(item.imageEl);
                return;
            }

            var img = new Image();
            img.onload = function() {
                paintImage(img);
            };
            img.onerror = function() {
                resolve();
            };

            var src = '';
            if (item.imageEl) {
                src = item.imageEl.currentSrc || item.imageEl.src || '';
            }
            if (!src) {
                src = item.src;
            }
            img.src = src;
        });
    }

    downloadBtn.addEventListener('click', async function() {
        var width = outfitCanvas.clientWidth;
        var height = outfitCanvas.clientHeight;
        if (!width || !height) {
            return;
        }

        var canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        var ctx = canvas.getContext('2d');
        if (!ctx) {
            setStatus('Could not export outfit.');
            return;
        }

        drawBuilderStageBackground(ctx, width, height);
        drawMannequin(ctx, width, height);

        var sorted = outfitItems.slice().sort(function(a, b) {
            return a.z - b.z;
        });

        for (var i = 0; i < sorted.length; i++) {
            await drawItemToContext(ctx, sorted[i]);
        }

        var link = document.createElement('a');
        link.download = 'fitspiration-outfit.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        setStatus('Outfit downloaded.');
    });

    saveOutfitBtn.addEventListener('click', function() {
        saveOutfit('default');
    });

    if (saveAsNewBtn) {
        saveAsNewBtn.addEventListener('click', function() {
            saveOutfit('fork');
        });
    }

    if (suggestOutfitBtn) {
        suggestOutfitBtn.addEventListener('click', autoGenerateOutfitFromSelected);
    }

    outfitNameInput.addEventListener('input', function() {
        markDirty();
    });

    document.addEventListener('click', function(event) {
        if (!builderStage.contains(event.target) && !event.target.closest('.builder-panel')) {
            selectedId = null;
            refreshSelectionUi();
        }
    });

    var tabUpload = document.getElementById('tabUpload');
    var tabLiked = document.getElementById('tabLiked');
    var uploadPane = document.getElementById('uploadPane');
    var likedPane = document.getElementById('likedPane');
    var likedPinsList = document.getElementById('likedPinsList');
    var likedPinsEmpty = document.getElementById('likedPinsEmpty');
    var likedPinsLoaded = false;

    function renderLikedPinCard(pin) {
        renderWardrobeCard(pin, likedPinsList, false);
    }

    function loadLikedPins() {
        if (likedPinsLoaded) {
            return;
        }
        likedPinsLoaded = true;
        initializeExistingLikedCards();
        if (!likedPinsList.children.length) {
            likedPinsEmpty.classList.remove('hidden');
        } else {
            likedPinsEmpty.classList.add('hidden');
        }
        setStatus('');
    }

    if (tabUpload && tabLiked) {
        tabUpload.addEventListener('click', function() {
            tabUpload.classList.add('active');
            tabLiked.classList.remove('active');
            uploadPane.classList.remove('hidden');
            likedPane.classList.add('hidden');
        });

        tabLiked.addEventListener('click', function() {
            tabLiked.classList.add('active');
            tabUpload.classList.remove('active');
            likedPane.classList.remove('hidden');
            uploadPane.classList.add('hidden');
            loadLikedPins();
        });
    }

    for (var rowIndex = 0; rowIndex < layerOrderRows.length; rowIndex++) {
        layerOrderRows[rowIndex].addEventListener('click', function() {
            var slotId = this.dataset.slotId;
            focusedSlotId = slotId;

            var slotItems = findItemsInSlot(slotId);
            if (slotItems.length) {
                selectOutfitItem(slotItems[0].id);
                setStatus(t('Focused ') + slotLabel(slotId) + t(' and selected the equipped item.'));
            } else {
                selectedId = null;
                refreshSelectionUi();
                setStatus(t('Focused ') + slotLabel(slotId) + t('. This slot is empty.'));
            }
        });
    }

    window.addEventListener('resize', function() {
        renderSlotLayer();
        for (var i = 0; i < outfitItems.length; i++) {
            if (outfitItems[i].slotId) {
                applySlotLayout(outfitItems[i], false);
            }
        }
    });

    window.addEventListener('beforeunload', function(event) {
        if (!hasUnsavedChanges) {
            return;
        }

        saveDraftNow();
        event.preventDefault();
        event.returnValue = '';
    });

    renderSlotLayer();
    updateSaveButtonState();
    if (initialBuilderConfig.name && outfitNameInput && !outfitNameInput.value) {
        outfitNameInput.value = initialBuilderConfig.name;
    }
    loadInitialOutfitState();
    if (!hasUnsavedChanges) {
        setDirtyState(false);
    }
    renderMatchReasons(null);
    refreshSelectionUi();
})();
