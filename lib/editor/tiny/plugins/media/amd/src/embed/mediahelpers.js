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
 * Tiny media plugin helpers for embed.
 *
 * @module      tiny_media/embed/mediahelpers
 * @copyright   2024 Stevani Andolo <stevani@hotmail.com.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from '../selectors';
import {
    convertStringUrlToObject,
    createUrlParams,
} from '../helpers';

/**
 * Return template context for insert media.
 *
 * @param {object} props
 * @returns {object}
 */
export const insertMediaTemplateContext = (props) => {
    return {
        mediaType: props.mediaType,
        showDropzone: props.canShowDropZone,
        showFilePicker: props.canShowFilePicker,
    };
};

/**
 * Check if the url is from a known media site.
 *
 * @param {string} url
 * @returns {boolean}
 */
export const isUrlFromKnownMediaSites = (url) => {
    let state = false;
    const sites = Selectors.EMBED.mediaSites;
    for (const site in sites) {
        if (url.includes(sites[site])) {
            state = true;
            break;
        }
    }
    return state;
};

/**
 * Format url when inserting media link to be previewed.
 *
 * @param {string} url
 * @returns {string}
 */
export const formatMediaUrl = (url) => {
    // Convert the string url into url param object.
    const params = convertStringUrlToObject(url);

    // Format the url for youtube links.
    if (url.includes(Selectors.EMBED.mediaSites.youtube)) {
        let fetchedUrl = null;
        let fetchedUrlValue = null;
        for (const k in params) {
            if (url.includes(k)) {
                fetchedUrl = k;
                fetchedUrlValue = params[k];
                delete params[k];
                break;
            }
        }
        url = fetchedUrl.replace('watch?v', 'embed/');
        url = url + fetchedUrlValue + '?' + createUrlParams(params);
    }
    return url;
};
