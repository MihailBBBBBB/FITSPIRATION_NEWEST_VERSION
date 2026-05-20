class Translator {
    constructor() {
        this.storageKey = 'fitspiration-language';
        this.currentLanguage = 'en';
        this.preferredLanguage = this.getSavedLanguage();
        this.translations = {};
        this.isLoaded = false;
        this.observer = null;
        this.nativeDialogsPatched = false;
        this.originalTitle = document && typeof document.title === 'string' ? document.title : '';
        this.userContentSelectors = [
            '.no-translate',
            '[data-user-content="true"]',
            '.pin-title',
            '.collection-title',
            '.collection-description',
            '.creator-link',
            '.follow-username',
            '.follow-list-item',
            '.comment-item',
            '.comment-content',
            '.username',
            '.notification-actor',
            '.collab-name',
            '.entry-author',
            '.winner-author',
            '.collection-collaborator-name',
            '#modalCommentList li',
            '.comment-list li',
            '.message-item .message-content p'
        ];
        this.init();
    }

    getSavedLanguage() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            return saved === 'lv' ? 'lv' : 'en';
        } catch (error) {
            console.warn('Could not access localStorage for language preference:', error);
            return 'en';
        }
    }

    saveLanguage(language) {
        try {
            localStorage.setItem(this.storageKey, language);
        } catch (error) {
            console.warn('Could not save language preference:', error);
        }
    }

    async init() {
        try {
            const response = await fetch('../JS/translations.json');
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            const data = await response.json();
            this.translations = data.translations;
            this.isLoaded = true;
            this.observeDomChanges();
            this.patchNativeDialogs();
        } catch (error) {
            console.error('Failed to load translations:', error);
        }
    }

    patchNativeDialogs() {
        if (this.nativeDialogsPatched || typeof window === 'undefined') {
            return;
        }

        this.nativeDialogsPatched = true;

        const originalAlert = window.alert ? window.alert.bind(window) : null;
        const originalConfirm = window.confirm ? window.confirm.bind(window) : null;
        const originalPrompt = window.prompt ? window.prompt.bind(window) : null;

        if (originalAlert) {
            window.alert = (message) => {
                const text = String(message == null ? '' : message);
                return originalAlert(this.t(text));
            };
        }

        if (originalConfirm) {
            window.confirm = (message) => {
                const text = String(message == null ? '' : message);
                return originalConfirm(this.t(text));
            };
        }

        if (originalPrompt) {
            window.prompt = (message, defaultValue) => {
                const text = String(message == null ? '' : message);
                return originalPrompt(this.t(text), defaultValue);
            };
        }
    }

    async restoreLanguage() {
        if (!this.isLoaded) {
            await this.init();
        }

        if (this.preferredLanguage === 'lv' && this.currentLanguage !== 'lv') {
            await this.translatePage('lv');
            return;
        }

        this.updateTranslateButton();
    }

    async translatePage(targetLanguage) {
        if (!this.isLoaded) {
            await this.init();
        }

        if (!this.translations[targetLanguage]) {
            console.error('Translation not available for language:', targetLanguage);
            return;
        }

        const dictionary = this.translations.en;
        if (!dictionary) {
            console.error('English dictionary not available for translation');
            return;
        }

        this.currentLanguage = targetLanguage;
        this.translateElementTree(document.body, dictionary);
        this.translateDocumentTitle(dictionary);
        this.saveLanguage(targetLanguage);
        this.updateTranslateButton();

        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('fitspiration:language-changed', {
                detail: { language: this.currentLanguage }
            }));
        }
    }

    observeDomChanges() {
        if (this.observer || !document.body || typeof MutationObserver === 'undefined') {
            return;
        }

        this.observer = new MutationObserver(mutations => {
            if (this.currentLanguage === 'en' || !this.isLoaded) {
                return;
            }

            const dictionary = this.translations[this.currentLanguage];
            if (!dictionary) {
                return;
            }

            mutations.forEach(mutation => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === Node.TEXT_NODE) {
                            if (!this.isUserGeneratedNode(node)) {
                                const translated = this.translateText(node.textContent, dictionary);
                                if (translated !== node.textContent) {
                                    node.textContent = translated;
                                }
                            }
                            return;
                        }

                        if (node.nodeType === Node.ELEMENT_NODE) {
                            this.translateElementTree(node, dictionary);
                        }
                    });
                }

                if (mutation.type === 'attributes' && mutation.target && mutation.target.nodeType === Node.ELEMENT_NODE) {
                    this.translateElementAttributes(mutation.target, dictionary);
                }
            });
        });

        this.observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['placeholder', 'title', 'aria-label', 'value']
        });
    }

    translateDocumentTitle(dictionary) {
        if (!document || typeof document.title !== 'string') {
            return;
        }

        if (!this.originalTitle) {
            this.originalTitle = document.title;
        }

        if (this.currentLanguage === 'en') {
            document.title = this.originalTitle;
            return;
        }

        const translatedTitle = this.translateText(this.originalTitle, dictionary);
        if (translatedTitle !== this.originalTitle) {
            document.title = translatedTitle;
        }
    }

    translateElementTree(root, dictionary) {
        if (!root || !dictionary) {
            return;
        }

        if (root.nodeType === Node.TEXT_NODE) {
            if (!this.isUserGeneratedNode(root)) {
                const translatedText = this.translateText(root.textContent, dictionary);
                if (translatedText !== root.textContent) {
                    root.textContent = translatedText;
                }
            }
            return;
        }

        if (root.nodeType !== Node.ELEMENT_NODE || this.isUserGeneratedElement(root)) {
            return;
        }

        const textNodes = this.getAllTextNodes(root);
        textNodes.forEach(node => {
            if (typeof node.__fitspirationOriginalText !== 'string') {
                node.__fitspirationOriginalText = node.textContent;
            }

            const baseText = node.__fitspirationOriginalText;
            const translatedText = this.currentLanguage === 'en'
                ? baseText
                : this.translateText(baseText, dictionary);

            if (translatedText !== node.textContent) {
                node.textContent = translatedText;
            }
        });

        this.translateElementAttributes(root, dictionary);

        const descendants = root.querySelectorAll('*');
        descendants.forEach(element => {
            this.translateElementAttributes(element, dictionary);
        });
    }

    translateElementAttributes(element, dictionary) {
        if (!element || this.isUserGeneratedElement(element)) {
            return;
        }

        const explicitTranslationKey = (element.getAttribute('data-translate') || '').trim();
        if (explicitTranslationKey) {
            const originalText = element.getAttribute('data-original-text')
                || (element.tagName === 'INPUT' && ['submit', 'button'].includes((element.type || '').toLowerCase())
                    ? element.value
                    : element.textContent);
            if (!element.hasAttribute('data-original-text')) {
                element.setAttribute('data-original-text', originalText);
            }

            const translatedExplicitText = this.currentLanguage === 'en'
                ? originalText
                : this.translateText(explicitTranslationKey, dictionary);

            if (element.tagName === 'OPTION') {
                element.textContent = translatedExplicitText;
                element.label = translatedExplicitText;
                this.refreshTranslatedSelect(element.parentElement);
            } else if (element.tagName === 'INPUT' && ['submit', 'button'].includes((element.type || '').toLowerCase())) {
                element.value = translatedExplicitText;
            } else {
                element.textContent = translatedExplicitText;
            }

            return;
        }

        ['placeholder', 'title', 'aria-label', 'alt'].forEach(attr => {
            const value = element.getAttribute(attr);
            if (!value) {
                return;
            }

            const originalAttrKey = 'data-original-' + attr;
            const originalValue = element.getAttribute(originalAttrKey) || value;
            if (!element.hasAttribute(originalAttrKey)) {
                element.setAttribute(originalAttrKey, originalValue);
            }

            const translated = this.currentLanguage === 'en'
                ? originalValue
                : this.translateText(originalValue, dictionary);

            if (translated !== element.getAttribute(attr)) {
                element.setAttribute(attr, translated);
            }
        });

        if (element.tagName === 'INPUT' && ['submit', 'button'].includes((element.type || '').toLowerCase())) {
            const originalValue = element.getAttribute('data-original-value') || element.value;
            if (!element.hasAttribute('data-original-value')) {
                element.setAttribute('data-original-value', originalValue);
            }

            if (typeof originalValue === 'string' && originalValue.trim()) {
                const translatedValue = this.currentLanguage === 'en'
                    ? originalValue
                    : this.translateText(originalValue, dictionary);
                if (translatedValue !== element.value) {
                    element.value = translatedValue;
                }
            }
        }

        if (element.tagName === 'OPTION') {
            const optionValue = (element.getAttribute('value') || '').trim();
            if (/^\d+$/.test(optionValue)) {
                return;
            }
            const originalOptionText = element.getAttribute('data-original-option-text') || element.textContent;
            if (!element.hasAttribute('data-original-option-text')) {
                element.setAttribute('data-original-option-text', originalOptionText);
            }

            const translatedOptionText = this.currentLanguage === 'en'
                ? originalOptionText
                : this.translateText(originalOptionText, dictionary);
            if (translatedOptionText !== element.textContent) {
                element.textContent = translatedOptionText;
                element.label = translatedOptionText;
                this.refreshTranslatedSelect(element.parentElement);
            }
        }
    }

    refreshTranslatedSelect(selectElement) {
        if (!selectElement || selectElement.tagName !== 'SELECT') {
            return;
        }

        const selectedIndex = selectElement.selectedIndex;
        if (selectedIndex < 0) {
            return;
        }

        const selectedValue = selectElement.value;
        selectElement.selectedIndex = -1;

        if (selectedValue !== '') {
            selectElement.value = selectedValue;
        }

        if (selectElement.selectedIndex === -1) {
            selectElement.selectedIndex = selectedIndex;
        }
    }

    getAllTextNodes(element) {
        const textNodes = [];
        const self = this;
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    // Skip script and style elements
                    if (node.parentElement && (
                        node.parentElement.tagName === 'SCRIPT' ||
                        node.parentElement.tagName === 'STYLE' ||
                        node.parentElement.tagName === 'NOSCRIPT' ||
                        node.parentElement.tagName === 'TEXTAREA' ||
                        node.parentElement.tagName === 'OPTION'
                    )) {
                        return NodeFilter.FILTER_REJECT;
                    }

                    if (self.isUserGeneratedNode(node)) {
                        return NodeFilter.FILTER_REJECT;
                    }

                    // Skip empty text nodes
                    if (!node.textContent.trim()) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            }
        );

        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        return textNodes;
    }

    isUserGeneratedNode(node) {
        const parent = node.parentElement;
        if (!parent) {
            return false;
        }

        if (this.isUserGeneratedElement(parent)) {
            return true;
        }

        if (parent.closest('[contenteditable="true"]')) {
            return true;
        }

        // Skip elements carrying user/entity identifiers or user-facing dynamic values.
        const dynamicDataAttributes = [
            'data-title',
            'data-creator-name',
            'data-creator-id',
            'data-pin-id',
            'data-collection-id',
            'data-user-id'
        ];

        if (dynamicDataAttributes.some(attr => parent.hasAttribute(attr))) {
            return true;
        }

        if (parent.tagName === 'OPTION') {
            const optionValue = parent.getAttribute('value') || '';
            if (/^\d+$/.test(optionValue.trim())) {
                return true;
            }
        }

        return false;
    }

    isUserGeneratedElement(element) {
        if (!element) {
            return false;
        }

        if (element.closest(this.userContentSelectors.join(', '))) {
            return true;
        }

        if (element.closest('[contenteditable="true"]')) {
            return true;
        }

        if (element.hasAttribute('data-user-content') && element.getAttribute('data-user-content') === 'true') {
            return true;
        }

        return false;
    }

    translateText(text, dictionary) {
        if (!text || !text.trim()) {
            return text;
        }

        if (dictionary[text.trim()]) {
            const leadingWhitespace = text.match(/^\s*/)[0];
            const trailingWhitespace = text.match(/\s*$/)[0];
            return leadingWhitespace + dictionary[text.trim()] + trailingWhitespace;
        }

        let translated = text;
        const keys = Object.keys(dictionary).sort((a, b) => b.length - a.length);

        keys.forEach(key => {
            if (!key || !key.trim()) {
                return;
            }

            const escapedKey = key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(
                '(^|[^\\p{L}\\p{N}_])(' + escapedKey + ')(?=$|[^\\p{L}\\p{N}_])',
                'gu'
            );
            translated = translated.replace(regex, function(match, prefix) {
                return prefix + dictionary[key];
            });
        });

        return translated;
    }

    t(text) {
        if (!this.isLoaded || !text) return text;
        if (this.currentLanguage === 'en') return text;
        const dictionary = this.translations['en'];
        if (!dictionary) return text;
        return this.translateText(text, dictionary);
    }

    updateTranslateButton() {
        const button = document.getElementById('translate-btn');
        if (button) {
            button.textContent = this.currentLanguage === 'en' ? 'LV' : 'EN';
        }
    }

    async toggleTranslation() {
        try {
            const targetLang = this.currentLanguage === 'en' ? 'lv' : 'en';
            await this.translatePage(targetLang);
        } catch (error) {
            console.error('Translation error:', error);
        }
    }
}

// Initialize translator when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.translator = new Translator();
    window.translator.restoreLanguage();
});