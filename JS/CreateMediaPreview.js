document.addEventListener('DOMContentLoaded', function() {
    var previewInputs = document.querySelectorAll('.preview-input[type="file"]');

    function colorDistance(r1, g1, b1, r2, g2, b2) {
        var dr = r1 - r2;
        var dg = g1 - g2;
        var db = b1 - b2;
        return Math.sqrt(dr * dr + dg * dg + db * db);
    }

    function getAverageCornerColor(imageData, width, height) {
        var data = imageData.data;
        var sampleSize = Math.max(8, Math.floor(Math.min(width, height) * 0.06));
        var corners = [
            { xStart: 0, yStart: 0 },
            { xStart: width - sampleSize, yStart: 0 },
            { xStart: 0, yStart: height - sampleSize },
            { xStart: width - sampleSize, yStart: height - sampleSize }
        ];

        var totalR = 0;
        var totalG = 0;
        var totalB = 0;
        var count = 0;

        corners.forEach(function(corner) {
            for (var y = corner.yStart; y < corner.yStart + sampleSize; y++) {
                for (var x = corner.xStart; x < corner.xStart + sampleSize; x++) {
                    var idx = (y * width + x) * 4;
                    totalR += data[idx];
                    totalG += data[idx + 1];
                    totalB += data[idx + 2];
                    count += 1;
                }
            }
        });

        return {
            r: Math.round(totalR / count),
            g: Math.round(totalG / count),
            b: Math.round(totalB / count)
        };
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

        if (count === 0) {
            return getAverageCornerColor(imageData, width, height);
        }

        return {
            r: Math.round(totalR / count),
            g: Math.round(totalG / count),
            b: Math.round(totalB / count)
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

            if (px > 0) {
                enqueueIfBackground(px - 1, py, softThreshold);
            }
            if (px < width - 1) {
                enqueueIfBackground(px + 1, py, softThreshold);
            }
            if (py > 0) {
                enqueueIfBackground(px, py - 1, softThreshold);
            }
            if (py < height - 1) {
                enqueueIfBackground(px, py + 1, softThreshold);
            }
        }

        var removed = qTail;
        var removedRatio = removed / totalPixels;
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

        // Keep the main foreground component and drop isolated leftovers.
        var alphaCutoff = 18;
        var labels = new Int32Array(totalPixels);
        var componentSizes = [0];
        var compQueue = new Uint32Array(totalPixels);
        var componentLabel = 1;

        function tryLabelComponent(startPos) {
            var h = 0;
            var t = 0;
            compQueue[t++] = startPos;
            labels[startPos] = componentLabel;
            var size = 0;

            while (h < t) {
                var cp = compQueue[h++];
                size += 1;

                var cx = cp % width;
                var cy = (cp - cx) / width;

                var left = cp - 1;
                var right = cp + 1;
                var up = cp - width;
                var down = cp + width;

                if (cx > 0 && labels[left] === 0 && data[left * 4 + 3] > alphaCutoff) {
                    labels[left] = componentLabel;
                    compQueue[t++] = left;
                }
                if (cx < width - 1 && labels[right] === 0 && data[right * 4 + 3] > alphaCutoff) {
                    labels[right] = componentLabel;
                    compQueue[t++] = right;
                }
                if (cy > 0 && labels[up] === 0 && data[up * 4 + 3] > alphaCutoff) {
                    labels[up] = componentLabel;
                    compQueue[t++] = up;
                }
                if (cy < height - 1 && labels[down] === 0 && data[down * 4 + 3] > alphaCutoff) {
                    labels[down] = componentLabel;
                    compQueue[t++] = down;
                }
            }

            componentSizes[componentLabel] = size;
            componentLabel += 1;
        }

        for (var p3 = 0; p3 < totalPixels; p3++) {
            if (labels[p3] !== 0) {
                continue;
            }
            if (data[p3 * 4 + 3] <= alphaCutoff) {
                continue;
            }
            tryLabelComponent(p3);
        }

        var largestLabel = 0;
        var largestSize = 0;
        for (var c = 1; c < componentSizes.length; c++) {
            if (componentSizes[c] > largestSize) {
                largestSize = componentSizes[c];
                largestLabel = c;
            }
        }

        if (largestLabel > 0) {
            var keepThreshold = Math.max(120, Math.floor(largestSize * 0.12));
            for (var p4 = 0; p4 < totalPixels; p4++) {
                var lbl = labels[p4];
                if (lbl === 0) {
                    continue;
                }
                if (lbl !== largestLabel && componentSizes[lbl] < keepThreshold) {
                    data[p4 * 4 + 3] = 0;
                }
            }
        }

        // Additional cleanup: remove pixels that still look like background and touch background heavily.
        for (var p5 = 0; p5 < totalPixels; p5++) {
            var a = data[p5 * 4 + 3];
            if (a <= alphaCutoff) {
                continue;
            }

            var x5 = p5 % width;
            var y5 = (p5 - x5) / width;
            var dist5 = colorDistance(data[p5 * 4], data[p5 * 4 + 1], data[p5 * 4 + 2], bg.r, bg.g, bg.b);
            if (dist5 > 42) {
                continue;
            }

            var bgNeighbors = 0;
            if (x5 > 0 && data[(p5 - 1) * 4 + 3] <= alphaCutoff) bgNeighbors += 1;
            if (x5 < width - 1 && data[(p5 + 1) * 4 + 3] <= alphaCutoff) bgNeighbors += 1;
            if (y5 > 0 && data[(p5 - width) * 4 + 3] <= alphaCutoff) bgNeighbors += 1;
            if (y5 < height - 1 && data[(p5 + width) * 4 + 3] <= alphaCutoff) bgNeighbors += 1;

            if (bgNeighbors >= 3) {
                data[p5 * 4 + 3] = 0;
            }
        }

        ctx.putImageData(imageData, 0, 0);
        return { ok: true };
    }

    function setInputFile(input, file) {
        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        input.files = dataTransfer.files;
    }

    previewInputs.forEach(function(input) {
        var uploadBox = input.closest('.upload-box');
        if (!uploadBox) {
            return;
        }

        var previewImage = document.createElement('img');
        previewImage.className = 'upload-preview';
        previewImage.alt = 'Selected image preview';

        var previewWidth = input.dataset.previewWidth || '100%';
        var previewMaxWidth = input.dataset.previewMaxWidth || '260px';
        var previewHeight = input.dataset.previewHeight || '220px';
        var previewRadius = input.dataset.previewRadius || '14px';

        previewImage.style.width = previewWidth;
        previewImage.style.maxWidth = previewMaxWidth;
        previewImage.style.height = previewHeight;
        previewImage.style.objectFit = 'cover';
        previewImage.style.borderRadius = previewRadius;
        uploadBox.appendChild(previewImage);

        var removeBgButton = document.createElement('button');
        removeBgButton.type = 'button';
        removeBgButton.textContent = 'Remove background';
        removeBgButton.style.display = 'none';
        removeBgButton.style.marginTop = '12px';
        removeBgButton.style.padding = '8px 14px';
        removeBgButton.style.border = 'none';
        removeBgButton.style.borderRadius = '10px';
        removeBgButton.style.background = '#111827';
        removeBgButton.style.color = '#ffffff';
        removeBgButton.style.cursor = 'pointer';
        removeBgButton.style.fontWeight = '600';
        uploadBox.appendChild(removeBgButton);

        var statusText = document.createElement('p');
        statusText.style.display = 'none';
        statusText.style.marginTop = '10px';
        statusText.style.fontSize = '0.85rem';
        statusText.style.color = '#111827';
        uploadBox.appendChild(statusText);

        function setPreviewFromFile(file) {
            if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                if (input.dataset.previewUrl) {
                    URL.revokeObjectURL(input.dataset.previewUrl);
                    delete input.dataset.previewUrl;
                }
                previewImage.removeAttribute('src');
                previewImage.classList.remove('visible');
                removeBgButton.style.display = 'none';
                statusText.style.display = 'none';
                uploadBox.classList.remove('has-preview');
                return;
            }

            if (input.dataset.previewUrl) {
                URL.revokeObjectURL(input.dataset.previewUrl);
            }

            var previewUrl = URL.createObjectURL(file);
            input.dataset.previewUrl = previewUrl;
            previewImage.src = previewUrl;
            previewImage.classList.add('visible');
            removeBgButton.style.display = 'inline-block';
            statusText.style.display = 'none';
            uploadBox.classList.add('has-preview');
        }

        input.addEventListener('change', function() {
            var file = input.files && input.files[0] ? input.files[0] : null;
            setPreviewFromFile(file);
        });

        removeBgButton.addEventListener('click', function() {
            var file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                return;
            }

            removeBgButton.disabled = true;
            statusText.style.display = 'block';
            statusText.textContent = 'Removing background...';

            var sourceUrl = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function() {
                try {
                    var canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth;
                    canvas.height = img.naturalHeight;

                    var ctx = canvas.getContext('2d');
                    if (!ctx) {
                        throw new Error('Could not create canvas context.');
                    }

                    ctx.drawImage(img, 0, 0);
                    var bgRemovalResult = removeBackgroundFromCanvas(canvas);
                    if (!bgRemovalResult.ok) {
                        statusText.textContent = bgRemovalResult.message || 'Could not process this image.';
                        URL.revokeObjectURL(sourceUrl);
                        removeBgButton.disabled = false;
                        return;
                    }

                    canvas.toBlob(function(blob) {
                        URL.revokeObjectURL(sourceUrl);
                        if (!blob) {
                            statusText.textContent = 'Could not process this image.';
                            removeBgButton.disabled = false;
                            return;
                        }

                        var processedName = (file.name || 'image').replace(/\.[^.]+$/, '') + '_nobg.png';
                        var processedFile = new File([blob], processedName, { type: 'image/png' });
                        setInputFile(input, processedFile);
                        setPreviewFromFile(processedFile);
                        statusText.textContent = 'Background removed.';
                        removeBgButton.disabled = false;
                    }, 'image/png');
                } catch (error) {
                    URL.revokeObjectURL(sourceUrl);
                    statusText.textContent = 'Could not process this image.';
                    removeBgButton.disabled = false;
                }
            };

            img.onerror = function() {
                URL.revokeObjectURL(sourceUrl);
                statusText.textContent = 'Could not load this image.';
                removeBgButton.disabled = false;
            };

            img.src = sourceUrl;
        });
    });
});
