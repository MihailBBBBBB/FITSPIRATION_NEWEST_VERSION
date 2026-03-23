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
    var scaleRange = document.getElementById('scaleRange');
    var rotateRange = document.getElementById('rotateRange');

    var removeBgBtn = document.getElementById('removeBgBtn');
    var bringFrontBtn = document.getElementById('bringFrontBtn');
    var sendBackBtn = document.getElementById('sendBackBtn');
    var deleteItemBtn = document.getElementById('deleteItemBtn');
    var clearOutfitBtn = document.getElementById('clearOutfitBtn');
    var downloadBtn = document.getElementById('downloadBtn');
    var saveOutfitBtn = document.getElementById('saveOutfitBtn');
    var outfitNameInput = document.getElementById('outfitNameInput');

    if (!wardrobeUpload || !wardrobeList || !outfitCanvas || !builderStage) {
        return;
    }

    var wardrobeItems = [];
    var outfitItems = [];
    var selectedId = null;
    var lastZ = 2;
    var nextWardrobeId = 1;
    var nextOutfitId = 1;

    function setStatus(text) {
        statusMessage.textContent = text || '';
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
        return outfitItems.find(function(item) {
            return item.id === id;
        }) || null;
    }

    function findWardrobeItem(id) {
        return wardrobeItems.find(function(item) {
            return item.id === id;
        }) || null;
    }

    function applyItemTransform(item) {
        item.element.style.width = item.width + 'px';
        item.element.style.height = item.height + 'px';
        item.element.style.transform = 'translate(' + item.x + 'px,' + item.y + 'px) scale(' + item.scale + ') rotate(' + item.rotation + 'deg)';
        item.element.style.zIndex = String(item.z);
    }

    function refreshSelectionUi() {
        var selected = findOutfitItem(selectedId);
        outfitItems.forEach(function(item) {
            item.element.classList.toggle('selected', selected && item.id === selected.id);
        });

        if (!selected) {
            selectedLabel.textContent = t('No item selected');
            scaleRange.value = '1';
            rotateRange.value = '0';
            return;
        }

        selectedLabel.textContent = t('Selected: ') + selected.name;
        scaleRange.value = String(selected.scale);
        rotateRange.value = String(selected.rotation);
    }

    function selectOutfitItem(id) {
        selectedId = id;
        refreshSelectionUi();
    }

    function makeDraggable(item) {
        var drag = {
            active: false,
            startX: 0,
            startY: 0,
            originX: 0,
            originY: 0
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

            item.element.setPointerCapture(event.pointerId);
            item.element.style.cursor = 'grabbing';
        });

        item.element.addEventListener('pointermove', function(event) {
            if (!drag.active) {
                return;
            }

            item.x = drag.originX + (event.clientX - drag.startX);
            item.y = drag.originY + (event.clientY - drag.startY);
            applyItemTransform(item);
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
        }

        item.element.addEventListener('pointerup', endDrag);
        item.element.addEventListener('pointercancel', endDrag);
    }

    function createOutfitItem(dataUrl, name) {
        var stageRect = builderStage.getBoundingClientRect();
        var itemId = nextOutfitId++;

        var element = document.createElement('div');
        element.className = 'outfit-item';

        var img = document.createElement('img');
        img.src = dataUrl;
        img.alt = name;
        element.appendChild(img);
        outfitCanvas.appendChild(element);

        var item = {
            id: itemId,
            name: name,
            src: dataUrl,
            width: 190,
            height: 190,
            x: Math.max(20, Math.round(stageRect.width * 0.5) - 95),
            y: Math.max(20, Math.round(stageRect.height * 0.28) - 95),
            scale: 1,
            rotation: 0,
            z: ++lastZ,
            element: element,
            imageEl: img
        };

        outfitItems.push(item);
        applyItemTransform(item);
        makeDraggable(item);
        selectOutfitItem(item.id);
        setStatus('Added "' + name + '" to outfit.');
    }

    function renderWardrobeCard(item) {
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

        var actions = document.createElement('div');
        actions.className = 'wardrobe-actions';

        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.textContent = t('Add');
        addBtn.addEventListener('click', function() {
            createOutfitItem(item.src, item.name);
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
            setStatus(t('Background removed for selected item.'));
            rmBgBtn.disabled = false;
        });

        actions.appendChild(addBtn);
        actions.appendChild(rmBgBtn);
        meta.appendChild(title);
        meta.appendChild(actions);
        card.appendChild(img);
        card.appendChild(meta);
        wardrobeList.prepend(card);
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
        var files = Array.from(wardrobeUpload.files || []);
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
                    src: dataUrl
                };
                wardrobeItems.push(item);
                renderWardrobeCard(item);
                createOutfitItem(item.src, item.name);
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
    });

    rotateRange.addEventListener('input', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }
        selected.rotation = parseFloat(rotateRange.value);
        applyItemTransform(selected);
    });

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
        setStatus(t('Background removed for selected item.'));
        removeBgBtn.disabled = false;
    });

    bringFrontBtn.addEventListener('click', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }
        selected.z = ++lastZ;
        applyItemTransform(selected);
    });

    sendBackBtn.addEventListener('click', function() {
        var selected = findOutfitItem(selectedId);
        if (!selected) {
            return;
        }

        selected.z = 1;
        outfitItems
            .filter(function(item) { return item.id !== selected.id; })
            .sort(function(a, b) { return a.z - b.z; })
            .forEach(function(item, index) {
                item.z = index + 2;
                applyItemTransform(item);
            });
        applyItemTransform(selected);
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
        setStatus(t('Item deleted.'));
    });

    clearOutfitBtn.addEventListener('click', function() {
        outfitItems.forEach(function(item) {
            item.element.remove();
        });
        outfitItems = [];
        selectedId = null;
        refreshSelectionUi();
        setStatus(t('Outfit cleared.'));
    });

    function drawMannequin(ctx, width, height) {
        var cx = width * 0.5;
        var headY = height * 0.11;
        var torsoY = height * 0.2;

        ctx.fillStyle = 'rgba(30, 41, 59, 0.15)';
        ctx.strokeStyle = 'rgba(15, 23, 42, 0.2)';

        ctx.beginPath();
        ctx.arc(cx, headY, width * 0.06, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();

        ctx.beginPath();
        ctx.roundRect(cx - width * 0.11, torsoY, width * 0.22, height * 0.34, width * 0.06);
        ctx.fill();
        ctx.stroke();

        ctx.beginPath();
        ctx.roundRect(cx - width * 0.2, torsoY + height * 0.03, width * 0.06, height * 0.28, width * 0.04);
        ctx.roundRect(cx + width * 0.14, torsoY + height * 0.03, width * 0.06, height * 0.28, width * 0.04);
        ctx.fill();
        ctx.stroke();

        ctx.beginPath();
        ctx.roundRect(cx - width * 0.085, torsoY + height * 0.31, width * 0.07, height * 0.35, width * 0.04);
        ctx.roundRect(cx + width * 0.015, torsoY + height * 0.31, width * 0.07, height * 0.35, width * 0.04);
        ctx.fill();
        ctx.stroke();
    }

    async function drawItemToContext(ctx, item) {
        return new Promise(function(resolve) {
            var img = new Image();
            img.onload = function() {
                var drawWidth = item.width;
                var drawHeight = item.height;
                var centerX = item.x + drawWidth / 2;
                var centerY = item.y + drawHeight / 2;

                ctx.save();
                ctx.translate(centerX, centerY);
                ctx.rotate((item.rotation * Math.PI) / 180);
                ctx.scale(item.scale, item.scale);
                ctx.drawImage(img, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
                ctx.restore();
                resolve();
            };
            img.onerror = function() {
                resolve();
            };
            img.src = item.src;
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

        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(0, 0, width, height);
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

    saveOutfitBtn.addEventListener('click', async function() {
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

        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(0, 0, width, height);
        drawMannequin(ctx, width, height);

        var sorted = outfitItems.slice().sort(function(a, b) { return a.z - b.z; });
        for (var i = 0; i < sorted.length; i++) {
            await drawItemToContext(ctx, sorted[i]);
        }

        var imageData = canvas.toDataURL('image/png');
        var name = (outfitNameInput.value || '').trim() || 'My Outfit';

        saveOutfitBtn.disabled = true;
        setStatus(t('Saving outfit...'));

        try {
            var response = await fetch('../includes/saveOutfit.inc.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image_data: imageData, name: name })
            });
            var result = await response.json();
            if (result.success) {
                setStatus(t('Outfit saved to your profile!'));
                outfitNameInput.value = '';
            } else {
                setStatus(result.error || t('Could not save outfit.'));
            }
        } catch (err) {
            setStatus(t('Network error. Please try again.'));
        }

        saveOutfitBtn.disabled = false;
    });

    document.addEventListener('click', function(event) {
        if (!outfitCanvas.contains(event.target)) {
            selectedId = null;
            refreshSelectionUi();
        }
    });

    refreshSelectionUi();
})();
