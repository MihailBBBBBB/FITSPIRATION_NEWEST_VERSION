class Translator {
    constructor() {
        this.storageKey = 'fitspiration-language';
        this.currentLanguage = 'en';
        this.preferredLanguage = this.getSavedLanguage();
        this.translations = {};
        this.isLoaded = false;
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
            '.comment-content'
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
        } catch (error) {
            console.error('Failed to load translations:', error);
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

        const sourceLang = this.currentLanguage;
        const dictionary = this.translations[sourceLang];
        if (!dictionary) {
            console.error('Source dictionary not available for language:', sourceLang);
            return;
        }

        // Get all text nodes
        const textNodes = this.getAllTextNodes(document.body);
        textNodes.forEach(node => {
            const text = node.textContent;
            const translatedText = this.translateText(text, dictionary);

            if (translatedText !== text) {
                node.textContent = translatedText;
            }
        });

        // Update placeholders in input fields
        const inputs = document.querySelectorAll('input[placeholder]');
        inputs.forEach(input => {
            const placeholder = input.placeholder;
            const translatedPlaceholder = this.translateText(placeholder, dictionary);
            if (translatedPlaceholder !== placeholder) {
                input.placeholder = translatedPlaceholder;
            }
        });

        // Update button text
        const buttons = document.querySelectorAll('button');
        buttons.forEach(button => {
            const text = button.textContent.trim();
            const translatedText = this.translateText(text, dictionary);
            if (translatedText !== text) {
                button.textContent = translatedText;
            }
        });

        // Translate select options for static UI dropdowns (e.g., sorting),
        // but skip likely user-generated options (numeric ids).
        const options = document.querySelectorAll('select option');
        options.forEach(option => {
            const optionValue = (option.getAttribute('value') || '').trim();
            if (/^\d+$/.test(optionValue)) {
                return;
            }

            const text = option.textContent;
            const translatedText = this.translateText(text, dictionary);
            if (translatedText !== text) {
                option.textContent = translatedText;
            }
        });

        this.currentLanguage = targetLanguage;
        this.saveLanguage(targetLanguage);
        this.updateTranslateButton();
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

        if (parent.closest(this.userContentSelectors.join(', '))) {
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
        const dictionary = this.translations[this.currentLanguage];
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