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
} from '../helpers';

prefetchStrings('tiny_media', [
    'insertmedia',
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
        ];
        const langStringValues = await getStrings([...langStringKeys].map((key) => ({key, component})));
        this.langStrings = Object.fromEntries(langStringKeys.map((key, index) => [key, langStringValues[index]]));
        this.currentModal.setTitle(this.langStrings.insertmedia);
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
}
