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
 * Tiny media plugin media insertion class for embed.
 *
 * @module      tiny_media/embed/mediainsert
 * @copyright   2024 Stevani Andolo <stevani@hotmail.com.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {alert} from 'core/notification';
import {prefetchStrings} from 'core/prefetch';
import {getStrings, getString} from 'core/str';
import {component} from "../common";
import {PropertySetter} from '../propertysetter';
import {formatMediaUrl} from './mediahelpers';
import Selectors from "../selectors";
import {MediaHandler} from './mediahandler';
import {
    getFileMimeTypeFromUrl,
    startMediaLoading,
    stopMediaLoading,
    showElements,
} from '../helpers';
import Dropzone from 'core/dropzone';
import uploadFile from 'editor_tiny/uploader';

prefetchStrings('tiny_media', [
    'insertmedia',
    'addmediafilesdrop',
    'loadingmedia',
    'uploading',
]);

export class MediaInsert extends PropertySetter {

    constructor(data) {
        super(data); // Creates dynamic properties based on "data" param.
    }

    /**
     * Init the dropzone and lang strings.
     */
    init = async() => {
        const langStringKeys = [
            'insertmedia',
            'addmediafilesdrop',
            'loadingmedia',
            'uploading',
        ];
        const langStringValues = await getStrings([...langStringKeys].map((key) => ({key, component})));
        this.langStrings = Object.fromEntries(langStringKeys.map((key, index) => [key, langStringValues[index]]));
        this.currentModal.setTitle(this.langStrings.insertmedia);

        // Let's init the dropzone if canShowDropZone is true and mediaType is null.
        if (this.canShowDropZone && !this.mediaType) {
            const dropZoneEle = document.querySelector(Selectors.EMBED.elements.dropzoneContainer);
            const dropZone = new Dropzone(
                dropZoneEle,
                'audio/*,video/*',
                files => {
                    this.handleUploadedFile(files);
                }
            );

            dropZone.setLabel(this.langStrings.addmediafilesdrop);
            dropZone.init();
        }
    };

    /**
     * Loads and displays a preview media based on the provided URL, and handles media loading events.
     *
     * @param {string} url - The URL of the media to load and display.
     */
    loadMediaPreview = async(url) => {
        startMediaLoading(this.root, 'EMBED');
        this.mediaSource = formatMediaUrl(url);

        // Get media mime type.
        const mediaType = await getFileMimeTypeFromUrl(this.mediaSource);
        if (!Selectors.EMBED.mediaTypes.includes(mediaType)) {
            alert(
                await getString('onlymediafiles', component),
                await getString('onlymediafilesdesc', component)
            );

            stopMediaLoading(this.root, 'EMBED');

            const mediaHandler = new MediaHandler();
            mediaHandler.resetUploadForm();
            return;
        }
        stopMediaLoading(this.root, 'EMBED');
    };

    /**
     * Updates the content of the loader icon.
     *
     * @param {HTMLElement} root - The root element containing the loader icon.
     * @param {object} langStrings - An object containing language strings.
     * @param {number|null} progress - The progress percentage (optional).
     * @returns {void}
     */
    updateLoaderIcon = (root, langStrings, progress = null) => {
        const loaderIcon = this.root.querySelector(Selectors.EMBED.elements.loaderIcon);
        if (loaderIcon && loaderIcon.classList.contains('d-none')) {
            showElements(Selectors.EMBED.elements.loaderIcon);
        }

        const loaderIconState = root.querySelector(Selectors.EMBED.elements.loaderIconContainer + ' div');
        loaderIconState.innerHTML = (progress !== null) ?
                               `${langStrings.uploading} ${Math.round(progress)}%` :
                               langStrings.loadingmedia;
    };

    /**
     * Handles media preview on file picker callback.
     *
     * @param {object} params Object of uploaded file
     */
    filePickerCallback = (params) => {
        if (params.url) {
            this.loadMediaPreview(params.url);
        }
    };

    /**
     * Handles the uploaded file, initiates the upload process, and updates the UI during the upload.
     *
     * @param {FileList} files - The list of files to upload (usually from a file input field).
     * @returns {Promise<void>} A promise that resolves when the file is uploaded and processed.
     */
    handleUploadedFile = async(files) => {
        try {
            startMediaLoading(this.root, 'EMBED');
            const fileURL = await uploadFile(this.editor, 'media', files[0], files[0].name, (progress) => {
                this.updateLoaderIcon(this.root, this.langStrings, progress);
            });

            // Set the loader icon content to "loading" after the file upload completes.
            this.updateLoaderIcon(this.root, this.langStrings);
            this.filePickerCallback({url: fileURL});
        } catch (error) {
            // Handle the error.
            const urlWarningLabelEle = this.root.querySelector(Selectors.EMBED.elements.urlWarning);
            urlWarningLabelEle.innerHTML = error.error !== undefined ? error.error : error;
            showElements(Selectors.EMBED.elements.urlWarning, this.root);
            stopMediaLoading(this.root, 'EMBED');
        }
    };
}
