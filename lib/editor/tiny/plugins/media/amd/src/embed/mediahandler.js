// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tiny media plugin media handler class for embed.
 *
 * @module      tiny_media/embed/mediahandler
 * @copyright   2024 Stevani Andolo <stevani@hotmail.com.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from "../selectors";
import {MediaInsert} from './mediainsert';
import {insertMediaTemplateContext} from "./mediahelpers";
import {
    body,
    footer,
    isValidUrl,
    hideElements,
} from '../helpers';
import {PropertySetter} from '../propertysetter';
import {displayFilepicker} from 'editor_tiny/utils';

export class MediaHandler extends PropertySetter {

    constructor(data) {
        super(data); // Creates dynamic properties based on "data" param.
    }

    /**
     * Load the media insert dialogue.
     *
     * @param {object} templateContext Object template context
     */
    loadTemplatePromise = (templateContext) => {
        templateContext.elementid = this.editor.id;
        templateContext.bodyTemplate = Selectors.EMBED.template.body.insertMediaBody;
        templateContext.footerTemplate = Selectors.EMBED.template.footer.insertMediaFooter;
        templateContext.selector = 'EMBED';

        Promise.all([body(templateContext, this.root), footer(templateContext, this.root)])
            .then(() => {
                const mediaInsert = new MediaInsert(this);
                mediaInsert.init();
                return;
            })
            .catch(error => {
                window.console.log(error);
            });
    };

    /**
     * Reset the media insert modal form.
     */
    resetUploadForm = () => {
        this.mediaType = null; // Set to null to be set again.
        this.loadTemplatePromise(insertMediaTemplateContext(this));
    };

    /**
     * Load the media preview dialogue.
     *
     * @param {string} url String of media url
     */
    loadMediaPreview = (url) => {
        (new MediaInsert(this)).loadMediaPreview(url);
    };

    /**
     * Handles changes in the media URL input field and loads a preview of the media if the URL has changed.
     */
    urlChanged() {
        hideElements(Selectors.EMBED.elements.urlWarning, this.root);
        const url = this.root.querySelector(Selectors.EMBED.elements.fromUrl).value;
        if (url && url !== this.currentUrl) {
            this.loadMediaPreview(url);
        }
    }

    /**
     * Callback for file picker that previews the media or add the captions and subtitles.
     *
     * @param {object} params Object of media url and etc
     */
    trackFilePickerCallback(params) {
        if (params.url !== '') {
            this.loadMediaPreview(params.url);
        }
    }

    /**
     * Handle click events.
     *
     * @param {html} e Selected element
     */
    clickHandler = async(e) => {
        const element = e.target;

        const mediaBrowser = element.closest(Selectors.EMBED.actions.mediaBrowser);
        if (mediaBrowser) {
            e.preventDefault();
            const params = await displayFilepicker(this.editor, 'media');
            this.trackFilePickerCallback(params);
        }

        const addUrlEle = e.target.closest(Selectors.EMBED.actions.addUrl);
        if (addUrlEle) {
            this.urlChanged();
        }
    };

    /**
     * Enables or disables the URL-related buttons in the footer based on the current URL and input value.
     *
     * @param {html} input Url input field
     */
    toggleUrlButton(input) {
        const url = input.value;
        const addUrl = this.root.querySelector(Selectors.EMBED.actions.addUrl);
        addUrl.disabled = !(url !== "" && isValidUrl(url));
    }

    registerEventListeners = async(modal) => {
        await modal.getBody();
        const $root = modal.getRoot();
        const root = $root[0];
        if (this.canShowFilePickerTrack) {
            root.addEventListener('click', this.clickHandler.bind(this));
        }

        root.addEventListener('input', (e) => {
            const urlEle = e.target.closest(Selectors.EMBED.elements.fromUrl);
            if (urlEle) {
                this.toggleUrlButton(urlEle);
            }
        });
    };
}
